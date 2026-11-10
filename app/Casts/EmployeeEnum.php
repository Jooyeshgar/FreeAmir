<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Employee columns store enum names; accept legacy numeric values when reading. */
class EmployeeEnum implements CastsAttributes
{
    public function __construct(private readonly string $enum) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null || $value === '' ? null : $this->enum::fromName($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null || $value === '' ? null : $this->enum::fromName($value)->valueName();
    }
}
