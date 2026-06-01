<x-app-layout :title="$customerGroup->name">
    <x-show-message-bags />

    @php
        $currency = config('amir.currency') ?? __('Rial');

        $statItems = [
            [
                'title' => __('Customers Count'),
                'value' => convertToFarsi(formatNumber($stats['customersCount'])),
                'description' => __('Members in this group'),
                'icon' => 'users',
                'tone' => 'indigo',
                'href' => route('customers.index', ['group_name' => $customerGroup->name]),
            ],
            [
                'title' => __('Net Sales'),
                'value' => convertToFarsi(formatNumber($stats['netSales'])),
                'description' => $currency,
                'icon' => 'check',
                'tone' => 'green',
            ],
            [
                'title' => __('Total Sales'),
                'value' => convertToFarsi(formatNumber($stats['totalSales'])),
                'description' => $currency,
                'icon' => 'briefcase',
                'tone' => 'sky',
            ],
            [
                'title' => __('Sales Returns'),
                'value' => convertToFarsi(formatNumber($stats['totalReturns'])),
                'description' => $currency,
                'icon' => 'document',
                'tone' => 'amber',
            ],
            [
                'title' => __('Invoices Count'),
                'value' => convertToFarsi(formatNumber($stats['invoicesCount'])),
                'description' => __('Approved sell invoices'),
                'icon' => 'calendar',
                'tone' => 'violet',
            ],
        ];

        if (auth()->user()?->can('reports.ledger') && $customerGroup->subject) {
            $statItems[] = [
                'title' => __('Subject Balance'),
                'value' => convertToFarsi(formatNumber($stats['subjectBalance'])),
                'description' => $currency,
                'icon' => 'clock',
                'tone' => 'cyan',
                'href' => route('transactions.index', ['subject_id' => $customerGroup->subject->id]),
            ];
        }

        $avatarTones = [
            'bg-blue-600 text-white',
            'bg-emerald-600 text-white',
            'bg-amber-500 text-white',
            'bg-rose-600 text-white',
            'bg-violet-600 text-white',
            'bg-cyan-600 text-white',
        ];
    @endphp

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold text-base-content">
                    <a href="{{ route('customers.index', ['group_name' => $customerGroup->name]) }}" class="hover:text-primary transition-colors">
                        {{ $customerGroup->name }}
                    </a>
                </h1>
                @if ($customerGroup->subject)
                    <a href="{{ route('transactions.index', ['subject_id' => $customerGroup->subject->id]) }}"
                        class="badge badge-accent gap-1.5 hover:brightness-110 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        {{ $customerGroup->subject->formattedCode() }}
                    </a>
                @endif
            </div>
            <p class="text-sm text-base-content/50">
                {{ $customerGroup->description ?: __('Customer group overview') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2" dir="ltr">
            <a href="{{ route('customers.index', ['group_name' => $customerGroup->name]) }}" class="btn btn-sm btn-outline gap-1.5" dir="rtl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 20v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1m8-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm8 13v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                {{ __('Customers') }}
            </a>
            <a href="{{ route('customer-groups.edit', $customerGroup) }}" class="btn btn-sm btn-primary gap-1.5" dir="rtl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                </svg>
                {{ __('Edit') }}
            </a>
            <a href="{{ route('customer-groups.index') }}" class="btn btn-sm btn-ghost gap-1.5" dir="rtl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('Back') }}
            </a>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="mb-6 px-1">
        <x-stat-strip :items="$statItems" />
    </div>

    <div class="grid grid-cols-1 gap-6 px-1 lg:grid-cols-5">
        {{-- Top Customers --}}
        <div class="card bg-base-100 shadow-sm border border-base-200 lg:col-span-2">
            <div class="card-body p-0">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-base-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                    <h2 class="text-base font-bold text-base-content">{{ __('Top Customers') }}</h2>
                </div>

                <div class="p-4 sm:p-5">
                    @if ($stats['topCustomers']->isNotEmpty())
                        <ul class="space-y-3">
                            @foreach ($stats['topCustomers'] as $rank => $row)
                                @php $tone = $avatarTones[$row['customer']->id % count($avatarTones)]; @endphp
                                <li class="flex items-center gap-3 rounded-lg border border-base-200 bg-base-100 p-3 transition hover:border-primary/30 hover:shadow-sm dark:bg-base-200/40">
                                    <span class="text-sm font-bold text-base-content/40 w-5 text-center">{{ convertToFarsi($rank + 1) }}</span>
                                    <div class="avatar placeholder shrink-0">
                                        <div class="{{ $tone }} flex h-11 w-11 items-center justify-center rounded-xl">
                                            <span class="text-base font-bold leading-none">{{ mb_substr($row['customer']->name, 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-grow">
                                        <a href="{{ route('customers.show', $row['customer']) }}" class="truncate block font-bold text-base-content hover:text-primary transition-colors">
                                            {{ $row['customer']->name }}
                                        </a>
                                        <span class="text-xs text-base-content/50">
                                            {{ convertToFarsi(formatNumber($row['count'])) }} {{ __('invoices') }}
                                        </span>
                                    </div>
                                    <div class="text-left shrink-0">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400 leading-none">{{ convertToFarsi(formatNumber($row['total'])) }}</div>
                                        <div class="mt-1 text-[10px] text-base-content/40">{{ $currency }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center justify-center py-10 text-base-content/35">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                            <p class="text-sm font-medium">{{ __('No sales recorded yet.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Recent Invoices --}}
        <div class="card bg-base-100 shadow-sm border border-base-200 lg:col-span-3">
            <div class="card-body p-0">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-base-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z" />
                    </svg>
                    <h2 class="text-base font-bold text-base-content">{{ __('Recent Invoices') }}</h2>
                </div>

                <div class="p-2 sm:p-3">
                    @if ($stats['recentInvoices']->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr class="text-base-content/50">
                                        <th>{{ __('Number') }}</th>
                                        <th>{{ __('Customer') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th class="text-left">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stats['recentInvoices'] as $invoice)
                                        <tr class="hover">
                                            <td>
                                                <a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-primary hover:underline">
                                                    {{ convertToFarsi($invoice->number) }}
                                                </a>
                                            </td>
                                            <td class="max-w-40 truncate">{{ $invoice->customer?->name ?? '-' }}</td>
                                            <td>
                                                <span class="badge badge-sm {{ $invoice->invoice_type === \App\Enums\InvoiceType::SELL ? 'badge-success' : 'badge-warning' }} badge-outline">
                                                    {{ $invoice->invoice_type->label() }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap text-base-content/60">{{ convertToFarsi(formatDate($invoice->date)) }}</td>
                                            <td class="text-left font-semibold whitespace-nowrap">{{ convertToFarsi(formatNumber($invoice->amount)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-10 text-base-content/35">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z" />
                            </svg>
                            <p class="text-sm font-medium">{{ __('No invoices found for this group.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
