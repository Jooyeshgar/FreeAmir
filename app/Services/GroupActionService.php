<?php

namespace App\Services;

use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class GroupActionService
{
    public function runGroupAction(array $conflicts): void
    {
        $invoiceService = new InvoiceService;
        $ancillaryCostService = new AncillaryCostService;

        $invoices = collect();
        $ancillaryCosts = collect();

        foreach ($conflicts as $conflict) {
            if (str_contains($conflict['type'], __('Invoice'))) {
                $invoices->push(Invoice::findOrFail($conflict['recursive_type']['id']));
            } elseif ($conflict['type'] === __('Ancillary Cost')) {
                $ancillaryCosts->push(AncillaryCost::findOrFail($conflict['recursive_type']['id']));
            }
        }

        $invoices = $invoices->sortBy('date')->sortBy('type')->sortBy('number');
        $ancillaryCosts = $ancillaryCosts->sortBy('date')->sortBy('invoice.number');

        // first unapprove invoices and ancillary costs
        foreach ($invoices as $invoice) {
            $invoiceService->changeInvoiceStatus($invoice, 'unapproved');
        }
        foreach ($ancillaryCosts as $ancillaryCost) {
            $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'unapproved');
        }

        // approve invoices and ancillary costs
        foreach ($invoices as $invoice) {
            $invoiceService->changeInvoiceStatus($invoice, 'approved');
        }
        foreach ($ancillaryCosts as $ancillaryCost) {
            $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'approved');
        }
    }

    /**
     * Recursively find all conflicts for invoices and ancillary costs
     */
    public function findAllConflictsRecursively(array $initialConflicts): array
    {
        $allConflicts = [];
        $processedIds = []; // Track processed items to avoid infinite loops

        foreach ($initialConflicts as $conflict) {
            if (! isset($conflict['recursive_type'])) {
                $allConflicts[] = $conflict;

                continue;
            }

            $model = $conflict['recursive_type'];
            $modelClass = get_class($model);
            $modelId = $model->id;
            $key = $modelClass.':'.$modelId;

            // Skip if already processed
            if (in_array($key, $processedIds)) {
                continue;
            }

            $processedIds[] = $key;
            $allConflicts[] = $conflict;

            // Find conflicts based on model type
            if ($model instanceof Invoice) {
                $nestedConflicts = $this->findInvoiceConflictsRecursively($model, $processedIds);
                $allConflicts = array_merge($allConflicts, $nestedConflicts);
            } elseif ($model instanceof AncillaryCost) {
                $nestedConflicts = $this->findAncillaryCostConflictsRecursively($model, $processedIds);
                $allConflicts = array_merge($allConflicts, $nestedConflicts);
            }
        }

        return $allConflicts;
    }

    /**
     * Find all conflicts for an invoice recursively
     */
    private function findInvoiceConflictsRecursively(Invoice $invoice, array &$processedIds): array
    {
        if (! isset($invoice->status)) {
            $invoice = Invoice::findOrFail($invoice->id);
        }
        $decision = InvoiceService::getChangeStatusValidation($invoice);

        $formattedConflicts = [];

        // Process all conflicts from the decision
        foreach ($decision->conflicts as $conflict) {
            $modelClass = get_class($conflict);
            $key = $modelClass.':'.$conflict->id;

            // Skip if already processed
            if (in_array($key, $processedIds)) {
                continue;
            }

            $processedIds[] = $key;

            // Format the conflict
            $formatted = $this->formatConflict($conflict);
            $formattedConflicts[] = $formatted;

            // Recursively find nested conflicts
            if ($conflict instanceof Invoice) {
                $nestedConflicts = $this->findInvoiceConflictsRecursively($conflict, $processedIds);
                $formattedConflicts = array_merge($formattedConflicts, $nestedConflicts);
            } elseif ($conflict instanceof AncillaryCost) {
                $nestedConflicts = $this->findAncillaryCostConflictsRecursively($conflict, $processedIds);
                $formattedConflicts = array_merge($formattedConflicts, $nestedConflicts);
            }
        }

        return $formattedConflicts;
    }

    /**
     * Find all conflicts for an ancillary cost recursively
     */
    private function findAncillaryCostConflictsRecursively(AncillaryCost $ancillaryCost, array &$processedIds): array
    {
        $formattedConflicts = [];

        // Get validation for ancillary cost
        $validation = \App\Services\AncillaryCostService::getChangeStatusValidation($ancillaryCost);

        // If not allowed, the ancillary cost itself has blocking issues. Need to find what's blocking it
        if (! $validation['allowed'] && $ancillaryCost->invoice) {
            $invoice = $ancillaryCost->invoice;
            $key = Invoice::class.':'.$invoice->id;

            if (! in_array($key, $processedIds)) {
                $processedIds[] = $key;

                $formatted = $this->formatConflict($invoice);
                $formattedConflicts[] = $formatted;

                // Recursively find conflicts for the invoice
                $nestedConflicts = $this->findInvoiceConflictsRecursively($invoice, $processedIds);
                $formattedConflicts = array_merge($formattedConflicts, $nestedConflicts);
            }
        }

        return $formattedConflicts;
    }

    /**
     * Format a list of conflicts into display-ready arrays. Supports two input shapes:
     * 1) array of array descriptors: [['id'=>..,'type'=>..], ...]
     * 2) array of models: [Invoice|AncillaryCost|Product, ...]
     */
    public function formatConflicts(array $conflicts): array
    {
        return array_map(fn ($conflict) => $this->formatConflict($conflict), $conflicts);
    }

    /**
     * Format a single conflict. Accepts either an Eloquent model instance.
     * (Invoice/AncillaryCost/Product) or an array descriptor (id/type).
     */
    private function formatConflict($conflict): array
    {
        if ($conflict instanceof Model) {
            return $this->formatConflictModel($conflict);
        }

        if (is_array($conflict)) {
            return $this->formatConflictDescriptor($conflict);
        }

        return [];
    }

    private function formatConflictDescriptor(array $conflict): array
    {
        $type = $conflict['type'] ?? null;
        $id = $conflict['id'] ?? null;

        if (! $type || ! $id) {
            return [];
        }

        // invoices come from controller as type=invoice_type (buy/sell/return_*)
        if (in_array($type, ['buy', 'sell', 'return_buy', 'return_sell'])) {
            return $this->formatConflictModel(Invoice::findOrFail($id));
        }

        if ($type === 'ancillarycost') {
            return $this->formatConflictModel(AncillaryCost::findOrFail($id));
        }

        if ($type === 'product') {
            return $this->formatConflictModel(Product::findOrFail($id));
        }

        return [];
    }

    private function formatConflictModel(Model $model): array
    {
        $formatted = [];
        $formatted['recursive_type'] = $model;

        if ($model instanceof Invoice) {
            // Ensure we have a fresh instance with relations if the passed one is partial
            $invoice = $model->exists ? Invoice::findOrFail($model->id) : $model;
            $formatted['recursive_type'] = $invoice;
            $formatted['date'] = $invoice->date;
            $formatted['number'] = $invoice->number;
            $formatted['type'] = __('Invoice').' '.$invoice->invoice_type->label();
            $formatted['customer']['name'] = $invoice->customer->name ?? '';
            $formatted['customer']['id'] = $invoice->customer->id ?? '';
            $formatted['price'] = isset($invoice->amount, $invoice->subtraction) ? formatNumber($invoice->amount - $invoice->subtraction) : '';
            $formatted['status'] = $invoice->status->label() ?? '';

            return $formatted;
        }

        if ($model instanceof AncillaryCost) {
            $ancillaryCost = $model->exists ? AncillaryCost::findOrFail($model->id) : $model;
            $formatted['recursive_type'] = $ancillaryCost;
            $formatted['date'] = $ancillaryCost->date;
            $formatted['type'] = __('Ancillary Cost');
            $formatted['customer']['name'] = $ancillaryCost->customer->name ?? '';
            $formatted['customer']['id'] = $ancillaryCost->customer->id ?? '';
            $formatted['price'] = $ancillaryCost->amount ? formatNumber((float) $ancillaryCost->amount) : '';
            $formatted['status'] = $ancillaryCost->status->label() ?? '';

            return $formatted;
        }

        if ($model instanceof Product) {
            $product = $model->exists ? Product::findOrFail($model->id) : $model;
            $formatted['type'] = __('Product');
            $formatted['price'] = isset($product->average_cost) ? formatNumber($product->average_cost) : '';

            return $formatted;
        }

        return [];
    }
}
