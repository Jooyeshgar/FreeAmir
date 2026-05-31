<x-app-layout :title="__('Customers')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex justify-between gap-4 ">
                <div class="flex items-center gap-2">
                    <a href="{{ route('customers.create') }}" class="btn btn-primary ">{{ __('Create Customer') }}</a>
                    <a href="{{ route('customers.export') }}" class="btn btn-outline">{{ __('Export CSV') }}</a>
                    <a href="{{ route('customers.import') }}" class="btn btn-outline">{{ __('Import CSV') }}</a>
                </div>

                {{-- Page Header --}}
                <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
                    <div class="min-w-48">
                        <h1 class="text-xl font-bold text-base-content">{{ __('Customers') }}</h1>
                        <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your customers and their accounts') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center justify-start gap-2" dir="ltr">
                        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm gap-1.5" dir="rtl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Create Customer') }}
                        </a>
                    </div>
                </div>

                {{-- Customer List --}}
                <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
                    <div class="card-body p-0">
                        {{-- Card Header: title + filters --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                            <div class="flex items-center gap-3">
                                <h2 class="text-base font-bold text-base-content">{{ __('Customer List') }}</h2>
                                <span class="badge badge-ghost">
                                    {{ convertToFarsi($customers->total()) }} {{ __('records') }}
                                </span>
                            </div>

                            <form action="{{ route('customers.index') }}" method="GET" class="flex flex-wrap items-center gap-2" dir="ltr">
                                <select name="group_id" class="select select-sm w-40" dir="rtl" onchange="this.form.submit()">
                                    <option value="all">{{ __('All Groups') }}</option>
                                    @foreach ($groups as $g)
                                        <option value="{{ $g->id }}" @selected(isset($groupId) && $groupId !== 'all' && (int) $groupId === $g->id)>
                                            {{ $g->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <label class="input input-sm flex w-44 max-w-full items-center gap-2 bg-base-100" dir="rtl">
                                    <input type="search" name="name" value="{{ request('name') }}" class="grow" placeholder="{{ __('Customer Name') }}" />
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                                    </svg>
                                </label>

                                <label class="input input-sm flex w-44 max-w-full items-center gap-2 bg-base-100" dir="rtl">
                                    <input type="search" name="phone" value="{{ request('phone') }}" class="grow" placeholder="{{ __('Phone number') }}" />
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97a1.125 1.125 0 0 0 .417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                    </svg>
                                </label>

                                <button type="submit" class="btn btn-sm btn-primary gap-1.5" dir="rtl">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                                    </svg>
                                    {{ __('Search') }}
                                </button>
                            </form>
                        </div>

                        {{-- Cards --}}
                        <div class="p-4 sm:p-5">
                            @if ($customers->count())
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                    @foreach ($customers as $customer)
                                        @php
                                            $avatarTones = [
                                                'bg-blue-600 text-white',
                                                'bg-emerald-600 text-white',
                                                'bg-amber-500 text-white',
                                                'bg-rose-600 text-white',
                                                'bg-violet-600 text-white',
                                                'bg-cyan-600 text-white',
                                            ];
                                            $avatarTone = $avatarTones[$customer->id % count($avatarTones)];
                                        @endphp
                                        <div
                                            class="card rounded-lg border border-base-200 bg-base-100 dark:bg-base-200/40 shadow-sm transition hover:border-primary/30 hover:shadow-md">
                                            <div class="card-body gap-4 p-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="avatar placeholder shrink-0">
                                                        <div class="{{ $avatarTone }} flex h-14 w-14 items-center justify-center rounded-2xl text-center">
                                                            <span class="text-lg font-bold leading-none">{{ mb_substr($customer->name, 0, 2) }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="min-w-0 space-y-1">
                                                        <h3 class="truncate text-base font-bold text-base-content">
                                                            <a href="{{ route('customers.show', $customer) }}" class="hover:text-primary">{{ $customer->name }}</a>
                                                        </h3>
                                                        <p class="truncate text-sm text-base-content/60">
                                                            {{ $customer->subject?->formattedCode() ?: __('No subject code') }}</p>
                                                    </div>
                                                </div>

                                                <div class="space-y-2 text-sm text-base-content/65">
                                                    <div class="flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97a1.125 1.125 0 0 0 .417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                                        </svg>
                                                        <span>{{ convertToFarsi($customer->phone) ?: __('No phone number') }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                        </svg>
                                                        <span>
                                                            @if ($customer->group)
                                                                <a href="{{ route('customer-groups.show', $customer->group) }}"
                                                                    class="hover:text-primary">{{ $customer->group->name }}</a>
                                                            @else
                                                                {{ __('No group') }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                        <span>
                                                            @if ($customer->subject)
                                                                <a href="{{ route('transactions.index', ['subject_id' => $customer->subject?->id]) }}"
                                                                    class="hover:text-primary">
                                                                    {{ formatNumber(app\Services\SubjectService::sumSubject($customer->subject)) }}
                                                                </a>
                                                            @else
                                                                —
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2">
                                                    <a href="{{ route('comments.index', $customer) }}" class="badge badge-ghost gap-1 hover:badge-primary">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M7 8h10M7 12h4m1 8-4-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-3l-4 4Z" />
                                                        </svg>
                                                        {{ convertToFarsi($customer->comments_count) }} {{ __('Comments') }}
                                                    </a>
                                                </div>

                                                <div class="card-actions items-center justify-between border-t border-base-200 pt-3">
                                                    <button type="button" class="btn btn-xs btn-ghost gap-1 js-add-comment" data-customer-id="{{ $customer->id }}"
                                                        data-customer-name="{{ $customer->name }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                        </svg>
                                                        {{ __('Add Comment') }}
                                                    </button>
                                                    <div class="flex items-center gap-1 text-base-content/45" dir="ltr">
                                                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-xs btn-ghost btn-square"
                                                            title="{{ __('View') }}">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                            </svg>
                                                        </a>
                                                        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-xs btn-ghost btn-square"
                                                            title="{{ __('Edit') }}">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                                                            </svg>
                                                        </a>
                                                        <form action="{{ route('customers.destroy', $customer) }}" method="POST"
                                                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-ghost btn-square text-error" title="{{ __('Delete') }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                                    stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.827L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-16 text-base-content/35">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <p class="text-base font-medium">{{ __('No customers found.') }}</p>
                                    <p class="mt-1 text-sm text-base-content/30">{{ __('Try adjusting your search filters.') }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- Pagination --}}
                        @if ($customers->hasPages())
                            <div class="px-5 py-4 border-t border-base-200">
                                {!! $customers->links() !!}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Add Comment Dialog --}}
                <dialog id="add-comment-modal" class="modal">
                    <div class="modal-box">
                        <form method="dialog">
                            <button class="btn btn-sm btn-circle btn-ghost absolute end-3 top-3" aria-label="{{ __('Close') }}">✕</button>
                        </form>

                        <h3 class="text-lg font-bold">{{ __('Add Comment') }}</h3>
                        <p class="mt-1 text-sm text-base-content/60" id="add-comment-customer"></p>

                        <form id="add-comment-form" method="POST" action="" class="mt-4 space-y-4" x-data="{ rating: 0 }">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ Auth::id() }}" />
                            <input type="hidden" name="customer_id" id="add-comment-customer-id" value="" />

                            <div>
                                <label class="label" for="add-comment-content">{{ __('Content') }}</label>
                                <textarea name="content" id="add-comment-content" rows="4" required maxlength="500" class="textarea textarea-bordered w-full"
                                    placeholder="{{ __('Content') }}"></textarea>
                            </div>

                            <div>
                                <label class="label">{{ __('Rating') }}</label>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="text-xs text-base-content/60 hover:underline" @click="rating = 0">{{ __('Reset') }}</button>
                                    <div class="rating rating-sm rating-half">
                                        <input type="radio" class="rating-hidden" name="rating" value="0" x-model="rating" />
                                        @for ($i = 1; $i <= 10; $i++)
                                            @php $starValue = $i * 0.5; @endphp
                                            <input type="radio" name="rating" value="{{ $starValue }}" x-model="rating"
                                                class="mask mask-star-2 cursor-pointer bg-yellow-400 hover:bg-yellow-500 dark:bg-sky-400 dark:hover:bg-sky-500 {{ $i % 2 ? 'mask-half-1' : 'mask-half-2' }}" />
                                        @endfor
                                    </div>
                                </div>
                            </div>

                            <div class="modal-action">
                                <button type="button" class="btn" onclick="document.getElementById('add-comment-modal').close()">{{ __('cancel') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                            </div>
                        </form>
                    </div>
                    <form method="dialog" class="modal-backdrop">
                        <button aria-label="{{ __('Close') }}"></button>
                    </form>
                </dialog>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const modal = document.getElementById('add-comment-modal');
                        const form = document.getElementById('add-comment-form');
                        const customerIdInput = document.getElementById('add-comment-customer-id');
                        const customerLabel = document.getElementById('add-comment-customer');
                        const contentInput = document.getElementById('add-comment-content');
                        const actionTemplate = @json(route('comments.store', ['customer' => '__CUSTOMER_ID__']));

                        document.querySelectorAll('.js-add-comment').forEach((button) => {
                            button.addEventListener('click', () => {
                                const id = button.dataset.customerId;
                                form.action = actionTemplate.replace('__CUSTOMER_ID__', id);
                                customerIdInput.value = id;
                                customerLabel.textContent = button.dataset.customerName || '';
                                contentInput.value = '';
                                modal.showModal();
                            });
                        });
                    });
                </script>
</x-app-layout>
