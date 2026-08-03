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

            $status = $this->initialStatus($direction, $purpose);

            $cheque = Cheque::create([
                'company_id' => getActiveCompany(),
                'amount' => $data['amount'],
                'write_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'serial' => ($data['serial'] ?? null) ?: null,
                'cheque_number' => ($data['cheque_number'] ?? null) ?: null,
                'sayad_number' => $data['sayad_number'],
                'direction' => $direction,
                'purpose' => $purpose,
                'status' => $status,
                'customer_id' => $data['account_side_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'chequebook_id' => $data['chequebook_id'] ?? null,
                'desc' => $data['description'] ?? null,
            ]);

            $document = $this->postInitialDocument($user, $cheque);
            $payment = $invoice ? $this->paymentService->saveChequePayment($user, $invoice, $cheque, $document, $this->subject($direction === ChequeType::RECEIVABLE ? '013001' : '020001')) : null;

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
                'amount' => $data['amount'],
                'write_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'serial' => ($data['serial'] ?? null) ?: null,
                'cheque_number' => ($data['cheque_number'] ?? null) ?: null,
                'sayad_number' => $data['sayad_number'],
                'direction' => $direction,
                'purpose' => $purpose,
                'status' => $status,
                'customer_id' => $data['account_side_id'],
                'endorsed_to_id' => null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'chequebook_id' => $data['chequebook_id'] ?? null,
                'desc' => $data['description'] ?? null,
            ]);

            $document = $this->postInitialDocument($user, $lockedCheque);
            if ($initialPayment && $document) {
                $initialPayment = $this->paymentService->saveChequePayment($user, $invoice, $lockedCheque, $document, $this->subject($direction === ChequeType::RECEIVABLE ? '013001' : '020001'), $initialPayment);
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

            $lockedCheque->payments()->delete();
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

        $amount = (float) $cheque->amount;
        $document = $this->post($user, $cheque, 'deposit', [
            [$this->subject('014001'), -$amount],
            [$this->subject('013001'), $amount],
        ], $data['date'] ?? null);

        $cheque->update(['status' => ChequeType::DEPOSITED, 'bank_account_id' => $account->id]);
        $this->history($cheque, $user, $cheque->status, ChequeType::DEPOSITED, $document, null, $data['description'] ?? null);

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

        $amount = (float) $cheque->amount;
        $entries = $cheque->direction === ChequeType::RECEIVABLE
            ? [[$bankSubject, -$amount], [$this->subject('014001'), $amount]]
            : [[$this->subject('020001'), -$amount], [$bankSubject, $amount]];

        $document = $this->post($user, $cheque, 'clear', $entries, $data['date'] ?? null);
        $payment = $this->payment($user, $cheque, $document, $bankSubject, $data, 'clear');
        DocumentService::syncDocumentable($document, $payment);

        $cheque->update(['status' => ChequeType::CLEARED]);
        $this->history($cheque, $user, $cheque->status, ChequeType::CLEARED, $document, $payment, $data['description'] ?? null);

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
        $amount = (float) $cheque->amount;
        $document = $this->post($user, $cheque, 'endorse', [
            [$vendorSubject, -$amount],
            [$this->subject('013001'), $amount],
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
            ? [[$this->subject('013001'), -$amount], [$this->subject('014001'), $amount]]
            : [[$this->subject('020001'), -$amount], [$this->accountSideSubject($cheque), $amount]];
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
            [$this->subject('013001'), $amount],
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
                [$this->subject('020001'), -$amount],
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
            ? [[$this->subject('013001'), -$amount], [$this->accountSideSubject($cheque), $amount]]
            : [[$this->accountSideSubject($cheque), -$amount], [$this->subject('020001'), $amount]];
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

        return $cheque->direction === ChequeType::RECEIVABLE
            ? $this->post($user, $cheque, 'register', [
                [$this->subject('013001'), -$amount],
                [$this->accountSideSubject($cheque), $amount],
            ], $cheque->write_date->toDateString())
            : $this->post($user, $cheque, 'issue', [
                [$this->accountSideSubject($cheque), -$amount],
                [$this->subject('020001'), $amount],
            ], $cheque->write_date->toDateString());
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
        if (! Customer::whereKey($data['account_side_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['account_side_id' => __('validation.exists', ['attribute' => __('Account side')])]);
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
        if ((int) ($data['account_side_id'] ?? 0) !== (int) $invoice->customer_id) {
            throw ValidationException::withMessages(['account_side_id' => __('The cheque account side must match the invoice account side.')]);
        }

        $decision = $this->paymentService->validateInvoicePayment($invoice, [
            'amount' => (float) $data['amount'],
            'date' => $data['issue_date'],
        ], $except);
        if ($decision->hasErrors()) {
            throw ValidationException::withMessages(['invoice_id' => $decision->messages->pluck('text')->all()]);
        }
    }

    private function post(User $user, Cheque $cheque, string $event, array $entries, ?string $date = null): Document
    {
        $description = $this->accountingDescription($event, $cheque);
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
            'cheque_id' => $cheque->id,
            'payer_id' => $cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null,
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
}
