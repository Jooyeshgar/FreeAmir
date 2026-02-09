<div {{ $attributes->except(['url', 'subjects', 'placeholder', 'disabled', 'class'])->merge(['class' => 'relative ' . $class]) }}
    x-data="searchSelect({
        url: '{{ $url }}',
        options: @js($finalLocalOptions),
        placeholder: '{{ $placeholder }}',
        disabled: @js($disabled),
    })" @click.outside="close()" x-cloak>
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

    <div x-show="open" x-transition.opacity.duration.200ms
        class="absolute z-[100] w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden">
        <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
            <input x-ref="searchInput" x-model="search" @input.debounce.300ms="handleSearch()"
                @keydown="onKeydown($event)" type="text" class="input input-sm input-bordered w-full"
                placeholder="{{ __('Search') }}">
        </div>

        <ul class="flex flex-col menu-compact w-full overflow-y-auto p-1 scroll-p-2" x-ref="optionsList">
            <li x-show="isLoading" class="pointer-events-none p-4 text-center">
                <span class="loading loading-spinner loading-xs text-primary"></span>
                <span class="text-xs text-gray-500 ml-2">{{ __('Loading') }}</span>
            </li>

            <li x-show="!isLoading && isEmpty" class="pointer-events-none p-4 text-center">
                <span class="text-gray-500 italic text-sm" x-text="emptyText"></span>
            </li>

            <template x-for="(opt, idx) in flatOptions" :key="opt.id">
                <li @click="selectOption(opt)"
                    :class="{
                        'bg-primary text-primary-content': selectedId === opt.id,
                        'bg-base-200': focusedIndex === idx
                    }"
                    class="option-item px-4 py-2 cursor-pointer hover:bg-base-200 rounded-btn transition-colors text-sm flex justify-between items-center">
                    <span :style="`padding-inline-start: ${opt.depth * 14}px`" class="flex-1 block truncate"
                        x-html="opt.highlightedName || opt.name"></span>

                    <span class="text-xs text-gray-500 ml-2" x-html="$store.utils.formatCode(opt.code)"></span>
                </li>
            </template>
        </ul>
    </div>
</div>

@pushOnce('scripts')
    <script>
        function searchSelect({
            url,
            options,
            placeholder,
            disabled
        }) {
            return {
                open: false,
                search: '',
                initialTree: options,
                filteredTree: options,
                flatOptions: [],
                focusedIndex: -1,
                isLoading: false,
                emptyText: '{{ __('No results found') }}',
                placeholder,
                disabled,
                url,
                remoteCache: {},
                abortController: null,
                minQueryLength: 2,
                searchIndex: {},
                nodeMap: {},
                parentMap: {},

                init() {
                    this.buildIndex(this.initialTree, null);
                    this.rebuildFlatOptions();
                },

                get selectedLabel() {
                    return this.selectedName;
                },

                get isEmpty() {
                    return !this.isLoading && this.flatOptions.length === 0;
                },

                buildIndex(nodes, parentId) {
                    nodes.forEach(node => {
                        this.nodeMap[node.id] = node;
                        this.parentMap[node.id] = parentId;

                        (node.name || '')
                        .toLowerCase()
                            .split(/\s+/)
                            .forEach(token => {
                                if (!this.searchIndex[token]) {
                                    this.searchIndex[token] = new Set();
                                }
                                this.searchIndex[token].add(node.id);
                            });

                        if (node.children?.length) {
                            this.buildIndex(node.children, node.id);
                        }
                    });
                },

                rebuildFlatOptions() {
                    const items = [];
                    this.walkTree(this.filteredTree, 0, items);
                    this.flatOptions = items;
                },

                walkTree(nodes, depth, acc) {
                    if (!Array.isArray(nodes)) return;

                    nodes.forEach(node => {
                        acc.push({
                            ...node,
                            depth,
                            highlightedName: this.search ?
                                this.highlight(node.name ?? '', this.search) : node.name,
                        });

                        if (node.children?.length) {
                            this.walkTree(node.children, depth + 1, acc);
                        }
                    });
                },

                toggle() {
                    if (this.disabled) return;
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    }
                },

                close() {
                    this.open = false;
                    this.focusedIndex = -1;
                },

                handleSearch() {
                    const q = this.search.trim().toLowerCase();

                    if (q.length < this.minQueryLength) {
                        this.filteredTree = this.initialTree;
                        this.rebuildFlatOptions();
                        return;
                    }

                    const matches = this.indexedSearch(q);

                    if (matches.length) {
                        const allowed = this.collectWithParents(matches);
                        this.filteredTree = this.buildFilteredTree(allowed, this.initialTree);
                        this.rebuildFlatOptions();
                    }

                    this.searchRemote(q);
                },

                indexedSearch(query) {
                    const tokens = query.split(/\s+/);
                    let matches = null;

                    for (const t of tokens) {
                        const ids = this.searchIndex[t];
                        if (!ids) return [];

                        matches = matches ?
                            new Set([...matches].filter(x => ids.has(x))) :
                            new Set(ids);
                    }

                    return [...matches];
                },

                collectWithParents(ids) {
                    const result = new Set();

                    ids.forEach(id => {
                        while (id) {
                            result.add(id);
                            id = this.parentMap[id];
                        }
                    });

                    return result;
                },

                buildFilteredTree(allowedIds, nodes) {
                    return nodes
                        .filter(n => allowedIds.has(n.id))
                        .map(n => {
                            const children = this.buildFilteredTree(
                                allowedIds,
                                n.children || []
                            );

                            return {
                                ...n,
                                ...(children.length ? {
                                    children
                                } : {})
                            };
                        });
                },

                searchRemote(q) {
                    if (this.remoteCache[q]) {
                        this.filteredTree = this.remoteCache[q];
                        this.rebuildFlatOptions();
                        return;
                    }

                    this.abortController?.abort();
                    this.abortController = new AbortController();
                    this.isLoading = true;

                    fetch(`${this.url}?q=${encodeURIComponent(q)}`, {
                            signal: this.abortController.signal
                        })
                        .then(r => r.json())
                        .then(data => {
                            const prepared = this.prepareIncoming(data);
                            this.remoteCache[q] = prepared;
                            this.filteredTree = prepared;
                            this.rebuildFlatOptions();
                            this.isLoading = false;
                        })
                        .catch(err => {
                            if (err.name !== 'AbortError') {
                                this.filteredTree = [];
                                this.flatOptions = [];
                                this.isLoading = false;
                            }
                        });
                },

                prepareIncoming(nodes) {
                    return nodes.map(n => ({
                        id: n.id,
                        name: n.name,
                        code: n.code,
                        parent_id: n.parent_id ?? null,
                        children: this.prepareIncoming(n.children || []),
                    }));
                },

                selectOption(opt) {
                    this.selectedId = opt.id;
                    this.selectedName = opt.name;
                    this.selectedCode = opt.code;

                    this.open = false;
                    this.search = '';
                    this.filteredTree = this.initialTree;
                    this.rebuildFlatOptions();
                    this.focusedIndex = -1;

                    this.$dispatch('selected', {
                        id: opt.id,
                        name: opt.name,
                        code: opt.code,
                    });
                },

                onKeydown(e) {
                    if (!this.open || !this.flatOptions.length) return;

                    const last = this.flatOptions.length - 1;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.focusedIndex = this.focusedIndex >= last ? 0 : this.focusedIndex + 1;
                        this.scrollIntoView();
                    }

                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.focusedIndex = this.focusedIndex <= 0 ? last : this.focusedIndex - 1;
                        this.scrollIntoView();
                    }

                    if (e.key === 'Enter' && this.focusedIndex >= 0) {
                        e.preventDefault();
                        this.selectOption(this.flatOptions[this.focusedIndex]);
                    }

                    if (e.key === 'Escape') this.close();
                },

                scrollIntoView() {
                    this.$nextTick(() => {
                        const el = this.$refs.optionsList
                            ?.querySelectorAll('.option-item')[this.focusedIndex];
                        el?.scrollIntoView({
                            block: 'nearest'
                        });
                    });
                },

                escapeRegex(str) {
                    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                },

                highlight(text, query) {
                    const safe = this.escapeRegex(query);
                    return String(text).replace(
                        new RegExp(`(${safe})`, 'ig'),
                        '<span class="bg-yellow-200">$1</span>'
                    );
                },
            };
        }
    </script>
@endPushOnce
