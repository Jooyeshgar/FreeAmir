<?php

namespace App\Services;

use App\Enums\InvoiceAncillaryCostStatus;
use App\Enums\InvoiceType;
use App\Models\Product;
use Exception;

class ProductService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): Product
    {
        $websites = $data['websites'] ?? [];
        unset($data['websites']);

        $data['company_id'] ??= session('active-company-id');

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
            $product = Product::find($invoiceItem['itemable_id']);
            if (! $product) {
                continue;
            }

            if ($invoice_type === InvoiceType::BUY) {
                $product->quantity += $invoiceItem['quantity'];
            } elseif ($invoice_type === InvoiceType::SELL) {
                $product->quantity -= $invoiceItem['quantity'];
            }

            $product->save();
        }
    }

    public static function subProductsQuantities(array $invoiceItems, InvoiceType $invoice_type): void
    {
        foreach ($invoiceItems as $invoiceItem) {
            if ($invoiceItem['itemable_type'] !== Product::class || $invoiceItem['itemable_type'] == 'product') {
                continue;
            }

            $product = Product::find($invoiceItem['itemable_id']);

            if (! $product) {
                continue;
            }

            if ($invoice_type === InvoiceType::BUY) {
                $product->quantity -= $invoiceItem['quantity'];
            } elseif ($invoice_type === InvoiceType::SELL) {
                $product->quantity += $invoiceItem['quantity'];
            }

            $product->save();
        }
    }

    public static function updateProductQuantities(array $oldItem, array $newItem, InvoiceType $invoice_type): void
    {
        $product = Product::find($newItem['itemable_id']);

        if (! $product) {
            throw new Exception(__('Product not found'), 404);
        }

        $diff = $newItem['quantity'] - $oldItem['quantity'];
        if ($diff !== 0) {
            if ($invoice_type === InvoiceType::BUY) {
                $product->quantity += $diff;
            } elseif ($invoice_type === InvoiceType::SELL) {
                $product->quantity -= $diff;
            }
            $product->save();
        }
    }

    protected function syncSubjects(Product $product): void
    {
        $product->loadMissing('productGroup', 'incomeSubject', 'salesReturnsSubject', 'cogsSubject', 'inventorySubject');

        $group = $product->productGroup;
        $companyId = $product->company_id ?? $group?->company_id ?? session('active-company-id');

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
                ->where('status', '!=', InvoiceAncillaryCostStatus::APPROVED)
            )
            ->sum('quantity');

        return $sum(InvoiceType::BUY) - $sum(InvoiceType::SELL);
    }

    public function totalSell(Product $product)
    {
        return $product->invoiceItems()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
                ->where('status', InvoiceAncillaryCostStatus::APPROVED)
            )
            ->sum('quantity');
    }

    public function lastApprovedBuyInvoiceItemCOG(Product $product)
    {
        $item = $product->invoiceItems()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', 'buy')
                ->where('status', InvoiceAncillaryCostStatus::APPROVED)
            )
            ->with('invoice:id,date')
            ->get()
            ->sortByDesc(fn ($item) => $item->invoice->date)
            ->first();

        if (! $item) {
            return 0;
        }

        $itemPrice = $item->unit_price ?? 0;

        $ancillaryCostsSum = \App\Models\AncillaryCostItem::whereHas('ancillaryCost', function ($q) use ($item) {
            $q->where('invoice_id', $item->invoice_id)
                ->where('status', InvoiceAncillaryCostStatus::APPROVED);
        })
            ->where('product_id', $product->id)
            ->sum('amount');

        return $itemPrice + ($ancillaryCostsSum / $item->quantity);
    }

    public function salesProfit(Product $product): float
    {
        return $this->subjectService->sumSubject($product->incomeSubject->code) - $product->average_cost;
    }
}
