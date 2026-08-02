<x-app-layout :title="__('cheques title')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="card-title">{{ __('cheques title') }}</h1>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('cheques.report') }}" class="btn btn-outline btn-sm">{{ __('cheques report') }}</a>
                    <a href="{{ route('cheques.calendar') }}" class="btn btn-outline btn-sm">{{ __('cheques calendar') }}</a>
                    <a href="{{ route('cheques.weighted-average-maturity') }}" class="btn btn-outline btn-sm">{{ __('cheques weighted_average_maturity') }}</a>
                    <a href="{{ route('checkbooks.index') }}" class="btn btn-outline btn-sm">{{ __('cheques checkbooks') }}</a>
                    <a href="{{ route('cheques.create') }}" class="btn btn-primary btn-sm">{{ __('cheques create') }}</a>
                </div>
            </div>
            <form method="GET" class="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <input name="q" value="{{ request('q') }}" placeholder="{{ __('cheques search_placeholder') }}" class="input input-bordered input-sm xl:col-span-2">
                <select name="direction" class="select select-bordered select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($directions as $item)
                        <option value="{{ $item->value }}" @selected((string) request('direction') === (string) $item->value)>{{ $item->label() }}</option>
                    @endforeach
                </select>
                <select name="status" class="select select-bordered select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($statuses as $item)
                        <option value="{{ $item->value }}" @selected((string) request('status') === (string) $item->value)>{{ $item->label() }}</option>
                    @endforeach
                </select>
                <input name="amount_min" value="{{ request('amount_min') }}" placeholder="{{ __('cheques amount_min') }}" class="input input-bordered input-sm">
                <input name="amount_max" value="{{ request('amount_max') }}" placeholder="{{ __('cheques amount_max') }}" class="input input-bordered input-sm">
                <x-date-picker name="due_from" id="due_from" :value="request('due_from')" :placeholder="__('cheques due_from')" />
                <x-date-picker name="due_to" id="due_to" :value="request('due_to')" :placeholder="__('cheques due_to')" />
                <select name="customer_id" class="select select-bordered select-sm">
                    <option value="">{{ __('cheques all_parties') }}</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((string) request('customer_id') === (string) $party->id)>{{ $party->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
            </form>
        </div>
    </div>

    <div class="card bg-base-100 shadow-xl overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Serial') }}</th>
                    <th>{{ __('Direction') }}</th>
                    <th>{{ __('Party') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Due date') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($cheques as $cheque)
                    <tr class="hover">
                        <td><a class="link link-primary" href="{{ route('cheques.show', $cheque) }}">{{ localizeNumber($cheque->serial) }}</a>
                            <div class="text-xs opacity-60">{{ localizeNumber($cheque->sayad_number) }}</div>
                        </td>
                        <td>
                            {{ $cheque->direction->label() }}
                            @if ($cheque->purpose === \App\Enums\ChequeType::GUARANTEE)
                                <span class="badge badge-warning badge-sm ms-1">{{ $cheque->purpose->label() }}</span>
                            @endif
                        </td>
                        <td>{{ $cheque->party?->name }}</td>
                        <td class="font-semibold">{{ formatNumber($cheque->amount) }}</td>
                        <td class="{{ $cheque->due_date->isPast() && !in_array($cheque->status, [\App\Enums\ChequeType::CLEARED, \App\Enums\ChequeType::CANCELLED, \App\Enums\ChequeType::RETURNED]) ? 'text-error font-bold' : '' }}">
                            {{ formatDate($cheque->due_date) }}
                        </td>
                        <td>
                            <span class="badge badge-{{ $cheque->status->color() }}">{{ $cheque->status->label() }}</span>
                        </td>
                        <td>
                            <a class="btn btn-ghost btn-xs" href="{{ route('cheques.show', $cheque) }}">{{ __('cheques view') }}</a>
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-10 opacity-60">{{ __('cheques empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $cheques->links() }}</div>
        </div>
    </div>
</x-app-layout>
