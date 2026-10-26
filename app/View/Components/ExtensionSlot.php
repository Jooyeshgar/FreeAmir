<?php

namespace App\View\Components;

use App\Extensions\ViewSlotRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\Component;

class ExtensionSlot extends Component
{
    public function __construct(
        private readonly ViewSlotRegistry $registry,
        public readonly string $slot = '',
        public readonly ?Model $invoice = null,
    ) {}

    public function render(): View
    {
        $items = [];
        foreach ($this->registry->items($this->slot) as $item) {
            $data = $item['data'] instanceof Closure ? ($item['data'])(['invoice' => $this->invoice]) : [];
            // Null data is a package opting out for this request, and a missing
            // view must never break the host page; both are skipped silently.
            if ($data === null || ! ViewFactory::exists($item['view'])) {
                continue;
            }

            $items[] = ['view' => $item['view'], 'data' => $data];
        }

        return view('components.extension-slot', ['items' => $items]);
    }
}
