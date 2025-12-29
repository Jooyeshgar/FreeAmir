<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupActionService
{
    public function approveInactiveInvoices(InvoiceService $invoiceService, AncillaryCostService $ancillaryCostService): void
    {
        $invoices = Invoice::where('status', InvoiceStatus::APPROVED_INACTIVE)->orderBy('date')->orderBy('number')
            ->orWhereHas('ancillaryCosts', function ($query) {
                $query->where('status', InvoiceStatus::APPROVED_INACTIVE);
            })->get();

        DB::transaction(function () use ($invoices, $invoiceService, $ancillaryCostService) {
            foreach ($invoices as $invoice) {
                $decision = $invoiceService->getChangeStatusValidation($invoice);

                if ($decision->hasErrors()) {
                    $conflicts = collect($decision->conflictsItems)
                        ->map(fn ($conflict) => $this->formatConflictForMessage($conflict))
                        ->implode(', ');

                    throw ValidationException::withMessages([
                        'invoice' => [__('Invoice #').$invoice->number.' '.__('cannot be approved because it has conflicts with').': '.$conflicts],
                    ]);
                }

                $invoiceService->changeInvoiceStatus($invoice, 'approved');

                foreach ($invoice->ancillaryCosts as $ancillaryCost) {
                    $validation = $ancillaryCostService->getChangeStatusValidation($ancillaryCost);

                    if (! $validation['allowed']) {
                        $reason = $validation['reason'] ?? __('unknown reason');
                        throw ValidationException::withMessages([
                            'ancillary_cost' => [__('Invoice #').$invoice->number.': '.__('Ancillary Cost').' #'.$ancillaryCost->id.' '.__('cannot be approved due to').': '.$reason],
                        ]);
                    }

                    $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'approve');
                }
            }
        });
    }

    public function groupAction(Invoice $invoice, InvoiceService $invoiceService, AncillaryCostService $ancillaryCostService): void
    {
        [$invoicesConflicts, $ancillaryConflicts, $productsConflicts] = $this->findAllConflictsRecursively($invoice);

        $oversellConflicts = collect($productsConflicts)->every(fn ($product) => $product->oversell === 1);

        if ($invoice->invoice_type === \App\Enums\InvoiceType::SELL && ! $oversellConflicts) {
            return;
        }

        $conflictsToResolve = array_merge($invoicesConflicts, $ancillaryConflicts);
        $sortedConflictsToResolve = collect($conflictsToResolve)->sortByDesc(function ($conflict) {
            $date = $conflict->date instanceof \Carbon\Carbon ? $conflict->date->format('Y-m-d') : $conflict->date;
            // Process AncillaryCost before Invoice if dates are equal (for unapproving)
            $priority = $conflict instanceof AncillaryCost ? '2' : '1';

            return $date.$priority;
        })->values()->all();

        foreach ($sortedConflictsToResolve as $conflict) {
            if ($conflict instanceof Invoice) {
                $decision = $invoiceService->getChangeStatusValidation($conflict);

                if (! $decision->hasErrors()) {
                    $invoiceService->changeInvoiceStatus($conflict, 'unapproved');
                    $conflict->status = \App\Enums\InvoiceStatus::APPROVED_INACTIVE;
                    $conflict->save();
                    dump('Invoice ID '.$conflict->id.' unapproved');
                } else {
                    dd($decision);
                }
            } elseif ($conflict instanceof AncillaryCost) {
                $validation = $ancillaryCostService->getChangeStatusValidation($conflict);

                if ($validation['allowed']) {
                    $ancillaryCostService->changeAncillaryCostStatus($conflict, 'unapprove');
                    $conflict->status = \App\Enums\InvoiceStatus::APPROVED_INACTIVE;
                    $conflict->save();
                }
            }
        }
    }

    /**
     * Recursively find all conflicts for invoices and ancillary costs
     */
    public function findAllConflictsRecursively(Invoice $invoice, bool $paginate = false): array
    {
        $allConflicts = [];

        $this->findConflictsRecursively($invoice, $allConflicts);

        $grouped = $this->groupConflictsByType($allConflicts);

        $grouped[Invoice::class] = array_filter($grouped[Invoice::class], fn ($inv) => $inv->id !== $invoice->id);

        if ($paginate) {
            return $this->paginateGroupedConflicts($grouped);
        }

        return [$grouped[Invoice::class], $grouped[AncillaryCost::class], $grouped[Product::class]];
    }

    /**
     * Recursively find conflicts for any model (Invoice, AncillaryCost, or Product)
     */
    private function findConflictsRecursively(Model|\Illuminate\Support\Collection $model, array &$allConflicts, array &$processedIds = []): void
    {
        $key = get_class($model).':'.$model->id;

        if (in_array($key, $processedIds)) {
            return;
        }

        $processedIds[] = $key;
        $allConflicts[] = $model;

        if ($model instanceof Invoice) {
            if (! isset($model->status)) {
                $model = Invoice::findOrFail($model->id);
            }
            $decision = InvoiceService::getChangeStatusValidation($model);

            foreach ($decision->conflictsItems as $conflict) {
                $formatted = $this->formatConflict($conflict);
                if ($formatted) {
                    $this->findConflictsRecursively($formatted, $allConflicts, $processedIds);
                }
            }

            foreach ($model->ancillaryCosts as $ancillaryCost) {
                if (! $ancillaryCost->status->isApproved()) {
                    continue;
                }

                $validation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);

                if (! $validation['allowed']) {
                    $this->findConflictsRecursively($ancillaryCost, $allConflicts, $processedIds);
                }
            }

        } elseif ($model instanceof AncillaryCost) {
            $validation = AncillaryCostService::getChangeStatusValidation($model);

            if (! $validation['allowed'] && $model->invoice) {
                $this->findConflictsRecursively($model->invoice, $allConflicts, $processedIds);
            }
        }
    }

    /**
     * Group conflicts by their model class
     */
    private function groupConflictsByType(array $conflicts): array
    {
        return collect($conflicts)->groupBy(fn ($conflict) => get_class($conflict))
            ->map(fn ($items) => $items->values()->all())->toArray() + [
                Invoice::class => [],
                AncillaryCost::class => [],
                Product::class => [],
            ];
    }

    /**
     * Paginate grouped conflicts
     */
    private function paginateGroupedConflicts(array $grouped): array
    {
        return [
            $this->paginateConflictItems(collect($grouped[Invoice::class])->sortByDesc('date'), 5),
            $this->paginateConflictItems(collect($grouped[AncillaryCost::class])->sortByDesc('date'), 5),
            $this->paginateConflictItems(collect($grouped[Product::class]), 5),
        ];
    }

    private function paginateConflictItems(\Illuminate\Support\Collection $conflictItems, int $perPage): \Illuminate\Pagination\LengthAwarePaginator
    {
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $conflictItems->forPage($page, $perPage),
            $conflictItems->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function formatConflict(array|Model $conflict): ?Model
    {
        if ($conflict instanceof Model) {
            return $this->resolveModel($conflict);
        }

        return $this->resolveArray($conflict);
    }

    private function resolveArray(array $conflict): ?Model
    {
        $type = $conflict['type'] ?? null;
        $id = $conflict['id'] ?? null;

        if (! $type || ! $id) {
            return null;
        }

        $modelClass = match (true) {
            in_array($type, \App\Enums\InvoiceType::cases(), true) => Invoice::class,
            $type === 'ancillarycost' => AncillaryCost::class,
            default => Product::class,
        };

        return $this->resolveModel($modelClass::findOrFail($id));
    }

    private function resolveModel(Model $model): Model
    {
        return $model->exists ? $model::findOrFail($model->id) : $model;
    }

    private function formatConflictForMessage(mixed $conflict): string
    {
        if ($conflict instanceof Invoice) {
            return __('Invoice #').$conflict->number;
        }

        if ($conflict instanceof AncillaryCost) {
            return __('Ancillary Cost').' #'.$conflict->id;
        }

        if (is_array($conflict) && isset($conflict['id'])) {
            $type = $conflict['type'] ?? __('Unknown');

            return ucfirst($type).' #'.$conflict['id'];
        }

        return __('Unknown conflict');
    }
}
