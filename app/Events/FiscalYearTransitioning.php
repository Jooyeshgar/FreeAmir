<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;

final class FiscalYearTransitioning
{
    public function __construct(
        public readonly Model $source,
        public readonly Model $target,
    ) {}
}
