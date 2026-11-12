<?php

namespace App\Extensions\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface HostContext
{
    public function user(): ?Authenticatable;

    public function activeFiscalYearId(): ?int;

    /** Authorize access to the selected fiscal year, including user membership. */
    public function authorizeFiscalYear(int $fiscalYearId): void;

    /** Reject a mutation when the fiscal year is closed or cannot be resolved. */
    public function ensureFiscalYearOpen(int $fiscalYearId): void;
}
