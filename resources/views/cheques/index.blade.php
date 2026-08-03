@php
    $filterKeys = ['q', 'direction', 'purpose', 'status', 'amount_min', 'amount_max', 'due_from', 'due_to', 'customer_id'];
    $activeFilters = array_filter(request()->only($filterKeys), static fn ($value) => filled($value));
@endphp

<x-app-layout :title="__('Cheque Management')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Cheque Management') }}</h1>
            <p class="mt-0.5 text-sm text-base-content/50">{{ __('Manage cheques and their lifecycle') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <a href="{{ route('cheques.create') }}" class="btn btn-sm btn-primary">{{ __('Register cheque') }}</a>
            @can('cheques.chequebooks.index')
                <a href="{{ route('cheques.chequebooks.index') }}" class="btn btn-sm btn-outline">{{ __('Chequebooks') }}</a>
            @endcan
            <a href="{{ route('cheques.report', $activeFilters) }}" class="btn btn-sm btn-outline">{{ __('Cheque Report') }}</a>
        </div>
    </div>

    {{-- Cheque List --}}
    <div class="card mx-1 mb-6 border border-base-200 bg-base-100 shadow-sm">
        <div class="card-body p-0">
            {{-- Card Header: title + filters --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-5 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Cheque list') }}</h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($cheques->total()) }} {{ __('records') }}
                    </span>
                </div>

                <form action="{{ route('cheques.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <div class="relative w-64 max-w-full [&_.input]:input-sm">
                        <x-input type="search" name="q" id="q" :value="request('q')"
                            :placeholder="__('Number, serial, Sayad, or account side…')" />
                        <button type="submit" aria-label="{{ __('Search') }}"
                            class="absolute inset-y-0 left-2 flex cursor-pointer items-center text-base-content/40 hover:text-base-content">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                            </svg>
                        </button>
                    </div>

                    <select name="direction" aria-label="{{ __('Direction') }}" class="select select-sm w-36">
                        <option value="">{{ __('All directions') }}</option>
                        @foreach ($directions as $item)
                            <option value="{{ $item->value }}" @selected((string) request('direction') === (string) $item->value)>{{ $item->label() }}</option>
                        @endforeach
                    </select>

                    <select name="purpose" aria-label="{{ __('Purpose') }}" class="select select-sm w-36">
                        <option value="">{{ __('All purposes') }}</option>
                        @foreach ($purposes as $item)
                            <option value="{{ $item->value }}" @selected((string) request('purpose') === (string) $item->value)>{{ $item->label() }}</option>
                        @endforeach
                    </select>

                    <select name="status" aria-label="{{ __('Status') }}" class="select select-sm w-36">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $item)
                            <option value="{{ $item->value }}" @selected((string) request('status') === (string) $item->value)>{{ $item->label() }}</option>
                        @endforeach
                    </select>

                    <div class="w-32 [&_.input]:input-sm">
                        <x-input name="amount_min" id="amount_min" :value="request('amount_min')"
                            :placeholder="__('Minimum amount')" inputmode="decimal" />
                    </div>

                    <div class="w-32 [&_.input]:input-sm">
                        <x-input name="amount_max" id="amount_max" :value="request('amount_max')"
                            :placeholder="__('Maximum amount')" inputmode="decimal" />
                    </div>

                    <div class="w-24 [&_.input]:input-sm">
                        <x-date-picker name="due_from" id="due_from" :value="request('due_from')" :placeholder="__('Due from')" />
                    </div>

                    <div class="w-24 [&_.input]:input-sm">
                        <x-date-picker name="due_to" id="due_to" :value="request('due_to')" :placeholder="__('Due to')" />
                    </div>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                    @if ($activeFilters !== [])
                        <a href="{{ route('cheques.index') }}" class="btn btn-sm btn-ghost">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            {{-- Cards --}}
            <div class="p-4 sm:p-5">
                @if ($cheques->count())
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($cheques as $cheque)
                            @php
                                $isOverdue = $cheque->due_date->isPast() && ! in_array($cheque->status, [
                                    \App\Enums\ChequeType::CLEARED,
                                    \App\Enums\ChequeType::CANCELLED,
                                    \App\Enums\ChequeType::RETURNED,
                                ], true);
                            @endphp
                            <div class="card rounded-lg border border-base-200 bg-base-100 shadow-sm transition hover:border-primary/30 hover:shadow-md dark:bg-base-200/40">
                                <div class="card-body gap-4 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-bold text-base-content">
                                                <a href="{{ route('cheques.show', $cheque) }}" class="hover:text-primary">
                                                    {{ $cheque->cheque_number ? __('Cheque #:number', ['number' => localizeNumber($cheque->cheque_number)]) : __('Cheque') }}
                                                </a>
                                            </h3>
                                            <p class="truncate text-xs text-base-content/50">
                                                {{ __('Cheque serial') }}: {{ localizeNumber($cheque->serial) ?: '—' }}
                                            </p>
                                            <p class="truncate text-xs text-base-content/50">
                                                {{ __('Sayad number') }}: {{ localizeNumber($cheque->sayad_number) }}
                                            </p>
                                        </div>
                                        <span class="badge badge-{{ $cheque->status->color() }} badge-sm shrink-0">{{ $cheque->status->label() }}</span>
                                    </div>

                                    <div class="space-y-2 text-sm text-base-content/65">
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ __('Account side') }}</span>
                                            <span class="truncate font-medium text-base-content">{{ $cheque->customer?->name ?? '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ __('Direction') }}</span>
                                            <span class="flex flex-wrap items-center justify-end gap-1 font-medium text-base-content">
                                                {{ $cheque->direction->label() }}
                                                @if ($cheque->purpose === \App\Enums\ChequeType::GUARANTEE)
                                                    <span class="badge badge-warning badge-xs">{{ $cheque->purpose->label() }}</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ __('Due date') }}</span>
                                            <span class="font-medium {{ $isOverdue ? 'text-error' : 'text-base-content' }}">{{ formatDate($cheque->due_date) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 border-t border-base-200 pt-2">
                                            <span>{{ __('Amount') }}</span>
                                            <span class="font-bold text-base-content">{{ formatNumber($cheque->amount) }}</span>
                                        </div>
                                    </div>

                                    <div class="card-actions justify-end border-t border-base-200 pt-3">
                                        <a href="{{ route('cheques.show', $cheque) }}" class="btn btn-xs btn-ghost">{{ __('View') }}</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center text-base-content/50">
                        <p>{{ __('No cheques found.') }}</p>
                    </div>
                @endif
            </div>

            @if ($cheques->hasPages())
                <div class="border-t border-base-200 px-5 py-4">
                    {{ $cheques->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
