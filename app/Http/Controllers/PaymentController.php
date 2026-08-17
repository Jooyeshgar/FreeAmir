<?php

namespace App\Http\Controllers;

use App\Enums\ChequeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ChequeService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService, private readonly ChequeService $chequeService) {}

    public function store(Request $request, Invoice $invoice)
    {
        $request->merge([
            'amount' => convertToFloat($request->input('amount', 0)),
            'date' => $request->input('date') ? convertToGregorian($request->input('date')) : null,
        ]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
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

    public function storeCheque(Request $request, Invoice $invoice)
    {
        $direction = $this->chequeService->directionForInvoice($invoice);
        abort_unless($direction, 422, __('This invoice type cannot be settled by cheque.'));

        $normalized = [
            'amount' => convertToFloat($request->input('amount', 0)),
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
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'serial' => ['nullable', 'string', 'max:50'],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'sayad_number' => ['required', 'regex:/^\d{16}$/', Rule::unique('cheques', 'sayad_number')],
            'bank_account_id' => [
                Rule::requiredIf($direction === ChequeType::PAYABLE),
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('company_id', getActiveCompany()),
            ],
            'chequebook_id' => $direction === ChequeType::PAYABLE ? ['nullable', Rule::exists('chequebooks', 'id')->where('company_id', getActiveCompany())] : ['exclude'],
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
