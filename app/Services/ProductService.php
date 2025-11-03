<?php

namespace App\Services;

use App\Enums\ConfigTitle;
use App\Enums\InvoiceType;
use App\Models\Product;

class ProductService
{
    public function __construct(
        private readonly SubjectCreatorService $subjectCreator,
    ) {
    }

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
        $this->deleteSubjects($product);
        $product->productWebsites()->delete();

        $product->delete();
    }

    public function deleteSubjects(Product $product): void
    {
        $product->incomeSubject?->delete();
        $product->returnSalesSubject?->delete();
        $product->cogsSubject?->delete();
        $product->inventorySubject?->delete();
    }

    public static function updateProductQuantities(array $invoiceItems, InvoiceType $invoice_type, bool $deletingInvoiceItem = false): void
    {
        foreach ($invoiceItems as $invoiceItem) {
            $product = Product::find($invoiceItem['product_id']);
            if (! $product) {
                continue;
            }

            if (! $deletingInvoiceItem) {
                if ($invoice_type === InvoiceType::BUY) {
                    $product->quantity += $invoiceItem['quantity'];
                } elseif ($invoice_type === InvoiceType::SELL) {
                    $product->quantity -= $invoiceItem['quantity'];
                }
            } else {
                if ($invoice_type === InvoiceType::BUY) {
                    $product->quantity -= $invoiceItem['quantity'];
                } elseif ($invoice_type === InvoiceType::SELL) {
                    $product->quantity += $invoiceItem['quantity'];
                }
            }

            $product->save();
        }
    }

    protected function syncSubjects(Product $product): void
    {
        $product->loadMissing('productGroup', 'incomeSubject', 'returnSalesSubject', 'cogsSubject', 'inventorySubject');

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
            'return_sales_subject_id' => [
                'relation' => 'returnSalesSubject',
                'parent_column' => 'return_sales_subject_id',
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
                $subject = $this->subjectCreator->createSubject([
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
            if ($product->$column !== $id) {
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
}
