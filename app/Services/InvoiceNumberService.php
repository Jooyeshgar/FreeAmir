<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /** Reassign invoice numbers in the active fiscal year by date and reference relationships. Numbering is intentionally explicit and never automatic. */
    public function sort(): array
    {
        return DB::transaction(function (): array {
            app(FiscalYearMutationGuard::class)->ensureOpen();
            $invoices = Invoice::query()->lockForUpdate()->orderBy('date')->orderBy('id')->get();

            $byId = $invoices->keyBy('id');
            $groups = ['sales' => collect(), 'purchases' => collect(), 'beginning_inventory' => collect()];
            foreach ($invoices as $invoice) {
                $type = $invoice->invoice_type;
                $referenceType = $type === InvoiceType::VOID ? $byId->get($invoice->returned_invoice_id)?->invoice_type : $type;

                if (in_array($referenceType, [InvoiceType::SELL, InvoiceType::RETURN_SELL], true)) {
                    $groups['sales']->push($invoice);
                } elseif (in_array($referenceType, [InvoiceType::BUY, InvoiceType::RETURN_BUY], true)) {
                    $groups['purchases']->push($invoice);
                } elseif ($type === InvoiceType::BEGINNING_INVENTORY) {
                    $groups['beginning_inventory']->push($invoice);
                }
            }

            // A void belongs to the same sequence as its referenced invoice. It is assigned by the traversal and must not also be treated as a root.
            $orderedGroups = [
                'sales' => $this->orderedSequence($groups['sales'], [InvoiceType::SELL, InvoiceType::RETURN_SELL]),
                'purchases' => $this->orderedSequence($groups['purchases'], [InvoiceType::BUY, InvoiceType::RETURN_BUY]),
                'beginning_inventory' => $groups['beginning_inventory']->values(),
            ];

            $temporaryNumber = (int) ceil($invoices->max(fn (Invoice $invoice) => abs((float) $invoice->number)) ?? 0) + $invoices->count() + 1;
            // Invoice numbers are unique within a type. Clear the old values first so a reorder can never collide with an existing number.
            foreach ($orderedGroups as $ordered) {
                foreach ($ordered as $index => $invoice) {
                    if ((float) $invoice->number !== (float) ($index + 1)) {
                        Invoice::whereKey($invoice->id)->toBase()->update(['number' => $temporaryNumber++]);
                    }
                }
            }

            foreach ($orderedGroups as $ordered) {
                foreach ($ordered as $index => $invoice) {
                    $invoice->update(['number' => $index + 1]);
                }
            }

            return array_map(static fn (Collection $group): int => $group->count(), $orderedGroups);
        });
    }

    /** Order roots by date, then place each void immediately after its reference and all returns immediately after the root in return-date order. */
    private function orderedSequence(Collection $invoices, array $rootTypes): Collection
    {
        $children = $invoices->groupBy('returned_invoice_id');
        $ordered = collect();
        $visited = [];

        $append = function (Invoice $invoice) use (&$append, &$ordered, &$visited, $children): void {
            if (isset($visited[$invoice->id])) {
                return;
            }

            $visited[$invoice->id] = true;
            $ordered->push($invoice);

            $children->get($invoice->id, collect())->each(function (Invoice $child) use (&$append): void {
                if ($child->invoice_type === InvoiceType::VOID) {
                    $append($child);
                }
            });

            $children->get($invoice->id, collect())->filter(fn (Invoice $child) => $child->invoice_type !== InvoiceType::VOID)
                ->sortBy([['date', 'asc'], ['id', 'asc']])->each($append);
        };

        $invoices->filter(fn (Invoice $invoice) => in_array($invoice->invoice_type, $rootTypes, true))->sortBy([['date', 'asc'], ['id', 'asc']])->each($append);

        // Keep malformed or legacy records in the sequence instead of silently leaving them unnumbered.
        $invoices->sortBy([['date', 'asc'], ['id', 'asc']])->each($append);

        return $ordered->values();
    }
}
