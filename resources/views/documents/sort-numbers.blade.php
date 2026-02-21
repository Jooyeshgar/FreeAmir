<x-app-layout>
    <x-slot name="header">
		<h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Sort Documents Number') }}</h2>
	</x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl" x-data="documentNumberSort({
        startUrl: @js(route('documents.sort-numbers.start')),
        processUrl: @js(route('documents.sort-numbers.process')),
        csrfToken: @js(csrf_token()),
        initialProgress: @js($progress),
        canStart: @js((int) ($statistics['unused_document_numbers_count'] ?? 0) > 0),
        confirmMessage: @js(__('Are you sure you want to start sorting document numbers?')),
        requestFailedMessage: @js(__('Request failed.')),
        statusLabels: {
            not_started: @js(__('Not started')),
            running: @js(__('Running')),
            completed: @js(__('Completed')),
        },
        shouldAutoRun: @js(($progress['status'] ?? '') === 'running'),
    })" x-init="init()">
        <div class="card-body gap-6">
            <div class="alert alert-warning">
                {{ __('With changing documents number, the printed documents will no longer be valid. This may also affect accounting reports and render them invalid, and if accounting reports were printed, they will likewise be considered invalid.') }}
            </div>

            <div>
                <h3 class="text-lg font-semibold mb-3">{{ __('Document Statistics') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-stat-card :title="__('Total Documents')" :value="formatNumber($statistics['total_documents_count'])" />
                    <x-stat-card :title="__('Automatic Documents')" :value="formatNumber($statistics['automatic_documents_count'])" />
                    <x-stat-card :title="__('Manual Documents')" :value="formatNumber($statistics['manual_documents_count'])" />
                    <x-stat-card :title="__('Minimum Document Date')" :value="$statistics['min_document_date'] ? formatDate($statistics['min_document_date']) : '-'" />
                    <x-stat-card :title="__('Maximum Document Date')" :value="$statistics['max_document_date'] ? formatDate($statistics['max_document_date']) : '-'" />
                    <x-stat-card :title="__('Minimum Document Number')" :value="$statistics['min_document_number'] !== null ? formatDocumentNumber($statistics['min_document_number']) : '-'" />
                    <x-stat-card :title="__('Maximum Document Number')" :value="$statistics['max_document_number'] !== null ? formatDocumentNumber($statistics['max_document_number']) : '-'" />
                    <x-stat-card :title="__('Unused Document Numbers Count')" :value="formatNumber($statistics['unused_document_numbers_count'])" />
                </div>

                @if (!empty($statistics['unused_document_numbers_samples']))
                    <div class="mt-4">
                        <h4 class="font-medium mb-2">{{ __('Some Unused Document Numbers') }}</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($statistics['unused_document_numbers_samples'] as $missingNumber)
                                <span class="badge badge-outline">{{ formatDocumentNumber($missingNumber) }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="card bg-gray-50">
                <div class="card-body p-6">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('Sort Progress') }}</h3>
                    <div class="bg-gray-200 rounded-full overflow-hidden relative h-5">
                        <div class="bg-blue-600 transition-all duration-300 h-5" :style="`width: ${progress.percent}%`"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700">
                            <span x-text="progress.percent + '%'"></span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600">
                        <span>{{ __('Processed') }}:
                            <span x-text="progress.processed_label"></span> /
                            <span x-text="progress.total_label"></span>
                        </span>
                    </div>

                    <div class="text-sm text-gray-500">{{ __('Status') }}:
                        <span class="font-semibold text-gray-700" x-text="statusText"></span>
                    </div>

                    <div class="flex justify-end mt-2 relative">
                        <div class="relative group" x-show="isStartDisabled">
                            <div class="absolute bottom-full mb-2 hidden group-hover:block bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                {{ __('No need to sort because document numbers are already sorted.') }}
                            </div>
                        </div>
                        <button type="button" class="px-4 py-2 rounded-lg text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed
                            transition duration-200" :disabled="isStartDisabled" @click="startSort()">
                            {{ __('Start Sorting Numbers') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @pushOnce('scripts')
        <script>
            function documentNumberSort(config) {
                return {
                    startUrl: config.startUrl,
                    processUrl: config.processUrl,
                    csrfToken: config.csrfToken,
                    confirmMessage: config.confirmMessage,
                    requestFailedMessage: config.requestFailedMessage,
                    statusLabels: config.statusLabels,
                    progress: {
                        ...config.initialProgress,
                        percent: Number(config.initialProgress.percent || 0),
                    },
                    canStart: config.canStart,
                    isProcessing: false,
                    shouldAutoRun: config.shouldAutoRun,

                    get statusText() {
                        return this.statusLabels[this.progress.status] || this.progress.status;
                    },

                    get isStartDisabled() {
                        return !this.canStart || this.isProcessing || this.progress.status === 'running';
                    },

                    init() {
                        if (this.shouldAutoRun) {
                            this.processAllBatches();
                        }
                    },

                    setProgress(progress) {
                        this.progress = {
                            ...this.progress,
                            ...progress,
                            percent: Number(progress.percent || 0),
                        };
                    },

                    async postJson(url) {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({}),
                        });

                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.message || this.requestFailedMessage);
                        }

                        return payload;
                    },

                    async processAllBatches() {
                        if (this.isProcessing) {
                            return;
                        }

                        this.isProcessing = true;

                        try {
                            while (true) {
                                const progress = await this.postJson(this.processUrl);
                                this.setProgress(progress);

                                if (progress.status !== 'running') {
                                    window.location.reload();
                                    break;
                                }
                            }
                        } catch (error) {
                            alert(error.message);
                        } finally {
                            this.isProcessing = false;
                        }
                    },

                    async startSort() {
                        if (this.isStartDisabled) {
                            return;
                        }

                        if (!window.confirm(this.confirmMessage)) {
                            return;
                        }

                        try {
                            const progress = await this.postJson(this.startUrl);
                            this.setProgress(progress);
                            if (progress.status === 'running') {
                                this.processAllBatches();
                            }
                        } catch (error) {
                            alert(error.message);
                        }
                    },
                };
            }
        </script>
    @endPushOnce
</x-app-layout>
