<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $customer->name }}</h2>

            <div class="flex flex-wrap gap-2 mt-2">
                @if ($customer->group)
                    <span class="badge badge-lg badge-success gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        {{ $customer->group->name }}
                    </span>
                @endif

                @if ($customer->subject)
                    <span class="badge badge-lg badge-accent gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <a href="{{ route('transactions.index', ['subject_id' => $customer->subject->id]) }}">{{ $customer->subject->formattedCode() }}</a>
                    </span>
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <a class="stats shadow bg-gradient-to-br from-success/10 to-success/5 border border-success/20"
                    href="{{ route('transactions.index', ['subject_id' => $customer->subject->id]) }}">
                    <div class="stat">
                        <div class="stat-title text-success">{{ __('Subject Balance') }}</div>
                        <div class="stat-value text-success text-2xl">{{ formatNumber($subjectBalance ?? 0) }}</div>
                        <div class="stat-desc">{{ __('Ledger balance') }}</div>
                    </div>
                </a>

                <div class="stats shadow bg-gradient-to-br from-info/10 to-info/5 border border-info/20">
                    <div class="stat">
                        <div class="stat-title text-info">{{ __('Credit') }}</div>
                        <div class="stat-value text-info text-2xl">{{ formatNumber($customer->credit ?? 0) }}</div>
                        <div class="stat-desc">{{ __('Credit limit') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-neutral/10 to-neutral/5 border border-neutral/20">
                    <div class="stat">
                        <div class="stat-title">{{ __('Orders') }}</div>
                        <div class="stat-value text-2xl">{{ formatNumber($orders?->total() ?? 0) }}</div>
                        <div class="stat-desc">{{ __('Total invoices') }}</div>
                    </div>
                </div>
            </div>

            <div class="divider text-lg font-semibold">{{ __('Identity Information') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Name') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Account Plan Group') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->group->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('National ID') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->personal_code ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Economic code') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->ecnmcs_code ?? '-' }}</p>
                    </div>
                </div>
            </div>

            @if ($customer->desc)
                <div class="divider text-lg font-semibold">{{ __('Description') }}</div>
                <div class="alert bg-base-200 shadow-sm mb-6">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>{{ $customer->desc }}</span>
                    </div>
                </div>
            @endif

            <div class="divider text-lg font-semibold">{{ __('Contact Information') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Phone') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->tel ?? ($customer->phone ?? '-') }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Mobile') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->cell ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Email') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->email ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Website') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->web_page ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Fax') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->fax ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Postal code') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->postal_code ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow lg:col-span-2">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Address') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->address ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="divider text-lg font-semibold">{{ __('Financial Information') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Account 1') }}</h3>
                        <div class="text-sm text-gray-600">{{ __('Name') }}: <span
                                class="font-semibold text-base-content">{{ $customer->acc_name_1 ?? '-' }}</span></div>
                        <div class="text-sm text-gray-600">{{ __('Account number') }}: <span
                                class="font-semibold text-base-content">{{ $customer->acc_no_1 ?? '-' }}</span></div>
                        <div class="text-sm text-gray-600">{{ __('Bank') }}: <span
                                class="font-semibold text-base-content">{{ $customer->acc_bank_1 ?? '-' }}</span></div>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Account 2') }}</h3>
                        <div class="text-sm text-gray-600">{{ __('Name') }}: <span
                                class="font-semibold text-base-content">{{ $customer->acc_name_2 ?? '-' }}</span></div>
                        <div class="text-sm text-gray-600">{{ __('Account number') }}: <span
                                class="font-semibold text-base-content">{{ $customer->acc_no_2 ?? '-' }}</span></div>
                        <div class="text-sm text-gray-600">{{ __('Bank') }}: <span
                                class="font-semibold text-base-content">{{ $customer->acc_bank_2 ?? '-' }}</span></div>
                    </div>
                </div>
            </div>

            <div class="divider text-lg font-semibold">{{ __('Other Information') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Connector') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->connector ?? '-' }}</p>
                    </div>
                </div>

                <div class="card bg-base-200 shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-sm text-gray-500">{{ __('Responsible') }}</h3>
                        <p class="text-xl font-semibold">{{ $customer->responsible ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="divider text-lg font-semibold">{{ __('Orders') }}</div>
            <div class="overflow-x-auto shadow-lg rounded-lg">
                <table class="table table-zebra w-full">
                    <thead class="bg-base-300">
                        <tr>
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3">{{ __('Invoice Number') }}</th>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Price') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr class="hover">
                                <td class="px-4 py-3">
                                    <span class="badge badge-ghost">{{ $order->date ? formatDate($order->date) : '-' }}</span>
                                </td>
                                <td class="px-4 py-3 font-semibold">
                                    <a class="link link-hover" href="{{ route('invoices.show', $order) }}">
                                        {{ isset($order->number) ? formatDocumentNumber($order->number) : $order->id ?? '-' }}
                                    </a>
                                    @if ($order->title)
                                        <div class="text-xs text-gray-500">{{ $order->title }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge badge-outline">{{ $order->invoice_type?->label() ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $order->status?->isApproved() ? 'badge-success' : 'badge-ghost' }}">
                                        {{ $order->status?->label() ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">
                                    {{ formatNumber(($order->amount ?? 0) - ($order->subtraction ?? 0)) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('invoices.show', $order) }}" class="btn btn-sm btn-info">{{ __('Show') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-gray-500">
                                    {{ __('No orders found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="mt-6 flex justify-center">
                    {{ $orders->links() }}
                </div>
            @endif

            <div class="card-actions justify-between mt-8">
                <a href="{{ route('customers.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>

                <div class="flex gap-2">
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary">{{ __('Edit') }}</a>
                    <form action="{{ route('customers.destroy', $customer) }}" method="POST"
                        onsubmit="return confirm('{{ __('Are you sure you want to delete this customer?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
