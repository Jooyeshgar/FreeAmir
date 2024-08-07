<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Welcome') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="stats shadow">
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="stat-title">{{ __('Total Invoice') }}</div>
                <div class="stat-value"><a href="{{ route('invoices.index') }}">{{ $invoiceCount }}</a></div>
                <div class="stat-desc">Jan 1st - Feb 1st</div>
            </div>
        </div>
        <div class="stats shadow">
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4">
                        </path>
                    </svg>
                </div>
                <div class="stat-title">{{ __('Total Users') }}</div>
                <div class="stat-value"><a href="{{ route('customers.index') }}">{{ $customerCount }}</a></div>
                <div class="stat-desc">↗︎ 400 (22%)</div>
            </div>
        </div>
        <div class="stats shadow">
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                        </path>
                    </svg>
                </div>
                <div class="stat-title">{{ __('Total Documents') }}</div>
                <div class="stat-value"><a href="{{ route('documents.index') }}">{{ $documentCount }}</a></div>
                <div class="stat-desc">↘︎ 90 (14%)</div>
            </div>
        </div>
        <div class="stats shadow">
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                        </path>
                    </svg>
                </div>
                <div class="stat-title">{{ __('Total Products') }}</div>
                <div class="stat-value"><a href="{{ route('products.index') }}">{{ $productCount }}</a></div>
                <div class="stat-desc">↘︎ 90 (14%)</div>
            </div>
        </div>
        <div class="card col-span-2 md:col-span-3 bg-white shadow-xl">
            <div class="card-body">
                <h2 class="card-title">{{ __('Last Invoice') }}</h2>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Client') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latestInvoices as $invoice)
                                <tr class="hover">
                                    <th>{{ $invoice->customer->name }}</th>
                                    <td>{{ $invoice->date }}</td>
                                    <td>{{ $invoice->invoice }}</td>
                                    <td>{{ $invoice->amount }}</td>
                                    <td>{{ $invoice->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-actions justify-end">
                    <button class="btn btn-primary">{{ __('Add Invoice') }}</button>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
