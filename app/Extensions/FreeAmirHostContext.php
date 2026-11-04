<?php

namespace App\Extensions;

use App\Extensions\Contracts\HostContext;
use App\Models\FiscalYear;
use App\Services\FiscalYearMutationGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Config\Repository;

final class FreeAmirHostContext implements HostContext
{
    public function __construct(
        private readonly Factory $auth,
        private readonly Repository $config,
        private readonly FiscalYearMutationGuard $mutationGuard,
    ) {}

    public function user(): ?Authenticatable
    {
        return $this->auth->guard()->user();
    }

    public function activeFiscalYearId(): ?int
    {
        $id = $this->config->get('active-fiscal-year-id');

        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }

    public function authorizeFiscalYear(int $fiscalYearId): void
    {
        $user = $this->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($this->activeFiscalYearId() !== $fiscalYearId || ! FiscalYear::query()
            ->whereKey($fiscalYearId)
            ->whereHas('users', fn ($query) => $query->whereKey($user->getAuthIdentifier()))
            ->exists()) {
            throw new AuthorizationException;
        }
    }

    public function ensureFiscalYearOpen(int $fiscalYearId): void
    {
        $this->authorizeFiscalYear($fiscalYearId);
        $this->mutationGuard->ensureOpen($fiscalYearId);
    }
}
