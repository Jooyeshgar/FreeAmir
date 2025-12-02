<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SelectBox extends Component
{
    public ?string $url;

    public array $options;

    public string $class;

    public string $placeholder;

    public bool $selectableGroups;

    public bool $disabled;

    public ?string $hint;

    public ?string $hint2;

    public array $finalLocalOptions;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?string $url = null,
        array $options = [],
        string $class = '',
        string $placeholder = 'Select an option',
        bool $selectableGroups = false,
        bool $disabled = false,
        ?string $hint = null,
        ?string $hint2 = null
    ) {
        $this->url = $url;
        $this->options = $options;
        $this->class = $class;
        $this->placeholder = $placeholder;
        $this->selectableGroups = $selectableGroups;
        $this->disabled = $disabled;
        $this->hint = $hint;
        $this->hint2 = $hint2;

        $this->finalLocalOptions = $this->prepareLocalOptions();
    }

    /**
     * Prepare the local options with grouping.
     */
    protected function prepareLocalOptions(): array
    {
        $finalLocalOptions = [];

        foreach ($this->options as $index => $option) {
            $headerGroupOptions = $option['headerGroup'] ?? '';

            if (is_array($option)) {
                $groupedOptions = $this->groupOptionsByHeader($option, $headerGroupOptions);
            } else {
                $groupedOptions = [];
            }

            $finalLocalOptions[] = [
                'id' => 'local_'.$index,
                'headerGroup' => $headerGroupOptions,
                'options' => $groupedOptions,
            ];
        }

        return $finalLocalOptions;
    }

    /**
     * Group options by their header group.
     */
    protected function groupOptionsByHeader(array $option, string $headerGroupOptions): object
    {
        $groupedOptions = [];
        $items = $option['options'] ?? [];

        foreach ($items as $opt) {
            // Handle Eloquent model or Array
            $optObj = is_array($opt) ? (object) $opt : $opt;

            // Determine Group
            $property = $headerGroupOptions ? "{$headerGroupOptions}Group" : '';

            // Logic to find the group object
            if ($property && isset($optObj->$property)) {
                $group = $optObj->$property;
            } elseif (isset($optObj->group)) {
                $group = $optObj->group;
            } else {
                $group = (object) ['id' => 0, 'name' => 'General'];
            }

            if (! isset($groupedOptions[$group->id])) {
                $groupedOptions[$group->id] = [];
            }

            $groupedOptions[$group->id][] = [
                'id' => $optObj->id,
                'groupId' => $group->id,
                'groupName' => $group->name,
                'text' => $optObj->name ?? ($optObj->text ?? ''),
                'type' => $headerGroupOptions,
            ];
        }

        // Return object to preserve keys in JS
        return (object) $groupedOptions;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.select-box');
    }
}
