<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Scopes\FiscalYearScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * One numbering sequence per fiscal year for every invoice type.
 * An earlier-dated invoice must have a lower number; equal dates may use any distinct numbers; gaps are allowed.
 * Numbers are never rewritten automatically because they feed the Moadian tax identifier.
 */
class InvoiceNumberService
{
    public const REASON_DUPLICATE = 'duplicate';

    public const REASON_OUT_OF_ORDER = 'out_of_order';

    public const MAX_LISTED = 3;

    /**
     * Find the invoices in the active fiscal year that would conflict with the given number/date.
     *
     * @return Collection<int, array{invoice: Invoice, reason: string}>
     */
    public function conflictsFor(int|float|string $number, CarbonInterface|string $date, ?int $ignoreInvoiceId = null, ?int $fiscalYearId = null): Collection
    {
        $date = Carbon::parse($date)->toDateString();
        $number = (float) $number;

        $query = Invoice::query()
            ->when($fiscalYearId, fn ($q) => $q->withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $fiscalYearId))
            ->when($ignoreInvoiceId, fn ($q) => $q->whereKeyNot($ignoreInvoiceId))
            ->where(function ($q) use ($number, $date) {
                $q->where('number', $number)
                    ->orWhere(fn ($q) => $q->where('number', '>', $number)->whereDate('date', '<', $date))
                    ->orWhere(fn ($q) => $q->where('number', '<', $number)->whereDate('date', '>', $date));
            })
            ->orderBy('date')->orderBy('number');

        return $query->get()->map(fn (Invoice $invoice) => [
            'invoice' => $invoice,
            'reason' => (float) $invoice->number === $number ? self::REASON_DUPLICATE : self::REASON_OUT_OF_ORDER,
        ]);
    }

    /**
     * Existing conflicts in the active fiscal year. Both sides of a conflict are flagged and each invoice appears once;
     * a duplicate number takes precedence over an out-of-order reason.
     *
     * @return Collection<int, array{invoice: Invoice, reason: string}>
     */
    public function existingConflicts(): Collection
    {
        $invoices = Invoice::query()->select(['id', 'number', 'date', 'invoice_type'])->orderBy('number')->orderBy('date')->orderBy('id')->get();
        $reasons = [];

        foreach ($invoices->groupBy(fn (Invoice $i) => (string) (float) $i->number) as $group) {
            if ($group->count() > 1) {
                foreach ($group as $invoice) {
                    $reasons[$invoice->id] = self::REASON_DUPLICATE;
                }
            }
        }

        // Sorted by number, an invoice is out of order when an earlier number has a later date (prefix max)
        // or a later number has an earlier date (suffix min). Equal numbers are handled as duplicates above.
        $sorted = $invoices->sortBy(fn (Invoice $i) => (float) $i->number)->values();
        $count = $sorted->count();
        $dates = $sorted->map(fn (Invoice $i) => $i->date->toDateString())->all();
        $numbers = $sorted->map(fn (Invoice $i) => (float) $i->number)->all();

        $suffixMin = array_fill(0, $count + 1, null);
        for ($i = $count - 1; $i >= 0; $i--) {
            $suffixMin[$i] = $suffixMin[$i + 1] === null || $dates[$i] < $suffixMin[$i + 1] ? $dates[$i] : $suffixMin[$i + 1];
        }

        $prefixMax = null;
        $start = 0;
        while ($start < $count) {
            $end = $start;
            while ($end + 1 < $count && $numbers[$end + 1] === $numbers[$start]) {
                $end++;
            }
            // Compare each number group only with strictly lower (prefix) and strictly higher (suffix) numbers.
            for ($k = $start; $k <= $end; $k++) {
                $later = $suffixMin[$end + 1];
                if (($prefixMax !== null && $prefixMax > $dates[$k]) || ($later !== null && $later < $dates[$k])) {
                    $reasons[$sorted[$k]->id] ??= self::REASON_OUT_OF_ORDER;
                }
            }
            for ($k = $start; $k <= $end; $k++) {
                $prefixMax = $prefixMax === null || $dates[$k] > $prefixMax ? $dates[$k] : $prefixMax;
            }
            $start = $end + 1;
        }

        return $invoices->filter(fn (Invoice $i) => isset($reasons[$i->id]))
            ->map(fn (Invoice $i) => ['invoice' => $i, 'reason' => $reasons[$i->id]])->values();
    }

    /**
     * Serialize numbering checks within the fiscal year and re-validate inside the caller's transaction,
     * so concurrent saves cannot both pass the request-level check. Must run inside DB::transaction().
     *
     * @throws ValidationException
     */
    public function lockAndAssertNoConflicts(int|float|string|null $number, CarbonInterface|string|null $date, ?int $ignoreInvoiceId = null, ?int $fiscalYearId = null): void
    {
        if ($number === null || $date === null || ! is_numeric($number)) {
            return;
        }

        $fiscalYearId ??= (int) getActiveFiscalYear();
        InvoiceService::lockExternalReferenceScope($fiscalYearId);

        // Check the same fiscal year that was locked, regardless of the active fiscal year.
        $conflicts = $this->conflictsFor($number, $date, $ignoreInvoiceId, $fiscalYearId);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['invoice_number' => $this->conflictMessage($conflicts)]);
        }
    }

    /** True when the given number and date equal the invoice's stored ones, so a save does not touch its numbering. */
    public function isUnchanged(Invoice $invoice, int|float|string|null $number, CarbonInterface|string|null $date): bool
    {
        return $number !== null && $date !== null && is_numeric($number)
            && (float) $number === (float) $invoice->number
            && Carbon::parse($date)->toDateString() === $invoice->date?->toDateString();
    }

    /**
     * Update-path variant: skips the check when the invoice keeps its number and date, so an existing conflict
     * does not block unrelated edits. Must run inside DB::transaction().
     *
     * @throws ValidationException
     */
    public function lockAndAssertNoConflictsForUpdate(Invoice $invoice, int|float|string|null $number, CarbonInterface|string|null $date): void
    {
        $number ??= $invoice->number;
        $date ??= $invoice->date;

        if ($this->isUnchanged($invoice, $number, $date)) {
            return;
        }

        $this->lockAndAssertNoConflicts($number, $date, $invoice->id, (int) $invoice->fiscal_year_id);
    }

    /**
     * Lowest free number that keeps the fiscal year chronological for an invoice with the given date:
     * above every number dated on or before it, below every number dated after it.
     * Must run after the fiscal-year lock. Returns null when no integer fits.
     */
    public function nextNumberFor(CarbonInterface|string $date, int $fiscalYearId): ?int
    {
        $date = Carbon::parse($date)->toDateString();
        $base = fn () => Invoice::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $fiscalYearId);

        $candidate = ((int) floor((float) ($base()->whereDate('date', '<=', $date)->max('number') ?? 0))) + 1;
        $nextLater = $base()->whereDate('date', '>', $date)->min('number');

        return $nextLater !== null && (float) $nextLater <= $candidate ? null : $candidate;
    }

    /** Localized validation message naming up to MAX_LISTED conflicting invoices. */
    public function conflictMessage(Collection $conflicts): ?string
    {
        if ($conflicts->isEmpty()) {
            return null;
        }

        return $conflicts->take(self::MAX_LISTED)->map(function (array $conflict): string {
            /** @var Invoice $invoice */
            $invoice = $conflict['invoice'];
            $key = $conflict['reason'] === self::REASON_DUPLICATE
                ? 'Invoice number :number is already used by an invoice dated :date.'
                : 'Invoice :number dated :date conflicts with this number and date. A later date must have a higher number.';

            return __($key, ['number' => formatDocumentNumber($invoice->number), 'date' => formatDate($invoice->date)]);
        })->implode(' ');
    }
}
