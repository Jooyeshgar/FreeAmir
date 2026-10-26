<?php

namespace App\View\Components;

use App\Extensions\Contracts\HostContext;
use App\Extensions\NavigationRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class ExtensionNavigation extends Component
{
    public function __construct(
        public readonly string $area,
        private readonly NavigationRegistry $registry,
        private readonly HostContext $hostContext,
    ) {}

    public function render(): View
    {
        $user = $this->hostContext->user();
        $context = ['user' => $user, 'fiscal_year_id' => $this->hostContext->activeFiscalYearId()];
        $items = array_filter($this->registry->items($this->area), static function (array $item) use ($user, $context): bool {
            if ($user === null || ! is_string($item['route'] ?? null) || ! Route::has($item['route'])) {
                return false;
            }

            if (! is_string($item['label'] ?? null) || $item['label'] === '') {
                return false;
            }

            if (isset($item['permission']) && ! $user->can($item['permission'])) {
                return false;
            }

            return ! isset($item['visible']) || ($item['visible'] instanceof Closure && ($item['visible'])($context));
        });

        return view('components.extension-navigation', ['items' => $items]);
    }
}
