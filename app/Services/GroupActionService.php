<?php

namespace App\Services;

use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class GroupActionService
{
    public function groupAction(Invoice $invoice, InvoiceService $invoiceService, AncillaryCostService $ancillaryCostService): void
    {
        [$invoicesConflicts, $ancillaryConflicts, $productsConflicts] = $this->findAllConflictsRecursively($invoice);

        $oversellConflicts = $productsConflicts->every(fn ($product) => $product->oversell === true);

        if ($invoice->invoice_type === \App\Enums\InvoiceType::SELL && ! $oversellConflicts) {
            return;
        }

        $conflictsToResolve = array_merge($invoicesConflicts, $ancillaryConflicts);
        $sortedConflictsToResolve = collect($conflictsToResolve)->sortByDesc(fn ($conflict) => $conflict->date)->values()->all();

        foreach ($sortedConflictsToResolve as $conflict) {
            if ($conflict instanceof Invoice) {
                $decision = $invoiceService->getChangeStatusValidation($conflict);

                if (! $decision->hasErrors()) {
                    $invoiceService->changeInvoiceStatus($conflict, 'unapproved');
                    $conflict->status = \App\Enums\InvoiceAncillaryCostStatus::APPROVED_INACTIVE;
                    $conflict->save();
                }
            } elseif ($conflict instanceof AncillaryCost) {
                $validation = $ancillaryCostService->getChangeStatusValidation($conflict);

                if (! $validation['allowed']) {
                    $ancillaryCostService->changeAncillaryCostStatus($conflict, 'unapproved');
                    $conflict->status = \App\Enums\InvoiceAncillaryCostStatus::APPROVED_INACTIVE;
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
