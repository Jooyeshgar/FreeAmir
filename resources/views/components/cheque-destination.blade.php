@php
    $destination = \App\Models\Subject::whereNotNull('parent_id')->find(old('destination_subject_id'));
@endphp
<div x-data="{
    selectedId: @js($destination?->id ?? ''),
    selectedLabel: @js($destination?->name ?? ''),
    query: '', results: [], loading: false, failed: false, requestId: 0,
    async searchSubjects() {
        const id = ++this.requestId;
        this.loading = true;
        this.failed = false;
        try {
            const response = await fetch(@js(route('cheques.search-subjects')) + '?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Search failed');
            const results = await response.json();
            if (id === this.requestId) this.results = results;
        } catch (error) {
            if (id === this.requestId) { this.failed = true; this.results = []; }
        } finally {
            if (id === this.requestId) this.loading = false;
        }
    }
}">
    <label class="label">{{ __('Receive and transfer cheque') }}</label>
    <input type="hidden" name="destination_subject_id" :value="selectedId">
    <div class="flex gap-2">
        <button type="button" class="btn btn-outline flex-1 min-w-0"
            @click="$refs.destinationDialog.showModal(); searchSubjects(); $nextTick(() => $refs.destinationSearch.focus())">
            <span class="truncate" x-text="selectedLabel || @js(__('Select destination subject'))"></span>
        </button>
        <button type="button" class="btn btn-ghost" x-show="selectedId" x-cloak
            @click="selectedId = ''; selectedLabel = ''">{{ __('Clear') }}</button>
    </div>
    <p class="mt-1 text-xs text-base-content/60">{{ __('Leave empty to receive the cheque normally. Selecting a destination receives and transfers it directly.') }}</p>
    <dialog x-ref="destinationDialog" class="modal">
        <div class="modal-box">
            <h3 class="font-bold mb-3">{{ __('Receive and transfer cheque') }}</h3>
            <input x-ref="destinationSearch" x-model="query" @input.debounce.300ms="searchSubjects()"
                @keydown.enter.prevent type="search" maxlength="100" class="input input-bordered w-full"
                placeholder="{{ __('Search... (heading code or name)') }}" aria-label="{{ __('Search') }}">
            <div class="max-h-72 overflow-y-auto mt-3">
                <p x-show="loading" class="p-3 text-center">{{ __('Loading') }}</p>
                <p x-show="failed && !loading" class="p-3 text-error" role="alert">{{ __('Could not load subjects. Please try again.') }}</p>
                <p x-show="!failed && !loading && results.length === 0" class="p-3 text-center">{{ __('No results found') }}</p>
                <template x-for="subject in results" :key="subject.id">
                    <button type="button" class="btn btn-ghost w-full justify-between font-normal"
                        :disabled="loading" @click="selectedId = subject.id; selectedLabel = subject.name; $refs.destinationDialog.close()">
                        <span class="truncate" x-text="subject.name"></span><span class="text-xs" x-text="subject.code"></span>
                    </button>
                </template>
            </div>
            <div class="modal-action">
                <button type="button" class="btn" @click="$refs.destinationDialog.close()">{{ __('Close') }}</button>
            </div>
        </div>
    </dialog>
</div>
