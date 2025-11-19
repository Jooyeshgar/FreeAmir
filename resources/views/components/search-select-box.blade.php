<!-- The Search Select Box Component created with daisyUI, Alpine.js and tailwindcss. it should be a laravel blade component.

    The Component must have the following future:

    - Searchable options
    - grouped options
    - title/label
    - name and id
    - selected value
    - placeholder
    - Ajax querying for when the search text is not in the current options 
    - hint as HTML code


    And it should not use any package.
    -->


@props([
    'label' => null,
    'name' => '',
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option',
    'searchUrl' => null,
    'hint' => null,
])

@php
    $id = $id ?? $name . '_' . uniqid();

    // Normalize Options into a consistent JavaScript-friendly format:
    // [{ value: '1', label: 'Text', group: 'Group Name' }]
    $normalizedOptions = [];
    
    // Helper to format a single option
    $formatOption = function($key, $value, $group = null) {
        return ['value' => (string)$key, 'label' => $value, 'group' => $group];
    };

    foreach ($options as $key => $value) {
        if (is_iterable($value)) {
            // Handle Grouped Options
            foreach ($value as $subKey => $subValue) {
                $normalizedOptions[] = $formatOption($subKey, $subValue, $key);
            }
        } else {
            // Handle Single Options
            $normalizedOptions[] = $formatOption($key, $value);
        }
    }

    // Determine initial label if a value is selected
    $initialLabel = null;
    if ($selected !== null) {
        foreach ($normalizedOptions as $opt) {
            if ($opt['value'] == $selected) {
                $initialLabel = $opt['label'];
                break;
            }
        }
        // If using Ajax and we have a value but no options loaded yet, 
        // you might pass the label as a separate prop or handle it differently.
        // Here we fallback to the selected value if label not found (common in Ajax edit forms).
        if (!$initialLabel && $searchUrl) {
            $initialLabel = $selected; 
        }
    }
@endphp

<div 
    class="form-control w-full"
    x-data="searchSelect({
        options: {{ json_encode($normalizedOptions) }},
        selected: '{{ $selected }}',
        selectedLabel: '{{ $initialLabel }}',
        searchUrl: '{{ $searchUrl }}',
        placeholder: '{{ $placeholder }}',
        name: '{{ $name }}'
    })"
    x-init="init()"
    @click.outside="closeDropdown()"
>
    <!-- Label -->
    @if($label)
        <label for="{{ $id }}" class="label">
            <span class="label-text">{{ $label }}</span>
        </label>
    @endif

    <!-- Trigger / Fake Select Box -->
    <div class="relative">

        <button type="button" 
            @click="toggleDropdown()" 
            :class="{'input-active': open}"
            class="input input-bordered w-full text-left font-normal flex items-center justify-between cursor-pointer"
            id="{{ $id }}">

            <span x-text="selectedLabel ? selectedLabel : placeholder" :class="{'text-base-content': selectedLabel, 'text-gray-400': !selectedLabel}"></span>
            
            <!-- Your Chevron Icon -->
            <svg class="w-4 h-4 ml-2 transition-transform" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>

        </button>

        <!-- Dropdown Content -->
        <div 
            x-show="open" 
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 shadow-lg rounded-box max-h-64 flex flex-col"
            style="display: none;"
        >
            <!-- Search Input -->
            <div class="p-2 sticky top-0 bg-base-100 z-10 border-b border-base-200">
                <input 
                    type="text" 
                    x-ref="searchInput"
                    x-model="search"
                    @input.debounce.300ms="performSearch()"
                    class="input input-sm input-bordered w-full"
                    placeholder="Search..."
                >
            </div>

            <!-- Options List -->
            <ul class="menu w-full overflow-y-auto flex-1 p-2 space-y-1">
                
                <!-- Loading State -->
                <li x-show="isLoading" class="disabled">
                    <a><span class="loading loading-spinner loading-sm"></span> Loading...</a>
                </li>

                <!-- No Results -->
                <li x-show="!isLoading && filteredOptions.length === 0" class="disabled">
                    <a class="text-gray-500">{{ __('No results found') }}</a>
                </li>

                <!-- Options Loop -->
                <template x-for="(option, index) in filteredOptions" :key="option.value">
                    <!-- Wrap in a fragment logic for grouping headers -->
                    <div class="contents">
                        <!-- Group Header: Show if it's the first item OR group changed from previous -->
                        <template x-if="option.group && (index === 0 || filteredOptions[index - 1].group !== option.group)">
                            <li class="menu-title opacity-70 mt-2 first:mt-0">
                                <span x-text="option.group"></span>
                            </li>
                        </template>

                        <!-- Option Item -->
                        <li>
                            <a 
                                @click="selectOption(option)" 
                                :class="{'active': option.value == selected}"
                                class="justify-between"
                            >
                                <span x-text="option.label"></span>
                                <!-- Checkmark for selected -->
                                <span x-show="option.value == selected">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </a>
                        </li>
                    </div>
                </template>
            </ul>
        </div>
    </div>

    <!-- Hidden Actual Input -->
    <input type="hidden" name="{{ $name }}" :value="selected">

    <!-- Hint -->
    @if($hint)
        <div class="label">
            <span class="label-text-alt text-gray-500">{!! $hint !!}</span>
        </div>
    @endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('searchSelect', (config) => ({
            open: false,
            search: '',
            selected: config.selected,
            selectedLabel: config.selectedLabel,
            options: config.options,
            filteredOptions: [],
            isLoading: false,
            
            init() {
                // Initialize filtered options with all options initially
                this.filteredOptions = this.options;
            },

            toggleDropdown() {
                this.open = !this.open;
                if (this.open) {
                    this.$nextTick(() => this.$refs.searchInput.focus());
                    // Reset search view on open if using static options
                    if(!config.searchUrl) {
                        this.search = '';
                        this.filteredOptions = this.options;
                    }
                }
            },

            closeDropdown() {
                this.open = false;
            },

            selectOption(option) {
                this.selected = option.value;
                this.selectedLabel = option.label;
                this.open = false;
                this.search = ''; // Optional: clear search after select
            },

            performSearch() {
                if (config.searchUrl) {
                    this.fetchOptions();
                } else {
                    this.filterLocalOptions();
                }
            },

            filterLocalOptions() {
                if (this.search === '') {
                    this.filteredOptions = this.options;
                    return;
                }

                const lowerSearch = this.search.toLowerCase();
                this.filteredOptions = this.options.filter(opt => {
                    return opt.label.toLowerCase().includes(lowerSearch) || 
                           (opt.group && opt.group.toLowerCase().includes(lowerSearch));
                });
            },

            async fetchOptions() {
                this.isLoading = true;
                try {
                    // Example URL structure: /search?q=term
                    const url = new URL(config.searchUrl, window.location.origin);
                    url.searchParams.append('q', this.search);

                    const response = await fetch(url);
                    if (!response.ok) throw new Error('Network response was not ok');
                    
                    const data = await response.json();
                    
                    // Expecting server to return Array of {value, label, group(optional)}
                    // Normalize just in case
                    this.filteredOptions = data.map(item => ({
                        value: item.value || item.id, // fallback to id
                        label: item.label || item.name, // fallback to name
                        group: item.group || null
                    }));

                } catch (error) {
                    console.error('Search failed:', error);
                    this.filteredOptions = [];
                } finally {
                    this.isLoading = false;
                }
            }
        }));
    });
</script>