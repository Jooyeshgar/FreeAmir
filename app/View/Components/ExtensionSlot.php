<?php

namespace App\View\Components;

use App\Extensions\ViewSlotRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\Component;
use Throwable;
use UnexpectedValueException;

class ExtensionSlot extends Component
{
    public function __construct(
        private readonly ViewSlotRegistry $registry,
        public readonly string $slot = '',
        /** @var array<string, mixed> */
        public readonly array $context = [],
    ) {}

    public function render(): View
    {
        $items = [];
        $context = $this->context;

        foreach ($this->registry->items($this->slot) as $item) {
            try {
                // Missing views and null data opt out without affecting other items.
                if (! ViewFactory::exists($item['view'])) {
                    continue;
                }

                $data = $item['data'] !== null ? ($item['data'])($context) : [];
                if ($data === null) {
                    continue;
                }
                if (! is_array($data)) {
                    throw new UnexpectedValueException("View slot item [{$this->slot}.{$item['id']}] data must be an array or null.");
                }

                // Render within the boundary so Blade errors cannot break the host page.
                $items[] = $this->renderItem($item['view'], array_merge($context, $data));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('components.extension-slot', ['items' => $items]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderItem(string $view, array $data): string
    {
        // Laravel flushes factory state on view errors, including the parent's
        // component stack. Give each optional item its own rendering state.
        $hostFactory = ViewFactory::getFacadeRoot();
        $factory = clone $hostFactory;
        $factory->flushState();
        $factory->share('__env', $factory);

        ViewFactory::swap($factory);
        Component::forgetFactory();

        try {
            return $factory->make($view, $data)->render();
        } finally {
            ViewFactory::swap($hostFactory);
            Component::forgetFactory();
        }
    }
}
