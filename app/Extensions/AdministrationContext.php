<?php

namespace App\Extensions;

use App\Extensions\Contracts\AdminPanel;

class AdministrationContext
{
    public function panel(): ?AdminPanel
    {
        if (! app()->bound(AdminPanel::class)) {
            return null;
        }

        $panel = app(AdminPanel::class);

        return $panel->enabledFor(auth()->user()) ? $panel : null;
    }

    public function isGlobal(): bool
    {
        return $this->panel() !== null && request()->routeIs('management.*');
    }
}
