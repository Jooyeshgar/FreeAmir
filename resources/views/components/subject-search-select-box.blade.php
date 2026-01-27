@props([
    'subjects' => [],
    'parentSelectable' => false,
    'placeholder' => __('Select a subject'),
    'siblingLimit' => 20,
    'fetchUrl' => route('documents.subject-search'),
])

<div {{ $attributes->merge(['class' => 'relative w-full']) }} x-data="subjectSearchSelectBox({
    initialNodes: @js($subjects),
    parentSelectable: {{ $parentSelectable ? 'true' : 'false' }},
    placeholder: '{{ $placeholder }}',
    fetchUrl: '{{ $fetchUrl }}',
    siblingLimit: {{ (int) $siblingLimit }},
})"
    data-selected-id="" data-selected-name="" data-selected-code="">
    <button type="button" @click="togglePanel"
        class="input input-bordered w-full text-left flex items-center justify-between px-4 bg-base-100 focus:outline-none focus:border-primary">
        <span class="block truncate" :class="{ 'text-base-content': selection.name, 'text-gray-400': !selection.name }"
            x-text="selection.name || placeholder"></span>

        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 transition-transform duration-200"
            :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open" x-transition.opacity.duration.200ms
        class="absolute z-[100] w-full mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden"
        @click.outside="closePanel">

        <div class="p-2 bg-base-100 border-b border-base-200 sticky top-0 z-10">
            <div class="flex gap-2 items-center">
                <input x-ref="searchInput" type="text" x-model.debounce.400ms="searchTerm" @input="performSearch"
                    class="input input-sm input-bordered w-full" placeholder="{{ __('Search') }}">
            </div>

            <template x-if="searchResults.length">
                <div class="mt-2 max-h-40 overflow-auto space-y-1 bg-base-100 border border-base-200 rounded-box p-2">
                    <div class="text-[11px] text-gray-500 mb-1">{{ __('Search results') }}</div>
                    <template x-for="result in searchResults" :key="result.id">
                        <button type="button"
                            class="w-full text-left text-sm px-2 py-1 rounded-btn hover:bg-base-200 flex justify-between items-center"
                            @click="selectFromSearch(result)">
                            <span x-text="result.name"></span>
                            <span class="text-[11px] text-gray-500" x-text="formatCode(result.code)"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-1" x-ref="treeRoot">
            <template x-if="!visibleNodes.length">
                <p class="text-sm text-gray-500 text-center py-4">{{ __('No subjects available') }}</p>
            </template>

            <template x-for="node in visibleNodes" :key="node.id">
                <div class="flex items-center gap-2 px-2 py-1 rounded-btn"
                    :class="{
                        'bg-primary/10 text-primary': selection.id === node.id,
                        'hover:bg-base-200': selection.id !== node
                            .id
                    }"
                    :style="`padding-left: ${node.depth * 14}px`">
                    <button type="button" class="w-6 h-6 flex items-center justify-center rounded hover:bg-base-200"
                        x-show="node.has_children" @click.stop="toggleNode(node)">
                        <svg x-show="!isExpanded(node.id)" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <svg x-show="isExpanded(node.id)" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                        </svg>
                    </button>
                    <button type="button" class="flex-1 flex justify-between items-center text-sm"
                        @click="selectNode(node)">
                        <span x-text="node.name"></span>
                        <div class="flex items-center gap-2 text-[11px] text-gray-500">
                            <span x-text="formatCode(node.code)"></span>
                            <svg x-show="isLoading(node.id)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" class="opacity-25" />
                                <path d="M4 12a8 8 0 018-8" class="opacity-75" />
                            </svg>
                        </div>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script>
        function subjectSearchSelectBox(config) {
            return {
                open: false,
                placeholder: config.placeholder,
                parentSelectable: config.parentSelectable,
                fetchUrl: config.fetchUrl,
                siblingLimit: config.siblingLimit || 20,
                nodes: [],
                visibleNodes: [],
                expandedIds: new Set(),
                loadingIds: new Set(),
                searchTerm: '',
                searchResults: [],
                selection: {
                    id: '',
                    name: '',
                    code: ''
                },
                initialNodes: config.initialNodes || [],
                togglePanel() {
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    }
                },
                closePanel() {
                    this.open = false;
                },
                normalizeNode(node) {
                    return {
                        id: node.id,
                        name: node.name,
                        code: node.code,
                        parent_id: node.parent_id,
                        has_children: !!node.has_children,
                        children: (node.children || []).map(child => this.normalizeNode(child)),
                        childrenLoaded: (node.children || []).length > 0,
                    };
                },
                recomputeVisibleNodes() {
                    const next = [];
                    const walk = (node, depth) => {
                        next.push({
                            ...node,
                            depth
                        });
                        if (this.isExpanded(node.id) && node.children?.length) {
                            node.children.forEach(child => walk(child, depth + 1));
                        }
                    };
                    this.nodes.forEach(node => walk(node, 0));
                    this.visibleNodes = next;
                },
                isExpanded(id) {
                    return this.expandedIds.has(id);
                },
                isLoading(id) {
                    return this.loadingIds.has(id);
                },
                async toggleNode(node) {
                    if (this.isExpanded(node.id)) {
                        this.expandedIds.delete(node.id);
                        this.recomputeVisibleNodes();
                        return;
                    }

                    this.expandedIds.add(node.id);
                    if (!node.childrenLoaded && node.has_children) {
                        await this.loadChildren(node.id);
                    } else {
                        this.recomputeVisibleNodes();
                    }
                },
                async loadChildren(parentId) {
                    this.loadingIds.add(parentId);
                    const url = new URL(this.fetchUrl, window.location.origin);
                    url.searchParams.set('parent_id', parentId);
                    url.searchParams.set('limit', this.siblingLimit);

                    try {
                        const response = await fetch(url.toString());
                        const payload = await response.json();
                        this.upsertChildren(parentId, payload.results || []);
                    } catch (e) {
                        console.error('Unable to load children', e);
                    } finally {
                        this.loadingIds.delete(parentId);
                        this.recomputeVisibleNodes();
                    }
                },
                upsertChildren(parentId, children) {
                    const incoming = (children || []).map(child => this.normalizeNode(child));

                    if (parentId === null || parentId === undefined) {
                        this.nodes = this.mergeNodes(this.nodes, incoming);
                        this.recomputeVisibleNodes();
                        return;
                    }

                    const parent = this.findNode(parentId, this.nodes);
                    if (!parent) {
                        return;
                    }

                    parent.children = this.mergeNodes(parent.children || [], incoming);
                    parent.childrenLoaded = true;
                    this.recomputeVisibleNodes();
                },
                mergeNodes(existing, incoming) {
                    const map = new Map();
                    existing.forEach(node => map.set(node.id, {
                        ...node
                    }));
                    incoming.forEach(node => {
                        const current = map.get(node.id) || {};
                        map.set(node.id, {
                            ...current,
                            ...node,
                            children: current.children || node.children || [],
                            childrenLoaded: current.childrenLoaded || node.childrenLoaded || (node.children
                                ?.length > 0),
                        });
                    });

                    return Array.from(map.values()).sort((a, b) => (a.code || '').localeCompare(b.code || ''));
                },
                findNode(id, list = this.nodes) {
                    for (const node of list) {
                        if (node.id === id) {
                            return node;
                        }
                        const child = this.findNode(id, node.children || []);
                        if (child) {
                            return child;
                        }
                    }
                    return null;
                },
                selectNode(node) {
                    if (!this.parentSelectable && node.has_children) {
                        this.toggleNode(node);
                        return;
                    }

                    this.selection = {
                        id: node.id,
                        name: node.name,
                        code: node.code
                    };
                    this.$dispatch('subject-picked', {
                        ...this.selection
                    });
                    this.closePanel();
                },
                async revealSelection(subjectId) {
                    const url = new URL(this.fetchUrl, window.location.origin);
                    url.searchParams.set('selected_id', subjectId);
                    url.searchParams.set('limit', this.siblingLimit);

                    try {
                        const response = await fetch(url.toString());
                        const payload = await response.json();

                        (payload.prefetch || [])
                        .forEach(set => {
                            this.expandedIds.add(set.parent_id);
                            this.upsertChildren(set.parent_id, set.children || []);
                        });

                        const target = (payload.path || []).slice(-1)[0];
                        if (target) {
                            this.selection = {
                                id: target.id,
                                name: target.name,
                                code: target.code
                            };
                        }

                        (payload.path || []).forEach(node => this.expandedIds.add(node.id));
                        this.recomputeVisibleNodes();
                    } catch (e) {
                        console.error('Unable to reveal selection', e);
                    }
                },
                async selectFromSearch(result) {
                    await this.revealSelection(result.id);
                    this.selection = {
                        id: result.id,
                        name: result.name,
                        code: result.code
                    };
                    this.$dispatch('subject-picked', {
                        ...this.selection
                    });
                    this.searchResults = [];
                    this.closePanel();
                },
                performSearch() {
                    if (!this.searchTerm || this.searchTerm.length < 2) {
                        this.searchResults = [];
                        return;
                    }

                    const url = new URL(this.fetchUrl, window.location.origin);
                    url.searchParams.set('q', this.searchTerm);
                    url.searchParams.set('limit', this.siblingLimit);

                    fetch(url.toString())
                        .then(response => response.json())
                        .then(payload => {
                            this.searchResults = payload.results || [];
                        })
                        .catch(error => console.error('Search failed', error));
                },
                formatCode(value) {
                    const utils = window.Alpine?.store('utils');
                    return utils?.formatCode ? utils.formatCode(value) : value;
                },
            };
        }
    </script>
@endPushOnce
