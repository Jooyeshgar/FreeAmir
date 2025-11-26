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
    'selectableGroups' => false,
])

@php
    $normalizedOptions = [];
    // Helper: basic formatting for scalar id => label pairs
    $formatBasic = function ($k, $v, $group = null, $code = null) {
        return [
            'value' => (string) $k,
            'label' => (string) $v,
            'group' => $group ? ['label' => (string)$group, 'code' => null, 'value' => null] : null,
            'code' => $code ?? null,
        ];
    };

    // Helper to build hierarchy path
    $buildHierarchyPath = function ($subject) use ($selectableGroups) {
        $path = [];
        $current = $subject;
        
        while ($current && isset($current->parent_id) && $current->parent_id) {
            if (!$current->relationLoaded('parent')) {
                $current->load('parent');
            }
            
            $parent = $current->parent;
            
            if ($parent) {
                $path[] = [
                    'label' => $parent->name,
                    'code' => isset($parent->code) ? $parent->code : null,
                    'value' => $selectableGroups ? (string)$parent->id : null,
                ];
                $current = $parent;
            } else {
                break;
            }
        }
        return array_reverse($path); 
    };

    // Helper to extract subject information from model
    $extractSubjectInfo = function ($modelId) use ($model, $buildHierarchyPath, $selectableGroups) {
        if (!$model || !$modelId) {
            return ['group' => null, 'code' => null, 'hierarchy' => []];
        }

        try {
            $modelClass = "App\\Models\\{$model}";
            if (!class_exists($modelClass)) {
                return ['group' => null, 'code' => null, 'hierarchy' => []];
            }

            // Handle Subject model specifically
            if ($model === 'Subject') {
                $instance = $modelClass::with('parent')->find($modelId);
                if (!$instance) {
                    return ['group' => null, 'code' => null, 'hierarchy' => []];
                }

                $itemCode = isset($instance->code) ? $instance->code : null;
                $hierarchy = $buildHierarchyPath($instance);
                
                $group = null;
                if ($instance->parent) {
                    $group = [
                        'label' => $instance->parent->name,
                        'code' => isset($instance->parent->code) ? $instance->parent->code : null,
                        'value' => $selectableGroups ? (string)$instance->parent->id : null,
                    ];
                }

                return ['group' => $group, 'code' => $itemCode, 'hierarchy' => $hierarchy];
            }

            // Handle models that have a subject relationship
            $instance = $modelClass::with('subject.parent')->find($modelId);
            if (!$instance || !$instance->subject) {
                return ['group' => null, 'code' => null, 'hierarchy' => []];
            }

            $subject = $instance->subject;
            $itemCode = isset($subject->code) ? $subject->code : null;
            $hierarchy = $buildHierarchyPath($subject);
            
            $group = null;
            if ($subject->parent) {
                $group = [
                    'label' => $subject->parent->name,
                    'code' => isset($subject->parent->code) ? $subject->parent->code : null,
                    'value' => $selectableGroups ? (string)$subject->parent->id : null,
                ];
            }

            return ['group' => $group, 'code' => $itemCode, 'hierarchy' => $hierarchy];
        } catch (\Exception $e) {
            return ['group' => null, 'code' => null, 'hierarchy' => []];
        }
    };

    foreach ($options as $key => $value) {
        if (is_array($value) && (array_key_exists('value', $value) || array_key_exists('label', $value))) {
            $group = null;
            if (!empty($value['group'])) {
                if (is_array($value['group'])) {
                    $group = [
                        'label' => $value['group']['label'] ?? $value['group']['name'] ?? null,
                        'code' => $value['group']['code'] ?? null,
                        'value' => $value['group']['value'] ?? $value['group']['id'] ?? null,
                    ];
                } else {
                    $group = [
                        'label' => (string)$value['group'], 
                        'code' => $value['group_code'] ?? null, 
                        'value' => null
                    ];
                }
            }

            $normalizedOptions[] = [
                'value' => (string) ($value['value'] ?? $key),
                'label' => $value['label'] ?? $value['name'] ?? $value['text'] ?? (string) ($value['value'] ?? $key),
                'group' => $group,
                'code' => isset($value['code']) ? $value['code'] : null,
                'hierarchy' => $value['hierarchy'] ?? [],
            ];
            continue;
        }

        if (is_iterable($value)) {
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue) && (array_key_exists('value', $subValue) || array_key_exists('label', $subValue))) {
                    $group = [
                        'label' => (string)$key, 
                        'code' => $subValue['group_code'] ?? null,
                        'value' => null 
                    ];

                    $normalizedOptions[] = [
                        'value' => (string) ($subValue['value'] ?? $subKey),
                        'label' => $subValue['label'] ?? $subValue['name'] ?? $subValue['text'] ?? (string) ($subValue['value'] ?? $subKey),
                        'group' => $group,
                        'code' => isset($subValue['code']) ? $subValue['code'] : null,
                        'hierarchy' => $subValue['hierarchy'] ?? [],
                    ];
                } else {
                    $normalizedOptions[] = array_merge($formatBasic($subKey, $subValue, $key), ['hierarchy' => []]);
                }
            }
        } else {
            $subjectInfo = $extractSubjectInfo($key);
            $option = [
                'value' => (string) $key,
                'label' => (string) $value,
                'group' => $subjectInfo['group'],
                'code' => $subjectInfo['code'],
                'hierarchy' => $subjectInfo['hierarchy'],
            ];
            $normalizedOptions[] = $option;
        }
    }

    $initialLabel = null;
    if ($selected !== null && $selected !== '') {
        foreach ($normalizedOptions as $opt) {
            if ((string)$opt['value'] === (string)$selected) {
                $initialLabel = $opt['label'];
                break;
            }
            if ($selectableGroups) {
                if (isset($opt['group']['value']) && (string)$opt['group']['value'] === (string)$selected) {
                    $initialLabel = $opt['group']['label'];
                    break;
                }
                if (!empty($opt['hierarchy'])) {
                    foreach ($opt['hierarchy'] as $hItem) {
                        if (isset($hItem['value']) && (string)$hItem['value'] === (string)$selected) {
                            $initialLabel = $hItem['label'];
                            break 2; 
                        }
                    }
                }
            }
        }
        if (!$initialLabel && $searchUrl) {
            $initialLabel = $selected; // Fallback to showing ID if loading
        }
    }


    $searchUrlResolved = $searchUrl ? route($searchUrl) : null;
@endphp

<div
    class="form-control w-full"
    x-data="searchSelect({
        name: '{{ $name }}',
        options: @js($normalizedOptions), 
        selected: @js($selected),
        selectedLabel: @js($initialLabel),
        placeholder: @js($placeholder),
        searchUrl: '{{ $searchUrlResolved }}',
        emptyText: '{{ __('No results found') }}',
        labelField: '{{ $labelField }}',
        limit: '{{ $limit }}',
        orderBy: '{{ $orderBy }}',
        direction: '{{ $direction }}',
        searchFields: '{{ $searchFields }}',
        model: '{{ $model }}',
        disabled: {{ $disabled ? 'true' : 'false' }},
        showCode: {{ $showCode ? 'true' : 'false' }},
        codeField: '{{ $codeField }}',
        selectableGroups: {{ $selectableGroups ? 'true' : 'false' }}
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
            :disabled="disabled"
            :class="{'btn-disabled opacity-60 cursor-not-allowed': disabled}"
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
            class="absolute z-[100] w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden"
            style="display: none;"
        >
            {{-- Search Input --}}
            <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
                <input 
                    x-ref="searchInput"
                    x-model="search"
                    @input.debounce.300ms="handleSearch()"
                    @keydown="onKeydown($event)"
                    type="text" 
                    class="input input-sm input-bordered w-full"
                    placeholder="{{ __('Search') }}"
                >
            </div>

            {{-- Options List --}}
            <ul class="flex flex-col menu-compact w-full overflow-y-auto p-1 scroll-p-2" x-ref="optionsList">
            
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
                <template x-for="(option, index) in filteredOptions" :key="option.value + '-' + index">
                    <div class="contents">
                        {{-- Hierarchy Group Headers --}}
                        <template x-for="(headerLevel, levelIdx) in getHierarchyHeadersToShow(option, index)" :key="'header-' + index + '-' + levelIdx">
                            <li class="mt-0.5 first:mt-0">
                                <a 
                                    x-show="selectableGroups && headerLevel.value"
                                    @click="selectGroup(headerLevel)"
                                    :class="{'active': headerLevel.value == selected}"
                                    class="flex items-center gap-1 cursor-pointer hover:bg-base-200/50 rounded-md py-1"
                                >
                                    {{-- Use _level for correct indentation based on actual hierarchy depth --}}
                                    <span class="flex items-center gap-1 w-full" :style="'padding-inline-start: ' + ((headerLevel._level ?? levelIdx) * 0.75) + 'rem'">
                                        <span class="opacity-50 text-xs font-bold">›</span>
                                        <span x-text="headerLevel.label" class="text-sm font-semibold opacity-80"></span>
                                        <template x-if="showCode && headerLevel.code">
                                            <span x-text="$store.utils.formatCode(headerLevel.code)" class="text-xs font-mono opacity-60"></span>
                                        </template>
                                    </span>
                                </a>
                                <span 
                                    x-show="!selectableGroups || !headerLevel.value"
                                    class="flex items-center gap-1 py-1 hover:bg-transparent cursor-default"
                                >
                                    <span class="flex items-center gap-1 w-full" :style="'padding-inline-start: ' + ((headerLevel._level ?? levelIdx) * 0.75) + 'rem'">
                                        <span class="opacity-50 text-xs font-bold">›</span>
                                        <span x-text="headerLevel.label" class="text-sm font-semibold opacity-80"></span>
                                        <template x-if="showCode && headerLevel.code">
                                            <span x-text="$store.utils.formatCode(headerLevel.code)" class="text-xs font-mono opacity-60"></span>
                                        </template>
                                    </span>
                                </span>
                            </li>
                        </template>
                        
                        {{-- Regular Group Header --}}
                        <template x-if="!option.hierarchy?.length && option.group && (index === 0 || !filteredOptions[index - 1].group || filteredOptions[index - 1].group.code !== option.group.code)">
                            <li class="mt-1 first:mt-0">
                                <a 
                                    x-show="selectableGroups && option.group.value"
                                    @click="selectGroup(option.group)"
                                    :class="{'active': option.group.value == selected}"
                                    class="flex items-center gap-1 cursor-pointer font-bold bg-base-200/50"
                                >
                                    <span x-text="option.group.label"></span>
                                    <template x-if="showCode && option.group && option.group.code">
                                        <span x-text="$store.utils.formatCode(option.group.code)" class="opacity-50 font-mono text-xs font-normal"></span>
                                    </template>
                                </a>
                                <span 
                                    x-show="!selectableGroups || !option.group.value"
                                    class="menu-title opacity-70 px-4 py-1"
                                >
                                    <span x-text="option.group.label"></span>
                                </span>                     
                            </li>
                        </template>

                        {{-- Option --}}
                        <li>
                            <a 
                                @click="select(option)" 
                                :data-opt-index="index"
                                :class="{'active': option.value == selected, 'bg-base-200': index === highlightedIndex && option.value != selected}"
                                class="block overflow-hidden py-1.5 px-2 rounded-md"
                                @mouseenter="highlightedIndex = index"
                            >
                                <div class="w-full" :style="option.hierarchy?.length ? 'padding-inline-start: ' + (option.hierarchy.length * 0.75 + 0.25) + 'rem' : ''">
                                    <!-- Label -->
                                    <div class="flex items-center justify-between gap-2">
                                        <span x-html="highlightMatch(option.label)" class="truncate"></span>
                                        
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <!-- Code -->
                                            <template x-if="showCode && option.code">
                                                <span x-html="highlightMatch($store.utils.formatCode(option.code))" class="text-xs opacity-70 font-mono border border-base-content/20 px-1 rounded"></span>
                                            </template>

                                            <!-- Checkmark -->
                                            <span x-show="option.value == selected">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </div>
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
    (function() {
        const registerComponent = () => {
            if (typeof Alpine === 'undefined') return;
            
            Alpine.data('searchSelect', (config) => ({
            open: false,
            search: '',
            selected: config.selected ? String(config.selected) : null,
            selectedLabel: config.selectedLabel,
            initialOptions: config.options,
            filteredOptions: [],
            isLoading: false,
            placeholder: config.placeholder,
            emptyText: config.emptyText,
            highlightedIndex: -1,
            minSearchLength: 1, 
            selectableGroups: config.selectableGroups,
            showCode: config.showCode,
            disabled: config.disabled,

            init() {
                this.filteredOptions = this.initialOptions;
                
                if (this.selected && !this.selectedLabel) {
                    this.updateFromParent(this.selected);
                }
                
                const checkInterval = setInterval(() => {
                    const wrapper = this.$el.closest('[x-data]');
                    if (wrapper) {
                        const context = Alpine.$data(wrapper);
                        // Check for variable "selectedId" or matching "name" in parent
                        const parentVal = context.selectedId ?? context[config.name]; 
                        if (parentVal !== undefined) {
                            if (String(parentVal) !== String(this.selected)) {
                                this.updateFromParent(parentVal);
                            }
                            this.$watch(() => context.selectedId ?? context[config.name], (newValue) => {
                                if (String(newValue) !== String(this.selected)) {
                                    this.updateFromParent(newValue);
                                }
                            });
                            clearInterval(checkInterval);
                        }
                    }
                }, 100);
                setTimeout(() => clearInterval(checkInterval), 3000);
            },
            
            updateFromParent(newValue) {
                this.selected = newValue ? String(newValue) : null;
                if (!this.selected) {
                    this.selectedLabel = null;
                    return;
                }

                // Check flat options
                const option = this.initialOptions.find(opt => String(opt.value) === String(newValue));
                
                // Check groups/hierarchy in initial options
                let labelFound = option ? option.label : null;
                
                if (!labelFound && this.selectableGroups) {
                     for(let opt of this.initialOptions) {
                        if (opt.group && String(opt.group.value) === String(newValue)) {
                            labelFound = opt.group.label;
                            break;
                        }
                        if (opt.hierarchy) {
                            const hItem = opt.hierarchy.find(h => String(h.value) === String(newValue));
                            if (hItem) {
                                labelFound = hItem.label;
                                break;
                            }
                        }
                     }
                }

                if (labelFound) {
                    this.selectedLabel = labelFound;
                } else if (config.searchUrl && config.model) {
                    this.fetchLabelById(newValue);
                }
            },
            
            async fetchLabelById(id) {
                if (!id) return;
                try {
                    const url = new URL(config.searchUrl, window.location.origin);
                    url.searchParams.append('model', config.model);
                    url.searchParams.append('q', id);
                    url.searchParams.append('limit', config.limit);
                    url.searchParams.append('labelField', config.labelField);
                    url.searchParams.append('searchFields', 'name,id');
                    url.searchParams.append('selectableGroups', this.selectableGroups);

                    const response = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                    if (!response.ok) return;

                    const data = await response.json();
                    
                    // Handle grouped response format
                    let item = null;
                    
                    if (Array.isArray(data)) {
                        // First, try to find in flat format (backward compatibility)
                        item = data.find(d => String(d.id) === String(id) || String(d.value) === String(id));
                        
                        // If not found, search within grouped format
                        if (!item) {
                            for (const group of data) {
                                if (group.hierarchy !== undefined && group.items !== undefined) {
                                    // Search in the items array
                                    const foundItem = group.items.find(i => 
                                        String(i.id) === String(id) || String(i.value) === String(id)
                                    );
                                    if (foundItem) {
                                        item = {
                                            ...foundItem,
                                            hierarchy: group.hierarchy
                                        };
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (item) {
                        this.selectedLabel = item.label ?? item.name ?? item.text;
                        const selectedCode = item[config.codeField] ?? item.code ?? null;
                        
                        // Dispatch the code along with the selection
                        this.$dispatch('subject-selected', { 
                            id: String(item.value ?? item.id), 
                            name: this.selectedLabel,
                            code: selectedCode
                        });
                        
                        // Add the fetched item to initialOptions if not already present
                        const exists = this.initialOptions.find(opt => String(opt.value) === String(id));
                        if (!exists) {
                            const newOption = {
                                value: String(item.value ?? item.id),
                                label: this.selectedLabel,
                                group: item.group ?? null,
                                code: selectedCode,
                                hierarchy: item.hierarchy ?? []
                            };
                            this.initialOptions.unshift(newOption);
                            this.filteredOptions = this.initialOptions;
                        }
                    }
                } catch (error) {
                    console.error('Fetch label error:', error);
                }
            },

            getHierarchyHeadersToShow(option, index) {
                if (!option.hierarchy || option.hierarchy.length === 0) return [];
                
                // Add level index to each header for proper indentation
                const addLevelIndex = (headers) => {
                    return headers.map((header, idx) => ({
                        ...header,
                        _level: idx  // Track the actual level in the hierarchy
                    }));
                };
                
                // Always show full hierarchy for the very first item
                if (index === 0) return addLevelIndex(option.hierarchy);
                
                const prevOption = this.filteredOptions[index - 1];
                
                // If previous item has no hierarchy, we must show full hierarchy
                if (!prevOption || !prevOption.hierarchy || prevOption.hierarchy.length === 0) {
                    return addLevelIndex(option.hierarchy);
                }
                
                // Compare levels and track which ones to show
                const headersToShow = [];
                let diffFound = false;

                for (let i = 0; i < option.hierarchy.length; i++) {
                    const currentLevel = option.hierarchy[i];
                    const prevLevel = prevOption.hierarchy[i];
                    
                    // If we found a difference earlier, add this level with its actual position
                    if (diffFound) {
                        headersToShow.push({
                            ...currentLevel,
                            _level: i
                        });
                        continue;
                    }

                    // If level doesn't exist in previous, it's new
                    if (!prevLevel) {
                        headersToShow.push({
                            ...currentLevel,
                            _level: i
                        });
                        diffFound = true; 
                        continue;
                    }

                    // Compare content - use value for more reliable comparison
                    const currentKey = String(currentLevel.value || '') + (currentLevel.code || '') + currentLevel.label;
                    const prevKey = String(prevLevel.value || '') + (prevLevel.code || '') + prevLevel.label;
                    
                    if (currentKey !== prevKey) {
                        headersToShow.push({
                            ...currentLevel,
                            _level: i
                        });
                        diffFound = true;
                    }
                }

                return headersToShow;
            },

            toggle() {
                if (this.disabled) return;
                if (this.open) return this.close();
                this.open = true;
                this.$nextTick(() => {
                    this.$refs.searchInput.focus();
                    this.scrollToSelected();
                });
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
                this.selected = String(option.value);
                this.selectedLabel = option.label;
                
                // Add to initialOptions if not already present (e.g., from remote search)
                const exists = this.initialOptions.find(opt => String(opt.value) === String(option.value));
                if (!exists) {
                    this.initialOptions.unshift(option);
                }
                
                this.$dispatch('input', this.selected); 
                this.$dispatch('subject-selected', { 
                    id: this.selected, 
                    name: option.label,
                    code: option.code 
                });
                
                this.search = '';
                this.close();
            },

            selectGroup(group) {
                if (!group || !group.value) return;
                this.selected = String(group.value);
                this.selectedLabel = group.label;
                
                // Check if this group value already exists as an option, group, or hierarchy item
                const groupValueStr = String(group.value);
                const exists = this.initialOptions.some(opt => {
                    // Check if it's already a direct option
                    if (String(opt.value) === groupValueStr) return true;
                    
                    // Check if it exists in this option's group
                    if (opt.group && String(opt.group.value) === groupValueStr) return true;
                    
                    // Check if it exists in this option's hierarchy
                    if (opt.hierarchy && opt.hierarchy.some(h => String(h.value) === groupValueStr)) return true;
                    
                    return false;
                });
                
                // Only add if not found anywhere
                if (!exists) {
                    const groupOption = {
                        value: groupValueStr,
                        label: group.label,
                        group: null,
                        code: group.code,
                        hierarchy: []
                    };
                    this.initialOptions.unshift(groupOption);
                }
                
                this.$dispatch('input', this.selected);
                this.$dispatch('subject-selected', { 
                    id: this.selected, 
                    name: group.label,
                    code: group.code 
                });
                
                this.search = '';
                this.close();
            },

            handleSearch() {
                const query = this.search.trim().toLowerCase();

                if (!query) {
                    this.filteredOptions = this.initialOptions;
                    this.isLoading = false;
                    this.highlightedIndex = -1;
                    return;
                }

                const localMatches = this.initialOptions.filter(opt => {
                    const matchLabel = opt.label.toLowerCase().includes(query);
                    const matchCode = opt.code && opt.code.toLowerCase().includes(query);
                    const matchGroup = opt.group && (
                        (opt.group.label && opt.group.label.toLowerCase().includes(query)) ||
                        (opt.group.code && opt.group.code.toLowerCase().includes(query))
                    );
                    const matchHierarchy = opt.hierarchy && opt.hierarchy.some(h => 
                        (h.label && h.label.toLowerCase().includes(query)) ||
                        (h.code && h.code.toLowerCase().includes(query))
                    );
                    
                    return matchLabel || matchCode || matchGroup || matchHierarchy;
                });

                if (config.searchUrl && config.model && query.length >= this.minSearchLength) {
                    this.fetchRemoteOptions(query);
                } else {
                    this.filteredOptions = localMatches;
                    this.isLoading = false;
                    this.highlightedIndex = localMatches.length ? 0 : -1;
                }
            },

            async fetchRemoteOptions(query) {
                this.isLoading = true;
                try {
                    const url = new URL(config.searchUrl, window.location.origin);
                    url.searchParams.append('model', config.model);
                    url.searchParams.append('q', query);
                    url.searchParams.append('labelField', config.labelField);
                    url.searchParams.append('limit', config.limit);
                    url.searchParams.append('orderBy', config.orderBy);
                    url.searchParams.append('direction', config.direction);
                    url.searchParams.append('searchFields', config.searchFields);
                    url.searchParams.append('selectableGroups', this.selectableGroups);

                    const response = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('Network error');

                    const data = await response.json();

                    // Handle grouped response format
                    const flattenedOptions = [];
                    
                    if (Array.isArray(data)) {
                        data.forEach(group => {
                            // Check if this is a grouped response (has 'hierarchy' and 'items' keys)
                            if (group.hierarchy !== undefined && group.items !== undefined) {
                                // Add each item from the group with the shared hierarchy
                                group.items.forEach(item => {
                                    flattenedOptions.push({
                                        value: String(item.value ?? item.id),
                                        label: item.label ?? item.name ?? item.text,
                                        group: item.group ?? null,
                                        code: item[config.codeField] ?? item.code ?? null,
                                        hierarchy: group.hierarchy ?? []
                                    });
                                });
                            } else {
                                // Handle flat format (backward compatibility)
                                flattenedOptions.push({
                                    value: String(group.value ?? group.id),
                                    label: group.label ?? group.name ?? group.text,
                                    group: group.group ?? null,
                                    code: group[config.codeField] ?? group.code ?? null,
                                    hierarchy: group.hierarchy ?? []
                                });
                            }
                        });
                    }

                    this.filteredOptions = flattenedOptions;
                    this.highlightedIndex = this.filteredOptions.length ? 0 : -1;

                } catch (error) {
                    console.error(error);
                    this.filteredOptions = [];
                } finally {
                    this.isLoading = false;
                }
            },

            escapeHtml(unsafe) {
                if (!unsafe) return '';
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            },

            highlightMatch(label) {
                let safeLabel = this.escapeHtml(String(label));
                if (!this.search) return safeLabel;
                
                // Use a visible color that works with active (blue) background
                // text-warning is typically orange/yellow, font-extrabold makes it pop
                const highlightClass = 'text-warning font-extrabold underline decoration-warning/30';
                
                const regex = new RegExp(`(${this.escapeRegex(this.search)})`, 'gi');
                return safeLabel.replace(regex, `<span class="${highlightClass}">$1</span>`);
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
                    const list = this.$refs.optionsList;
                    if (!list) return;
                    const item = list.querySelector(`[data-opt-index='${this.highlightedIndex}']`);
                    
                    if (item) {
                        // Find the closest LI to ensure we scroll to the full row including padding
                        const row = item.closest('li');
                        if (row) {
                            row.scrollIntoView({ block: 'nearest' });
                        }
                    }
                });
            },
            
            scrollToSelected() {
                 if (!this.selected) return;
                 // Find index of selected
                 const idx = this.filteredOptions.findIndex(o => String(o.value) === String(this.selected));
                 if (idx >= 0) {
                     this.highlightedIndex = idx;
                     this.scrollToHighlighted();
                 }
            }

            }));
        };

        if (typeof Alpine !== 'undefined') {
            registerComponent();
        } else {
            document.addEventListener('alpine:init', registerComponent);
        }
    })();
</script>
@endonce