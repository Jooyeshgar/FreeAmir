@props([
    'url' => null,
    'options' => [],
    'class' => '',
    'placeholder' => 'Select an option',
    'selected' => null,
    'selectableGroups' => false,
    'disabled' => false,
])

@php
    // Prepare initial local options
    $finalLocalOptions = [];
    foreach ($options as $index => $option) {
        $headerGroupOptions = $option['headerGroup'] ?? '';
        
        // Use the existing helper logic within the view or passed data
        // For simplicity, we assume the Controller structure above matches this local structure logic
        if (is_array($option)) {
            $groupedOptions = groupOptionsByHeader($option, $headerGroupOptions);
        } else {
            $groupedOptions = [];
        }

        $finalLocalOptions[] = [
            'id' => 'local_' . $index,
            'headerGroup' => $headerGroupOptions,
            'options' => $groupedOptions,
        ];
    }

    function groupOptionsByHeader($option, $headerGroupOptions) {
        $groupedOptions = [];
        // Ensure we have options to iterate
        $items = $option['options'] ?? []; 
        
        foreach ($items as $opt) {
            // Handle Eloquent model or Array
            $optObj = is_array($opt) ? (object)$opt : $opt;
            
            // Determine Group
            $property = $headerGroupOptions ? "{$headerGroupOptions}Group" : '';
            
            // Logic to find the group object
            if ($property && isset($optObj->$property)) {
                $group = $optObj->$property;
            } elseif (isset($optObj->group)) {
                $group = $optObj->group;
            } else {
                $group = (object)['id'=> 0,'name'=>'General'];
            }

            if (!isset($groupedOptions[$group->id])) {
                $groupedOptions[$group->id] = [];
            }

            $groupedOptions[$group->id][] = [
                'id' => $optObj->id,
                'groupId' => $group->id,
                'groupName' => $group->name,
                'text' => $optObj->name ?? $optObj->text ?? '',
                'type' => $headerGroupOptions, // Pass context like 'product' or 'service'
            ];
        }
        return (object)$groupedOptions; // Return object to preserve Keys in JS
    }
@endphp

<div
    class="relative w-full {{ $class }}"
    {{ $attributes->merge(['class' => 'relative w-full ' . $class]) }}
    x-data="searchSelect({
        url: '{{ $url }}',
        options: @js($finalLocalOptions),
        placeholder: '{{ $placeholder }}',
        selected: @js($selected),
        selectableGroups: @js($selectableGroups),
        disabled: @js($disabled)
    })"
    x-init="init()"
    @click.outside="close()"
>
    {{-- Trigger Button --}}
    <button type="button" 
        @click="toggle()" 
        :disabled="disabled"
        :class="{'opacity-60 cursor-not-allowed': disabled}"
        class="input input-bordered w-full text-left flex items-center justify-between px-4 bg-base-100 focus:outline-none focus:border-primary">
        
        <span x-text="selectedLabel ? selectedLabel : placeholder"
              :class="{'text-base-content': selectedLabel, 'text-gray-400': !selectedLabel}"
              class="block truncate"></span>
              
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 transition-transform duration-200"
             :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Hidden Input for Form Submission --}}
    <input type="hidden" name="{{ $attributes->get('name') }}" x-model="selectedValue">

    {{-- Dropdown Menu --}}
    <div x-show="open" 
         x-transition.opacity.duration.200ms
         class="absolute z-[100] w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden">

        {{-- Search Input --}}
        <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
            <input x-ref="searchInput" 
                   x-model="search" 
                   @input.debounce.300ms="handleSearch()"
                   @keydown="onKeydown($event)"
                   type="text"
                   class="input input-sm input-bordered w-full"
                   placeholder="Type to search...">
        </div>

        {{-- Options List --}}
        <ul class="flex flex-col menu-compact w-full overflow-y-auto p-1 scroll-p-2" x-ref="optionsList">
            
            {{-- Loading State --}}
            <li x-show="isLoading" class="pointer-events-none p-4 text-center">
                <span class="loading loading-spinner loading-xs text-primary"></span>
                <span class="text-xs text-gray-500 ml-2">Loading...</span>
            </li>

            {{-- No Results State --}}
            <li x-show="!isLoading && isEmpty" class="pointer-events-none p-4 text-center">
                <span class="text-gray-500 italic text-sm" x-text="emptyText"></span>
            </li>

            {{-- Results --}}
            <template x-for="(groupBlock, blockIndex) in filteredOptions" :key="groupBlock.id || blockIndex">
                <div class="contents">
                    <!-- Header Group (Product/Service) -->
                    <template x-if="groupBlock.headerGroup && hasOptionsInBlock(groupBlock)">
                        <li class="mt-2 first:mt-0 bg-base-200/50">
                            <span class="menu-title text-xs font-bold uppercase tracking-wider opacity-70 px-4 py-1" 
                                  x-text="groupBlock.headerGroup"></span>
                        </li>
                    </template>

                    <!-- Sub-Groups (Product Categories) -->
                    <!-- Iterating over Object: (items, groupId) -->
                    <template x-for="(groupItems, groupId) in groupBlock.options" :key="groupId">
                        <div class="contents">
                            <!-- Group Name -->
                            <li class="px-4 py-1 text-[10px] font-semibold text-gray-400 mt-1" 
                                x-text="groupItems[0].groupName"></li>

                            <!-- Actual Options -->
                            <template x-for="opt in groupItems" :key="opt.id + '-' + opt.groupId">
                                <li @click="selectOption(opt)"
                                    :class="{'bg-primary text-primary-content': selectedValue == opt.id, 'bg-base-200': focusedOptionId === opt.id}"
                                    class="px-6 py-2 cursor-pointer hover:bg-base-200 rounded-btn transition-colors text-sm">
                                    <span x-text="opt.text"></span>
                                </li>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </ul>
    </div>
</div>

<script>
function searchSelect({ url, options, placeholder, selected, selectableGroups, disabled }) {
    return {
        open: false,
        search: '',
        initialOptions: options,
        filteredOptions: options,
        selectedValue: selected,
        selectedLabel: '',
        focusedOptionId: null,
        isLoading: false,
        placeholder,
        emptyText: 'No results found',
        selectableGroups,
        disabled,
        url,

        get isEmpty() {
            if (!this.filteredOptions || this.filteredOptions.length === 0) return true;
            return this.filteredOptions.every(block => Object.keys(block.options).length === 0);
        },

        init() {
            // 1. Try to find the label in the locally provided options
            this.setLabelFromOptions(this.initialOptions);

            // 2. If we have a selected ID but couldn't find the label locally, fetch it from remote
            if (this.selectedValue && !this.selectedLabel && this.url) {
                this.fetchSelectedLabel();
            }
        },

        fetchSelectedLabel() {
            this.isLoading = true;
            // Fetch using 'id' parameter specifically
            fetch(`${this.url}?id=${this.selectedValue}`)
                .then(res => {
                    if (!res.ok) throw new Error('Network error');
                    return res.json();
                })
                .then(data => {
                    // Use the same helper to parse the remote response
                    this.setLabelFromOptions(data);
                    this.isLoading = false;
                })
                .catch(err => {
                    console.error("Error fetching initial label:", err);
                    this.isLoading = false;
                    // Fallback: show the ID if fetch fails
                    if (!this.selectedLabel) {
                        this.selectedLabel = `ID: ${this.selectedValue}`; 
                    }
                });
        },

        setLabelFromOptions(optionsList) {
            if (!this.selectedValue || !optionsList) return;
            
            for (let block of optionsList) {
                for (let groupId in block.options) {
                    // Use == to allow string/int comparison
                    const found = block.options[groupId].find(opt => opt.id == this.selectedValue);
                    if (found) {
                        this.selectedLabel = found.text;
                        return; // Stop once found
                    }
                }
            }
        },

        hasOptionsInBlock(block) {
            return block.options && Object.keys(block.options).length > 0;
        },

        toggle() {
            if (this.disabled) return;
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.$refs.searchInput.focus());
            }
        },

        close() {
            this.open = false;
            this.focusedOptionId = null;
        },

        handleSearch() {
            if (this.search.trim() === '') {
                this.filteredOptions = this.initialOptions;
                this.isLoading = false;
                return;
            }

            if (!this.url) {
                this.filterLocal();
                return;
            }
            
            this.isLoading = true;
            
            fetch(`${this.url}?q=${encodeURIComponent(this.search)}`)
                .then(res => res.ok ? res.json() : [])
                .then(data => {
                    this.filteredOptions = data;
                    this.isLoading = false;
                    this.focusedOptionId = null;
                })
                .catch(() => {
                    this.filteredOptions = [];
                    this.isLoading = false;
                });
        },

        filterLocal() {
            this.isLoading = true;
            setTimeout(() => {
                const searchLower = this.search.toLowerCase();
                this.filteredOptions = this.initialOptions.map(block => {
                    const newOptionsMap = {};
                    Object.entries(block.options).forEach(([groupId, items]) => {
                        const filteredItems = items.filter(opt => 
                            opt.text.toLowerCase().includes(searchLower)
                        );
                        if (filteredItems.length > 0) {
                            newOptionsMap[groupId] = filteredItems;
                        }
                    });
                    return { ...block, options: newOptionsMap };
                }).filter(block => Object.keys(block.options).length > 0);
                this.isLoading = false;
            }, 100);
        },

        selectOption(opt) {
            this.selectedValue = opt.id;
            this.selectedLabel = opt.text;
            this.open = false;
            this.search = ''; 
            this.filteredOptions = this.initialOptions;
            
            this.$dispatch('selected', { id: opt.id, type: opt.type, text: opt.text });
            this.$dispatch('input', opt.id); 
        },

        onKeydown(event) {
            if (!this.open) return;
            const allVisibleOptions = [];
            this.filteredOptions.forEach(block => {
                Object.values(block.options).forEach(items => {
                    items.forEach(opt => allVisibleOptions.push(opt));
                });
            });

            if (allVisibleOptions.length === 0) return;
            let index = allVisibleOptions.findIndex(o => o.id === this.focusedOptionId);

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    index = (index + 1) % allVisibleOptions.length;
                    this.focusedOptionId = allVisibleOptions[index].id;
                    this.scrollToFocused();
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    index = (index - 1 + allVisibleOptions.length) % allVisibleOptions.length;
                    this.focusedOptionId = allVisibleOptions[index].id;
                    this.scrollToFocused();
                    break;
                case 'Enter':
                    event.preventDefault();
                    if (this.focusedOptionId) this.selectOption(allVisibleOptions.find(o => o.id === this.focusedOptionId));
                    break;
                case 'Escape':
                    this.close();
                    break;
            }
        },

        scrollToFocused() {
            this.$nextTick(() => {
                const el = this.$refs.optionsList.querySelector('.bg-base-200');
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        }
    }
}
</script>