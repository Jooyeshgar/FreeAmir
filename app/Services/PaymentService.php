<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\ChequeType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Cheque;
use App\Models\Config;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function settlementSubjects(string $search = ''): Collection
    {
        $code = str_replace('/', '', toEnglish($search));

        return Subject::query()->with('parent')->whereNotNull('parent_id')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$code}%")))
            ->orderBy('code')->orderBy('id')->limit(25)->get();
    }

    public function paidAmount(Invoice $invoice, ?Payment $except = null): float
    {
        return (float) $invoice->payments()->whereNotNull('document_id')->when($except, fn ($query) => $query->where('id', '!=', $except->id))->sum('amount');
    }

    public function payableAmount(Invoice $invoice): float
    {
        $ancillaryCostsAmount = $invoice->invoice_type === InvoiceType::BUY
            ? (float) $invoice->ancillaryCosts()->where('status', InvoiceStatus::APPROVED)->sum('amount')
            : 0.0;

        return (float) $invoice->amount + $ancillaryCostsAmount;
    }

    public function remainingAmount(Invoice $invoice, ?Payment $except = null): float
    {
        return max($this->payableAmount($invoice) - $this->paidAmount($invoice, $except), 0.0);
    }

    public function validateInvoicePayment(Invoice $invoice, array $data = [], ?Payment $except = null): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        $editingSettledPayment = $except && $invoice->status->isApprovedOrSettled();
        if (! $invoice->status->isApproved() && ! $invoice->status->isPartiallyPaid() && ! $editingSettledPayment) {
            $decision->addMessage('error', __('The invoice must be approved before recording a payment.'));
        }

        if ($this->remainingAmount($invoice, $except) <= 0) {
            $decision->addMessage('error', __('This invoice is fully paid.'));
        }

        if (isset($data['subject_id']) && ! Subject::query()->whereNotNull('parent_id')->whereKey($data['subject_id'])->exists()) {
            $decision->addMessage('error', __('The selected settlement account is invalid.'));
        }

        if (isset($data['amount']) && $data['amount'] > $this->remainingAmount($invoice, $except) + 0.001) {
            $decision->addMessage('error', __('Payment amount exceeds the remaining balance of the invoice.'));
        }

        $fee = ($data['bank_service_fee'] ?? '') === '' ? 0 : $data['bank_service_fee'];
        if (! is_numeric($fee) || ! is_finite((float) $fee) || $fee < 0) {
            $decision->addMessage('error', __('Bank service fee must be zero or greater.'));
        } elseif ($fee > 0 && ! $this->bankServiceFeeSubjectId()) {
            $decision->addMessage('error', __('Configure a bank service fee expense account for the active fiscal year.'));
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
            $fee = round((float) ($data['bank_service_fee'] ?? 0), 2);
            $document = $this->paymentDocument($user, $invoice, $subjectId, $data['amount'], $date, $fee);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payer_id' => $invoice->customer_id,
                'amount' => $data['amount'],
                'bank_service_fee' => $fee,
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

    public function updatePayment(User $user, Payment $payment, array $data): InvoiceStatusDecision
    {
        return DB::transaction(function () use ($user, $payment, $data) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            app(FiscalYearMutationGuard::class)->ensureOpen($invoice);

            if ($payment->cheque_id) {
                throw ValidationException::withMessages([
                    'payment' => __('A cheque payment document is managed through the cheque lifecycle.'),
                ]);
            }

            $decision = $this->validateInvoicePayment($invoice, $data, $payment);
            if ($decision->hasErrors()) {
                return $decision;
            }

            $payment->update([
                'amount' => $data['amount'],
                'bank_service_fee' => round((float) ($data['bank_service_fee'] ?? 0), 2),
                'settlement_subject_id' => (int) $data['subject_id'],
                'date' => $data['date'] ?? $payment->date?->toDateString() ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
            ]);

            if ($payment->document_id) {
                $this->paymentDocument($user, $invoice, $payment->settlement_subject_id,
                    (float) $payment->amount, $payment->date->toDateString(),
                    (float) $payment->bank_service_fee, $payment->document);
            }

            $this->syncInvoiceStatus($invoice);

            return $decision;
        });
    }

    public function saveChequePayment(User $user, ?Invoice $invoice, Cheque $cheque, Document $document, int $settlementSubjectId, ?Payment $payment = null, float $fee = 0): Payment
    {
        if ($invoice) {
            $decision = $this->validateInvoicePayment($invoice, [
                'amount' => (float) $cheque->amount,
                'bank_service_fee' => $fee,
                'date' => $cheque->write_date->toDateString(),
            ], $payment);
            if ($decision->hasErrors()) {
                throw ValidationException::withMessages([
                    'invoice_id' => $decision->messages->pluck('text')->all(),
                ]);
            }
        }

        $attributes = [
            'invoice_id' => $invoice?->id,
            'cheque_id' => $cheque->id,
            'payer_id' => $cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null,
            'amount' => $cheque->amount,
            'bank_service_fee' => $fee,
            'date' => $cheque->write_date,
            'description' => $cheque->desc,
            'reference_number' => $cheque->sayad_number,
            'document_id' => $document->id,
            'settlement_subject_id' => $settlementSubjectId,
            'creator_id' => $user->id,
        ];

        if ($payment) {
            $payment->update($attributes);
        } else {
            $payment = Payment::create($attributes);
        }

        DocumentService::syncDocumentable($document, $payment);
        if ($invoice) {
            $this->syncInvoiceStatus($invoice);
        }

        return $payment->fresh();
    }

    public function syncInvoiceStatus(Invoice $invoice): void
    {
        if (! $invoice->status->isApprovedOrSettled()) {
            return;
        }

        $paid = $this->paidAmount($invoice);
        $amount = $this->payableAmount($invoice);

        $status = match (true) {
            $paid <= 0.0 => InvoiceStatus::APPROVED,
            $paid + 0.001 < $amount => InvoiceStatus::PARTIALLY_PAID,
            default => InvoiceStatus::PAID,
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }

    private function bankServiceFeeSubjectId(): ?int
    {
        // Read the scoped setting directly to avoid using another fiscal year's cached config.
        $subjectId = (int) Config::where('key', 'bank_service_fee')->value('value');

        return $subjectId && Subject::whereKey($subjectId)->exists() ? $subjectId : null;
    }

    public function bankServiceFeeTransactions(float $fee, int $settlementSubjectId): array
    {
        if ($fee <= 0) {
            return [];
        }

        $expenseSubjectId = $this->bankServiceFeeSubjectId();
        if (! $expenseSubjectId) {
            throw ValidationException::withMessages([
                'bank_service_fee' => __('Configure a bank service fee expense account for the active fiscal year.'),
            ]);
        }

        return [
            ['subject_id' => $expenseSubjectId, 'desc' => __('Bank service fee'), 'value' => -$fee],
            ['subject_id' => $settlementSubjectId, 'desc' => __('Bank service fee'), 'value' => $fee],
        ];
    }

    private function paymentDocument(User $user, Invoice $invoice, int $settlementSubjectId, float $amount, ?string $date = null, float $fee = 0, ?Document $document = null)
    {
        $customerSubjectId = $invoice->customer->subject?->id ?? $invoice->customer->subject_id;
        $customerSign = in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_BUY]) ? 1 : -1;
        $desc = __('Payment of').' '.__('Invoice').' '.$invoice->invoice_type->label().' '.__(' with number ').' '.localizeNumber($invoice->number ?? $invoice->id);

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
        $transactions = array_merge($transactions, $this->bankServiceFeeTransactions($fee, $settlementSubjectId));

        $documentData = [
            'date' => $date ?? now()->toDateString(),
            'title' => __('Payment of').' '.(__('Invoice #').localizeNumber($invoice->number ?? $invoice->id)),
            'number' => null,
        ];
        if ($document) {
            DocumentService::updateDocument($document, [
                'date' => $documentData['date'],
                'documentable' => $document->documentable,
            ]);
            app(ActivityLogService::class)->deleteModels($document->transactions()->getQuery());
            foreach ($transactions as $transactionData) {
                DocumentService::createTransaction($document, $transactionData);
            }

            return $document;
        }

        $document = DocumentService::createDocument($user, $documentData, $transactions);
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        return $document;
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->cheque_id) { // payment with cheque
                $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($lockedPayment->cheque_id);

                $invoices = Invoice::whereIn('id', $lockedCheque->payments()->whereNotNull('invoice_id')->pluck('invoice_id')->unique())->get();

                $documentIds = $lockedCheque->histories()->pluck('document_id')->merge($lockedCheque->payments()->pluck('document_id'))->merge(
                    Document::withoutGlobalScopes()->where('documentable_type', $lockedCheque->getMorphClass())->where('documentable_id', $lockedCheque->id)->pluck('id')
                )->filter()->unique();

                app(ActivityLogService::class)->deleteModels($lockedCheque->payments()->getQuery());
                $lockedCheque->delete();

                foreach ($documentIds as $documentId) {
                    DocumentService::deleteDocument((int) $documentId);
                }

                foreach ($invoices as $invoice) {
                    $this->syncInvoiceStatus($invoice);
                }

                return;
            }

            $invoice = $lockedPayment->invoice;
            $documentId = $lockedPayment->document_id;

            $lockedPayment->delete();

            if ($documentId) {
                DocumentService::deleteDocument((int) $documentId);
            }

            if ($invoice) {
                $this->syncInvoiceStatus($invoice);
            }
        });
    }

    public function removePaymentDocument(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            if ($payment->cheque_id) {
                throw ValidationException::withMessages([
                    'payment' => __('A cheque payment document is managed through the cheque lifecycle.'),
                ]);
            }

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

            if ($payment->cheque_id) {
                $decision->addMessage('error', __('A cheque payment document is managed through the cheque lifecycle.'));

                return $decision;
            }

            $invoice = $payment->invoice;
            $settlementSubjectId = (int) $payment->settlement_subject_id;

            if (! $invoice || ! $settlementSubjectId) {
                $decision->addMessage('error', __('Cannot create a payment document without a settlement account.'));

                return $decision;
            }

            $decision = $this->validateInvoicePayment($invoice, [
                'amount' => (float) $payment->amount,
                'subject_id' => $settlementSubjectId,
                'bank_service_fee' => (float) $payment->bank_service_fee,
            ], $payment);
            if ($decision->hasErrors()) {
                return $decision;
            }

            $document = $this->paymentDocument($user, $invoice, $settlementSubjectId, (float) $payment->amount, $payment->date?->toDateString(), (float) $payment->bank_service_fee);

            $payment->document_id = $document->id;
            $payment->save();

            DocumentService::syncDocumentable($document, $payment);

            $this->syncInvoiceStatus($invoice);

            return $decision;
        });
    }
}
