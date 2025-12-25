<?php

namespace App\Services;

use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class GroupActionService
{
    // public function groupAction(array $conflicts): void
    // {
    //     $invoiceService = new InvoiceService;
    //     $ancillaryCostService = new AncillaryCostService;

    //     $invoices = collect();
    //     $ancillaryCosts = collect();

    //     foreach ($conflicts as $conflict) {
    //         if (str_contains($conflict['type'], __('Invoice'))) {
    //             $invoices->push(Invoice::findOrFail($conflict['item']['id']));
    //         } elseif ($conflict['type'] === __('Ancillary Cost')) {
    //             $ancillaryCosts->push(AncillaryCost::findOrFail($conflict['item']['id']));
    //         }
    //     }

    //     $invoices = $invoices->sortBy('date')->sortBy('type')->sortBy('number');
    //     $ancillaryCosts = $ancillaryCosts->sortBy('date')->sortBy('invoice.number');

    //     // first unapprove invoices and ancillary costs
    //     foreach ($invoices as $invoice) {
    //         $invoiceService->changeInvoiceStatus($invoice, 'unapproved');
    //     }
    //     foreach ($ancillaryCosts as $ancillaryCost) {
    //         $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'unapproved');
    //     }

    //     // approve invoices and ancillary costs
    //     foreach ($invoices as $invoice) {
    //         $invoiceService->changeInvoiceStatus($invoice, 'approved');
    //     }
    //     foreach ($ancillaryCosts as $ancillaryCost) {
    //         $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'approved');
    //     }
    // }

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
    private function findConflictsRecursively(Model $model, array &$allConflicts, array &$processedIds = []): void
    {
        $key = get_class($model).':'.$model->id;

        // Skip if already processed
        if (in_array($key, $processedIds)) {
            return;
        }

        $processedIds[] = $key;
        $allConflicts[] = $model;

        // Get conflicts based on model type and recursively process them
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

        return $this->resolveDescriptor($conflict);
    }

    private function resolveDescriptor(array $conflict): ?Model
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
}
