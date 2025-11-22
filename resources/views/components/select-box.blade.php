@props([
    'label' => null,
    'name' => '',
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => '',
    'searchUrl' => 'model-select.search',
    'model' => null,
    'labelField' => null,
    'limit' => 10,
    'orderBy' => '',
    'direction' => 'asc',
    'searchFields' => null,
    'hint' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $id = $id ?? 'select_' . md5($name . uniqid());

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

    $initialLabel = null;
    if ($selected !== null && $selected !== '') {
        foreach ($normalizedOptions as $opt) {
            if ((string)$opt['value'] === (string)$selected) {
                $initialLabel = $opt['label'];
                break;
            }
        }
        if (!$initialLabel && $searchUrl) $initialLabel = $selected; 
    }
    
    // Convert route name to URL if searchUrl is provided
    $searchUrlResolved = $searchUrl ? route($searchUrl) : null;
@endphp

<div
    class="form-control w-full"
    x-data="searchSelect({
        name: '{{ $name }}',
        options: {{ json_encode($normalizedOptions) }}, 
        selected: '{{ $selected }}',
        selectedLabel: '{{ $initialLabel }}',
        placeholder: '{{ $placeholder }}',
        searchUrl: '{{ $searchUrlResolved }}',
        emptyText: '{{ __('No results found') }}',
        labelField: '{{ $labelField }}',
        limit: '{{ $limit }}',
        orderBy: '{{ $orderBy }}',
        direction: '{{ $direction }}',
        searchFields: '{{ $searchFields }}',
        model: '{{ $model }}',
        disabled: '{{ $disabled }}'
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
                    @keydown="onKeydown($event)"
                    type="text" 
                    class="input input-sm input-bordered w-full"
                    placeholder="{{ __('Search') }}"
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
                                :class="{'active': option.value == selected, 'bg-primary/10': index === highlightedIndex}"
                                class="justify-between"
                                @mouseenter="highlightedIndex = index"
                            >
                                <span x-html="highlightMatch(option.label)"></span>
                                <span x-show="option.value == selected">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
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
            initialOptions: config.options,
            filteredOptions: [],
            isLoading: false,
            placeholder: config.placeholder,
            emptyText: config.emptyText,
            highlightedIndex: -1,
            minSearchLength: 2, // minimum characters for remote search

            init() {
                this.filteredOptions = this.initialOptions;
            },

            toggle() {
                if (config.disabled) return;
                if (this.open) return this.close();
                this.open = true;
                this.$nextTick(() => this.$refs.searchInput.focus());
            },

            close() {
                this.open = false;
                setTimeout(() => {
                    this.search = '';
                    this.filteredOptions = this.initialOptions;
                    this.highlightedIndex = -1;
                }, 200);
            },

            select(option) {
                if (!option) return;
                this.selected = option.value;
                this.selectedLabel = option.label;
                this.open = false;
            },

            handleSearch() {
                const query = this.search.trim().toLowerCase();

                if (!query) {
                    this.filteredOptions = this.initialOptions;
                    this.isLoading = false;
                    this.highlightedIndex = 0;
                    return;
                }

                // Filter locally first
                const localMatches = this.initialOptions.filter(opt => 
                    opt.label.toLowerCase().includes(query) || 
                    (opt.group && opt.group.toLowerCase().includes(query))
                );

                if (localMatches.length > 0) {
                    this.filteredOptions = localMatches;
                    this.isLoading = false;
                } 
                // Remote fetch if local matches empty and searchUrl exists
                else if (config.searchUrl && query.length >= this.minSearchLength) {
                    this.fetchRemoteOptions(query);
                } 
                else {
                    this.filteredOptions = [];
                }

                this.highlightedIndex = this.filteredOptions.length ? 0 : -1;
            },

            async fetchRemoteOptions(query) {
                this.isLoading = true;
                try {
                    // Build URL with query parameters
                    const url = new URL(config.searchUrl, window.location.origin);
                    url.searchParams.append('model', config.model);
                    url.searchParams.append('q', query);
                    url.searchParams.append('labelField', config.labelField);
                    url.searchParams.append('limit', config.limit);
                    url.searchParams.append('orderBy', config.orderBy);
                    url.searchParams.append('direction', config.direction);
                    url.searchParams.append('searchFields', config.searchFields);

                    console.log(url);


                    const response = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('خطایی رخ داده است.');

                    const data = await response.json();

                    this.filteredOptions = data.map(item => ({
                        value: item.value ?? item.id,
                        label: item.label ?? item.name ?? item.text,
                        group: item.group ?? null
                    }));

                    this.highlightedIndex = this.filteredOptions.length ? 0 : -1;

                } catch (error) {
                    console.error(error);
                    this.filteredOptions = [];
                    this.highlightedIndex = -1;
                } finally {
                    this.isLoading = false;
                }
            },

            highlightMatch(label) {
                if (!this.search) return label;
                const regex = new RegExp(`(${this.escapeRegex(this.search)})`, 'gi');
                return label.replace(regex, `<span class="bg-yellow-200">$1</span>`);
            },

            escapeRegex(str) {
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            },

            onKeydown(event) {
                if (!this.open) return;

                const maxIndex = this.filteredOptions.length - 1;

                switch(event.key) {
                    case 'ArrowDown':
                        event.preventDefault();
                        if (maxIndex < 0) break;
                        this.highlightedIndex = (this.highlightedIndex + 1) > maxIndex ? 0 : this.highlightedIndex + 1;
                        this.scrollToHighlighted();
                        break;

                    case 'ArrowUp':
                        event.preventDefault();
                        if (maxIndex < 0) break;
                        this.highlightedIndex = (this.highlightedIndex - 1) < 0 ? maxIndex : this.highlightedIndex - 1;
                        this.scrollToHighlighted();
                        break;

                    case 'Enter':
                        event.preventDefault();
                        if (this.highlightedIndex >= 0 && this.filteredOptions[this.highlightedIndex]) {
                            this.select(this.filteredOptions[this.highlightedIndex]);
                        }
                        break;

                    case 'Escape':
                        this.close();
                        break;
                }
            },

            scrollToHighlighted() {
                this.$nextTick(() => {
                    const menu = this.$el.querySelector('ul.menu');
                    const item = menu?.children[this.highlightedIndex];
                    if (item) {
                        const itemTop = item.offsetTop;
                        const itemBottom = itemTop + item.offsetHeight;
                        if (itemTop < menu.scrollTop) menu.scrollTop = itemTop;
                        else if (itemBottom > menu.scrollTop + menu.clientHeight) menu.scrollTop = itemBottom - menu.clientHeight;
                    }
                });
            }

        }));
    });
</script>
@endonce