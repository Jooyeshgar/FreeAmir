<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

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

        $decision = $this->paymentService->validateInvoicePayment($invoice, $validated);
        if ($decision->hasErrors()) {
            return redirect()->back()->with('error', $decision->messages->pluck('text')->all());
        }

        $this->paymentService->createPayment($request->user(), $invoice, $validated);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment recorded successfully.'));
    }

    public function destroy(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);
        $this->paymentService->deletePayment($payment);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Payment removed successfully.'));
    }
}
