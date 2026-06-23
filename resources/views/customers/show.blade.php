<x-app-layout :title="$customer->name">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $customer->name }}</h2>

                    <div class="flex flex-wrap gap-2 mt-2">
                        @if ($customer->group)
                            <a href="{{ route('customer-groups.show', ['customer_group' => $customer->group->id]) }}"
                                class="badge badge-lg badge-primary gap-2 hover:badge-primary hover:brightness-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                {{ $customer->group->name }}
                            </a>
                        @endif

                        @if ($customer->subject)
                            <a href="{{ route('transactions.index', ['subject_id' => $customer->subject_id]) }}"
                                class="badge badge-lg badge-accent gap-2 hover:brightness-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                {{ $customer->subject->formattedCode() }}
                            </a>
                        @endif

                        <span class="badge badge-lg badge-ghost gap-2">{{ $customer->type->label() }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-primary gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {{ __('Edit') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            {{-- Key figures --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <a class="stats shadow bg-gradient-to-br from-success/10 to-success/5 border border-success/20"
                    href="{{ route('transactions.index', ['subject_id' => $customer->subject_id]) }}">
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

            {{-- Contact information — the most important block, kept prominent --}}
            <div class="rounded-2xl border border-primary/20 bg-primary/5 dark:bg-primary/10 p-5 mb-6">
                <h3 class="flex items-center gap-2 text-lg font-bold text-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    {{ __('Contact Information') }}
                </h3>

                {{-- Primary phone numbers, emphasized and click-to-call --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <a @if ($customer->mobile) href="tel:{{ $customer->mobile }}" @endif
                        class="flex items-center gap-3 rounded-xl bg-base-100 border border-base-300 p-3 {{ $customer->mobile ? 'hover:border-primary hover:shadow-md transition-all' : 'opacity-70 pointer-events-none' }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">{{ __('Mobile') }}</div>
                            <div class="text-lg font-bold truncate text-right" dir="ltr">{{ $customer->mobile ?? '-' }}</div>
                        </div>
                    </a>

                    <a @if ($customer->tel || $customer->phone) href="tel:{{ $customer->tel ?? $customer->phone }}" @endif
                        class="flex items-center gap-3 rounded-xl bg-base-100 border border-base-300 p-3 {{ $customer->tel || $customer->phone ? 'hover:border-primary hover:shadow-md transition-all' : 'opacity-70 pointer-events-none' }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-info/10 text-info">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">{{ __('Phone') }}</div>
                            <div class="text-lg font-bold truncate text-right" dir="ltr">{{ $customer->tel ?? ($customer->phone ?? '-') }}</div>
                        </div>
                    </a>
                </div>

                {{-- Secondary contact details, compact stacked fields --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 rounded-xl bg-base-100 border border-base-300 p-4">
                    <div class="border-b border-base-200/70 pb-2">
                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Email') }}</div>
                        @if ($customer->email)
                            <a href="mailto:{{ $customer->email }}" class="block font-semibold link link-hover truncate text-right" dir="ltr">{{ $customer->email }}</a>
                        @else
                            <div class="font-semibold">-</div>
                        @endif
                    </div>

                    <div class="border-b border-base-200/70 pb-2">
                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Website') }}</div>
                        @if ($customer->web_page)
                            <a href="{{ $customer->web_page }}" target="_blank" rel="noopener noreferrer"
                                class="block font-semibold link link-hover truncate text-right" dir="ltr">{{ $customer->web_page }}</a>
                        @else
                            <div class="font-semibold">-</div>
                        @endif
                    </div>

                    <div class="border-b border-base-200/70 pb-2">
                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Fax') }}</div>
                        <div class="font-semibold truncate text-right" dir="ltr">{{ $customer->fax ?? '-' }}</div>
                    </div>

                    <div class="border-b border-base-200/70 pb-2">
                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Postal code') }}</div>
                        <div class="font-semibold truncate text-right" dir="ltr">{{ $customer->postal_code ?? '-' }}</div>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Address') }}</div>
                        <div class="font-semibold">{{ $customer->address ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- Customer details — compact, secondary information grouped together --}}
            <div class="collapse collapse-arrow border border-base-300 bg-base-100 rounded-2xl mb-6">
                <input type="checkbox" checked />
                <div class="collapse-title text-lg font-semibold">{{ __('Customer Details') }}</div>
                <div class="collapse-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2">
                        <div class="border-b border-base-200/70 pb-2">
                            <div class="text-xs text-gray-500 mb-0.5">{{ __('Name') }}</div>
                            <div class="font-semibold">{{ $customer->name ?? '-' }}</div>
                        </div>

                        <div class="border-b border-base-200/70 pb-2">
                            <div class="text-xs text-gray-500 mb-0.5">{{ __('Account Plan Group') }}</div>
                            @if ($customer->group)
                                <a href="{{ route('customer-groups.show', ['customer_group' => $customer->group->id]) }}"
                                    class="font-semibold link link-hover">{{ $customer->group->name }}</a>
                            @else
                                <div class="font-semibold">-</div>
                            @endif
                        </div>

                        <div class="border-b border-base-200/70 pb-2">
                            <div class="text-xs text-gray-500 mb-0.5">{{ __('National ID') }}</div>
                            <div class="font-semibold text-right" dir="ltr">{{ $customer->personal_code ?? '-' }}</div>
                        </div>

                        <div class="border-b border-base-200/70 pb-2">
                            <div class="text-xs text-gray-500 mb-0.5">{{ __('Economic code') }}</div>
                            <div class="font-semibold text-right" dir="ltr">{{ $customer->ecnmcs_code ?? '-' }}</div>
                        </div>

                        <div class="border-b border-base-200/70 pb-2">
                            <div class="text-xs text-gray-500 mb-0.5">{{ __('Connector') }}</div>
                            <div class="font-semibold">{{ $customer->connector ?? '-' }}</div>
                        </div>

                        <div class="border-b border-base-200/70 pb-2">
                            <div class="text-xs text-gray-500 mb-0.5">{{ __('Responsible') }}</div>
                            <div class="font-semibold">{{ $customer->responsible ?? '-' }}</div>
                        </div>
                    </div>

                    {{-- Bank accounts --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        @foreach ([['name' => $customer->acc_name_1, 'no' => $customer->acc_no_1, 'bank' => $customer->acc_bank_1, 'label' => __('Account 1')], ['name' => $customer->acc_name_2, 'no' => $customer->acc_no_2, 'bank' => $customer->acc_bank_2, 'label' => __('Account 2')]] as $account)
                            <div class="rounded-xl border border-base-300 bg-base-200/40 p-3">
                                <h4 class="text-sm font-semibold text-gray-500 mb-2">{{ $account['label'] }}</h4>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Name') }}</div>
                                        <div class="font-semibold text-base-content">{{ $account['name'] ?? '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Account number') }}</div>
                                        <div class="font-semibold text-base-content text-right" dir="ltr">{{ $account['no'] ?? '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Bank') }}</div>
                                        <div class="font-semibold text-base-content">{{ $account['bank'] ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($customer->desc)
                        <div class="alert bg-base-200 shadow-sm mt-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>{{ $customer->desc }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Comments — the add action sits right next to the table so it's obvious --}}
            <div class="flex flex-wrap items-center justify-between gap-2 mt-2">
                <h3 class="text-lg font-semibold">{{ __('Comments') }}</h3>
                <a href="{{ route('comments.create', $customer->id) }}" class="btn btn-sm btn-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Comment') }}
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="table w-full mt-4 overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">{{ __('Commented By') }}</th>
                            <th class="px-4 py-3">{{ __('Content') }}</th>
                            <th class="px-4 py-3">{{ __('Rating') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->comments->take(5) as $comment)
                            <tr class="hover:bg-base-300">
                                <td class="px-4 py-3">
                                    <span class="badge badge-ghost">{{ $comment->commentBy->name }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm">{{ \Illuminate\Support\Str::limit($comment->content, 80, '…') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="rating rating-sm rating-half">
                                        @for ($i = 1; $i <= 10; $i++)
                                            @php
                                                $starValue = $i / 2;
                                                $isFilled = $starValue <= $comment->rating;
                                            @endphp
                                            <input type="radio" disabled
                                                class="pointer-events-none mask mask-star-2 {{ $i % 2 ? 'mask-half-1' : 'mask-half-2' }} {{ $isFilled ? 'bg-orange-400 dark:bg-sky-400' : 'bg-gray-300 dark:bg-gray-600' }}" />
                                        @endfor
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-8 text-gray-500">
                                    {{ __('There is no comments.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customer->comments->count() > 5)
                <div class="mt-2 text-center">
                    <a href="{{ route('comments.index', $customer) }}" class="link link-primary text-sm">{{ __('View all comments') }}</a>
                </div>
            @endif

            {{-- Orders --}}
            <div class="divider text-lg font-semibold">{{ __('Orders') }}</div>
            <div class="overflow-x-auto">
                <table class="table w-full mt-4 overflow-auto">
                    <thead>
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
                            <tr class="hover:bg-base-300">
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
                    <a href="{{ route('comments.index', $customer) }}" class="btn btn-primary">{{ __('Comments') }}</a>
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
