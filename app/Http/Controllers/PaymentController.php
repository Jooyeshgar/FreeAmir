<?php

namespace App\Http\Controllers;

use App\Enums\ChequeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ChequeService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
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

        $request->merge([
            'amount' => convertToFloat($request->input('amount', 0)),
            'sayad_number' => preg_replace('/\D/', '', toEnglish((string) $request->input('sayad_number'))),
            'serial' => trim(toEnglish((string) $request->input('serial'))),
            'issue_date' => $request->filled('issue_date') ? jalaliInputToGregorian($request->input('issue_date'), 'issue_date') : null,
            'due_date' => $request->filled('due_date') ? jalaliInputToGregorian($request->input('due_date'), 'due_date') : null,
        ]);
        if ($request->filled('checkbook_leaf_number')) {
            $request->merge(['checkbook_leaf_number' => convertToInt($request->input('checkbook_leaf_number'))]);
        }

        $companyId = getActiveCompany();
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'serial' => ['nullable', 'required_without:checkbook_leaf_number', 'string', 'max:50'],
            'sayad_number' => ['required', 'regex:/^\d{16}$/', Rule::unique('cheques', 'sayad_number')],
            'bank_id' => ['required', Rule::exists('banks', 'id')->where('company_id', $companyId)],
            'bank_account_id' => [
                Rule::requiredIf($direction === ChequeType::PAYABLE),
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('company_id', $companyId),
            ],
            'checkbook_id' => ['nullable', Rule::exists('checkbooks', 'id')->where('company_id', $companyId)],
            'checkbook_leaf_number' => ['nullable', 'integer', 'min:1'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'branch_city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->chequeService->register($request->user(), [
            ...$validated,
            'invoice_id' => $invoice->id,
            'direction' => $direction->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'party_id' => $invoice->customer_id,
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
