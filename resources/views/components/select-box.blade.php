<div {{ $attributes->except(['url', 'options', 'placeholder', 'selectableGroups', 'disabled', 'hint', 'hint2', 'class'])->merge(['class' => 'relative w-full ' . $class]) }}
    x-data="searchSelect({
        url: '{{ $url }}',
        options: @js($finalLocalOptions),
        placeholder: '{{ $placeholder }}',
        selectableGroups: @js($selectableGroups),
        disabled: @js($disabled)
    })" @click.outside="close()">
    {{-- Trigger Button --}}
    <button type="button" @click="toggle()" :disabled="disabled" :class="{ 'opacity-60 cursor-not-allowed': disabled }"
        class="input input-bordered w-full text-left flex items-center justify-between px-4 bg-base-100 focus:outline-none focus:border-primary">

        <span x-text="selectedLabel ? selectedLabel : placeholder"
            :class="{ 'text-base-content': selectedLabel, 'text-gray-400': !selectedLabel }"
            class="block truncate"></span>

        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 transition-transform duration-200"
            :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Dropdown Menu --}}
    <div x-show="open" x-transition.opacity.duration.200ms
        class="absolute z-[100] w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden">

        {{-- Search Input --}}
        <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
            <input x-ref="searchInput" x-model="search" @input.debounce.300ms="handleSearch()"
                @keydown="onKeydown($event)" type="text" class="input input-sm input-bordered w-full"
                placeholder="{{ __('Search') }}">
        </div>

        {{-- Options List --}}
        <ul class="flex flex-col menu-compact w-full overflow-y-auto p-1 scroll-p-2" x-ref="optionsList">

            {{-- Loading State --}}
            <li x-show="isLoading" class="pointer-events-none p-4 text-center">
                <span class="loading loading-spinner loading-xs text-primary"></span>
                <span class="text-xs text-gray-500 ml-2">{{ __('Loading') }}</span>
            </li>

            {{-- No Results State --}}
            <li x-show="!isLoading && isEmpty" class="pointer-events-none p-4 text-center">
                <span class="text-gray-500 italic text-sm" x-text="emptyText"></span>
            </li>

            {{-- Results --}}
            <template x-for="(groupBlock, blockIndex) in filteredOptions" :key="groupBlock.id || blockIndex">
                <div class="contents">
                    <!-- Header Group -->
                    <template x-if="groupBlock.headerGroup && hasOptionsInBlock(groupBlock)">
                        <li class="mt-2 first:mt-0 bg-base-200/50">
                            <span class="menu-title text-xs font-bold uppercase tracking-wider opacity-70 px-4 py-1"
                                x-text="groupBlock.headerGroup"></span>
                        </li>
                    </template>

                    <!-- Iterating over Object -->
                    <template x-for="(groupItems, groupId) in groupBlock.options" :key="groupId">
                        <div class="contents">
                            <!-- Group Name -->
                            <li class="px-4 py-1 text-[10px] font-semibold text-gray-400 mt-1">
                                <span
                                    x-html="groupItems[0].groupNameHighlighted ? groupItems[0].groupNameHighlighted : groupItems[0].groupName"></span>
                            </li>

                            <!-- Actual Options -->
                            <template x-for="opt in groupItems" :key="opt.id + '-' + opt.groupId">
                                <li @click="selectOption(opt)"
                                    :class="{
                                        'bg-primary text-primary-content': selectedValue == opt
                                            .id,
                                        'bg-base-200': focusedOptionId === opt.id
                                    }"
                                    class="px-6 py-2 cursor-pointer hover:bg-base-200 rounded-btn transition-colors text-sm">
                                    <span x-html="opt.highlighted ? opt.highlighted : opt.text"></span>
                                </li>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </ul>
        <div class='label'>
            <span class="label-text-alt mr-3">{!! $hint !!}</span>
            <span class="label-text-alt ml-3">{!! $hint2 !!}</span>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script>
        function searchSelect({
            url,
            options,
            placeholder,
            selectableGroups,
            disabled
        }) {
            return {
                open: false,
                search: '',
                initialOptions: options,
                filteredOptions: options,
                selectedValue: '',
                selectedLabel: '',
                focusedOptionId: null,
                isLoading: false,
                emptyText: 'نتیجه‌ای یافت نشد',
                placeholder,
                selectableGroups,
                disabled,
                url,

                get isEmpty() {
                    return !this.filteredOptions || this.filteredOptions.every(b => !b.options || Object.keys(b.options)
                        .length === 0);
                },

                init() {
                    this.$nextTick(() => {
                        this.setLabelFromOptions(this.initialOptions);
                        if (this.selectedValue && !this.selectedLabel && this.url) {
                            this.fetchSelectedLabel();
                        }
                    });
                    this.setLabelFromOptions(this.initialOptions);

                    if (this.selectedValue && !this.selectedLabel && this.url) {
                        this.fetchSelectedLabel();
                    }

                    this.$watch('selectedValue', (newValue, oldValue) => {
                        if (newValue) {
                            this.setLabelFromOptions(this.initialOptions);
                            if (!this.selectedLabel && this.url) {
                                this.fetchSelectedLabel();
                            }
                        } else {
                            this.selectedLabel = '';
                        }
                    });
                },

                parseSelectedValue() {
                    if (!this.selectedValue) return {
                        type: null,
                        id: null
                    };

                    const parts = this.selectedValue.split('-');
                    if (parts.length !== 2) return {
                        type: null,
                        id: null
                    };

                    return {
                        type: parts[0],
                        id: parts[1]
                    };
                },

                hasOptionsInBlock(block) {
                    for (let gid in block.options) {
                        if (block.options[gid].length > 0) {
                            return true;
                        }
                    }
                    return false;
                },

                fetchSelectedLabel() {
                    this.isLoading = true;
                    fetch(`${this.url}?q=${this.selectedValue}`)
                        .then(r => r.json())
                        .then(data => {
                            this.setLabelFromOptions(data);
                            this.isLoading = false;
                        })
                        .catch(() => {
                            this.isLoading = false;
                        });
                },

                setLabelFromOptions(list) {
                    if (!list) return;

                    const {
                        type,
                        id
                    } = this.parseSelectedValue();

                    if (!id || !type) return;

                    // Extract numeric ID from formats
                    let searchId = this.selectedValue;
                    let searchType = null;


                    for (let block of list) {
                        for (let gid in block.options) {
                            const found = block.options[gid].find(o => o.id == id && o.type == type);
                            if (found) {
                                this.selectedLabel = found.text;
                                return;
                            }
                        }
                    }
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
                    const q = this.search.trim().toLowerCase();

                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        if (q === "") {
                            this.filteredOptions = this.initialOptions;
                            this.isLoading = false;
                            return;
                        }

                        const local = this.searchLocal(q);
                        if (local.found) {
                            this.filteredOptions = local.options;
                            this.isLoading = false;
                        } else if (this.url) {
                            this.searchRemote(q);
                        } else {
                            this.filteredOptions = [];
                            this.isLoading = false;
                        }
                    }, 300);
                },

                searchLocal(query) {
                    let resultBlocks = [];

                    this.initialOptions.forEach(block => {
                        let resultGroups = {};

                        for (let gid in block.options) {
                            const items = block.options[gid];
                            if (!items.length) continue;

                            const groupName = items[0].groupName.toLowerCase();
                            const isGroupMatch = groupName.includes(query);

                            // If group matches, include ALL items
                            let itemMatches = [];
                            if (!isGroupMatch) {
                                itemMatches = items.filter(i =>
                                    i.text.toLowerCase().includes(query)
                                );
                            }

                            // Skip group if no match in this group
                            if (!isGroupMatch && itemMatches.length === 0) continue;

                            // Final items assigned based on match type
                            const finalItems = (isGroupMatch ? items : itemMatches).map(i => ({
                                ...i,
                                groupNameHighlighted: isGroupMatch ?
                                    this.highlight(i.groupName, query) : null,
                                highlighted: !isGroupMatch ?
                                    this.highlight(i.text, query) : null
                            }));

                            resultGroups[gid] = finalItems;
                        }

                        if (Object.keys(resultGroups).length > 0) {
                            resultBlocks.push({
                                ...block,
                                options: resultGroups
                            });
                        }
                    });

                    return {
                        found: resultBlocks.length > 0,
                        options: resultBlocks
                    };
                },

                searchRemote(q) {
                    this.isLoading = true;

                    fetch(`${this.url}?q=${encodeURIComponent(q)}`)
                        .then(r => r.json())
                        .then(data => {
                            const query = q.toLowerCase();

                            data.forEach(block => {
                                let newGroups = {};

                                for (let gid in block.options) {
                                    const items = block.options[gid];
                                    if (!items.length) continue;

                                    const groupName = items[0].groupName.toLowerCase();
                                    const isGroupMatch = groupName.includes(query);

                                    let itemMatches = [];
                                    if (!isGroupMatch) {
                                        itemMatches = items.filter(i =>
                                            i.text.toLowerCase().includes(query)
                                        );
                                    }

                                    if (!isGroupMatch && itemMatches.length === 0) continue;

                                    newGroups[gId] = (isGroupMatch ? items : itemMatches).map(i => ({
                                        ...i,
                                        groupNameHighlighted: isGroupMatch ?
                                            this.highlight(i.groupName, query) : null,
                                        highlighted: !isGroupMatch ?
                                            this.highlight(i.text, query) : null
                                    }));
                                }

                                block.options = newGroups;
                            });

                            this.filteredOptions = data.filter(b => Object.keys(b.options).length);
                            this.isLoading = false;
                        })
                        .catch(() => {
                            this.filteredOptions = [];
                            this.isLoading = false;
                        });
                },

                highlight(text, query) {
                    const regex = new RegExp(`(${query})`, 'ig');
                    return text.replace(regex, '<span class="bg-yellow-200">$1</span>');
                },

                selectOption(opt) {
                    // Keep selectedValue format consistent with parent
                    const compositeId = `${opt.type}-${opt.id}`;
                    this.selectedValue = compositeId;
                    this.selectedLabel = opt.text;
                    this.open = false;
                    this.search = '';
                    this.filteredOptions = this.initialOptions;

                    this.$dispatch('selected', {
                        id: opt.id,
                        type: opt.type,
                        text: opt.text
                    });
                },

                onKeydown(e) {
                    if (!this.open) return;

                    const all = [];
                    this.filteredOptions.forEach(b => {
                        Object.values(b.options).forEach(list => list.forEach(o => all.push(o)));
                    });

                    if (!all.length) return;

                    let idx = all.findIndex(o => o.id === this.focusedOptionId);

                    switch (e.key) {
                        case 'ArrowDown':
                            e.preventDefault();
                            this.focusedOptionId = all[(idx + 1) % all.length].id;
                            break;

                        case 'ArrowUp':
                            e.preventDefault();
                            this.focusedOptionId = all[(idx - 1 + all.length) % all.length].id;
                            break;

                        case 'Enter':
                            e.preventDefault();
                            if (this.focusedOptionId !== null)
                                this.selectOption(all.find(o => o.id === this.focusedOptionId));
                            break;

                        case 'Escape':
                            this.close();
                            break;
                    }
                }
            };
        }
    </script>
@endPushOnce
