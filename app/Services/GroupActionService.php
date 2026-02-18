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
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly AncillaryCostService $ancillaryCostService
    ) {}

    public function approveInactiveInvoices(): void
    {
        $invoices = Invoice::where('status', InvoiceStatus::APPROVED_INACTIVE)->orderBy('date')->orderBy('number')
            ->orWhereHas('ancillaryCosts', function ($query) {
                $query->where('status', InvoiceStatus::APPROVED_INACTIVE);
            })->get();

        DB::transaction(function () use ($invoices) {
            foreach ($invoices as $invoice) {
                $decision = $this->invoiceService->getChangeStatusDecision($invoice, 'approved');

                if ($decision->hasErrors()) {
                    $conflicts = collect($decision->conflictsItems)
                        ->map(fn ($conflict) => $this->formatConflictForMessage($conflict))
                        ->implode(', ');

                    throw ValidationException::withMessages([
                        'invoice' => [__('Invoice #').$invoice->number.' '.__('cannot be approved because it has conflicts with').': '.$conflicts],
                    ]);
                }

                $this->invoiceService->changeInvoiceStatus($invoice, 'approved');

                foreach ($invoice->ancillaryCosts as $ancillaryCost) {
                    $validation = $this->ancillaryCostService->getChangeStatusValidation($ancillaryCost);

                    if (! $validation['allowed']) {
                        $reason = $validation['reason'] ?? __('unknown reason');
                        throw ValidationException::withMessages([
                            'ancillary_cost' => [__('Invoice #').$invoice->number.': '.__('Ancillary Cost').' #'.$ancillaryCost->id.' '.__('cannot be approved due to').': '.$reason],
                        ]);
                    }

                    $this->ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'approve');
                }
            }
        });
    }

    public function inactivateDependentInvoices(Invoice $invoice): void
    {
        // Find all conflicting invoices recursively (only invoices, not products or ancillary costs)
        $conflictingInvoices = $this->getConflictingInvoices($invoice);

        // Sort by date DESC, then number DESC (latest first for unapproving)
        $sortedInvoices = collect($conflictingInvoices)->sortByDesc(function ($inv) {
            $date = $inv->date instanceof \Carbon\Carbon ? $inv->date->format('Y-m-d') : $inv->date;

            return $date.str_pad((string) $inv->number, 10, '0', STR_PAD_LEFT);
        })->values()->all();

        DB::transaction(function () use ($sortedInvoices) {
            foreach ($sortedInvoices as $conflictInvoice) {
                // First, unapprove all ancillary costs for this invoice
                foreach ($conflictInvoice->ancillaryCosts as $ancillaryCost) {
                    if ($ancillaryCost->status->isApproved()) {
                        $validation = $this->ancillaryCostService->getChangeStatusValidation($ancillaryCost);

                        if (! $validation['allowed']) {
                            $reason = $validation['reason'] ?? __('unknown reason');
                            throw ValidationException::withMessages([
                                'ancillary_cost' => [__('Cannot unapprove Ancillary Cost #').$ancillaryCost->id.' '.__('of Invoice #').$conflictInvoice->number.' '.__('because of').': '.$reason],
                            ]);
                        }

                        $this->ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'unapprove');
                        $ancillaryCost->status = InvoiceStatus::APPROVED_INACTIVE;
                        $ancillaryCost->save();
                    }
                }

                // Then unapprove the invoice itself
                $this->invoiceService->changeInvoiceStatus($conflictInvoice, 'unapproved');
                $conflictInvoice->status = InvoiceStatus::APPROVED_INACTIVE;
                $conflictInvoice->save();
            }
        });
    }

    /**
     * Find all conflicting invoices, their ancillary costs, and referenced products
     *
     * Returns an array with three elements:
     * - Invoice conflicts (excluding the original invoice)
     * - AncillaryCost conflicts (from conflicting invoices)
     * - Product conflicts (products referenced in conflicts)
     */
    public function findAllConflictsRecursively(Invoice $invoice, bool $paginate = false): array
    {
        // Find all conflicting invoices recursively
        $conflictingInvoices = $this->findConflictingInvoices($invoice);

        // Exclude the original invoice from results
        $conflictingInvoices = array_filter($conflictingInvoices, fn ($inv) => $inv->id !== $invoice->id);

        // Find ancillary costs and products from conflicting invoices
        $conflictingAncillaryCosts = $this->findConflictingAncillaryCostsFromInvoices($conflictingInvoices);
        $conflictingProducts = $this->findConflictingProductsFromInvoices($conflictingInvoices);

        if ($paginate) {
            return [
                $this->paginateConflictItems(collect($conflictingInvoices)->sortByDesc('date'), 5),
                $this->paginateConflictItems(collect($conflictingAncillaryCosts)->sortByDesc('date'), 5),
                $this->paginateConflictItems(collect($conflictingProducts), 5),
            ];
        }

        return [$conflictingInvoices, $conflictingAncillaryCosts, $conflictingProducts];
    }

    /**
     * Get all invoices that need to be inactivated before the given invoice can be modified.
     * This method recursively follows the chain of invoice conflicts.
     *
     * For inactivation, we only care about invoice dependencies, not products or ancillary costs.
     */
    private function getConflictingInvoices(Invoice $invoice, array &$processedIds = []): array
    {
        $key = 'Invoice:'.$invoice->id;
        // Avoid infinite loops
        if (in_array($key, $processedIds)) {
            return [];
        }

        $processedIds[] = $key;
        $conflicts = [$invoice];

        // // Ensure the invoice is fully loaded
        // if (! isset($invoice->status)) {
        //     $invoice = Invoice::findOrFail($invoice->id);
        // }

        // Get product IDs from this invoice
        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        if (empty($productIds)) {
            return $conflicts;
        }

        // Find subsequent approved invoices that reference the same products
        $subsequentInvoices = Invoice::where('id', '!=', $invoice->id)
            ->where(function ($q) use ($invoice) {
                $q->where('date', '>', $invoice->date)
                    ->orWhere(function ($sub) use ($invoice) {
                        $sub->where('date', $invoice->date)
                            ->where('number', '>', $invoice->number);
                    });
            })
            ->where('status', InvoiceStatus::APPROVED)
            ->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
                ->whereIn('itemable_id', $productIds)
            )
            ->get();

        // Recursively find conflicts for each subsequent invoice
        foreach ($subsequentInvoices as $subsequentInvoice) {
            $nestedConflicts = $this->getConflictingInvoices($subsequentInvoice, $processedIds);
            $conflicts = array_merge($conflicts, $nestedConflicts);
        }

        return $conflicts;
    }

    /**
     * Recursively find all invoices that conflict with the given invoice
     *
     * An invoice conflicts if it cannot change status (approve/unapprove) due to other invoices.
     * This method follows the chain of conflicts to find all dependent invoices.
     *
     * @deprecated Use getConflictingInvoices for inactivation operations
     */
    private function findConflictingInvoices(Invoice $invoice, array &$processedIds = []): array
    {
        $key = 'Invoice:'.$invoice->id;

        // Avoid infinite loops by tracking processed invoices
        if (in_array($key, $processedIds)) {
            return [];
        }

        $processedIds[] = $key;
        $conflicts = [$invoice];

        // Ensure the invoice has status loaded
        if (! isset($invoice->status)) {
            $invoice = Invoice::findOrFail($invoice->id);
        }

        // Get validation decision which contains conflicting invoices
        $decision = InvoiceService::getChangeStatusValidation($invoice);

        // Recursively find conflicts from each conflicting item
        foreach ($decision->conflictsItems as $conflictItem) {
            $conflictModel = $this->resolveConflictToModel($conflictItem);

            if ($conflictModel instanceof Invoice) {
                $nestedConflicts = $this->findConflictingInvoices($conflictModel, $processedIds);
                $conflicts = array_merge($conflicts, $nestedConflicts);
            }
        }

        return $conflicts;
    }

    /**
     * Find ancillary costs that belong to conflicting invoices and have conflicts themselves
     *
     * Ancillary costs are only conflicts if:
     * 1. They belong to a conflicting invoice
     * 2. They are approved
     * 3. They cannot change status (have their own validation issues)
     */
    private function findConflictingAncillaryCostsFromInvoices(array $invoices): array
    {
        $conflictingAncillaryCosts = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->ancillaryCosts as $ancillaryCost) {
                // Only consider approved ancillary costs
                if (! $ancillaryCost->status->isApproved()) {
                    continue;
                }

                // Check if this ancillary cost has validation conflicts
                $validation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);

                if (! $validation['allowed']) {
                    $conflictingAncillaryCosts[] = $ancillaryCost;
                }
            }
        }

        return $conflictingAncillaryCosts;
    }

    /**
     * Extract products referenced in validation conflicts from invoices
     *
     * Products appear in conflicts for inventory/stock validation issues
     */
    private function findConflictingProductsFromInvoices(array $invoices): array
    {
        $products = [];
        $productIds = [];

        foreach ($invoices as $invoice) {
            $decision = InvoiceService::getChangeStatusValidation($invoice);

            foreach ($decision->conflictsItems as $conflictItem) {
                $model = $this->resolveConflictToModel($conflictItem);

                if ($model instanceof Product && ! in_array($model->id, $productIds)) {
                    $products[] = $model;
                    $productIds[] = $model->id;
                }
            }
        }

        return $products;
    }

    /**
     * Resolve a conflict item (array or Model) to its actual Model instance
     */
    private function resolveConflictToModel(array|Model $conflict): ?Model
    {
        if ($conflict instanceof Model) {
            return $this->ensureModelLoaded($conflict);
        }

        return $this->resolveConflictArrayToModel($conflict);
    }

    /**
     * Convert conflict array structure to Model instance
     */
    private function resolveConflictArrayToModel(array $conflict): ?Model
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

        return $modelClass::findOrFail($id);
    }

    /**
     * Ensure model is fully loaded from database (not just a reference)
     */
    private function ensureModelLoaded(Model $model): Model
    {
        return $model->exists ? $model::findOrFail($model->id) : $model;
    }

    /**
     * Paginate a collection of conflict items for display
     */
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

    /**
     * Format a conflict model for display in error messages
     */
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

            // Convert enum to string if needed
            if ($type instanceof \BackedEnum) {
                $type = $type->value;
            } elseif ($type instanceof \UnitEnum) {
                $type = $type->name;
            }

            return ucfirst((string) $type).' #'.$conflict['id'];
        }

        return __('Unknown conflict');
    }
}
