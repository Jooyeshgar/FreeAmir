<?php

namespace App\Extensions\Events;

use App\Models\FiscalYear;

final class FiscalYearCopied
{
    public function __construct(
        public readonly FiscalYear $source,
        public readonly FiscalYear $target,
    ) {}
}
