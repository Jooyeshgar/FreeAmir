<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class SubjectSelect extends Component
{
    public string $url = '';

    public string $title = '';

    public string $placeholder = '';

    public bool $disabled = false;

    public string $class = '';

    public array $finalLocalOptions = [];

    public function __construct(
        string $url = '',
        string $title = '',
        string $placeholder = '',
        bool $disabled = false,
        string $class = '',
        array|Collection|null $subjects = null,
    ) {
        $this->url = $url ?: route('subjects.search');
        $this->title = $title;
        $this->placeholder = __($placeholder);
        $this->disabled = $disabled;
        $this->class = $class;
        $this->finalLocalOptions = is_array($subjects) ? $subjects : $subjects->toArray();
    }

    public function render(): View
    {
        return view('components.subject-select');
    }
}
