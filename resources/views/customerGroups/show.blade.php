<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><a
                    href="{{ route('customers.index', ['group_name' => $customerGroup->name]) }}">
                    {{ $customerGroup->name }}
                </a>
            </h2>

            <div class="flex flex-wrap gap-2 mt-2">
                @if ($customerGroup->subject)
                    <span class="badge badge-lg badge-accent gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <a
                            href="{{ route('transactions.index', ['subject_id' => $customerGroup->subject->id]) }}">{{ $customerGroup->subject->formattedCode() }}</a>
                    </span>
                    </a>
                @endif
            </div>

            <div class="max-w-7xl mt-2">
                <div class="overflow-hidden sm:rounded-lg">
                    <p class="text-gray-700"><strong>{{ __('Description') }}:</strong>
                        {{ $customerGroup->description }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-3">
                <x-stat-card :title="__('Subject Balance')" :value="formatNumber($customerGroup->balance ?? 0)" type="info" />
                <x-stat-card :title="__('Credit')" :value="formatNumber($customerGroup->credit ?? 0)" type="info" />
            </div>
        </div>
</x-app-layout>
