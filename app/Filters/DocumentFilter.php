<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class DocumentFilter extends FilterAbstract
{
    protected array $filterables = [
        'number' => self::TYPE_STRING,
        'date' => self::TYPE_STRING,
        'text' => self::TYPE_STRING,
        'status' => self::TYPE_STRING,
    ];

    public function number(string $number): void
    {
        if ($number !== '') {
            $this->builder->where('number', convertToFloat($number));
        }
    }

    public function date(string $date): void
    {
        if ($date !== '') {
            $this->builder->where('date', convertToGregorian($date));
        }
    }

    public function text(string $text): void
    {
        if ($text !== '') {
            $this->builder->where(function ($query) use ($text) {
                $query->where('title', 'like', '%'.$text.'%')
                    ->orWhereHas('transactions', function ($transaction) use ($text) {
                        $transaction->where('desc', 'like', '%'.$text.'%');
                    });
            });
        }
    }

    public function status(string $status): void
    {
        if ($status === 'approved') {
            $this->builder->whereNotNull('approved_at');
        } elseif ($status === 'unapproved') {
            $this->builder->whereNull('approved_at');
        }
    }
}
