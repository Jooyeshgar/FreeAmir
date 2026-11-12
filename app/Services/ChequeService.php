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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    public function validateInvoiceCheque(array $input, Invoice $invoice, ?Cheque $cheque = null): array
    {
        $direction = $this->directionForInvoice($invoice);
        abort_unless($direction, 422, __('This invoice type cannot be settled by cheque.'));

        $fee = $input['bank_service_fee'] ?? 0;
        if (is_string($fee)) {
            $fee = str_replace([',', '٬', '٫'], ['', '', '.'], trim(toEnglish($fee)));
            $fee = $fee === '' ? 0 : $fee;
        }

        $normalized = [
            'title' => filled($input['title'] ?? null) ? trim((string) $input['title']) : null,
            'amount' => convertToFloat($input['amount'] ?? 0),
            'bank_service_fee' => $fee,
            'sayad_number' => preg_replace('/\D/', '', toEnglish((string) ($input['sayad_number'] ?? null))),
            'serial' => trim(toEnglish((string) ($input['serial'] ?? null))),
            'cheque_number' => trim(toEnglish((string) ($input['cheque_number'] ?? null))),
        ];
        foreach (['issue_date', 'due_date'] as $field) {
            if (filled($input[$field] ?? null)) {
                $normalized[$field] = jalaliInputToGregorian($input[$field], $field);
            }
        }

        $validated = Validator::make(array_replace($input, $normalized), [
            'title' => ['nullable', 'string', 'min:2', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank_service_fee' => ['required', 'numeric', 'min:0', 'lt:10000000000000000'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'serial' => ['nullable', 'string', 'max:50'],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'sayad_number' => [
                'required',
                'regex:/^\d{16}$/',
                Rule::unique('cheques', 'sayad_number')->where('fiscal_year_id', getActiveFiscalYear())->ignore($cheque?->id),
            ],
            'bank_account_id' => [
                Rule::requiredIf($direction === ChequeType::PAYABLE),
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('fiscal_year_id', getActiveFiscalYear()),
            ],
            'chequebook_id' => $direction === ChequeType::PAYABLE
                ? ['nullable', Rule::exists('chequebooks', 'id')->where('fiscal_year_id', getActiveFiscalYear())]
                : ['exclude'],
            'description' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return [
            ...$validated,
            'direction' => $direction->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'customer_id' => $invoice->customer_id,
        ];
    }

    public function register(User $user, array $data): Cheque
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($data['fiscal_year_id'] ?? getActiveFiscalYear());

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
                'fiscal_year_id' => getActiveFiscalYear(),
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

            $fee = $this->validateBankServiceFee($data);
            $document = $this->postInitialDocument($user, $cheque);
            $paymentSubject = $direction === ChequeType::RECEIVABLE ? self::DOCUMENTS_RECEIVABLE_CONFIG : self::DOCUMENTS_PAYABLE_CONFIG;
            $payment = $invoice ? $this->paymentService->saveChequePayment($user, $invoice, $cheque, $document, $this->subject($paymentSubject), fee: $fee) : null;

            $history = $this->history($cheque, $user, null, $status, $document, $payment, $data['description'] ?? null);
            $this->recordBankServiceFee($cheque, $user, $history, $data, $data['issue_date']);

            return $cheque->fresh();
        });
    }

    public function update(Cheque $cheque, User $user, array $data): Cheque
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($cheque);

        return DB::transaction(function () use ($cheque, $user, $data) {
            $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);

            $histories = $lockedCheque->histories()->with(['document.transactions', 'payment.invoice'])->orderBy('id')->get();
            $historySnapshots = $this->snapshotHistories($histories);
            $hasLifecycle = $histories->contains(fn (ChequeHistory $history): bool => $history->from_status !== null);
            $initialPurpose = $this->initialPurpose($histories, $lockedCheque->purpose);

            $direction = ChequeType::from((int) $data['direction']);
            $purpose = ChequeType::from((int) $data['purpose']);
            $this->validateRegistrationData($data, $direction, $lockedCheque);

            if ($hasLifecycle && ($direction !== $lockedCheque->direction || $purpose !== $initialPurpose)) {
                throw ValidationException::withMessages(['cheque' => __('The cheque direction and purpose cannot be changed after a lifecycle action.')]);
            }

            $oldBankAccountId = (int) $lockedCheque->bank_account_id;
            $bankAccountChanged = array_key_exists('bank_account_id', $data) && (int) ($data['bank_account_id'] ?? 0) !== $oldBankAccountId;
            $data['bank_account_id'] = array_key_exists('bank_account_id', $data) ? ($data['bank_account_id'] ?: null) : $lockedCheque->bank_account_id;
            $data['chequebook_id'] = array_key_exists('chequebook_id', $data) ? ($data['chequebook_id'] ?: null) : $lockedCheque->chequebook_id;

            if (! empty($data['chequebook_id'])) {
                $data['cheque_number'] = (int) $data['chequebook_id'] === (int) $lockedCheque->chequebook_id ? $lockedCheque->cheque_number : $this->allocateChequebookLeaf((int) $data['chequebook_id']);
            }

            $initialHistory = $histories->firstWhere('from_status', null);
            $initialPayment = $initialHistory?->payment;
            $invoice = $initialPayment?->invoice;
            $data['bank_service_fee'] ??= (float) $initialHistory?->bank_service_fee ?: ($initialPayment?->bank_service_fee ?? 0);
            $this->validateBankServiceFee($data);
            if ($invoice) {
                $this->validateInvoicePaymentData($invoice, $direction, $purpose, $data, $initialPayment);
            }

            $replay = $this->prepareLifecycleReplay($historySnapshots, $direction, $purpose);
            if (! $hasLifecycle) {
                $documentIds = $histories->pluck('document_id')->merge($histories->pluck('payment')->pluck('document_id'))->filter()->unique()->values();
                foreach ($documentIds as $documentId) {
                    DocumentService::deleteDocument((int) $documentId);
                }
                $historySnapshots = collect($historySnapshots)->map(fn (array $snapshot): array => [
                    ...$snapshot,
                    'document_id' => null,
                ])->all();
            }

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
                'status' => $this->initialStatus($direction, $purpose),
                'customer_id' => $data['customer_id'],
                'endorsed_to_id' => null,
                'bank_account_id' => $data['bank_account_id'],
                'chequebook_id' => $data['chequebook_id'],
                'desc' => $data['description'] ?? null,
            ]);

            $this->rebuildLifecycle($lockedCheque, $user, $historySnapshots, $initialPayment, $invoice, $data, $replay, $bankAccountChanged);

            if ($invoice) {
                $this->paymentService->syncInvoiceStatus($invoice->fresh());
            }

            return $lockedCheque->fresh();
        });
    }

    /**
     * Capture the lifecycle information before its accounting documents are rebuilt.
     * Documents are generated from the edited cheque, while action dates, notes and settlement subjects remain attached to their original history rows.
     */
    private function snapshotHistories(Collection $histories): array
    {
        return $histories->map(fn (ChequeHistory $history): array => [
            'id' => $history->id,
            'action' => $this->actionFromHistory($history),
            'document_id' => $history->document_id,
            'desc' => $history->desc,
            'bank_service_fee' => (float) $history->bank_service_fee,
            'document_date' => $history->document?->date?->toDateString(),
            'payment_id' => $history->payment_id,
            'payment_date' => $history->payment?->date?->toDateString(),
            'payment_description' => $history->payment?->description,
            'payment_payer_id' => $history->payment?->payer_id,
            'payment_settlement_subject_id' => $history->payment?->settlement_subject_id,
            'payment_creator_id' => $history->payment?->creator_id,
        ])->all();
    }

    private function initialPurpose(Collection $histories, ChequeType $fallback): ChequeType
    {
        $initial = $histories->firstWhere('from_status', null);

        return in_array($initial?->to_status, [ChequeType::GUARANTEE_RECEIVED, ChequeType::GUARANTEE_GIVEN], true)
            ? ChequeType::GUARANTEE
            : ($fallback === ChequeType::GUARANTEE ? ChequeType::GUARANTEE : ChequeType::SETTLEMENT);
    }

    public function registrationDetails(Cheque $cheque): array
    {
        $histories = $cheque->histories()->orderBy('id')->get();

        return [
            'direction' => $cheque->direction,
            'purpose' => $this->initialPurpose($histories, $cheque->purpose),
        ];
    }

    private function actionFromHistory(ChequeHistory $history): ?string
    {
        if ($history->from_status === null) {
            return null;
        }

        if ($history->from_status === $history->to_status) {
            return 'fee';
        }

        if (in_array($history->from_status, [ChequeType::GUARANTEE_RECEIVED, ChequeType::GUARANTEE_GIVEN], true)
                && in_array($history->to_status, [ChequeType::REGISTERED, ChequeType::ISSUED], true)) {
            return 'execute';
        }

        return match ($history->to_status) {
            ChequeType::DEPOSITED => 'deposit',
            ChequeType::CLEARED => 'clear',
            ChequeType::ENDORSED => 'endorse',
            ChequeType::BOUNCED => 'bounce',
            ChequeType::RETURNED => 'return',
            ChequeType::CANCELLED => 'cancel',
            default => throw ValidationException::withMessages(['cheque' => __('This cheque history cannot be replayed after editing.')]),
        };
    }

    /** Validate the saved action sequence against the edited cheque type, then compute the statuses that should be written back to each history row.*/
    private function prepareLifecycleReplay(array $snapshots, ChequeType $direction, ChequeType $purpose): array
    {
        $cheque = new Cheque;
        $cheque->direction = $direction;
        $cheque->purpose = $purpose;
        $cheque->status = $this->initialStatus($direction, $purpose);

        $events = [];
        $endorsedToId = null;
        foreach (array_slice($snapshots, 1) as $snapshot) {
            $action = $snapshot['action'];
            if (! $action) {
                throw ValidationException::withMessages(['cheque' => __('This cheque history cannot be replayed after editing.')]);
            }

            $from = $cheque->status;
            $purposeBefore = $cheque->purpose;
            if ($action === 'fee') {
                $to = $from;
            } else {
                if (! in_array($action, $cheque->availableActions(), true)) {
                    throw ValidationException::withMessages(['cheque' => __('The edited cheque details are not compatible with its saved lifecycle actions.')]);
                }

                $to = match ($action) {
                    'deposit' => ChequeType::DEPOSITED,
                    'clear' => ChequeType::CLEARED,
                    'endorse' => ChequeType::ENDORSED,
                    'bounce' => ChequeType::BOUNCED,
                    'return' => ChequeType::RETURNED,
                    'cancel' => ChequeType::CANCELLED,
                    'execute' => $this->initialStatus($direction, ChequeType::SETTLEMENT),
                    default => throw ValidationException::withMessages(['cheque' => __('This cheque history cannot be replayed after editing.')]),
                };
                if ($action === 'execute') {
                    $cheque->purpose = ChequeType::SETTLEMENT;
                }
                $endorsedToId = $action === 'endorse' ? $snapshot['payment_payer_id'] : null;
            }

            $snapshot['action'] = $action;
            $snapshot['replayed_from_status'] = $from;
            $snapshot['replayed_to_status'] = $to;
            $snapshot['purpose_before'] = $purposeBefore;
            $events[] = $snapshot;
            $cheque->status = $to;
        }

        return [
            'histories' => $events,
            'status' => $cheque->status,
            'purpose' => $cheque->purpose,
            'endorsed_to_id' => $endorsedToId,
        ];
    }

    /** Rebuild every accounting document affected by an edited cheque, including documents created by actions that happened before the edit.*/
    private function rebuildLifecycle(Cheque $cheque, User $user, array $snapshots, ?Payment $initialPayment, ?Invoice $invoice, array $data, array $replay, bool $bankAccountChanged): void
    {
        $initialSnapshot = $snapshots[0] ?? [
            'id' => null,
            'from_status' => null,
            'to_status' => $this->initialStatus($cheque->direction, $cheque->purpose)->value,
            'user_id' => $user->id,
            'desc' => $data['description'] ?? null,
            'bank_service_fee' => 0,
            'document_date' => null,
            'payment_id' => null,
        ];
        $initialHistory = $initialSnapshot['id'] ? ChequeHistory::findOrFail($initialSnapshot['id']) : null;
        $initialDocument = ! empty($initialSnapshot['document_id']) ? Document::find($initialSnapshot['document_id']) : null;
        $initialEntries = $cheque->purpose === ChequeType::GUARANTEE
            ? []
            : ($cheque->direction === ChequeType::RECEIVABLE
                ? [
                    [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), -(float) $cheque->amount],
                    [$this->accountSideSubject($cheque), (float) $cheque->amount],
                ]
                : [
                    [$this->accountSideSubject($cheque), -(float) $cheque->amount],
                    [$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), (float) $cheque->amount],
                ]);
        $initialEvent = $cheque->direction === ChequeType::RECEIVABLE ? 'register' : 'issue';
        $initialDocument = $this->rewriteEditedDocument($user, $cheque, $initialDocument, $initialEvent, $initialEntries, $data['issue_date'], (float) $data['bank_service_fee']);
        if ($initialPayment && $initialDocument) {
            $paymentSubject = $cheque->direction === ChequeType::RECEIVABLE ? self::DOCUMENTS_RECEIVABLE_CONFIG : self::DOCUMENTS_PAYABLE_CONFIG;
            $initialPayment = $this->paymentService->saveChequePayment($user, $invoice, $cheque, $initialDocument, $this->subject($paymentSubject), $initialPayment, (float) $data['bank_service_fee']);
        } elseif ($initialPayment) {
            $initialPayment->delete();
            $initialPayment = null;
        }

        if ($initialHistory) {
            $initialHistory->update([
                'to_status' => $this->initialStatus($cheque->direction, $cheque->purpose),
                'document_id' => $initialDocument?->id,
                'payment_id' => $initialPayment?->id,
                'desc' => $data['description'] ?? null,
            ]);
        } else {
            $initialHistory = $this->history($cheque, $user, null, $this->initialStatus($cheque->direction, $cheque->purpose), $initialDocument, $initialPayment, $data['description'] ?? null);
        }
        $initialHistory->update(['bank_service_fee' => (float) $data['bank_service_fee']]);

        foreach ($replay['histories'] as $snapshot) {
            $history = ChequeHistory::findOrFail($snapshot['id']);
            [$document, $payment] = $this->rebuildTransition($cheque, $user, $snapshot, $bankAccountChanged);

            $history->update([
                'from_status' => $snapshot['replayed_from_status'],
                'to_status' => $snapshot['replayed_to_status'],
                'document_id' => $document?->id,
                'payment_id' => $payment?->id,
                'bank_service_fee' => (float) $snapshot['bank_service_fee'],
            ]);
        }

        $cheque->update([
            'status' => $replay['status'],
            'purpose' => $replay['purpose'],
            'endorsed_to_id' => $replay['endorsed_to_id'],
        ]);
    }

    private function rebuildTransition(Cheque $cheque, User $user, array $snapshot, bool $bankAccountChanged): array
    {
        $date = $snapshot['document_date'] ?? now()->toDateString();
        $payment = $snapshot['payment_id'] ? Payment::find($snapshot['payment_id']) : null;
        $action = $snapshot['action'];
        $amount = (float) $cheque->amount;
        $entries = [];
        $event = $action;

        if ($action === 'execute') {
            $entries = $cheque->direction === ChequeType::RECEIVABLE
                ? [[$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), -$amount], [$this->accountSideSubject($cheque), $amount]]
                : [[$this->accountSideSubject($cheque), -$amount], [$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), $amount]];
        } else {
            $entries = match ($action) {
                'deposit' => [
                    [$this->subject(self::DOCUMENTS_IN_COLLECTION_CONFIG), -$amount],
                    [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
                ],
                'clear' => $cheque->direction === ChequeType::RECEIVABLE
                    ? [[$this->clearSettlementSubject($cheque, $snapshot, $bankAccountChanged), -$amount], [$this->subject(self::DOCUMENTS_IN_COLLECTION_CONFIG), $amount]]
                    : [[$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), -$amount], [$this->clearSettlementSubject($cheque, $snapshot, $bankAccountChanged), $amount]],
                'endorse' => $this->endorseEntries($cheque, $snapshot, $amount),
                'bounce' => $cheque->direction === ChequeType::RECEIVABLE
                    ? [[$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), -$amount], [$this->subject(self::DOCUMENTS_IN_COLLECTION_CONFIG), $amount]]
                    : [[$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), -$amount], [$this->accountSideSubject($cheque), $amount]],
                'return' => [
                    [$this->accountSideSubject($cheque), -$amount],
                    [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
                ],
                'cancel' => ($snapshot['purpose_before'] === ChequeType::SETTLEMENT && $snapshot['replayed_from_status'] === ChequeType::ISSUED)
                    ? [
                        [$this->subject(self::DOCUMENTS_PAYABLE_CONFIG), -$amount],
                        [$this->accountSideSubject($cheque), $amount],
                    ]
                    : [],
                default => [],
            };
        }

        $document = ! empty($snapshot['document_id']) ? Document::find($snapshot['document_id']) : null;
        $document = $this->rewriteEditedDocument($user, $cheque, $document, $event, $entries, $date, (float) $snapshot['bank_service_fee']);

        if (! $document && $payment) {
            $payment->delete();

            return [null, null];
        }

        if ($document && $payment) {
            $subjectId = $action === 'clear' ? $this->clearSettlementSubject($cheque, $snapshot, $bankAccountChanged) : (int) $snapshot['payment_settlement_subject_id'];
            $payment->update([
                'amount' => $cheque->amount,
                'date' => $snapshot['payment_date'] ?? $date,
                'description' => $snapshot['payment_description'] ?? $snapshot['desc'] ?? $cheque->desc,
                'reference_number' => $cheque->sayad_number,
                'payer_id' => $action === 'clear'
                    ? ($cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null)
                    : $snapshot['payment_payer_id'],
                'document_id' => $document->id,
                'settlement_subject_id' => $subjectId ?: null,
                'creator_id' => $snapshot['payment_creator_id'] ?: $user->id,
            ]);
            DocumentService::syncDocumentable($document, $payment);
            $payment = $payment->fresh();
        }

        return [$document, $payment];
    }

    private function endorseEntries(Cheque $cheque, array $snapshot, float $amount): array
    {
        $subjectId = (int) $snapshot['payment_settlement_subject_id'];
        if (! $subjectId || ! Subject::where('fiscal_year_id', $cheque->fiscal_year_id)->whereKey($subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => __('The endorsed settlement account is invalid.')]);
        }

        return [
            [$subjectId, -$amount],
            [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
        ];
    }

    private function rewriteEditedDocument(User $user, Cheque $cheque, ?Document $document, string $event, array $entries, string $date, float $fee): ?Document
    {
        $description = $cheque->desc ?: ($event === 'fee' ? __('Bank service fee') : $this->accountingDescription($event, $cheque));
        $transactions = array_map(fn (array $entry): array => [
            'subject_id' => $entry[0],
            'desc' => $description,
            'value' => $entry[1],
        ], $entries);
        if ($fee > 0) {
            $transactions = array_merge($transactions, $this->paymentService->bankServiceFeeTransactions($fee, $this->accountSideSubject($cheque)));
        }

        $title = $entries === []
            ? __('Bank service fee').' - '.$cheque->title
            : ($cheque->title ?: $this->accountingDescription($event, $cheque));

        if ($transactions === []) {
            if ($document) {
                DocumentService::deleteDocument($document->id);
            }

            return null;
        }

        if ($document) {
            if ($document->approved_at) {
                DocumentService::changeDocumentStatus($document, $user, 'unapproved');
            }
            DocumentService::updateDocument($document, [
                'date' => $date,
                'title' => $title,
                'documentable' => $document->documentable,
            ]);
            $transactionIds = $document->transactions()->orderBy('id')->pluck('id')->all();
            $transactions = array_map(function (array $transaction, int $index) use ($transactionIds): array {
                if (isset($transactionIds[$index])) {
                    $transaction['transaction_id'] = $transactionIds[$index];
                }

                return $transaction;
            }, $transactions, array_keys($transactions));
            DocumentService::updateDocumentTransactions($document->id, $transactions);
            DocumentService::changeDocumentStatus($document, $user, 'approved');

            return $document->fresh();
        }

        $document = DocumentService::createDocument($user, [
            'date' => $date,
            'title' => $title,
            'number' => null,
            'documentable' => $cheque,
        ], $transactions);
        DocumentService::changeDocumentStatus($document, $user, 'approved');

        return $document;
    }

    private function clearSettlementSubject(Cheque $cheque, array $snapshot, bool $bankAccountChanged): int
    {
        $storedSubjectId = (int) ($snapshot['payment_settlement_subject_id'] ?? 0);
        if ($storedSubjectId && ! $bankAccountChanged && Subject::where('fiscal_year_id', $cheque->fiscal_year_id)->whereKey($storedSubjectId)->exists()) {
            return $storedSubjectId;
        }

        return $this->bankAccountSubject($cheque);
    }

    private function bankAccountSubject(Cheque $cheque): int
    {
        $subjectId = (int) ($cheque->bankAccount?->subject_id ?: $cheque->bankAccount?->subject?->id);
        if (! $subjectId) {
            throw ValidationException::withMessages(['bank_account_id' => __('The bank account has no accounting subject.')]);
        }

        return $subjectId;
    }

    public function delete(Cheque $cheque): void
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($cheque);

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
        app(FiscalYearMutationGuard::class)->ensureOpen($cheque);

        return DB::transaction(function () use ($cheque, $user, $action, $data) {
            $lockedCheque = Cheque::query()->lockForUpdate()->findOrFail($cheque->id);

            if ($action === 'fee') {
                if ($this->validateBankServiceFee($data) <= 0) {
                    throw ValidationException::withMessages(['bank_service_fee' => __('Enter a bank service fee greater than zero.')]);
                }
                $history = $this->history($lockedCheque, $user, $lockedCheque->status, $lockedCheque->status, null, null, $data['description'] ?? null);
                $this->recordBankServiceFee($lockedCheque, $user, $history, $data, $data['date'] ?? now()->toDateString());

                return $lockedCheque->fresh();
            }

            if (! in_array($action, $lockedCheque->availableActions(), true)) {
                throw ValidationException::withMessages(['status' => __('This action is not allowed in the current status.')]);
            }

            $this->validateBankServiceFee($data);
            $result = match ($action) {
                'deposit' => $this->deposit($lockedCheque, $user, $data),
                'clear' => $this->clear($lockedCheque, $user, $data),
                'endorse' => $this->endorse($lockedCheque, $user, $data),
                'bounce' => $this->bounce($lockedCheque, $user, $data),
                'return' => $this->returnToCustomer($lockedCheque, $user, $data),
                'cancel' => $this->cancel($lockedCheque, $user, $data),
                'execute' => $this->executeGuarantee($lockedCheque, $user, $data),
                default => throw ValidationException::withMessages(['action' => __('This action is not allowed in the current status.')]),
            };
            $history = $result->histories()->latest('id')->firstOrFail();
            $this->recordBankServiceFee($result, $user, $history, $data, $data['date'] ?? now()->toDateString());

            return $result->fresh();
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
        if (! empty($data['subject_id'])) {
            $subject = Subject::where('fiscal_year_id', $cheque->fiscal_year_id)->find($data['subject_id']);
            if (! $subject) {
                throw ValidationException::withMessages(['subject_id' => __('The selected Subject does not exist.')]);
            }
            $vendorSubject = (int) $subject->id;
            $vendor = Customer::where('fiscal_year_id', $cheque->fiscal_year_id)->where('subject_id', $vendorSubject)->first();
        } elseif (! empty($data['customer_id'])) {
            $vendor = Customer::findOrFail($data['customer_id']);
            $vendorSubject = $this->customerSubject($vendor, $cheque);
        } else {
            throw ValidationException::withMessages(['subject_id' => __('Select a subject')]);
        }

        $from = $cheque->status;
        $amount = (float) $cheque->amount;
        $document = $this->post($user, $cheque, 'endorse', [
            [$vendorSubject, -$amount],
            [$this->subject(self::DOCUMENTS_RECEIVABLE_CONFIG), $amount],
        ], $data['date'] ?? null);
        $payment = $this->payment($user, $cheque, $document, $vendorSubject, $data, 'endorse', $vendor?->id);
        DocumentService::syncDocumentable($document, $payment);

        $cheque->update(['status' => ChequeType::ENDORSED, 'endorsed_to_id' => $vendor?->id]);
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
        $fiscalYearId = $except?->fiscal_year_id ?? getActiveFiscalYear();
        $duplicateSayad = Cheque::withoutGlobalScopes()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('sayad_number', $data['sayad_number'])
            ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
            ->exists();
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
            'bank_service_fee' => $data['bank_service_fee'] ?? 0,
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
            'payer_id' => $event === 'endorse' ? $payerId : ($payerId ?? ($cheque->direction === ChequeType::RECEIVABLE ? $cheque->customer_id : null)),
            'amount' => $cheque->amount,
            'date' => $data['date'] ?? now()->toDateString(),
            'description' => $data['description'] ?? $this->accountingDescription($event, $cheque),
            'reference_number' => $cheque->sayad_number,
            'document_id' => $document->id,
            'settlement_subject_id' => $settlementSubjectId,
            'creator_id' => $user->id,
        ]);
    }

    public function validateBankServiceFee(array $data): float
    {
        $fee = $data['bank_service_fee'] ?? 0;

        if (is_string($fee)) {
            $fee = str_replace([',', '٬', '٫'], ['', '', '.'], trim(toEnglish($fee)));
            $fee = $fee === '' ? 0 : $fee;
        }

        if (! is_numeric($fee) || ! is_finite((float) $fee) || (float) $fee < 0 || (float) $fee >= 1e16) {
            throw ValidationException::withMessages(['bank_service_fee' => __('Bank service fee must be zero or greater.')]);
        }

        return round((float) $fee, 2);
    }

    private function recordBankServiceFee(Cheque $cheque, User $user, ChequeHistory $history, array $data, string $date): void
    {
        $fee = $this->validateBankServiceFee($data);
        if ($fee > 0) {
            $customerSubjectId = $this->accountSideSubject($cheque);
            $transactions = $this->paymentService->bankServiceFeeTransactions($fee, $customerSubjectId);
            $document = $history->document;
            if ($document) {
                DocumentService::changeDocumentStatus($document, $user, 'unapproved');
                foreach ($transactions as $transaction) {
                    DocumentService::createTransaction($document, $transaction);
                }
            } else {
                $document = DocumentService::createDocument($user, [
                    'date' => $date,
                    'title' => __('Bank service fee').' - '.$cheque->title,
                    'number' => null,
                    'documentable' => $cheque,
                ], $transactions);
                $history->document()->associate($document);
            }
            DocumentService::changeDocumentStatus($document, $user, 'approved');
        }
        $history->bank_service_fee = $fee;
        $history->save();
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
        $subject = $subjectId ? Subject::where('fiscal_year_id', getActiveFiscalYear())->find($subjectId) : null;
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
        if ((int) $customer->fiscal_year_id != (int) $cheque->fiscal_year_id) {
            throw ValidationException::withMessages(['customer_id' => __('The selected account side is invalid.')]);
        }

        $subjectId = (int) ($customer->subject_id ?: $customer->subject?->id);
        $subjectBelongsToCompany = $subjectId && Subject::where('fiscal_year_id', $cheque->fiscal_year_id)->whereKey($subjectId)->exists();
        if (! $subjectBelongsToCompany) {
            throw ValidationException::withMessages(['customer_id' => __('The account side has no accounting subject.')]);
        }

        return $subjectId;
    }
}
