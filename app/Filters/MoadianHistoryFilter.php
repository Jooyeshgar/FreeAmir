<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class MoadianHistoryFilter extends FilterAbstract
{
    protected array $filterables = [
        'status' => self::TYPE_STRING,
        'invoice_number' => self::TYPE_STRING,
        'date' => self::TYPE_STRING,
    ];

    public function status(string $status): void
    {
        if ($status !== '') {
            $this->builder->where('data->status', $status);
        }
    }

    public function invoice_number(string $invoiceNumber): void
    {
        if ($invoiceNumber !== '') {
            $this->builder->whereHas('invoice', fn ($invoice) => $invoice->where('number', $invoiceNumber));
        }
    }

    public function date(string $date): void
    {
        if ($date !== '') {
            $this->builder->whereDate('created_at', $date);
        }
    }
}
