<x-app-layout :title="__('cheques calendar')">
    <div class="space-y-4">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="flex justify-between">
                    <h1 class="card-title">{{ __('cheques calendar') }}</h1>
                    <a class="btn btn-ghost btn-sm" href="{{ route('cheques.index') }}">{{ __('cheques back') }}</a>
                </div>
                <form class="grid gap-3 sm:grid-cols-3">
                    <x-date-picker name="from" :value="request('from', toEnglish(formatDate($from)))" :placeholder="__('cheques due_from')" />
                    <x-date-picker name="to" :value="request('to', toEnglish(formatDate($to)))" :placeholder="__('cheques due_to')" />
                    <button class="btn btn-primary">{{ __('cheques show') }}</button>
                </form>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($cheques->groupBy(fn($cheque) => $cheque->due_date->toDateString()) as $date => $items)
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-4">
                        <h2 class="font-bold text-lg">{{ formatDate($date) }}</h2>
                        <div class="space-y-2">
                            @foreach ($items as $cheque)
                                <a href="{{ route('cheques.show', $cheque) }}" class="block rounded-box border p-3 hover:bg-base-200">
                                    <div class="flex justify-between">
                                        <span>{{ localizeNumber($cheque->serial) }}</span>
                                        <span class="badge badge-{{ $cheque->status->color() }} badge-sm">{{ $cheque->status->label() }}</span>
                                    </div>
                                    <div class="text-sm opacity-70">{{ $cheque->party?->name }}</div>
                                    <div class="font-bold">{{ formatNumber($cheque->amount) }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
            <div class="alert sm:col-span-2 lg:col-span-3">{{ __('cheques empty') }}</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
