@props([
    'options' => [],
    'label' => null,
    'placeholder' => '',
    'hint' => null,
    'icon' => null,
    'selected' => null,
    'id' => uniqid(),
])

<div class="w-full">
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
        </label>
    @endif

    <div class="relative" x-data="selectSearch()" wire:ignore>
        @if($icon)
            <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                <i class="{{ $icon }}"></i>
            </span>
        @endif

        <select
            x-ref="select"
            id="{{ $id }}"
            {{ $attributes->merge(['class' => ($icon ? 'pl-8' : '')]) }}
        >
            @foreach($options as $group => $opts)
                @if(is_iterable($opts))
                    <optgroup label="{{ $group }}">
                        @foreach($opts as $value => $text)
                            <option value="{{ $value }}" @selected($value == $selected)>
                                {{ $text }}
                            </option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $group }}" @selected($group == $selected)>
                        {{ $opts }}
                    </option>
                @endif
            @endforeach
        </select>
    </div>

    @if($hint)
        <p class="text-xs text-gray-500 mt-1">{!! $hint !!}</p>
    @endif
</div>

@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <script>
        function selectSearch() {
            return {
                choices: null,

                init() {
                    this.choices = new Choices(this.$refs.select, {
                        searchEnabled: true,
                        itemSelectText: '',
                        removeItemButton: true,
                        shouldSort: false,
                        placeholderValue: this.$refs.select?.getAttribute('placeholder') ?? '',
                    });

                    this.$refs.select.addEventListener('change', () => {
                        this.$dispatch('input', this.choices.getValue(true));
                    });
                }
            }
        }
    </script>

    <style>
        .choices {
            @apply w-full;
        }

        .choices__inner {
            @apply bg-white border border-gray-300 rounded-lg shadow-sm
                   px-3 py-2 text-sm min-h-[42px] flex items-center
                   focus-within:ring-1 focus-within:ring-blue-500 focus-within:border-blue-500;
        }

        .choices__list--dropdown {
            @apply mt-1 bg-white border border-gray-300 rounded-lg shadow-lg;
        }

        .choices__list--dropdown .choices__item {
            @apply text-sm px-3 py-2 hover:bg-gray-100 cursor-pointer;
        }

        .choices__input {
            @apply bg-transparent text-sm px-1;
        }

        .choices__button {
            @apply !mt-0 !mr-1 text-gray-500;
        }
    </style>
@endonce
