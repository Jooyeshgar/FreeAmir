<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCostItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): Product
    {
        $websites = $data['websites'] ?? [];
        unset($data['websites']);

        $data['company_id'] ??= getActiveCompany();

        $product = Product::create($data);

        WarehouseProductStock::create([
            'warehouse_id' => $product->warehouse_id,
            'product_id' => $product->id,
            'quantity' => $product->quantity ?? 0,
            'average_cost' => $product->average_cost ?? 0,
        ]);

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

        WarehouseProductStock::updateOrCreate(
            ['warehouse_id' => $product->warehouse_id, 'product_id' => $product->id],
            ['quantity' => $product->quantity ?? 0, 'average_cost' => $product->average_cost ?? 0]
        );

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

            if ($invoice_type === InvoiceType::BUY || $invoice_type === InvoiceType::RETURN_SELL || $invoice_type->isVoid()) {
                $product->quantity += $invoiceItem['quantity'];
            } elseif ($invoice_type === InvoiceType::SELL || $invoice_type === InvoiceType::RETURN_BUY) {
                $product->quantity -= $invoiceItem['quantity'];
            }
            $product->save();

            self::adjustForInvoice(
                $product,
                $invoiceItem['warehouse_id'] ?? $product->warehouse_id,
                (float) $invoiceItem['quantity'],
                in_array($invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_SELL], true) || $invoice_type->isVoid()
            );
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

            if ($invoice_type === InvoiceType::BUY || $invoice_type === InvoiceType::RETURN_SELL || $invoice_type->isVoid()) {
                $product->quantity -= $invoiceItem['quantity'];
            } elseif ($invoice_type === InvoiceType::SELL || $invoice_type === InvoiceType::RETURN_BUY) {
                $product->quantity += $invoiceItem['quantity'];
            }

            $product->save();

            self::adjustForInvoice(
                $product,
                $invoiceItem['warehouse_id'] ?? $product->warehouse_id,
                (float) $invoiceItem['quantity'],
                in_array($invoice_type, [InvoiceType::BUY, InvoiceType::RETURN_SELL], true) || $invoice_type->isVoid(),
                true
            );
        }
    }

    private static function adjustForInvoice(Product $product, ?int $warehouseId, float $quantity, bool $incoming, bool $reverse = false): void
    {
        $warehouse = $warehouseId ? Warehouse::findOrFail($warehouseId) : $product->warehouse;
        if (! $warehouse) {
            return;
        }

        $stock = WarehouseProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0, 'average_cost' => $product->average_cost ?? 0]
        );

        $delta = $incoming ? $quantity : -$quantity;

        if ($reverse) {
            $delta *= -1;
        }

        $stock->quantity = (float) $stock->quantity + $delta;
        $stock->average_cost = $product->average_cost ?? $stock->average_cost;
        $stock->save();
    }

    public static function recalculateQuantity(Product $product): float
    {
        return DB::transaction(function () use ($product): float {
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

            foreach ($invoices as $invoice) {
                foreach ($invoice->items as $item) {
                    $item->quantity_at = $quantity;
                    $item->save();

                    $sign = match ($invoice->invoice_type) {
                        InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID => 1,
                        InvoiceType::SELL, InvoiceType::RETURN_BUY => -1,
                    };

                    $quantity += $sign * (float) $item->quantity;
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
