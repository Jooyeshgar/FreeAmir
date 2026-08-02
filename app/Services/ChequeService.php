<?php

namespace App\Services;

use App\Enums\ChequeType;
use App\Enums\InvoiceType;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\ChequeHistory;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ChequeService
{
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

            $serial = $data['serial'] ?: null;

            $status = $this->initialStatus($direction, $purpose);

            $cheque = Cheque::create([
                'company_id' => getActiveCompany(),
                'amount' => $data['amount'],
                'write_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'serial' => $serial,
                'cheque_number' => ($data['cheque_number'] ?? null) ?: null,
                'sayad_number' => $data['sayad_number'],
                'direction' => $direction,
                'purpose' => $purpose,
                'status' => $status,
                'customer_id' => $data['account_side_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'desc' => $data['description'] ?? null,
                'version' => 1,
            ]);

            $document = $this->postInitialDocument($user, $cheque);
            $payment = $invoice ? $this->paymentService->saveChequePayment($user, $invoice, $cheque, $document, $this->subject($direction === ChequeType::RECEIVABLE ? '013001' : '020001')) : null;

            $this->history($cheque, $user, null, $status, $document, $payment, ['description' => $data['description'] ?? null]);

            return $cheque->fresh();
        });
    }

    public function update(Cheque $cheque, User $user, array $data): Cheque
    {
        return DB::transaction(function () use ($cheque, $user, $data) {
            /** @var Cheque $locked */
            $locked = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);
            if (isset($data['version']) && (int) $data['version'] !== $locked->version) {
                throw ValidationException::withMessages(['cheque' => __('This cheque changed in another session. Reload and retry.')]);
            }
            if ($locked->histories()->whereNotNull('from_status')->exists()) {
                throw ValidationException::withMessages(['cheque' => __('A cheque cannot be edited after a lifecycle action. Register a replacement cheque to preserve its audit history.')]);
            }

            $direction = ChequeType::from((int) $data['direction']);
            $purpose = ChequeType::from((int) $data['purpose']);
            $this->validateRegistrationData($data, $direction, $locked);
            $initialHistory = $locked->histories()->whereNull('from_status')->oldest('id')->first();
            $initialPayment = $initialHistory?->payment;
            $invoice = $initialPayment?->invoice;
            if ($invoice) {
                $this->validateInvoicePaymentData($invoice, $direction, $purpose, $data, $initialPayment);
            }

            $serial = $data['serial'] ?: null;

            if ($initialHistory?->document_id) {
                DocumentService::deleteDocument($initialHistory->document_id);
            }

            $status = $this->initialStatus($direction, $purpose);
            $locked->update([
                'amount' => $data['amount'],
                'write_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'serial' => $serial,
                'cheque_number' => ($data['cheque_number'] ?? null) ?: null,
                'sayad_number' => $data['sayad_number'],
                'direction' => $direction,
                'purpose' => $purpose,
                'status' => $status,
                'customer_id' => $data['account_side_id'],
                'endorsed_to_id' => null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'desc' => $data['description'] ?? null,
                'version' => $locked->version + 1,
            ]);

            $document = $this->postInitialDocument($user, $locked);
            if ($initialPayment && $document) {
                $initialPayment = $this->paymentService->saveChequePayment($user, $invoice, $locked, $document, $this->subject($direction === ChequeType::RECEIVABLE ? '013001' : '020001'), $initialPayment);
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
                $this->history($locked, $user, null, $status, $document, $initialPayment, ['description' => $data['description'] ?? null]);
            }

            return $locked->fresh();
        });
    }

    public function delete(Cheque $cheque, User $user, ?int $version = null): void
    {
        DB::transaction(function () use ($cheque, $version) {
            /** @var Cheque $locked */
            $locked = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);
            if ($version !== null && $version !== $locked->version) {
                throw ValidationException::withMessages(['cheque' => __('This cheque changed in another session. Reload and retry.')]);
            }

            $invoices = Invoice::whereIn('id', $locked->payments()->whereNotNull('invoice_id')->pluck('invoice_id')->unique())->get();
            $documentIds = $locked->histories()->pluck('document_id')->merge($locked->payments()->pluck('document_id'))
                ->merge(Document::withoutGlobalScopes()->where('documentable_type', $locked->getMorphClass())->where('documentable_id', $locked->id)->pluck('id'))
                ->filter()->unique()->values();

            $locked->payments()->delete();
            $locked->delete();
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
            /** @var Cheque $locked */
            $locked = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);
            if (isset($data['version']) && (int) $data['version'] !== $locked->version) {
                throw ValidationException::withMessages(['cheque' => __('This cheque changed in another session. Reload and retry.')]);
            }

            if (! in_array($action, $locked->availableActions(), true)) {
                throw ValidationException::withMessages(['status' => __('This action is not allowed in the current status.')]);
            }

            return match ($action) {
                'deposit' => $this->deposit($locked, $user, $data),
                'clear' => $this->clear($locked, $user, $data),
                'endorse' => $this->endorse($locked, $user, $data),
                'bounce' => $this->bounce($locked, $user, $data),
                'return' => $this->returnToCustomer($locked, $user, $data),
                'cancel' => $this->cancel($locked, $user, $data),
                'execute' => $this->executeGuarantee($locked, $user, $data),
                default => throw ValidationException::withMessages(['action' => __('This action is not allowed in the current status.')]),
            };
        });
    }

    private function deposit(Cheque $cheque, User $user, array $data): Cheque
    {
        $account = $this->bankAccount($data);
        $from = $cheque->status;
        $previousAccountId = $cheque->bank_account_id;
        $document = $this->post($user, $cheque, 'deposit', [
            [$this->subject('014001'), -$this->amount($cheque)],
            [$this->subject('013001'), $this->amount($cheque)],
        ], $data['date'] ?? null);

        $cheque->update([
            'status' => ChequeType::DEPOSITED,
            'bank_account_id' => $account->id,
            'version' => $cheque->version + 1,
        ]);
        $this->history($cheque, $user, $from, ChequeType::DEPOSITED, $document, null, [
            'bank_account_id' => $account->id,
            'previous_bank_account_id' => $previousAccountId,
            'description' => $data['description'] ?? null,
        ]);

        return $cheque->fresh();
    }

    private function clear(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $account = $cheque->direction === ChequeType::RECEIVABLE ? ($cheque->bankAccount ?? $this->bankAccount($data)) : $cheque->bankAccount;

        if (! $account) {
            throw ValidationException::withMessages(['bank_account_id' => __('A bank account is required.')]);
        }

        $bankSubject = $this->bankSubject($account);
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$bankSubject, -$this->amount($cheque)], [$this->subject('014001'), $this->amount($cheque)]]
            : [[$this->subject('020001'), -$this->amount($cheque)], [$bankSubject, $this->amount($cheque)]];

        $document = $this->post($user, $cheque, 'clear', $entries, $data['date'] ?? null);
        $payment = $this->payment($user, $cheque, $document, $bankSubject, $data, 'clear');
        DocumentService::syncDocumentable($document, $payment);

        $cheque->update(['status' => ChequeType::CLEARED, 'version' => $cheque->version + 1]);
        $this->history($cheque, $user, $from, ChequeType::CLEARED, $document, $payment, [
            'bank_account_id' => $account->id,
            'description' => $data['description'] ?? null,
        ]);

        return $cheque->fresh();
    }

    private function endorse(Cheque $cheque, User $user, array $data): Cheque
    {
        if (empty($data['account_side_id'])) {
            throw ValidationException::withMessages(['account_side_id' => __('Select the vendor receiving the endorsed cheque.')]);
        }

        $vendor = Customer::findOrFail($data['account_side_id']);
        $vendorSubject = (int) ($vendor->subject_id ?: $vendor->subject?->id);
        if (! $vendorSubject) {
            throw ValidationException::withMessages(['account_side_id' => __('The account side has no accounting subject.')]);
        }

        $from = $cheque->status;
        $document = $this->post($user, $cheque, 'endorse', [
            [$vendorSubject, -$this->amount($cheque)],
            [$this->subject('013001'), $this->amount($cheque)],
        ], $data['date'] ?? null);
        $payment = $this->payment($user, $cheque, $document, $vendorSubject, $data, 'endorse', $vendor->id);
        DocumentService::syncDocumentable($document, $payment);

        $cheque->update([
            'status' => ChequeType::ENDORSED,
            'endorsed_to_id' => $vendor->id,
            'version' => $cheque->version + 1,
        ]);
        $this->history($cheque, $user, $from, ChequeType::ENDORSED, $document, $payment, [
            'endorsed_to_id' => $vendor->id,
            'description' => $data['description'] ?? null,
        ]);

        return $cheque->fresh();
    }

    private function bounce(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$this->subject('013001'), -$this->amount($cheque)], [$this->subject('014001'), $this->amount($cheque)]]
            : [[$this->subject('020001'), -$this->amount($cheque)], [$this->accountSideSubject($cheque), $this->amount($cheque)]];
        $document = $this->post($user, $cheque, 'bounce', $entries, $data['date'] ?? null);

        $cheque->update(['status' => ChequeType::BOUNCED, 'version' => $cheque->version + 1]);
        $this->history($cheque, $user, $from, ChequeType::BOUNCED, $document, null, [
            'description' => $data['description'] ?? null,
        ]);

        return $cheque->fresh();
    }

    private function returnToCustomer(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $document = $this->post($user, $cheque, 'return', [
            [$this->accountSideSubject($cheque), -$this->amount($cheque)],
            [$this->subject('013001'), $this->amount($cheque)],
        ], $data['date'] ?? null);

        $cheque->update(['status' => ChequeType::RETURNED, 'version' => $cheque->version + 1]);
        $this->history($cheque, $user, $from, ChequeType::RETURNED, $document, null, [
            'description' => $data['description'] ?? null,
        ]);

        return $cheque->fresh();
    }

    private function cancel(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $document = null;
        if ($cheque->purpose === ChequeType::SETTLEMENT && $from === ChequeType::ISSUED) {
            $document = $this->post($user, $cheque, 'cancel', [
                [$this->subject('020001'), -$this->amount($cheque)],
                [$this->accountSideSubject($cheque), $this->amount($cheque)],
            ], $data['date'] ?? null);
        }

        $cheque->update(['status' => ChequeType::CANCELLED, 'version' => $cheque->version + 1]);
        $this->history($cheque, $user, $from, ChequeType::CANCELLED, $document, null, [
            'description' => $data['description'] ?? null,
        ]);

        return $cheque->fresh();
    }

    private function executeGuarantee(Cheque $cheque, User $user, array $data): Cheque
    {
        $from = $cheque->status;
        $to = $cheque->direction === ChequeType::RECEIVABLE ? ChequeType::REGISTERED : ChequeType::ISSUED;
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$this->subject('013001'), -$this->amount($cheque)], [$this->accountSideSubject($cheque), $this->amount($cheque)]]
            : [[$this->accountSideSubject($cheque), -$this->amount($cheque)], [$this->subject('020001'), $this->amount($cheque)]];
        $document = $this->post($user, $cheque, 'execute', $entries, $data['date'] ?? null);

        $cheque->update([
            'purpose' => ChequeType::SETTLEMENT,
            'status' => $to,
            'version' => $cheque->version + 1,
        ]);
        $this->history($cheque, $user, $from, $to, $document, null, [
            'previous_purpose' => ChequeType::GUARANTEE->value,
            'description' => $data['description'] ?? null,
        ]);

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

        return $cheque->direction === ChequeType::RECEIVABLE
            ? $this->post($user, $cheque, 'register', [
                [$this->subject('013001'), -$this->amount($cheque)],
                [$this->accountSideSubject($cheque), $this->amount($cheque)],
            ], $cheque->write_date->toDateString())
            : $this->post($user, $cheque, 'issue', [
                [$this->accountSideSubject($cheque), -$this->amount($cheque)],
                [$this->subject('020001'), $this->amount($cheque)],
            ], $cheque->write_date->toDateString());
    }

    private function bankAccount(array $data): BankAccount
    {
        if (empty($data['bank_account_id'])) {
            throw ValidationException::withMessages(['bank_account_id' => __('A bank account is required.')]);
        }

        return BankAccount::findOrFail($data['bank_account_id']);
    }

    private function validateRegistrationData(array $data, ChequeType $direction, ?Cheque $except = null): void
    {
        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Cheque amount must be greater than zero.'),
            ]);
        }
        if (! preg_match('/^\d{16}$/', (string) ($data['sayad_number'] ?? ''))) {
            throw ValidationException::withMessages(['sayad_number' => __('validation.regex', ['attribute' => __('16-digit Sayad number')])]);
        }
        $duplicateSayad = Cheque::withoutGlobalScopes()->where('sayad_number', $data['sayad_number'])->when($except, fn ($query) => $query->where('id', '!=', $except->id))->exists();
        if ($duplicateSayad) {
            throw ValidationException::withMessages(['sayad_number' => __('validation.unique', ['attribute' => __('16-digit Sayad number')])]);
        }
        if (! Customer::whereKey($data['account_side_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['account_side_id' => __('validation.exists', ['attribute' => __('Account side')])]);
        }
        $account = ! empty($data['bank_account_id']) ? BankAccount::find($data['bank_account_id']) : null;
        if ($direction === ChequeType::PAYABLE && ! $account) {
            throw ValidationException::withMessages(['bank_account_id' => __('A bank account is required.')]);
        }
    }

    private function validateInvoicePaymentData(Invoice $invoice, ChequeType $direction, ChequeType $purpose, array $data, ?Payment $except = null): void
    {
        $expectedDirection = $this->directionForInvoice($invoice);
        if (! $expectedDirection) {
            throw ValidationException::withMessages([
                'invoice_id' => __('This invoice type cannot be settled by cheque.'),
            ]);
        }
        if ($purpose !== ChequeType::SETTLEMENT) {
            throw ValidationException::withMessages([
                'purpose' => __('A guarantee cheque cannot settle an invoice.'),
            ]);
        }
        if ($direction !== $expectedDirection) {
            throw ValidationException::withMessages([
                'direction' => __('The cheque direction does not match the invoice type.'),
            ]);
        }
        if ((int) ($data['account_side_id'] ?? 0) !== (int) $invoice->customer_id) {
            throw ValidationException::withMessages([
                'account_side_id' => __('The cheque account side must match the invoice account side.'),
            ]);
        }

        $decision = $this->paymentService->validateInvoicePayment($invoice, [
            'amount' => (float) $data['amount'],
            'date' => $data['issue_date'],
        ], $except);
        if ($decision->hasErrors()) {
            throw ValidationException::withMessages([
                'invoice_id' => $decision->messages->pluck('text')->all(),
            ]);
        }
    }

    private function post(User $user, Cheque $cheque, string $event, array $entries, ?string $date = null): Document
    {
        $description = $this->accountingDescription($event, $cheque->serial);
        $transactions = array_map(fn (array $entry) => [
            'subject_id' => $entry[0],
            'value' => $entry[1],
            'desc' => $description,
        ], $entries);

        $document = DocumentService::createDocument($user, [
            'date' => $date ?? now()->toDateString(),
            'title' => $description,
            'number' => null,
            'documentable' => $cheque,
        ], $transactions);
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        return $document;
    }

    private function payment(User $user, Cheque $cheque, Document $document, int $settlementSubjectId, array $data, string $event): Payment
    {
        return Payment::create([
            'invoice_id' => null,
            'cheque_id' => $cheque->id,
            'payer_id' => $cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null,
            'amount' => $cheque->amount,
            'date' => $data['date'] ?? now()->toDateString(),
            'description' => $data['description'] ?? $this->accountingDescription($event, $cheque->serial),
            'reference_number' => $cheque->sayad_number,
            'document_id' => $document->id,
            'settlement_subject_id' => $settlementSubjectId,
            'creator_id' => $user->id,
        ]);
    }

    private function history(Cheque $cheque, User $user, ?ChequeType $from, ChequeType $to, ?Document $document, ?Payment $payment, array $metadata): ChequeHistory
    {
        return ChequeHistory::create([
            'cheque_id' => $cheque->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->id,
            'document_id' => $document?->id,
            'payment_id' => $payment?->id,
            'desc' => $metadata['description'] ?? null,
        ]);
    }

    private function accountingDescription(string $event, string $serial): string
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

        return __($key, ['serial' => $serial]);
    }

    private function subject(string $code): int
    {
        $subject = Subject::where('code', $code)->first();
        if (! $subject) {
            throw ValidationException::withMessages(['accounting' => __('Accounting subject :code is not configured.', ['code' => $code])]);
        }

        return (int) $subject->id;
    }

    private function accountSideSubject(Cheque $cheque): int
    {
        $subjectId = (int) ($cheque->customer?->subject_id ?: $cheque->customer?->subject?->id);
        if (! $subjectId) {
            throw ValidationException::withMessages(['account_side_id' => __('The account side has no accounting subject.')]);
        }

        return $subjectId;
    }

    private function bankSubject(BankAccount $account): int
    {
        $subjectId = (int) ($account->subject_id ?: $account->subject?->id);
        if (! $subjectId) {
            throw ValidationException::withMessages(['bank_account_id' => __('The bank account has no accounting subject.')]);
        }

        return $subjectId;
    }

    private function amount(Cheque $cheque): float
    {
        return (float) $cheque->amount;
    }
}
