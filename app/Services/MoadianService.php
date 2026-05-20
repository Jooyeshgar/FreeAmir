<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\CustomerType;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Carbon;
use Jooyeshgar\Moadian\Facades\Moadian;
use Jooyeshgar\Moadian\Invoice as MoadianInvoice;
use Jooyeshgar\Moadian\InvoiceHeader;
use Jooyeshgar\Moadian\InvoiceItem;
use Jooyeshgar\Moadian\Payment;

class MoadianService
{
    private string $moadian_username;

    private string $taxID;

    private InvoiceHeader $header;

    private Invoice $invoice;

    public MoadianInvoice $moadianInvoice;

    private string $transaction_date;

    private ?string $transaction_reference_number;

    public function sendInvoice(Invoice $invoice, ?string $transaction_reference_number, string $transaction_date): bool
    {
        $this->moadian_username = config('moadian.username');
        $this->taxID = config('services.moadian.tax_id');

        $this->transaction_reference_number = $transaction_reference_number;
        $this->transaction_date = $transaction_date;

        $this->moadianData($invoice);

        $info = [];
        $sentInvoiceToMoadianWithSuccess = true;

        try {
            $info = Moadian::sendInvoice($this->moadianInvoice);
            $info = $info->getBody();
            $info = $info['result'][0];
        } catch (\Exception $e) {
            $sentInvoiceToMoadianWithSuccess = false;
            $info = ['status' => 'FAILED', 'error' => $e->getMessage()];
        }

        $invoice->moadianHistories()->create(['data' => $info]);
        $invoice->update(['taxID' => $this->header->taxid]);

        return $sentInvoiceToMoadianWithSuccess;
    }

    public function validateSendMoadian(Invoice $invoice): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        if (! $invoice->status->isApproved()) {
            $decision->addMessage('error', __('Cannot send an unapproved invoice to moadian.'));
        }

        if (! $invoice->invoice_type->isMoadianSendable()) {
            $decision->addMessage('error', __('Cannot send a buy or return buy invoice to moadian.'));
        }

        $hasMoadianSuccess = $invoice->moadianHistories->contains(function ($history) {
            $data = $history->data;

            return strtoupper($data['status'] ?? '') === 'SUCCESS';
        });

        if ($hasMoadianSuccess) {
            $decision->addMessage('error', __('Cannot send an invoice to moadian that already has successful status from moadian.'));
        }

        foreach ($invoice->items as $item) {
            if (! $item->itemable->sstid) {
                $decision->addMessage('error', __('All invoice items must have a valid SSTID.'));
            }
        }

        return $decision;
    }

    public function moadianStatus(string $referenceNumber, Invoice $invoice): array
    {
        try {
            $response = Moadian::inquiryByReferenceNumbers($referenceNumber);
            $statusData = $response->getBody()[0] ?? [];
        } catch (ClientException $e) {
            $statusData = ['status' => 'FAILED', 'error' => $e->getMessage()];
        }

        $invoice->moadianHistories()->create(['data' => $statusData]);

        return $statusData;
    }

    private function moadianData(Invoice $invoice): void
    {
        $this->invoice = $invoice;

        $this->header = $this->moadianHeader();
        $this->moadianInvoice = new MoadianInvoice($this->header);

        $this->moadianInvoiceItems();
        $this->moadianInvoicePayments();
    }

    private function voidInvoice(): bool
    {
        if (! $this->invoice->returned_invoice_id) {
            return false;
        }

        $refInvoice = Invoice::with('items')->find($this->invoice->returned_invoice_id);

        if (! $refInvoice || $this->invoice->items->isEmpty()) {
            return false;
        }

        $refQuantities = $refInvoice->items->groupBy(fn ($item) => $item->itemable_type.'_'.$item->itemable_id)->map->sum('quantity');
        $currentQuantities = $this->invoice->items->groupBy(fn ($item) => $item->itemable_type.'_'.$item->itemable_id)->map->sum('quantity');

        return $refQuantities->toArray() == $currentQuantities->toArray();
    }

    private function moadianHeader(): InvoiceHeader
    {
        $timestamp = Carbon::parse($this->invoice->date)->timestamp * 1000;

        $header = new InvoiceHeader($this->moadian_username);
        $header->setTaxID(Carbon::parse($this->invoice->date), $this->invoice->number);

        if ($this->invoice->invoice_type === InvoiceType::SELL) {
            $header->inty = 1;
            $header->ins = 1;
            $header->irtaxid = null;
        } elseif ($this->invoice->invoice_type === InvoiceType::RETURN_SELL) {
            $isVoid = $this->voidInvoice();
            $refInvoice = Invoice::find($this->invoice->returned_invoice_id);

            $header->irtaxid = $refInvoice->taxID;
            $header->inty = $isVoid ? 3 : 4;
            $header->ins = $isVoid ? 3 : 4;
        } elseif ($this->invoice->invoice_type === InvoiceType::VOID) {
            $refInvoice = Invoice::find($this->invoice->returned_invoice_id);

            $header->irtaxid = $refInvoice?->taxID;
            $header->inty = 3;
            $header->ins = 3;
        }

        $header->indatim = $timestamp;
        $header->indati2m = $timestamp;
        $header->inno = $this->invoice->number;
        $header->inp = 1;
        $header->tins = $this->taxID;
        $header->tob = match ($this->invoice->customer->type) {
            CustomerType::INDIVIDUAL => 1,
            CustomerType::LEGAL_ENTITY => 2,
            CustomerType::CIVIL_PARTNERSHIP => 3,
            CustomerType::FOREIGN_NATIONAL => 4,
        };
        $header->bid = $this->invoice->customer->personal_code;
        $header->tinb = $this->invoice->customer->ecnmcs_code;
        $header->bpc = $this->invoice->customer->postal_code;

        $amount = $this->invoice->amount;
        $discount = $this->invoice->items->sum('unit_discount');
        $header->tprdis = $amount + $discount;
        $header->tdis = $discount;
        $header->tadis = $amount;
        $header->tvam = $this->invoice->vat;
        $header->todam = 0;
        $header->tbill = $amount;
        $header->setm = 1;
        $header->cap = $amount;

        return $header;
    }

    private function moadianInvoiceItems(): void
    {
        foreach ($this->invoice->items as $item) {
            $amountAfterDiscount = $item->unit_price * $item->quantity - $item->discount;

            $body = new InvoiceItem;
            $body->sstid = $item->itemable->sstid;
            $body->sstt = $item->itemable->name;
            $body->am = $item->quantity;
            $body->fee = $item->unit_price;
            $body->prdis = $item->unit_price * $item->quantity;
            $body->dis = $item->unit_discount;
            $body->adis = $amountAfterDiscount;
            $body->vra = $amountAfterDiscount > 0 ? $item->vat / ($amountAfterDiscount) : 0.0;
            $body->vam = $item->vat;
            $body->tsstam = $item->amount;
            $this->moadianInvoice->addItem($body);
        }
    }

    private function moadianInvoicePayments(): void
    {
        $payment = new Payment;
        $payment->trn = $this->transaction_reference_number;
        $payment->pdt = Carbon::parse($this->transaction_date)->timestamp * 1000;
        $this->moadianInvoice->addPayment($payment);
    }
}
