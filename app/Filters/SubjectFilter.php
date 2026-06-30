<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class SubjectFilter extends FilterAbstract
{
    protected array $filterables = [
        'name_is_default' => self::TYPE_BOOLEAN,
        'parent_id' => self::TYPE_INTEGER,
    ];

    public function name_is_default(bool $nameIsDefault): void
    {
        if (! $nameIsDefault) {
            return;
        }

        $prefixes = [
            __('Kol :code', ['code' => '']),
            __('Moein :code', ['code' => '']),
            __('Tafsili :code', ['code' => '']),
            explode(':n', __('Level :n :code'))[0],
        ];

        $this->builder->where(function ($query) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                if ($prefix !== '') {
                    $query->orWhere('name', 'like', "{$prefix}%");
                }
            }
        });
    }

    public function parent_id(int $parentId): void
    {
        if (! $this->request->boolean('name_is_default')) {
            $this->builder->where('parent_id', $parentId);
        }
    }
}
