<?php

namespace App\Http\Controllers;

use App\Enums\ChequeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Services\ChequeService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService, private readonly ChequeService $chequeService) {}

    public function searchSubjects(Request $request, Invoice $invoice)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $groups = $this->paymentService->settlementSubjects(trim($validated['q'] ?? ''))
            ->groupBy(fn ($subject) => $subject->parent?->name ?? __('Other'))
            ->map(fn ($subjects, $label) => [
                'label' => (string) $label,
                'options' => $subjects->map(fn ($subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->formattedCode(),
                ])->values(),
            ])->values();

        return response()->json($groups);
    }

    public function store(Request $request, Invoice $invoice)
    {
        $request->merge([
            'amount' => convertToFloat($request->input('amount', 0)),
            'bank_service_fee' => $this->normalizeBankServiceFee($request),
            'date' => $request->input('date') ? convertToGregorian($request->input('date')) : null,
        ]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank_service_fee' => ['required', 'numeric', 'min:0'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:20'],
        ]);

        $decision = $this->paymentService->createPayment($request->user(), $invoice, $validated);
        if ($decision->hasErrors()) {
            return redirect()->back()->with('error', $decision->messages->pluck('text')->all());
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment recorded successfully.'));
    }

    public function edit(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id && ! $payment->cheque_id, 404);

        return view('payments.edit', [
            'invoice' => $invoice,
            'payment' => $payment,
            'settlementSubjects' => $this->paymentService->settlementSubjects(),
            'selectedSubject' => Subject::find(old('subject_id', $payment->settlementSubject()?->id)),
            'remainingAmount' => $this->paymentService->remainingAmount($invoice, $payment),
        ]);
    }

    public function update(Request $request, Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id && ! $payment->cheque_id, 404);

        $normalized = array_replace($request->all(), [
            'amount' => convertToFloat($request->input('amount', 0)),
            'bank_service_fee' => $this->normalizeBankServiceFee($request),
            'date' => $request->input('date') ? jalaliInputToGregorian($request->input('date'), 'date') : null,
        ]);
        $validated = Validator::make($normalized, [
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank_service_fee' => ['required', 'numeric', 'min:0'],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('fiscal_year_id', getActiveFiscalYear())],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $decision = $this->paymentService->updatePayment($request->user(), $payment, $validated);
        if ($decision->hasErrors()) {
            return back()->withInput()->with('error', $decision->messages->pluck('text')->all());
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment updated successfully.'));
    }

    public function storeCheque(Request $request, Invoice $invoice)
    {
        if ($request->has('bank_service_fee')) {
            $request->merge(['bank_service_fee' => $this->normalizeBankServiceFee($request)]);
        }

        $request->validate([
            'bank_service_fee' => ['sometimes', 'required', 'numeric', 'min:0', 'lt:10000000000000000'],
        ]);

        $direction = $this->chequeService->directionForInvoice($invoice);
        abort_unless($direction, 422, __('This invoice type cannot be settled by cheque.'));

        $normalized = [
            'amount' => convertToFloat($request->input('amount', 0)),
            'bank_service_fee' => $this->normalizeBankServiceFee($request),
            'sayad_number' => preg_replace('/\D/', '', toEnglish((string) $request->input('sayad_number'))),
            'serial' => trim(toEnglish((string) $request->input('serial'))),
            'cheque_number' => trim(toEnglish((string) $request->input('cheque_number'))),
        ];

        foreach (['issue_date', 'due_date'] as $field) {
            if ($request->filled($field)) {
                $normalized[$field] = jalaliInputToGregorian($request->input($field), $field);
            }
        }

        $validated = Validator::make(array_replace($request->all(), $normalized), [
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank_service_fee' => ['required', 'numeric', 'min:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'serial' => ['nullable', 'string', 'max:50'],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'sayad_number' => [
                'required',
                'regex:/^\d{16}$/',
                Rule::unique('cheques', 'sayad_number')->where('fiscal_year_id', getActiveFiscalYear()),
            ],
            'bank_account_id' => [
                Rule::requiredIf($direction === ChequeType::PAYABLE),
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('fiscal_year_id', getActiveFiscalYear()),
            ],
            'chequebook_id' => $direction === ChequeType::PAYABLE ? ['nullable', Rule::exists('chequebooks', 'id')->where('fiscal_year_id', getActiveFiscalYear())] : ['exclude'],
            'description' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $this->chequeService->register($request->user(), [
            ...$validated,
            'invoice_id' => $invoice->id,
            'direction' => $direction->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'customer_id' => $invoice->customer_id,
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice payment cheque registered successfully.'));
    }

    private function normalizeBankServiceFee(Request $request): mixed
    {
        $fee = $request->input('bank_service_fee') ?? 0;
        if (is_string($fee)) {
            $fee = str_replace([',', '٬', '٫'], ['', '', '.'], trim(toEnglish($fee)));
            $fee = $fee === '' ? 0 : $fee;
        }

        return $fee;
    }

    public function destroy(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $this->paymentService->deletePayment($payment);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment removed successfully.'));
    }

    public function createDocument(Request $request, Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $decision = $this->paymentService->createPaymentDocument($request->user(), $payment);
        if ($decision->hasErrors()) {
            return redirect()->route('invoices.show', $invoice)->with('error', $decision->messages->pluck('text')->all());
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment document created successfully.'));
    }

    public function destroyDocument(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);
        $this->paymentService->removePaymentDocument($payment);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment document removed successfully.'));
    }
}
