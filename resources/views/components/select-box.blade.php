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
    'showCode' => false,
    'codeField' => 'code',
])

@php
$normalizedOptions = [];
    // helper: basic formatting for scalar id => label pairs
    $formatBasic = function ($k, $v, $group = null, $code = null) {
        return [
            'value' => (string) $k,
            'label' => (string) $v,
            'group' => $group ? ['label' => (string)$group, 'code' => null] : null,
            'code' => $code,
        ];
    };

    // Helper to extract subject information from model
    $extractSubjectInfo = function ($modelId) use ($model) {
        if (!$model || !$modelId) {
            return ['group' => null, 'code' => null];
        }

        try {
            $modelClass = "App\\Models\\{$model}";
            if (!class_exists($modelClass)) {
                return ['group' => null, 'code' => null];
            }

            $instance = $modelClass::with('subject.parent')->find($modelId);
            if (!$instance || !$instance->subject) {
                return ['group' => null, 'code' => null];
            }

            $subject = $instance->subject;
            $itemCode = $subject->code ? formatCode($subject->code) : null;
            
            $group = null;
            if ($subject->parent) {
                $group = [
                    'label' => $subject->parent->name,
                    'code' => $subject->parent->code ? formatCode($subject->parent->code) : null,
                ];
            }

            return ['group' => $group, 'code' => $itemCode];
        } catch (\Exception $e) {
            return ['group' => null, 'code' => null];
        }
    };

    foreach ($options as $key => $value) {
        // Structured option detection
        if (is_array($value) && (array_key_exists('value', $value) || array_key_exists('label', $value))) {
        $group = null;
        if (!empty($value['group'])) {
            if (is_array($value['group'])) {
                $group = [
                'label' => $value['group']['label'] ?? $value['group']['name'] ?? null,
                'code' => $value['group']['code'] ?? null,
                ];
            } else {
                $group = ['label' => (string)$value['group'], 'code' => $value['group_code'] ?? null];
        }
    }


    $normalizedOptions[] = [
            'value' => (string) ($value['value'] ?? $key),
            'label' => $value['label'] ?? $value['name'] ?? $value['text'] ?? (string) ($value['value'] ?? $key),
            'group' => $group,
            'code' => $value['code'] ?? null,
        ];
        continue;
    }


    // Group handling
    if (is_iterable($value)) {
        foreach ($value as $subKey => $subValue) {
        if (is_array($subValue) && (array_key_exists('value', $subValue) || array_key_exists('label', $subValue))) {
            $group = ['label' => (string)$key, 'code' => $subValue['group_code'] ?? null];


            $normalizedOptions[] = [
                'value' => (string) ($subValue['value'] ?? $subKey),
                'label' => $subValue['label'] ?? $subValue['name'] ?? $subValue['text'] ?? (string) ($subValue['value'] ?? $subKey),
                'group' => $group,
                'code' => $subValue['code'] ?? null,
            ];
        } else {
            $normalizedOptions[] = $formatBasic($subKey, $subValue, $key);
        }
    }
    } else {
        // For simple key-value pairs with a model, extract subject info
        $subjectInfo = $extractSubjectInfo($key);
        $option = [
            'value' => (string) $key,
            'label' => (string) $value,
            'group' => $subjectInfo['group'],
            'code' => $subjectInfo['code'],
        ];
        $normalizedOptions[] = $option;
        }
    }

    // Selected label resolution
    $initialLabel = null;
    if ($selected !== null && $selected !== '') {
        foreach ($normalizedOptions as $opt) {
            if ((string)$opt['value'] === (string)$selected) {
                $initialLabel = $opt['label'];
                break;
            }
        }
        if (!$initialLabel && $searchUrl) {
            $initialLabel = $selected;
        }
    }

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
        disabled: '{{ $disabled }}',
        showCode: '{{ $showCode ? 'true' : 'false' }}',
        codeField: '{{ $codeField }}'
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
            <ul class="flex flex-col menu-compact w-full overflow-y-auto overflow-x-auto p-1">
            
                {{-- Loading Spinner --}}
                <li x-show="isLoading" class="pointer-events-none">
                    <a class="flex justify-center gap-2">
                        <span class="loading loading-spinner loading-xs"></span>
                        <span class="text-xs opacity-50">{{ __('Searching') }}</span>
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
                        <template x-if="option.group && (index === 0 || !filteredOptions[index - 1].group || filteredOptions[index - 1].group.label !== option.group.label)">
                            <li class="menu-title opacity-70 mt-2 first:mt-0">
                                <span class="flex items-center gap-2">
                                    <span x-text="option.group.label"></span>
                                    <template x-if="option.group && option.group.code">
                                        <span x-text="option.group.code"></span>
                                    </template>
                                </span>                     
                            </li>
                        </template>

                        {{-- Option --}}
                        <li>
                            <a 
                                @click="select(option)" 
                                :class="{'active': option.value == selected, 'bg-primary/10': index === highlightedIndex}"
                                class="block overflow-hidden p-1"
                                @mouseenter="highlightedIndex = index"
                            >
                                <div class="w-full">
                                    <!-- Label -->
                                    <div class="flex items-center justify-between gap-2">
                                        <span x-html="highlightMatch(option.label)" class="font-medium truncate"></span>
                                        <span x-show="option.value == selected" class="flex-shrink-0">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                        <!-- Code -->
                                        <template x-if="option.code">
                                            <span x-text="option.code" class="text-xs opacity-60 font-mono"></span>
                                        </template>
                                    </div>
                                </div>
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
            minSearchLength: 1, // minimum characters for remote search

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
                    (opt.group && opt.group.label && opt.group.label.toLowerCase().includes(query)) ||
                    (opt.group && opt.group.code && opt.group.code.toLowerCase().includes(query)) ||
                    (opt.code && opt.code.toLowerCase().includes(query))
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
                        group: item.group ?? null,
                        code: item[config.codeField] ?? item.code ?? null
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