<div {{ $attributes->except(['url', 'options', 'placeholder', 'selectableGroups', 'disabled', 'hint', 'hint2', 'class'])->merge(['class' => 'relative w-full ' . $class]) }}
    x-data="searchSelect({
        url: '{{ $url }}',
        options: @js($finalLocalOptions),
        placeholder: '{{ $placeholder }}',
        selectableGroups: @js($selectableGroups),
        disabled: @js($disabled),
        searchSubjectCode: @js($searchSubjectCode)
    })" @click.outside="close()" @if ($searchSubjectCode) x-modelable="selectedValue" @endif>
    {{-- Trigger Button --}}
    <button type="button" @click="toggle()" :disabled="disabled" :class="{ 'opacity-60 cursor-not-allowed': disabled }"
        class="input rounded-md border-slate-400 h-10 min-h-10 w-full text-left flex items-center justify-between px-4 bg-base-100 focus:outline-none focus:border-primary">

        <span x-text="selectedLabel ? selectedLabel : placeholder"
            :class="{ 'text-base-content': selectedLabel, 'text-gray-400': !selectedLabel }"
            class="block truncate"></span>
        @if ($searchSubjectCode)
            <bdi dir="ltr" x-show="selectedSubjectCode" x-text="selectedSubjectCode"
                class="ms-auto me-2 shrink-0 text-xs opacity-70"></bdi>
        @endif

        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200"
            :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-5 5-5-5" />
        </svg>
    </button>

    {{-- Dropdown Menu --}}
    <div x-show="open" x-transition.opacity.duration.200ms
        class="absolute z-[100] w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden">

        {{-- Search Input --}}
        <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
            <input x-ref="searchInput"
                @if ($searchSubjectCode)
                    :value="search" @input="updateSubjectSearch($event.target)"
                @else
                    x-model="search" @input.debounce.300ms="handleSearch()"
                @endif
                @keydown="onKeydown($event)" type="text" class="input input-sm w-full"
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
                                x-text="@js($headerLabel) || groupBlock.headerGroup"></span>
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
                                    class="px-6 py-2 cursor-pointer hover:bg-base-200 rounded-btn transition-colors text-sm @if ($searchSubjectCode) flex items-center justify-between gap-2 @endif">
                                    <span x-html="opt.highlighted ? opt.highlighted : opt.text"></span>
                                    @if ($searchSubjectCode)
                                        <bdi dir="ltr" x-show="opt.subjectCode" x-text="opt.subjectCode"
                                            class="shrink-0 text-xs opacity-70"></bdi>
                                    @endif
                                </li>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </ul>
        <div class='label'>
            <span class="text-xs mr-3">{!! $hint !!}</span>
            <span class="text-xs ml-3">{!! $hint2 !!}</span>
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
            disabled,
            searchSubjectCode
        }) {
            return {
                open: false,
                search: '',
                initialOptions: options,
                filteredOptions: options,
                selectedValue: '',
                selectedLabel: '',
                selectedSubjectCode: '',
                focusedOptionId: null,
                isLoading: false,
                searchVersion: 0,
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
                            this.selectedSubjectCode = '';
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
                                this.selectedSubjectCode = found.subjectCode || '';
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

                updateSubjectSearch(input) {
                    const value = input.value;
                    const query = value.trim();
                    const code = /^[0-9۰-۹/]+$/.test(query) ?
                        this.$store.utils.convertToEnglish(query).replaceAll('/', '') : '';

                    if (code) {
                        const formatted = this.$store.utils.localizeNumber(this.$store.utils.formatCode(code));
                        if (formatted !== value) {
                            const digitsBeforeCaret = value.slice(0, input.selectionStart).replace(/[^0-9۰-۹]/g, '').length;
                            const caret = digitsBeforeCaret + Math.floor(Math.max(0, digitsBeforeCaret - 1) / 3);
                            input.value = formatted;
                            input.setSelectionRange(caret, caret);
                        }
                    }
                    this.search = input.value;
                    this.handleSearch(query.toLowerCase(), Boolean(code));
                },

                handleSearch(q = this.search.trim().toLowerCase(), isSubjectCode = false) {
                    clearTimeout(this.searchTimeout);
                    if (searchSubjectCode) {
                        this.searchVersion++;
                        this.focusedOptionId = null;
                        this.filteredOptions = q ? [] : this.initialOptions;
                        this.isLoading = Boolean(q);
                    }

                    this.searchTimeout = setTimeout(() => {
                        if (q === "") {
                            this.filteredOptions = this.initialOptions;
                            this.isLoading = false;
                            return;
                        }

                        if (isSubjectCode && this.url) {
                            this.searchRemote(q, true);
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

                searchRemote(q, isSubjectCode = false) {
                    this.isLoading = true;
                    const searchVersion = this.searchVersion;

                    fetch(`${this.url}?q=${encodeURIComponent(q)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (searchVersion !== this.searchVersion) return;

                            if (isSubjectCode) {
                                this.filteredOptions = Array.isArray(data) ? data : [];
                                this.isLoading = false;
                                return;
                            }

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

                                    newGroups[gid] = (isGroupMatch ? items : itemMatches).map(i => ({
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
                            if (searchVersion !== this.searchVersion) return;

                            this.filteredOptions = [];
                            this.isLoading = false;
                        });
                },

                highlight(text, query) {
                    const regex = new RegExp(`(${query})`, 'ig');
                    return text.replace(regex, '<span class="bg-yellow-200 dark:bg-blue-500">$1</span>');
                },

                selectOption(opt) {
                    // Keep selectedValue format consistent with parent
                    const compositeId = `${opt.type}-${opt.id}`;
                    this.selectedValue = compositeId;
                    this.selectedLabel = opt.text;
                    this.selectedSubjectCode = opt.subjectCode || '';
                    this.open = false;
                    this.search = '';
                    this.filteredOptions = this.initialOptions;

                    this.$dispatch('selected', {
                        id: opt.id,
                        type: opt.type,
                        text: opt.text,
                        customer_id: opt.customer_id ?? null
                    });
                },

                onKeydown(e) {
                    if (!this.open) return;

                    if (searchSubjectCode && this.isEmpty) {
                        if (e.key === 'Enter') e.preventDefault();
                        if (e.key === 'Escape') this.close();
                        return;
                    }

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
