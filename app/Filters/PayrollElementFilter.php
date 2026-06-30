<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class PayrollElementFilter extends FilterAbstract
{
    protected array $filterables = [
        'category' => self::TYPE_STRING,
        'title' => self::TYPE_STRING,
    ];

    public function category(string $category): void
    {
        if ($category !== '') {
            $this->builder->where('category', $category);
        }
    }

    public function title(string $title): void
    {
        if ($title !== '') {
            $this->builder->where('title', 'like', '%'.$title.'%');
        }
    }
}
