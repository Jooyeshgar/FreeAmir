<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Subjects that a payment can settle into: every bank account's subject
     * plus the descendants of the cash_book subject.
     *
     * The bank and cash_book roots themselves are grouping headers only and are
     * never selectable settlement subjects.
     *
     * @return array<int, int>
     */
    public function settlementSubjectIds(): array
    {
        $bankRootId = (int) config('amir.bank');
        $cashBookId = (int) config('amir.cash_book');

        $bankSubjectIds = BankAccount::query()
            ->whereNotNull('subject_id')
            ->pluck('subject_id')
            ->all();

        $cashSubtreeIds = $cashBookId
            ? (Subject::find($cashBookId)?->getAllDescendantIds() ?? [])
            : [];

        $ids = array_map('intval', array_merge($bankSubjectIds, $cashSubtreeIds));

        // Root subjects are only used to group their children, not selectable.
        $ids = array_diff($ids, [$bankRootId, $cashBookId]);

        return array_values(array_unique($ids));
    }

    /**
     * Settlement subjects available for selection when recording a payment,
     * with their parent eager loaded for grouping.
     */
    public function settlementSubjects()
    {
        $ids = $this->settlementSubjectIds();

        if (empty($ids)) {
            return Subject::query()->whereRaw('1 = 0')->get();
        }

        return Subject::query()->with('parent')->whereIn('id', $ids)->orderBy('code')->get();
    }

    public function validateInvoicePayment(Invoice $invoice, array $data = []): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        if (! $invoice->status->isApproved()) {
            $decision->addMessage('error', __('The invoice must be approved before recording a payment.'));
        }

        if ($invoice->remainingAmount() <= 0) {
            $decision->addMessage('error', __('This invoice is fully paid.'));
        }

        if (isset($data['subject_id']) && ! in_array((int) $data['subject_id'], $this->settlementSubjectIds(), true)) {
            $decision->addMessage('error', __('The selected settlement account is not a valid bank or cash subject.'));
        }

        if (isset($data['amount']) && $data['amount'] > $invoice->remainingAmount() + 0.001) {
            $decision->addMessage('error', __('Payment amount exceeds the remaining balance of the invoice.'));
        }

        return $decision;
    }

    public function createPayment(User $user, Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($user, $invoice, $data) {
            $decision = $this->validateInvoicePayment($invoice, $data);
            if ($decision->hasErrors()) {
                throw new InvoiceServiceException($decision->messages->pluck('text')->implode(' '));
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
                'creator_id' => $user->id,
            ]);

            DocumentService::syncDocumentable($document, $payment);

            return $payment;
        });
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
            if ($payment->document) {
                DocumentService::deleteDocument($payment->document_id);
            }

            $payment->delete();
        });
    }
}
