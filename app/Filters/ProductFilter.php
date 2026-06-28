<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterGroupName;
use App\Traits\FilterName;

class ProductFilter extends FilterAbstract
{
    use FilterGroupName;
    use FilterName;

    protected string $groupNameRelation = 'productGroup';

    protected array $filterables = [
        'name' => self::TYPE_STRING,
        'code' => self::TYPE_STRING,
        'group_name' => self::TYPE_STRING,
        'min_quantity' => self::TYPE_STRING,
    ];

    public function code(string $code): void
    {
        if ($code !== '') {
            $this->builder->where('code', 'like', '%'.$code.'%');
        }
    }

    public function min_quantity(string $quantity): void
    {
        if (is_numeric($quantity)) {
            $this->builder->where('quantity', '>=', (float) $quantity);
        }
    }
}
