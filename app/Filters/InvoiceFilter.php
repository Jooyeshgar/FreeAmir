<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Enums\InvoiceType;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;

class InvoiceFilter extends FilterAbstract
{
    protected array $filterables = [
        'invoice_type' => self::TYPE_STRING,
        'number' => self::TYPE_STRING,
        'start_date' => self::TYPE_STRING,
        'end_date' => self::TYPE_STRING,
        'text' => self::TYPE_STRING,
        'voided' => self::TYPE_BOOLEAN,
        'moadian_status' => self::TYPE_STRING,
    ];

    private const VALID_TYPES = ['buy', 'sell', 'return_buy', 'return_sell', 'void'];

    public function apply(Builder $builder): void
    {
        parent::apply($builder);
        $this->applyItemTypeFilter();
    }

    public function invoice_type(string $invoiceType): void
    {
        if (in_array($invoiceType, self::VALID_TYPES, true)) {
            $this->builder->where('invoice_type', $invoiceType);
        }
    }

    public function number(string $number): void
    {
        if ($number !== '') {
            $this->builder->where('number', $number);
        }
    }

    public function start_date(string $date): void
    {
        if ($date !== '') {
            $this->builder->where('date', '>=', convertToGregorian($date));
        }
    }

    public function end_date(string $date): void
    {
        if ($date !== '') {
            $this->builder->where('date', '<=', convertToGregorian($date));
        }
    }

    public function text(string $text): void
    {
        if ($text !== '') {
            $this->builder->where(function ($invoice) use ($text) {
                $invoice->whereHas('items', function ($items) use ($text) {
                    $items->where('description', 'like', "%{$text}%");
                })->orWhereHas('customer', function ($customer) use ($text) {
                    $customer->where('name', 'like', "%{$text}%");
                });
            });
        }
    }

    public function voided(bool $voided): void
    {
        if ($voided && $this->request->invoice_type === InvoiceType::SELL->value) {
            $this->builder->whereHas('voidInvoice');
        }
    }

    public function moadian_status(string $status): void
    {
        if ($status === '' || ! in_array($this->request->invoice_type, [InvoiceType::SELL->value, InvoiceType::VOID->value, InvoiceType::RETURN_SELL->value], true)) {
            return;
        }

        if ($status === 'not_sent') {
            $this->builder->whereDoesntHave('moadianHistories');
        } else {
            $this->builder->whereHas('latestMoadianHistory', fn ($history) => $history->where('data->status', $status));
        }
    }

    public function applyStatus(Builder $builder): void
    {
        $status = $this->request->input('status');

        if (in_array($status, ['approved', 'unapproved', 'pending', 'approved_inactive', 'rejected', 'ready_to_approve', 'pre_invoice', 'partially_paid', 'paid'], true)) {
            $builder->where('status', $status);
        }
    }

    public function isServiceBuy(): bool
    {
        return $this->request->filled('invoice_type')
            && in_array($this->request->invoice_type, [InvoiceType::BUY->value, InvoiceType::RETURN_BUY->value], true)
            && $this->request->filled('service_buy')
            && $this->request->service_buy == '1';
    }

    private function applyItemTypeFilter(): void
    {
        if ($this->isServiceBuy()) {
            $this->builder->whereHas('items', fn ($item) => $item->where('itemable_type', Service::class));

            return;
        }

        if (! in_array($this->request->invoice_type, [InvoiceType::SELL->value, InvoiceType::RETURN_SELL->value], true)) {
            $this->builder->whereHas('items', fn ($item) => $item->where('itemable_type', Product::class));
        }
    }
}
