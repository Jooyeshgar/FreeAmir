<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Subjects that a payment can settle into: every bank account's subject plus the descendants of the cash_book subject.
     */
    public function settlementSubjectIds(): array
    {
        $bankRootId = (int) config('amir.bank');
        $cashBookId = (int) config('amir.cash_book');

        $bankSubjectIds = BankAccount::query()->whereNotNull('subject_id')->pluck('subject_id')->all();
        $cashSubtreeIds = $cashBookId ? (Subject::find($cashBookId)?->getAllDescendantIds() ?? []) : [];
        $ids = array_map('intval', array_merge($bankSubjectIds, $cashSubtreeIds));
        $ids = array_diff($ids, [$bankRootId, $cashBookId]);

        return array_values(array_unique($ids));
    }

    public function settlementSubjects(): Collection
    {
        $ids = $this->settlementSubjectIds();

        if (empty($ids)) {
            return new Collection;
        }

        return Subject::query()->with('parent')->whereIn('id', $ids)->orderBy('code')->get();
    }

    public function paidAmount(Invoice $invoice): float
    {
        return (float) $invoice->payments()->whereNotNull('document_id')->sum('amount');
    }

    public function remainingAmount(Invoice $invoice): float
    {
        return max((float) $invoice->amount - $this->paidAmount($invoice), 0.0);
    }

    public function validateInvoicePayment(Invoice $invoice, array $data = []): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        if (! $invoice->status->isApproved() && ! $invoice->status->isPartiallyPaid()) {
            $decision->addMessage('error', __('The invoice must be approved before recording a payment.'));
        }

        if ($this->remainingAmount($invoice) <= 0) {
            $decision->addMessage('error', __('This invoice is fully paid.'));
        }

        if (isset($data['subject_id']) && ! in_array((int) $data['subject_id'], $this->settlementSubjectIds(), true)) {
            $decision->addMessage('error', __('The selected settlement account is not a valid bank or cash subject.'));
        }

        if (isset($data['amount']) && $data['amount'] > $this->remainingAmount($invoice) + 0.001) {
            $decision->addMessage('error', __('Payment amount exceeds the remaining balance of the invoice.'));
        }

        if (! empty($data['date']) && $invoice->date
            && Carbon::parse($data['date'])->startOfDay()->lt($invoice->date->copy()->startOfDay())) {
            $decision->addMessage('error', __('The payment date cannot be earlier than the invoice date.'));
        }

        return $decision;
    }

    public function createPayment(User $user, Invoice $invoice, array $data): InvoiceStatusDecision
    {
        return DB::transaction(function () use ($user, $invoice, $data) {
            $decision = $this->validateInvoicePayment($invoice, $data);
            if ($decision->hasErrors()) {
                return $decision;
            }

            $subjectId = (int) $data['subject_id'];
            $date = $data['date'] ?? now()->toDateString();
            $document = $this->paymentDocument($user, $invoice, $subjectId, $data['amount'], $date);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payer_id' => $invoice->customer_id,
                'amount' => $data['amount'],
                'date' => $date,
                'description' => $data['description'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'document_id' => $document->id,
                'settlement_subject_id' => $subjectId,
                'creator_id' => $user->id,
            ]);

            DocumentService::syncDocumentable($document, $payment);

            $this->syncInvoiceStatus($invoice);

            return $decision;
        });
    }

    private function syncInvoiceStatus(Invoice $invoice): void
    {
        if (! $invoice->status->isApprovedOrSettled()) {
            return;
        }

        $paid = $this->paidAmount($invoice);
        $amount = (float) $invoice->amount;

        $status = match (true) {
            $paid <= 0.0 => InvoiceStatus::APPROVED,
            $paid + 0.001 < $amount => InvoiceStatus::PARTIALLY_PAID,
            default => InvoiceStatus::PAID,
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }

    private function paymentDocument(User $user, Invoice $invoice, int $settlementSubjectId, float $amount, ?string $date = null)
    {
        $customerSubjectId = $invoice->customer->subject?->id ?? $invoice->customer->subject_id;
        $customerSign = in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_BUY]) ? 1 : -1;

        $settlementName = Subject::find($settlementSubjectId)?->name;
        $desc = __('Payment of').' '.__('Invoice').' '.$invoice->invoice_type->label()
            .' '.__(' with number ').' '.formatNumber($invoice->number ?? $invoice->id)
            .($settlementName ? ' ('.$settlementName.')' : '');

        $transactions = [
            [
                'subject_id' => $customerSubjectId,
                'desc' => $desc,
                'value' => $customerSign * $amount,
            ],
            [
                'subject_id' => $settlementSubjectId,
                'desc' => $desc,
                'value' => -$customerSign * $amount,
            ],
        ];

        $documentData = [
            'date' => $date ?? now()->toDateString(),
            'title' => __('Payment of').' '.(__('Invoice #').($invoice->number ?? $invoice->id)),
            'number' => null,
        ];
        $document = DocumentService::createDocument($user, $documentData, $transactions);
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        return $document;
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            if ($payment->document) {
                DocumentService::deleteDocument($payment->document_id);
            }

            $payment->delete();

            if ($invoice) {
                $this->syncInvoiceStatus($invoice);
            }
        });
    }

    public function removePaymentDocument(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            if ($payment->document) {
                DocumentService::deleteDocument($payment->document_id);
                $payment->document_id = null;
                $payment->save();
            }

            if ($invoice) {
                $this->syncInvoiceStatus($invoice);
            }
        });
    }

    public function createPaymentDocument(User $user, Payment $payment): InvoiceStatusDecision
    {
        return DB::transaction(function () use ($user, $payment) {
            $decision = new InvoiceStatusDecision;

            if ($payment->document_id) {
                $decision->addMessage('error', __('This payment already has an accounting document.'));

                return $decision;
            }

            $invoice = $payment->invoice;
            $settlementSubjectId = (int) $payment->settlement_subject_id;

            if (! $invoice || ! $settlementSubjectId) {
                $decision->addMessage('error', __('Cannot create a payment document without a settlement account.'));

                return $decision;
            }

            $document = $this->paymentDocument($user, $invoice, $settlementSubjectId, (float) $payment->amount, $payment->date?->toDateString());

            $payment->document_id = $document->id;
            $payment->save();

            DocumentService::syncDocumentable($document, $payment);

            $this->syncInvoiceStatus($invoice);

            return $decision;
        });
    }
}
