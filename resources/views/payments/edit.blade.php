<x-app-layout :title="__('Edit Payment')">
    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <form method="POST" action="{{ route('invoices.payments.update', [$invoice, $payment]) }}" class="card-body space-y-4">
            @csrf
            @method('PUT')
            <h1 class="card-title">{{ __('Edit Payment') }}</h1>
            <x-show-message-bags />
            <div>
                <label class="label">{{ __('Settlement Account') }}</label>
                @php
                    $settlementGroups = $settlementSubjects
                        ->groupBy(fn($subject) => $subject->parent?->name ?? __('Other'))
                        ->map(
                            fn($group, $label) => [
                                'label' => (string) $label,
                                'options' => $group
                                    ->map(
                                        fn($subject) => [
                                            'id' => $subject->id,
                                            'name' => $subject->name,
                                            'code' => $subject->formattedCode(),
                                        ],
                                    )
                                    ->values(),
                            ],
                        )
                        ->values();
                @endphp
                <div x-data="paymentEditSettlementSelect(@js($settlementGroups), @js(route('invoices.payments.search-subjects', $invoice)), @js(old('subject_id', $selectedSubject?->id)), @js($selectedSubject?->name))" @click.outside="open = false" class="relative">
                    <input type="hidden" name="subject_id" :value="selectedId">
                    <button type="button" @click="toggle()"
                        class="input input-bordered w-full flex items-center justify-between text-left">
                        <span class="block truncate" :class="{ 'text-gray-400': !selectedLabel }"
                            x-text="selectedLabel || '{{ __('Select an account') }}'"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-5 5-5-5" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity
                        class="absolute left-0 right-0 z-[100] mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden">
                        <div class="p-2 border-b border-base-200">
                            <input x-ref="settlementSearch" x-model="search" type="text" @input.debounce.300ms="searchRemote()"
                                class="input input-sm w-full" placeholder="{{ __('Search') }}" @click.stop>
                        </div>
                        <ul class="overflow-y-auto p-1">
                            <li x-show="loading" class="p-4 text-center text-sm">{{ __('Loading') }}</li>
                            <template x-for="group in filteredGroups" :key="group.label">
                                <li>
                                    <div class="px-3 pt-2 pb-1 text-xs font-semibold uppercase text-gray-500" x-text="group.label">
                                    </div>
                                    <template x-for="opt in group.options" :key="opt.id">
                                        <div @click="select(opt)"
                                            class="px-4 py-2 cursor-pointer hover:bg-base-200 rounded-btn flex justify-between items-center text-sm"
                                            :class="{ 'bg-primary text-primary-content': Number(selectedId) === opt.id }">
                                            <span class="block truncate" x-text="opt.name"></span>
                                            <span class="text-xs text-gray-500 ms-2" x-text="opt.code"></span>
                                        </div>
                                    </template>
                                </li>
                            </template>
                            <li x-show="!loading && filteredGroups.length === 0" class="p-4 text-center text-gray-500 italic text-sm">
                                {{ __('No results found') }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div>
                <label class="label" for="payment_amount">{{ __('Amount') }}</label>
                <x-text-input input_name="amount" id_input="payment_amount" input_class="input-bordered locale-number" :input_value="old('amount', $payment->amount)" required />
                <p class="text-sm">{{ __('Remaining Amount') }}: {{ formatNumber($remainingAmount) }}</p>
            </div>
            <div>
                <label class="label" for="payment_fee">{{ __('Bank service fee') }}</label>
                <x-text-input input_name="bank_service_fee" id_input="payment_fee" input_class="input-bordered locale-number" :input_value="old('bank_service_fee', $payment->bank_service_fee)" />
            </div>
            <div>
                <label class="label" for="payment_date">{{ __('Date') }}</label>
                <x-text-input data-jdp input_name="date" id_input="payment_date" input_class="input-bordered" :input_value="old('date', $payment->date ? convertToJalali($payment->date, true) : '')" autocomplete="off" readonly />
            </div>
            <div>
                <label class="label" for="payment_reference">{{ __('Reference Number') }}</label>
                <x-text-input input_name="reference_number" id_input="payment_reference" input_class="input-bordered" :input_value="old('reference_number', $payment->reference_number)" maxlength="20" />
            </div>
            <div>
                <label class="label" for="payment_description">{{ __('Description') }}</label>
                <textarea name="description" id="payment_description" class="textarea textarea-bordered w-full" maxlength="1000">{{ old('description', $payment->description) }}</textarea>
            </div>
            <div class="card-actions justify-end">
                <a href="{{ route('invoices.show', $invoice) }}" class="btn">{{ __('Cancel') }}</a>
                <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
    @pushOnce('scripts')
    <script>
        function paymentEditSettlementSelect(groups, url, selectedId = '', selectedLabel = '') {
            return {
                open: false,
                search: '',
                groups: groups || [],
                initialGroups: groups || [],
                loading: false,
                requestId: 0,
                selectedId,
                selectedLabel,
                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.settlementSearch?.focus());
                    }
                },
                get filteredGroups() {
                    return this.groups;
                },
                async searchRemote() {
                    const requestId = ++this.requestId;
                    const q = this.search.trim();
                    if (!q) {
                        this.groups = this.initialGroups;
                        this.loading = false;
                        return;
                    }
                    this.loading = true;
                    this.groups = [];
                    try {
                        const response = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!response.ok) throw new Error('Settlement search failed');
                        const results = await response.json();
                        if (requestId === this.requestId) this.groups = results;
                    } catch {
                        if (requestId === this.requestId) this.groups = [];
                    } finally {
                        if (requestId === this.requestId) this.loading = false;
                    }
                },
                select(opt) {
                    this.selectedId = opt.id;
                    this.selectedLabel = opt.name;
                    this.open = false;
                    this.search = '';
                    this.requestId++;
                    this.loading = false;
                    this.groups = this.initialGroups;
                },
            };
        }
    </script>
    @endPushOnce
    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({persianDigits: true});
        </script>
    @endpush
</x-app-layout>
