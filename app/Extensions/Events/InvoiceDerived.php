<?php

namespace App\Extensions\Events;

use App\Models\Invoice;

final class InvoiceDerived
{
    public function __construct(
        public readonly Invoice $source,
        public readonly Invoice $target,
    ) {}
}
