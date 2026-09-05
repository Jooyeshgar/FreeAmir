<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): Product
    {
        $websites = $data['websites'] ?? [];
        unset($data['websites']);

        $data['company_id'] ??= getActiveCompany();

        $product = Product::create($data);

        $this->syncSubjects($product);
        $this->syncWebsites($product, $websites);

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $websites = $data['websites'] ?? null;
        unset($data['websites']);

        $product->fill($data);
        $product->save();

        $this->syncSubjects($product);

        if ($websites !== null) {
            $this->syncWebsites($product, $websites);
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->productWebsites()->delete();
        $product->delete();
        $this->deleteSubjects($product);
    }

    public function deleteSubjects(Product $product): void
    {
        $product->incomeSubject?->delete();
        $product->salesReturnsSubject?->delete();
        $product->cogsSubject?->delete();
        $product->inventorySubject?->delete();
    }

    public static function addProductsQuantities(array $invoiceItems, InvoiceType $invoice_type): void
    {
        foreach ($invoiceItems as $invoiceItem) {
            // When editing an existing invoice, itemable_type is set to Product::class or Service::class.
            // When creating a new invoice, itemable_type is set to the string 'product' or 'service'.
            if (! in_array($invoiceItem['itemable_type'], [Product::class, 'product'], true)) {
                continue;
            }
            $product = Product::find($invoiceItem['itemable_id']);
            if (! $product) {
                continue;
            }

            self::adjustForInvoice(
                $product,
                self::resolveWarehouseId($invoiceItem, $product),
                (float) $invoiceItem['quantity'],
                in_array($invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_SELL], true) || $invoice_type->isVoid()
            );

            if ($invoice_type === InvoiceType::BUY || $invoice_type === InvoiceType::RETURN_SELL || $invoice_type->isVoid()) {
                $product->quantity += $invoiceItem['quantity'];
            } elseif ($invoice_type === InvoiceType::SELL || $invoice_type === InvoiceType::RETURN_BUY) {
                $product->quantity -= $invoiceItem['quantity'];
            }
            $product->save();
        }
    }

    public static function subProductsQuantities(array $invoiceItems, InvoiceType $invoice_type): void
    {
        foreach ($invoiceItems as $invoiceItem) {
            // itemable_type cannot be 'product' when unapproving invoices
            if ($invoiceItem['itemable_type'] !== Product::class) {
                continue;
            }

            $product = Product::find($invoiceItem['itemable_id']);

            if (! $product) {
                continue;
            }

            self::adjustForInvoice(
                $product,
                self::resolveWarehouseId($invoiceItem, $product),
                (float) $invoiceItem['quantity'],
                in_array($invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_SELL], true) || $invoice_type->isVoid(),
                true
            );

            if ($invoice_type === InvoiceType::BUY || $invoice_type === InvoiceType::RETURN_SELL || $invoice_type->isVoid()) {
                $product->quantity -= $invoiceItem['quantity'];
            } elseif ($invoice_type === InvoiceType::SELL || $invoice_type === InvoiceType::RETURN_BUY) {
                $product->quantity += $invoiceItem['quantity'];
            }

            $product->save();
        }
    }

    private static function adjustForInvoice(Product $product, ?int $warehouseId, float $quantity, bool $incoming, bool $reverse = false): void
    {
        $warehouse = $warehouseId ? Warehouse::findOrFail($warehouseId) : null;
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => __('The selected warehouse is invalid.')]);
        }

        if ((int) $warehouse->company_id !== (int) $product->company_id) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('The selected warehouse is invalid.'),
            ]);
        }

        WarehouseProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0, 'average_cost' => $product->average_cost ?? 0]
        );

        $stock = WarehouseProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->firstOrFail();

        $delta = $incoming ? $quantity : -$quantity;

        if ($reverse) {
            $delta *= -1;
        }

        if (! $product->oversell && (float) $stock->quantity + $delta < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('The selected warehouse does not have enough stock.'),
            ]);
        }

        $stock->quantity = (float) $stock->quantity + $delta;
        $stock->save();
    }

    private static function resolveWarehouseId(array $invoiceItem, Product $product): ?int
    {
        if (isset($invoiceItem['warehouse_id'])) {
            return (int) $invoiceItem['warehouse_id'];
        }

        $invoiceWarehouseId = isset($invoiceItem['invoice_id']) ? Invoice::query()->whereKey($invoiceItem['invoice_id'])->value('warehouse_id') : null;

        return $invoiceWarehouseId ?? Warehouse::query()->where('company_id', $product->company_id)->orderBy('id')->value('id');
    }

    public static function adjustWarehouseAverageCostForAncillaryCost(AncillaryCost $ancillaryCost, bool $reverse = false): void
    {
        $ancillaryCost->loadMissing('items', 'invoice.items');

        foreach ($ancillaryCost->items as $ancillaryCostItem) {
            $invoiceItems = $ancillaryCost->invoice->items
                ->filter(fn (InvoiceItem $item) => $item->itemable_type === Product::class
                    && (int) $item->itemable_id === (int) $ancillaryCostItem->product_id
                    && $item->warehouse_id);
            $totalQuantity = (float) $invoiceItems->sum('quantity');

            if ($totalQuantity <= 0) {
                continue;
            }

            foreach ($invoiceItems as $invoiceItem) {
                $stock = WarehouseProductStock::query()
                    ->where('product_id', $ancillaryCostItem->product_id)
                    ->where('warehouse_id', $invoiceItem->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || (float) $stock->quantity <= 0) {
                    continue;
                }

                $allocation = (float) $ancillaryCostItem->amount
                    * ((float) $invoiceItem->quantity / $totalQuantity);
                $costDelta = $allocation * ($reverse ? -1 : 1);
                $stockValue = ((float) $stock->quantity * (float) $stock->average_cost) + $costDelta;
                $stock->average_cost = max(0.0, $stockValue / (float) $stock->quantity);
                $stock->save();
            }
        }
    }

    public static function updateWarehouseAverageCosts(Invoice $invoice): void
    {
        $invoice->loadMissing('items.itemable', 'ancillaryCosts.items');

        foreach ($invoice->items as $invoiceItem) {
            if ($invoiceItem->itemable_type !== Product::class || ! $invoice->warehouse_id) {
                continue;
            }

            $stock = WarehouseProductStock::query()
                ->where('product_id', $invoiceItem->itemable_id)
                ->where('warehouse_id', $invoice->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $quantity = (float) $invoiceItem->quantity;
            $stockQuantity = (float) $stock->quantity;
            $incomingUnitCost = self::invoiceItemIncomingUnitCost($invoice, $invoiceItem);

            if ($invoice->status->isApprovedOrSettled()) {
                if (in_array($invoice->invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true)) {
                    $quantityBefore = max(0.0, $stockQuantity - $quantity);
                    $stock->average_cost = $stockQuantity > 0
                        ? (($quantityBefore * (float) $stock->average_cost) + ($quantity * $incomingUnitCost)) / $stockQuantity
                        : 0;
                } elseif ($stockQuantity <= 0) {
                    $stock->average_cost = 0;
                }
            } elseif (in_array($invoice->invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true)) {
                $quantityBeforeReversal = $stockQuantity + $quantity;
                $remainingValue = ($quantityBeforeReversal * (float) $stock->average_cost) - ($quantity * $incomingUnitCost);
                $stock->average_cost = $stockQuantity > 0 ? max(0.0, $remainingValue / $stockQuantity) : 0;
            } elseif ($invoice->invoice_type === InvoiceType::RETURN_BUY) {
                $quantityBeforeReversal = max(0.0, $stockQuantity - $quantity);
                $stock->average_cost = $stockQuantity > 0
                    ? (($quantityBeforeReversal * (float) $stock->average_cost) + ($quantity * $incomingUnitCost)) / $stockQuantity
                    : 0;
            }

            $stock->save();
        }
    }

    private static function invoiceItemIncomingUnitCost(Invoice $invoice, InvoiceItem $invoiceItem): float
    {
        if (in_array($invoice->invoice_type, [InvoiceType::RETURN_SELL, InvoiceType::RETURN_BUY, InvoiceType::VOID], true)) {
            $originalItem = InvoiceItem::query()
                ->where('invoice_id', $invoice->returned_invoice_id)
                ->where('itemable_type', Product::class)
                ->where('itemable_id', $invoiceItem->itemable_id)
                ->first();

            return (float) ($originalItem?->cog_after ?? $invoiceItem->cog_after);
        }

        $baseCost = (float) $invoiceItem->amount - (float) ($invoiceItem->vat ?? 0);
        $ancillaryCost = (float) $invoice->ancillaryCosts
            ->where('status', InvoiceStatus::APPROVED)
            ->flatMap->items
            ->where('product_id', $invoiceItem->itemable_id)
            ->sum('amount');

        return (float) $invoiceItem->quantity > 0
            ? ($baseCost + $ancillaryCost) / (float) $invoiceItem->quantity
            : 0;
    }

    public static function recalculateQuantity(Product $product): float
    {
        return DB::transaction(function () use ($product): float {
            $stocks = WarehouseProductStock::query()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('warehouse_id');

            foreach ($stocks as $stock) {
                $stock->quantity = 0;
                $stock->save();
            }

            $invoices = Invoice::withoutGlobalScopes()
                ->whereIn('status', InvoiceStatus::approvedOrSettled())
                ->whereHas('items', function ($query) use ($product) {
                    $query->where('itemable_type', Product::class)->where('itemable_id', $product->id);
                })
                ->with(['items' => function ($query) use ($product) {
                    $query->where('itemable_type', Product::class)->where('itemable_id', $product->id);
                }])
                ->orderBy('date')
                ->orderBy('number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $quantity = 0.0;
            $warehouseQuantities = [];

            foreach ($invoices as $invoice) {
                foreach ($invoice->items as $item) {
                    $warehouseId = $invoice->warehouse_id;
                    $warehouseQuantity = $warehouseQuantities[$warehouseId] ?? 0.0;
                    $item->quantity_at = $quantity;
                    $item->save();

                    $sign = match ($invoice->invoice_type) {
                        InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID => 1,
                        InvoiceType::SELL, InvoiceType::RETURN_BUY => -1,
                    };

                    $quantity += $sign * (float) $item->quantity;
                    if ($warehouseId) {
                        $warehouseQuantity += $sign * (float) $item->quantity;
                        $warehouseQuantities[$warehouseId] = $warehouseQuantity;

                        $stock = $stocks->get($warehouseId);
                        if (! $stock) {
                            $stock = WarehouseProductStock::query()->firstOrCreate(
                                ['warehouse_id' => $warehouseId, 'product_id' => $product->id],
                                ['quantity' => 0, 'average_cost' => $product->average_cost ?? 0]
                            );
                            $stocks->put($warehouseId, $stock);
                        }

                        $stock->quantity = $warehouseQuantity;
                        $stock->save();
                    }
                }
            }

            $product->quantity = $quantity;
            $product->save();

            return $quantity;
        });
    }

    protected function syncSubjects(Product $product): void
    {
        $product->loadMissing('productGroup', 'incomeSubject', 'salesReturnsSubject', 'cogsSubject', 'inventorySubject');

        $group = $product->productGroup;
        $companyId = $product->company_id ?? $group?->company_id ?? getActiveCompany();

        if (! $companyId) {
            throw new \RuntimeException('Unable to determine company for product subject synchronization.');
        }

        $subjectConfigs = [
            'income_subject_id' => [
                'relation' => 'incomeSubject',
                'parent_column' => 'income_subject_id',
            ],
            'sales_returns_subject_id' => [
                'relation' => 'salesReturnsSubject',
                'parent_column' => 'sales_returns_subject_id',
            ],
            'cogs_subject_id' => [
                'relation' => 'cogsSubject',
                'parent_column' => 'cogs_subject_id',
            ],
            'inventory_subject_id' => [
                'relation' => 'inventorySubject',
                'parent_column' => 'inventory_subject_id',
            ],
        ];

        $updatedIds = [];

        foreach ($subjectConfigs as $column => $settings) {
            $relation = $settings['relation'];
            $subject = $product->$relation;
            $parentId = $group?->{$settings['parent_column']} ?? null;
            $targetName = $product->name;

            if (! $subject) {
                $subject = $this->subjectService->createSubject([
                    'name' => $targetName,
                    'parent_id' => $parentId,
                    'company_id' => $companyId,
                ]);
            }

            $needsSave = false;

            if ($subject->name !== $targetName) {
                $subject->name = $targetName;
                $needsSave = true;
            }

            $normalizedParentId = $parentId ?: null;
            if ($subject->parent_id !== $normalizedParentId) {
                $subject->parent_id = $normalizedParentId;
                $needsSave = true;
            }

            if ($subject->subjectable_id !== $product->id || $subject->subjectable_type !== $product->getMorphClass()) {
                $subject->subjectable()->associate($product);
                $needsSave = true;
            }

            if ($needsSave) {
                $subject->save();
            }

            $product->setRelation($relation, $subject);
            $updatedIds[$column] = $subject->id;
        }

        $dirtyIds = [];

        foreach ($updatedIds as $column => $id) {
            if ($id !== $product->$column) {
                $dirtyIds[$column] = $id;
            }
        }

        if ($dirtyIds) {
            $product->updateQuietly($dirtyIds);
        }
    }

    protected function syncWebsites(Product $product, array $websites): void
    {
        $product->productWebsites()->delete();

        $prepared = [];

        foreach ($websites as $website) {
            $link = $website['link'] ?? null;

            if (filled($link)) {
                $prepared[] = ['link' => $link];
            }
        }

        if (! empty($prepared)) {
            $product->productWebsites()->createMany($prepared);
        }
    }

    public function unapprovedQuantity(Product $product)
    {
        $sum = fn ($type) => $product->invoiceItems()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', $type)
                ->whereNotIn('status', InvoiceStatus::approvedOrSettled())
            )
            ->sum('quantity');

        return $sum(InvoiceType::BUY) - $sum(InvoiceType::SELL);
    }

    public function totalSellCount(Product $product)
    {
        return $product->invoiceItems()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
                ->whereIn('status', InvoiceStatus::approvedOrSettled())
            )
            ->sum('quantity');
    }

    public function lastApprovedBuyInvoiceItemCOG(Product $product)
    {
        $item = $product->invoiceItems()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::BUY)
                ->whereIn('status', InvoiceStatus::approvedOrSettled())
            )
            ->with('invoice:id,date')
            ->get()
            ->sortByDesc(fn ($item) => $item->invoice->date)
            ->first();

        if (! $item) {
            return 0;
        }

        $itemPrice = $item->unit_price ?? 0;

        $ancillaryCostsSum = AncillaryCostItem::whereHas('ancillaryCost', function ($q) use ($item) {
            $q->where('invoice_id', $item->invoice_id)
                ->where('status', InvoiceStatus::APPROVED);
        })
            ->where('product_id', $product->id)
            ->sum('amount');

        return $itemPrice + ($ancillaryCostsSum / $item->quantity);
    }

    public function totalCOGS(Product $product): float
    {
        return $this->subjectService->sumSubject($product->cogsSubject);
    }

    public function totalSell(Product $product): float
    {
        return $this->subjectService->sumSubject($product->incomeSubject);
    }
}
