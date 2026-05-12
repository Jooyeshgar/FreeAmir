<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use DateTime;
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

    private DateTime $transaction_date;

    private ?string $transaction_reference_number;

    private function moadianData(Invoice $invoice)
    {
        $this->invoice = $invoice;

        $this->header = $this->moadianHeader();
        $this->moadianInvoice = new MoadianInvoice($this->header);

        $this->moadianInvoiceItems();
        $this->moadianInvoicePayments();
    }

    public function sendInvoice(Invoice $invoice, ?string $transaction_reference_number, DateTime $transaction_date)
    {
        $this->moadian_username = env('MOADIAN_USERNAME');
        $this->taxID = env('TAXID');

        $this->transaction_reference_number = $transaction_reference_number;
        $this->transaction_date = $transaction_date;

        $this->moadianData($invoice);

        $info = Moadian::sendInvoice($this->moadianInvoice);
        $info = $info->getBody();
        $info = $info['result'][0];

        $invoice->moadianHistories()->create(['data' => json_encode($info)]);
        $invoice->update(['taxID' => $this->header->taxid]);
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
        }

        $header->indatim = $timestamp;
        $header->indati2m = $timestamp;
        $header->inno = $this->invoice->number;
        $header->inp = 1;
        $header->tins = $this->taxID;
        $header->tob = $this->invoice->customer->type;
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
            $body = new InvoiceItem;
            $body->sstid = $item->itemable->sstid;
            $body->sstt = $item->itemable->name;
            $body->am = $item->quantity;
            $body->fee = $item->unit_price;
            $body->prdis = $item->unit_price * $item->quantity;
            $body->dis = $item->unit_discount;
            $body->adis = $item->unit_price * $item->quantity - $item->discount;
            $body->vra = $item->vat / ($item->unit_price * $item->quantity - $item->discount);
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
