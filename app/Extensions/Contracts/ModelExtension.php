<?php

namespace App\Extensions\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Contract for extension data attached to an Eloquent model.
 */
interface ModelExtension
{
    /** @return array<string, mixed> */
    public function validate(array $input, ?Model $model = null): array;

    /** @param array<string, mixed> $data */
    public function persist(Model $model, array $data): void;

    public function extendQuery(Builder $query, Request $request): void;
}
