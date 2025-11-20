@props([
    'label' => null,
    'name' => '',
    'id' => null,
    'options' => [],       // The "Little" list (e.g., top 10 or recently used)
    'selected' => null,
    'placeholder' => 'Select an option',
    'searchUrl' => null,   // The URL to hit if local search fails
    'hint' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $id = $id ?? 'select_' . md5($name . uniqid());

    // 1. Normalize PHP Options into JavaScript-friendly format
    $normalizedOptions = [];
    $formatOption = fn($k, $v, $g = null) => ['value' => (string)$k, 'label' => $v, 'group' => $g];

    foreach ($options as $key => $value) {
        if (is_iterable($value)) {
            foreach ($value as $subKey => $subValue) {
                $normalizedOptions[] = $formatOption($subKey, $subValue, $key);
            }
        } else {
            $normalizedOptions[] = $formatOption($key, $value);
        }
    }

    // 2. Handle Initial Label
    $initialLabel = null;
    if ($selected !== null && $selected !== '') {
        foreach ($normalizedOptions as $opt) {
            if ((string)$opt['value'] === (string)$selected) {
                $initialLabel = $opt['label'];
                break;
            }
        }
        // If selected value exists but isn't in the "little" local list, 
        // display the value or handle via extra prop if needed.
        if (!$initialLabel && $searchUrl) $initialLabel = $selected; 
    }
@endphp

<div
    class="form-control w-full"
    x-data="searchSelect({
        name: '{{ $name }}',
        options: {{ json_encode($normalizedOptions) }}, 
        selected: '{{ $selected }}',
        selectedLabel: '{{ $initialLabel }}',
        placeholder: '{{ $placeholder }}',
        searchUrl: '{{ $searchUrl }}',
        emptyText: '{{ __('No results found') }}'
    })"
    x-init="init()"
    @click.outside="close()"
>
    {{-- Label --}}
    @if($label)
        <label for="{{ $id }}" class="label">
            <span class="label-text font-semibold">
                {!! $label !!}
                @if($required) <span class="text-error">*</span> @endif
            </span>
        </label>
    @endif

    <div class="relative">
        <input type="hidden" name="{{ $name }}" :value="selected">

        {{-- Trigger Button --}}
        <button 
            type="button" 
            id="{{ $id }}"
            @click="toggle()"
            :class="{'btn-disabled': {{ $disabled ? 'true' : 'false' }}}"
            class="input input-bordered w-full text-left flex items-center justify-between px-4 bg-base-100 focus:outline-none focus:border-primary"
        >
            <span 
                x-text="selectedLabel ? selectedLabel : placeholder" 
                :class="{'text-base-content': selectedLabel, 'text-gray-400': !selectedLabel}"
                class="block truncate"
            ></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </button>

        {{-- Dropdown --}}
        <div 
            x-show="open" 
            x-transition.opacity.duration.200ms
            class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden"
            style="display: none;"
        >
            {{-- Search Input --}}
            <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
                <input 
                    x-ref="searchInput"
                    x-model="search"
                    @input.debounce.400ms="handleSearch()"
                    type="text" 
                    class="input input-sm input-bordered w-full"
                    placeholder="{{ __('Search...') }}"
                >
            </div>

            {{-- Options List --}}
            <ul class="menu menu-compact w-full overflow-y-auto flex-1 p-1">
                
                {{-- Loading Spinner --}}
                <li x-show="isLoading" class="pointer-events-none">
                    <a class="flex justify-center gap-2">
                        <span class="loading loading-spinner loading-xs"></span>
                        <span class="text-xs opacity-50">Searching server...</span>
                    </a>
                </li>

                {{-- No Results --}}
                <li x-show="!isLoading && filteredOptions.length === 0" class="pointer-events-none">
                    <a class="text-gray-500 italic text-sm" x-text="emptyText"></a>
                </li>

                {{-- Options --}}
                <template x-for="(option, index) in filteredOptions" :key="option.value">
                    <div class="contents">
                        {{-- Group Header --}}
                        <template x-if="option.group && (index === 0 || filteredOptions[index - 1].group !== option.group)">
                            <li class="menu-title opacity-70 mt-2 first:mt-0">
                                <span x-text="option.group"></span>
                            </li>
                        </template>

                        {{-- Option --}}
                        <li>
                            <a 
                                @click="select(option)" 
                                :class="{'active': option.value == selected}"
                                class="justify-between"
                            >
                                <span x-text="option.label"></span>
                                <span x-show="option.value == selected">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                </span>
                            </a>
                        </li>
                    </div>
                </template>
            </ul>
        </div>
    </div>

    @if($hint)
        <label class="label">
            <span class="label-text-alt text-gray-500">{!! $hint !!}</span>
        </label>
    @endif
</div>

@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('searchSelect', (config) => ({
            open: false,
            search: '',
            selected: config.selected,
            selectedLabel: config.selectedLabel,
            initialOptions: config.options, // The "Little" list
            filteredOptions: [],
            isLoading: false,
            placeholder: config.placeholder,
            emptyText: config.emptyText,

            init() {
                // Start with the initial "little" list
                this.filteredOptions = this.initialOptions;
            },

            toggle() {
                if (this.open) return this.close();
                this.open = true;
                this.$nextTick(() => this.$refs.searchInput.focus());
            },

            close() {
                this.open = false;
                // Optional: Reset to "little list" when closed
                setTimeout(() => {
                    this.search = '';
                    this.filteredOptions = this.initialOptions;
                }, 200);
            },

            select(option) {
                this.selected = option.value;
                this.selectedLabel = option.label;
                this.open = false;
            },

            handleSearch() {
                // 1. If search is empty, show the initial "little" list
                if (this.search === '') {
                    this.filteredOptions = this.initialOptions;
                    this.isLoading = false;
                    return;
                }

                const q = this.search.toLowerCase();

                // 2. FILTER LOCALLY FIRST
                // Check if the initial options contain the search term
                const localMatches = this.initialOptions.filter(opt => 
                    opt.label.toLowerCase().includes(q) || 
                    (opt.group && opt.group.toLowerCase().includes(q))
                );

                // 3. DECIDE: Local Results OR Ajax?
                if (localMatches.length > 0) {
                    // We found matches in the "little" list, show them.
                    this.filteredOptions = localMatches;
                    this.isLoading = false;
                } else {
                    // No matches locally? Check if we have a URL to ask the server.
                    if (config.searchUrl) {
                        this.fetchRemoteOptions();
                    } else {
                        this.filteredOptions = [];
                    }
                }
            },

            async fetchRemoteOptions() {
                this.isLoading = true;
                // Clear current options while searching to avoid confusion, 
                // or keep them visible until new data arrives (user preference).
                // this.filteredOptions = []; 

                try {
                    const url = new URL(config.searchUrl, window.location.origin);
                    url.searchParams.append('q', this.search);

                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    if (!response.ok) throw new Error('Failed to load');
                    
                    const data = await response.json();

                    // Normalize server response
                    this.filteredOptions = data.map(item => ({
                        value: item.value || item.id,
                        label: item.label || item.name || item.text,
                        group: item.group || item.group_name || null
                    }));

                } catch (error) {
                    console.error(error);
                    this.filteredOptions = [];
                } finally {
                    this.isLoading = false;
                }
            }
        }));
    });
</script>
@endonce