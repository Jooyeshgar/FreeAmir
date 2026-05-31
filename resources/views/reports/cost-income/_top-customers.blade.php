@php
    $lists = [
        [
            'title' => __('Top Debtors'),
            'subtitle' => __('Customers who owe the business'),
            'rows' => $debtors ?? [],
        ],
        [
            'title' => __('Top Creditors'),
            'subtitle' => __('Customers the business owes'),
            'rows' => $creditors ?? [],
        ],
    ];
@endphp

@foreach ($lists as $list)
    <article class="card border border-base-300 bg-base-100/90 shadow-sm">
        <div class="card-body p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="card-title text-base">{{ $list['title'] }}</h2>
                    <p class="text-xs text-base-content/55">{{ $list['subtitle'] }}</p>
                </div>
            </div>

            <div class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between border-b border-base-300 pb-2 text-xs font-semibold text-base-content/55">
                    <span>{{ __('Customer') }}</span>
                    <span>{{ __('Amount') }}</span>
                </div>

                @forelse ($list['rows'] as $row)
                    <div class="flex items-center justify-between rounded-lg p-2 transition hover:bg-base-200/70">
                        <a href="{{ route('transactions.index', ['subject_id' => $row['subject_id']]) }}"
                            class="link link-hover font-medium">
                            {{ $row['name'] }}
                        </a>
                        <span class="font-mono">
                            {{ convertToFarsi(number_format($row['amount'])) }}
                        </span>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-base-300 bg-base-200/50 p-5 text-center text-sm text-base-content/60">
                        {{ __('No customers found.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </article>
@endforeach
