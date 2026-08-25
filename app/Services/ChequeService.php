<?php

namespace App\Services;

use App\Enums\ChequeType;
use App\Enums\InvoiceType;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Chequebook;
use App\Models\ChequeHistory;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ChequeService
{
    private const DOCUMENTS_RECEIVABLE_CONFIG = 'cheque_documents_receivable';

    private const DOCUMENTS_IN_COLLECTION_CONFIG = 'cheque_documents_in_collection';

    private const DOCUMENTS_PAYABLE_CONFIG = 'cheque_documents_payable';

    public function __construct(private readonly PaymentService $paymentService) {}

    public function directionForInvoice(Invoice $invoice): ?ChequeType
    {
        return match ($invoice->invoice_type) {
            InvoiceType::SELL, InvoiceType::RETURN_BUY => ChequeType::RECEIVABLE,
            InvoiceType::BUY, InvoiceType::RETURN_SELL => ChequeType::PAYABLE,
            default => null,
        };
    }

    public function register(User $user, array $data): Cheque
    {
        return DB::transaction(function () use ($user, $data) {
            $direction = ChequeType::from((int) $data['direction']);
            $purpose = ChequeType::from((int) $data['purpose']);
            $this->validateRegistrationData($data, $direction);
            $invoice = ! empty($data['invoice_id']) ? Invoice::findOrFail($data['invoice_id']) : null;
            if ($invoice) {
                $this->validateInvoicePaymentData($invoice, $direction, $purpose, $data);
            }

            if (! empty($data['chequebook_id'])) {
                $data['cheque_number'] = $this->allocateChequebookLeaf((int) $data['chequebook_id']);
            }

            $status = $this->initialStatus($direction, $purpose);

            $cheque = Cheque::create([
                'company_id' => getActiveCompany(),
                'title' => filled($data['title'] ?? null) ? trim($data['title']) : __('Cheque #').($data['cheque_number'] ?? ''),
                'amount' => $data['amount'],
                'write_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'serial' => ($data['serial'] ?? null) ?: null,
                'cheque_number' => ($data['cheque_number'] ?? null) ?: null,
                'sayad_number' => $data['sayad_number'],
                'direction' => $direction,
                'purpose' => $purpose,
                'status' => $status,
                'customer_id' => $data['customer_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'chequebook_id' => $data['chequebook_id'] ?? null,
                'desc' => $data['description'] ?? null,
            ]);

            $document = $this->postInitialDocument($user, $cheque);
            $paymentSubject = $direction === ChequeType::RECEIVABLE ? self::DOCUMENTS_RECEIVABLE_CONFIG : self::DOCUMENTS_PAYABLE_CONFIG;
            $payment = $invoice ? $this->paymentService->saveChequePayment($user, $invoice, $cheque, $document, $this->subject($paymentSubject)) : null;

            $this->history($cheque, $user, null, $status, $document, $payment, $data['description'] ?? null);

            return $cheque->fresh();
        });
    }

    public function update(Cheque $cheque, User $user, array $data): Cheque
    {
        return DB::transaction(function () use ($cheque, $user, $data) {
            $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);
            if ($lockedCheque->histories()->whereNotNull('from_status')->exists()) {
                throw ValidationException::withMessages(['cheque' => __('A cheque cannot be edited after a lifecycle action. Register a replacement cheque to preserve its audit history.')]);
            }

            $direction = ChequeType::from((int) $data['direction']);
            $purpose = ChequeType::from((int) $data['purpose']);
            $this->validateRegistrationData($data, $direction, $lockedCheque);

            if (! empty($data['chequebook_id'])) {
                $data['cheque_number'] = (int) $data['chequebook_id'] === (int) $lockedCheque->chequebook_id ? $lockedCheque->cheque_number : $this->allocateChequebookLeaf((int) $data['chequebook_id']);
            }

            $initialHistory = $lockedCheque->histories()->whereNull('from_status')->oldest('id')->first();
            $initialPayment = $initialHistory?->payment;
            $invoice = $initialPayment?->invoice;
            if ($invoice) {
                $this->validateInvoicePaymentData($invoice, $direction, $purpose, $data, $initialPayment);
            }

            if ($initialHistory?->document_id) {
                DocumentService::deleteDocument($initialHistory->document_id);
            }

            $status = $this->initialStatus($direction, $purpose);
            $lockedCheque->update([
                'title' => filled($data['title'] ?? null) ? trim($data['title']) : __('Cheque #').($data['cheque_number'] ?? ''),
                'amount' => $data['amount'],
                'write_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'serial' => ($data['serial'] ?? null) ?: null,
                'cheque_number' => ($data['cheque_number'] ?? null) ?: null,
                'sayad_number' => $data['sayad_number'],
                'direction' => $direction,
                'purpose' => $purpose,
                'status' => $status,
                'customer_id' => $data['customer_id'],
                'endorsed_to_id' => null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'chequebook_id' => $data['chequebook_id'] ?? null,
                'desc' => $data['description'] ?? null,
            ]);

            $document = $this->postInitialDocument($user, $lockedCheque);
            if ($initialPayment && $document) {
                $paymentSubject = $direction === ChequeType::RECEIVABLE ? self::DOCUMENTS_RECEIVABLE_CONFIG : self::DOCUMENTS_PAYABLE_CONFIG;
                $initialPayment = $this->paymentService->saveChequePayment($user, $invoice, $lockedCheque, $document, $this->subject($paymentSubject), $initialPayment);
            } elseif ($initialPayment) {
                $initialPayment->delete();
                $initialPayment = null;
            }
            $historyData = [
                'to_status' => $status,
                'user_id' => $user->id,
                'document_id' => $document?->id,
                'payment_id' => $initialPayment?->id,
                'desc' => $data['description'] ?? null,
            ];
            if ($initialHistory) {
                $initialHistory->update($historyData);
            } else {
                $this->history($lockedCheque, $user, null, $status, $document, $initialPayment, $data['description'] ?? null);
            }

            return $lockedCheque->fresh();
        });
    }

    public function delete(Cheque $cheque): void
    {
        DB::transaction(function () use ($cheque) {
            $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);

            $invoices = Invoice::whereIn('id', $lockedCheque->payments()->whereNotNull('invoice_id')->pluck('invoice_id')->unique())->get();
            $documentIds = $lockedCheque->histories()->pluck('document_id')->merge($lockedCheque->payments()->pluck('document_id'))
                ->merge(Document::withoutGlobalScopes()->where('documentable_type', $lockedCheque->getMorphClass())->where('documentable_id', $lockedCheque->id)->pluck('id'))
                ->filter()->unique()->values();

            app(ActivityLogService::class)->deleteModels($lockedCheque->payments()->getQuery());
            $lockedCheque->delete();

            foreach ($documentIds as $documentId) {
                DocumentService::deleteDocument((int) $documentId);
            }

            foreach ($invoices as $invoice) {
                $this->paymentService->syncInvoiceStatus($invoice);
            }
        });
    }

    public function transition(Cheque $cheque, User $user, string $action, array $data = []): Cheque
    {
        return DB::transaction(function () use ($cheque, $user, $action, $data) {
            $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);

            if (! in_array($action, $lockedCheque->availableActions(), true)) {
                throw ValidationException::withMessages(['status' => __('This action is not allowed in the current status.')]);
            }

            return match ($action) {
                'deposit' => $this->deposit($lockedCheque, $user, $data),
                'clear' => $this->clear($lockedCheque, $user, $data),
                'endorse' => $this->endorse($lockedCheque, $user, $data),
                'bounce' => $this->bounce($lockedCheque, $user, $data),
                'return' => $this->returnToCustomer($lockedCheque, $user, $data),
                'cancel' => $this->cancel($lockedCheque, $user, $data),
                'execute' => $this->executeGuarantee($lockedCheque, $user, $data),
                default => throw ValidationException::withMessages(['action' => __('This action is not allowed in the current status.')]),
            };
        });
    }

    private function deposit(Cheque $cheque, User $user, array $data): Cheque
    {
        if (empty($data['bank_account_id'])) {
            throw ValidationException::withMessages(['bank_account_id' => __('A bank account is required.')]);
        }
        $account = BankAccount::findOrFail($data['bank_account_id']);

        $from = $cheque->status;
        $amount = (float) $cheque->amount;
        $document = $this->post($user, $cheque, 'deposit', [
            [$this->subject(self::DOCUMENTS_IN_COLLECTION_CONFIG), -$amount],
            [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
        ], $data['date'] ?? null);

        $cheque->update(['status' => ChequeType::DEPOSITED, 'bank_account_id' => $account->id]);
        $this->history($cheque, $user, $from, ChequeType::DEPOSITED, $document, null, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function clear(Cheque $cheque, User $user, array $data): Cheque
    {
        $account = $cheque->bankAccount;
        if (! $account && ! empty($data['bank_account_id'])) {
            $account = BankAccount::findOrFail($data['bank_account_id']);
        }

        if (! $account) {
            throw ValidationException::withMessages(['bank_account_id' => __('A bank account is required.')]);
        }

        $bankSubject = (int) ($account->subject_id ?: $account->subject?->id);
        if (! $bankSubject) {
            throw ValidationException::withMessages(['bank_account_id' => __('The bank account has no accounting subject.')]);
        }

        $from = $cheque->status;
        $amount = (float) $cheque->amount;
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$bankSubject, -$amount], [$this->subject(self::DOCUMENTS_IN_COLLECTION_CONFIG), $amount]]
            : [[$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), -$amount], [$bankSubject, $amount]];

        $document = $this->post($user, $cheque, 'clear', $entries, $data['date'] ?? null);
        $payment = $this->payment($user, $cheque, $document, $bankSubject, $data, 'clear');
        DocumentService::syncDocumentable($document, $payment);

        $cheque->update(['status' => ChequeType::CLEARED]);
        $this->history($cheque, $user, $from, ChequeType::CLEARED, $document, $payment, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function endorse(Cheque $cheque, User $user, array $data): Cheque
    {
        if (empty($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => __('Select the vendor receiving the endorsed cheque.')]);
        }

        $vendor = Customer::findOrFail($data['customer_id']);
        $vendorSubject = $this->customerSubject($vendor, $cheque);

        $from = $cheque->status;
        $amount = (float) $cheque->amount;
        $document = $this->post($user, $cheque, 'endorse', [
            [$vendorSubject, -$amount],
            [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
        ], $data['date'] ?? null);
        $payment = $this->payment($user, $cheque, $document, $vendorSubject, $data, 'endorse', $vendor->id);
        DocumentService::syncDocumentable($document, $payment);

        $cheque->update(['status' => ChequeType::ENDORSED, 'endorsed_to_id' => $vendor->id]);
        $this->history($cheque, $user, $from, ChequeType::ENDORSED, $document, $payment, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function bounce(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $amount = (float) $cheque->amount;
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), -$amount], [$this->subject(self::DOCUMENTS_IN_COLLECTION_CONFIG), $amount]]
            : [[$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), -$amount], [$this->accountSideSubject($cheque), $amount]];
        $document = $this->post($user, $cheque, 'bounce', $entries, $data['date'] ?? null);

        $cheque->update(['status' => ChequeType::BOUNCED]);
        $this->history($cheque, $user, $from, ChequeType::BOUNCED, $document, null, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function returnToCustomer(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $amount = (float) $cheque->amount;
        $document = $this->post($user, $cheque, 'return', [
            [$this->accountSideSubject($cheque), -$amount],
            [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
        ], $data['date'] ?? null);

        $cheque->update(['status' => ChequeType::RETURNED]);
        $this->history($cheque, $user, $from, ChequeType::RETURNED, $document, null, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function cancel(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $document = null;
        if ($cheque->purpose === ChequeType::SETTLEMENT && $from === ChequeType::ISSUED) {
            $amount = (float) $cheque->amount;
            $document = $this->post($user, $cheque, 'cancel', [
                [$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), -$amount],
                [$this->accountSideSubject($cheque), $amount],
            ], $data['date'] ?? null);
        }

        $cheque->update(['status' => ChequeType::CANCELLED]);
        $this->history($cheque, $user, $from, ChequeType::CANCELLED, $document, null, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function executeGuarantee(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $to = $cheque->direction === ChequeType::RECEIVABLE ? ChequeType::REGISTERED : ChequeType::ISSUED;
        $amount = (float) $cheque->amount;
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), -$amount], [$this->accountSideSubject($cheque), $amount]]
            : [[$this->accountSideSubject($cheque), -$amount], [$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), $amount]];
        $document = $this->post($user, $cheque, 'execute', $entries, $data['date'] ?? null);

        $cheque->update(['purpose' => ChequeType::SETTLEMENT, 'status' => $to]);
        $this->history($cheque, $user, $from, $to, $document, null, $data['description'] ?? null);

        return $cheque->fresh();
    }

    private function initialStatus(ChequeType $direction, ChequeType $purpose): ChequeType
    {
        return match (true) {
            $purpose === ChequeType::GUARANTEE && $direction === ChequeType::RECEIVABLE => ChequeType::GUARANTEE_RECEIVED,
            $purpose === ChequeType::GUARANTEE => ChequeType::GUARANTEE_GIVEN,
            $direction === ChequeType::RECEIVABLE => ChequeType::REGISTERED,
            default => ChequeType::ISSUED,
        };
    }

    private function postInitialDocument(User $user, Cheque $cheque): ?Document
    {
        if ($cheque->purpose === ChequeType::GUARANTEE) {
            return null;
        }

        $amount = (float) $cheque->amount;
        $writeDate = Carbon::parse($cheque->getRawOriginal('write_date'))->toDateString();

        return $cheque->direction === ChequeType::RECEIVABLE
            ? $this->post($user, $cheque, 'register', [
                [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), -$amount],
                [$this->accountSideSubject($cheque), $amount],
            ], $writeDate)
            : $this->post($user, $cheque, 'issue', [
                [$this->accountSideSubject($cheque), -$amount],
                [$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), $amount],
            ], $writeDate);
    }

    private function validateRegistrationData(array $data, ChequeType $direction, ?Cheque $except = null): void
    {
        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount' => __('Cheque amount must be greater than zero.')]);
        }
        if (! preg_match('/^\d{16}$/', (string) ($data['sayad_number'] ?? ''))) {
            throw ValidationException::withMessages(['sayad_number' => __('validation.regex', ['attribute' => __('16-digit Sayad number')])]);
        }
        $duplicateSayad = Cheque::withoutGlobalScopes()->where('sayad_number', $data['sayad_number'])->when($except, fn ($query) => $query->where('id', '!=', $except->id))->exists();
        if ($duplicateSayad) {
            throw ValidationException::withMessages(['sayad_number' => __('validation.unique', ['attribute' => __('16-digit Sayad number')])]);
        }
        if (! Customer::whereKey($data['customer_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['customer_id' => __('validation.exists', ['attribute' => __('Account side')])]);
        }
        $account = ! empty($data['bank_account_id']) ? BankAccount::find($data['bank_account_id']) : null;
        if ($direction === ChequeType::PAYABLE && ! $account) {
            throw ValidationException::withMessages(['bank_account_id' => __('A bank account is required.')]);
        }

        $chequebook = ! empty($data['chequebook_id']) ? Chequebook::find($data['chequebook_id']) : null;
        if (! empty($data['chequebook_id']) && ! $chequebook) {
            throw ValidationException::withMessages(['chequebook_id' => __('The selected chequebook is invalid.')]);
        }
        if ($chequebook && $direction !== ChequeType::PAYABLE) {
            throw ValidationException::withMessages(['chequebook_id' => __('Only payable cheques can belong to a chequebook.')]);
        }
        if ($chequebook && (int) $chequebook->bank_account_id !== (int) $account?->id) {
            throw ValidationException::withMessages(['chequebook_id' => __('The chequebook must belong to the selected bank account.')]);
        }
    }

    private function validateInvoicePaymentData(Invoice $invoice, ChequeType $direction, ChequeType $purpose, array $data, ?Payment $except = null): void
    {
        $expectedDirection = $this->directionForInvoice($invoice);
        if (! $expectedDirection) {
            throw ValidationException::withMessages(['invoice_id' => __('This invoice type cannot be settled by cheque.')]);
        }
        if ($purpose !== ChequeType::SETTLEMENT) {
            throw ValidationException::withMessages(['purpose' => __('A guarantee cheque cannot settle an invoice.')]);
        }
        if ($direction !== $expectedDirection) {
            throw ValidationException::withMessages(['direction' => __('The cheque direction does not match the invoice type.')]);
        }
        if ((int) ($data['customer_id'] ?? 0) !== (int) $invoice->customer_id) {
            throw ValidationException::withMessages(['customer_id' => __('The cheque account side must match the invoice account side.')]);
        }

        $decision = $this->paymentService->validateInvoicePayment($invoice, [
            'amount' => (float) $data['amount'],
            'date' => $data['issue_date'],
        ], $except);
        if ($decision->hasErrors()) {
            throw ValidationException::withMessages(['invoice_id' => $decision->messages->pluck('text')->all()]);
        }
    }

    private function allocateChequebookLeaf(int $chequebookId): string
    {
        $chequebook = Chequebook::query()->lockForUpdate()->findOrFail($chequebookId);

        if ($chequebook->next_leaf > $chequebook->last_leaf) {
            throw ValidationException::withMessages(['chequebook_id' => __('The selected chequebook has no unused leaves.')]);
        }

        $leaf = $chequebook->next_leaf;
        $chequebook->increment('next_leaf');

        return (string) $leaf;
    }

    private function post(User $user, Cheque $cheque, string $event, array $entries, ?string $date = null): Document
    {
        $title = $cheque->title ?? $this->accountingDescription($event, $cheque);
        $description = $cheque->desc ?? $this->accountingDescription($event, $cheque);
        $transactions = array_map(fn (array $entry) => [
            'subject_id' => $entry[0],
            'value' => $entry[1],
            'desc' => $description,
        ], $entries);

        $document = DocumentService::createDocument($user, [
            'date' => $date ?? now()->toDateString(),
            'title' => $title,
            'number' => null,
            'documentable' => $cheque,
        ], $transactions);
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        return $document;
    }

    private function payment(User $user, Cheque $cheque, Document $document, int $settlementSubjectId, array $data, string $event, ?int $payerId = null): Payment
    {
        return Payment::create([
            'cheque_id' => $cheque->id,
            'payer_id' => $payerId ?? ($cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null),
            'amount' => $cheque->amount,
            'date' => $data['date'] ?? now()->toDateString(),
            'description' => $data['description'] ?? $this->accountingDescription($event, $cheque),
            'reference_number' => $cheque->sayad_number,
            'document_id' => $document->id,
            'settlement_subject_id' => $settlementSubjectId,
            'creator_id' => $user->id,
        ]);
    }

    private function history(Cheque $cheque, User $user, ?ChequeType $from, ChequeType $to, ?Document $document, ?Payment $payment, ?string $description = null): ChequeHistory
    {
        return ChequeHistory::create([
            'cheque_id' => $cheque->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->id,
            'document_id' => $document?->id,
            'payment_id' => $payment?->id,
            'desc' => $description,
        ]);
    }

    private function accountingDescription(string $event, Cheque $cheque): string
    {
        $key = match ($event) {
            'register' => 'Receive cheque :serial',
            'issue' => 'Issue cheque :serial',
            'deposit' => 'Deposit cheque :serial',
            'clear' => 'Clear cheque :serial',
            'endorse' => 'Endorse cheque :serial',
            'bounce' => 'Dishonour cheque :serial',
            'return' => 'Return cheque :serial',
            'cancel' => 'Cancel cheque :serial',
            'execute' => 'Execute guarantee cheque :serial',
            default => throw new InvalidArgumentException("Unknown cheque event [{$event}]."),
        };

        return __($key, ['serial' => $cheque->serial ?: $cheque->cheque_number ?: $cheque->sayad_number]);
    }

    private function subject(string $configKey): int
    {
        $subjectId = (int) config('amir.'.$configKey);
        $subject = $subjectId ? Subject::where('company_id', getActiveCompany())->find($subjectId) : null;
        if (! $subject) {
            throw ValidationException::withMessages(['accounting' => __('Accounting subject configuration :key is missing or invalid.', ['key' => $configKey])]);
        }

        return (int) $subject->id;
    }

    private function accountSideSubject(Cheque $cheque): int
    {
        $customer = $cheque->customer;
        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => __('The account side has no accounting subject.')]);
        }

        return $this->customerSubject($customer, $cheque);
    }

    private function customerSubject(Customer $customer, Cheque $cheque): int
    {
        if ((int) $customer->company_id != (int) $cheque->company_id) {
            throw ValidationException::withMessages(['customer_id' => __('The selected account side is invalid.')]);
        }

        $subjectId = (int) ($customer->subject_id ?: $customer->subject?->id);
        $subjectBelongsToCompany = $subjectId && Subject::where('company_id', $cheque->company_id)->whereKey($subjectId)->exists();
        if (! $subjectBelongsToCompany) {
            throw ValidationException::withMessages(['customer_id' => __('The account side has no accounting subject.')]);
        }

        return $subjectId;
    }
}
