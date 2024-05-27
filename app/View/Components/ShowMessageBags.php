<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Session;
use Illuminate\View\Component;

class ShowMessageBags extends Component
{
    public function __construct(
        public string $type = 'errors'
    ) {
    }

    public function render()
    {
        $types = ['success', 'warning', 'errors', 'info'];
        $class = ['success' => 'success', 'warning' => 'warning', 'errors' => 'error', 'info' => 'info'];

        $msgs = collect();

        foreach ($types as $type) {
            $messages = Session::get($type);
            if ($messages) {
                // Handle MessageBag
                if ($messages instanceof \Illuminate\Support\ViewErrorBag) {
                    $messages = collect($messages->getMessages())->flatten()->all();
                }

                // Wrap single string in array
                if (is_string($messages)) {
                    $messages = [$messages];
                }

                $msgs->push([
                    'type' => $class[$type],
                    'message' => $messages,
                ]);

                Session::forget($type);
            }
        }

        return view('components.show-message-bags', ['messages' => $msgs]);
    }
}
