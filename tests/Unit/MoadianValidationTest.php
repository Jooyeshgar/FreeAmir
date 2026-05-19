<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MoadianHistory;
use App\Models\Product;
use App\Services\MoadianService;
use Tests\TestCase;

class MoadianValidationTest extends TestCase
{
    private MoadianService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MoadianService;
    }

    private function invoice(InvoiceType $type, InvoiceStatus $status): Invoice
    {
        $invoice = new Invoice;
        $invoice->forceFill(['invoice_type' => $type->value, 'status' => $status->value]);
        $invoice->setRelation('moadianHistories', collect());
        $invoice->setRelation('items', collect());

        return $invoice;
    }

    private function historyWith(string $status): MoadianHistory
    {
        $h = new MoadianHistory;
        $h->forceFill(['data' => ['status' => $status]]);

        return $h;
    }

    private function itemWith(?string $sstid): InvoiceItem
    {
        $product = new Product;
        $product->forceFill(['sstid' => $sstid]);
        $item = new InvoiceItem;
        $item->setRelation('itemable', $product);

        return $item;
    }

    public function test_sell_invoice_is_allowed(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);

        $this->assertFalse($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_return_sell_invoice_is_allowed(): void
    {
        $invoice = $this->invoice(InvoiceType::RETURN_SELL, InvoiceStatus::APPROVED);

        $this->assertFalse($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_void_invoice_is_allowed(): void
    {
        $invoice = $this->invoice(InvoiceType::VOID, InvoiceStatus::APPROVED);

        $this->assertFalse($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_buy_invoice_is_rejected(): void
    {
        $invoice = $this->invoice(InvoiceType::BUY, InvoiceStatus::APPROVED);

        $this->assertTrue($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_return_buy_invoice_is_rejected(): void
    {
        $invoice = $this->invoice(InvoiceType::RETURN_BUY, InvoiceStatus::APPROVED);

        $this->assertTrue($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_non_approved_statuses_are_rejected(): void
    {
        $blocked = [
            InvoiceStatus::PENDING,
            InvoiceStatus::PRE_INVOICE,
            InvoiceStatus::UNAPPROVED,
            InvoiceStatus::REJECTED,
            InvoiceStatus::READY_TO_APPROVE,
        ];

        foreach ($blocked as $status) {
            $invoice = $this->invoice(InvoiceType::SELL, $status);
            $this->assertTrue(
                $this->service->validateSendMoadian($invoice)->hasErrors(),
                "Status [{$status->value}] should be blocked."
            );
        }
    }

    public function test_invoice_with_success_history_is_blocked(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $invoice->setRelation('moadianHistories', collect([$this->historyWith('SUCCESS')]));

        $this->assertTrue($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_success_check_is_case_insensitive(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $invoice->setRelation('moadianHistories', collect([$this->historyWith('success')]));

        $this->assertTrue($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_invoice_with_failed_history_can_retry(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $invoice->setRelation('moadianHistories', collect([$this->historyWith('FAILED')]));

        $this->assertFalse($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_invoice_with_empty_history_data_can_retry(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $h = new MoadianHistory;
        $h->forceFill(['data' => []]);
        $invoice->setRelation('moadianHistories', collect([$h]));

        $this->assertFalse($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_item_without_sstid_is_rejected(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $invoice->setRelation('items', collect([$this->itemWith(null)]));

        $this->assertTrue($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_item_with_sstid_passes_validation(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $invoice->setRelation('items', collect([$this->itemWith('SSTID-001')]));

        $this->assertFalse($this->service->validateSendMoadian($invoice)->hasErrors());
    }

    public function test_mixed_items_one_missing_sstid_is_rejected(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED);
        $invoice->setRelation('items', collect([
            $this->itemWith('SSTID-001'),
            $this->itemWith(null),
        ]));

        $this->assertTrue($this->service->validateSendMoadian($invoice)->hasErrors());
    }
}
