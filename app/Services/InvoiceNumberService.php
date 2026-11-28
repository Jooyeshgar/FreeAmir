<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Invoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One numbering sequence per fiscal year for every invoice type.
 * An earlier-dated invoice must have a lower number; equal dates may use any distinct numbers; gaps are allowed.
 * Numbers are never rewritten automatically because they feed the Moadian tax identifier.
 */
class InvoiceNumberService
{
    public const REASON_DUPLICATE = 'duplicate';

    public const REASON_OUT_OF_ORDER = 'out_of_order';

    /**
     * Explicitly renumber the active fiscal year into one gapless chronological sequence (1..n) across all types,
     * ordered by date, then current number, then id. Never runs automatically.
     *
     * @return int number of invoices in the sequence
     */
    public function sort(): int
    {
        return DB::transaction(function (): int {
            $fiscalYear = FiscalYear::query()->whereKey(getActiveFiscalYear())->lockForUpdate()->firstOrFail();
            app(FiscalYearMutationGuard::class)->ensureOpen($fiscalYear);

            $invoices = Invoice::query()->lockForUpdate()->orderBy('date')->orderBy('number')->orderBy('id')->get();
            $temporary = (int) ceil($invoices->max(fn (Invoice $i) => abs((float) $i->number)) ?? 0) + $invoices->count() + 1;

            // Numbers are unique per type, so move changed invoices out of the way first to avoid collisions mid-reorder.
            foreach ($invoices as $index => $invoice) {
                if ((float) $invoice->number !== (float) ($index + 1)) {
                    Invoice::whereKey($invoice->id)->toBase()->update(['number' => $temporary++]);
                }
            }
            foreach ($invoices as $index => $invoice) {
                if ((float) $invoice->number !== (float) ($index + 1)) {
                    Invoice::whereKey($invoice->id)->toBase()->update(['number' => $index + 1]);
                }
            }

            return $invoices->count();
        });
    }

    /**
     * Find the invoices in the active fiscal year that would conflict with the given number/date.
     *
     * @return Collection<int, array{invoice: Invoice, reason: string}>
     */
    public function conflictsFor(int|float|string $number, CarbonInterface|string $date, ?int $ignoreInvoiceId = null): Collection
    {
        $date = Carbon::parse($date)->toDateString();
        $number = (float) $number;

        $query = Invoice::query()
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
        $invoices = Invoice::query()->orderBy('number')->orderBy('date')->orderBy('id')->get();
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

    /** Localized validation message naming the first conflicting invoice. */
    public function conflictMessage(Collection $conflicts): ?string
    {
        $first = $conflicts->first();
        if ($first === null) {
            return null;
        }

        /** @var Invoice $invoice */
        $invoice = $first['invoice'];
        $key = $first['reason'] === self::REASON_DUPLICATE
            ? 'Invoice number :number is already used by an invoice dated :date.'
            : 'Invoice :number dated :date conflicts with this number and date. A later date must have a higher number.';

        return __($key, ['number' => formatDocumentNumber($invoice->number), 'date' => formatDate($invoice->date)]);
    }
}
