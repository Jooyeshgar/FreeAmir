<?php

namespace Tests\Helpers;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\InvoiceService;

trait InvoiceTestHelper
{
    private function createProduct(array $overrides = []): Product
    {
        $group = \App\Models\ProductGroup::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        return \App\Models\Product::factory()
            ->withGroup($group)
            ->withSubjects()
            ->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    private function productItem(Product $product, int $qty, float $unit): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => $qty,
            'unit' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
        ];
    }

    private function createInvoice(
        InvoiceType $type,
        array $items,
        bool $approved = true,
        ?int $number = null,
        ?string $date = null,
        ?int $returnedInvoiceId = null
    ): array {
        $number ??= ++$this->nextInvoiceNumber;

        $result = InvoiceService::createInvoice(
            $this->user,
            [
                'title' => strtoupper($type->value).' Invoice',
                'date' => $date ?? now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'document_number' => $number,
                'number' => $number,
                'returned_invoice_id' => $returnedInvoiceId,
            ],
            $items,
            $approved
        );

        $invoice = $this->findInvoice($result['invoice']->id);

        if ($approved && ! $invoice->status->isApproved()) {
            $this->approveInvoice($invoice);
            $invoice = $this->findInvoice($invoice->id);
        }

        return [
            'document' => $result['document'],
            'invoice' => $invoice,
        ];
    }

    private function buy(array $items, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::BUY, $items, $approved, $number, $date);
    }

    private function sell(array $items, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::SELL, $items, $approved, $number, $date);
    }

    private function returnBuy(array $items, int $returnedInvoiceId, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::RETURN_BUY, $items, $approved, $number, $date, $returnedInvoiceId);
    }

    private function returnSell(array $items, int $returnedInvoiceId, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::RETURN_SELL, $items, $approved, $number, $date, $returnedInvoiceId);
    }

    private function findProduct(int $productId): Product
    {
        return Product::withoutGlobalScopes()->findOrFail($productId);
    }

    private function findInvoice(int $invoiceId): Invoice
    {
        return Invoice::withoutGlobalScopes()->with('items')->findOrFail($invoiceId);
    }

    private function findInvoiceItem(Invoice $invoice, Product $product): InvoiceItem
    {
        return InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->where('itemable_id', $product->id)
            ->where('itemable_type', Product::class)
            ->firstOrFail();
    }

    private function approveInvoice(Invoice $invoice): void
    {
        $invoice = $this->findInvoice($invoice->id);
        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
    }

    private function unapproveInvoice(Invoice $invoice): void
    {
        $invoice = $this->findInvoice($invoice->id);
        (new InvoiceService)->changeInvoiceStatus($invoice, 'unapproved');
    }
}
