<x-app-layout :title="__('Invoices and Ancillary Costs')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex justify-between items-center">
                <div class="mb-2">
                    <button id="toggle-select" class="btn btn-sm gap-2">
                        <span id="toggle-text">{{ __('Select All') }}</span>
                    </button>
                </div>

                <form method="GET" action="{{ route('invoices-and-ancillary-costs') }}">
                    <select name="status" id="status" onchange="this.form.submit()"
                        class="select select-sm select-bordered">
                        <option value="all"
                            {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>
                            {{ __('All Status') }}
                            ({{ convertToFarsi($allInvoicesCount) }})
                        </option>

                        @foreach (\App\Enums\InvoiceAncillaryCostStatus::cases() as $status)
                            <option
                                value="{{ $status->value }}"{{ request('status') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                                ({{ convertToFarsi($invoicesCountByStatus[$status->value] ?? 0) }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            <table class="table w-full mt-2 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Select') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Invoice Number') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Document') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td>
                                <input type="checkbox" class="item-checkbox" name="selected_items[]"
                                    value="{{ $invoice->id }}">
                            </td>
                            <td>{{ __('Invoice') }} {{ $invoice->invoice_type->label() }}</td>
                            <td>{{ formatDocumentNumber($invoice->number) }}</td>
                            <td>{{ $invoice->customer->name ?? '' }}</td>
                            <td>{{ $invoice->document?->number ?? '' }}</td>
                            <td>{{ $invoice->date ? formatDate($invoice->date) : '' }}</td>
                            <td>{{ formatNumber($invoice->amount - $invoice->subtraction) }}</td>
                            <td>{{ $invoice->status?->label() }}</td>
                        </tr>

                        @foreach ($invoice->ancillaryCosts as $ancillaryCost)
                            <tr class="bg-gray-50">
                                <td>
                                    <input type="checkbox" class="item-checkbox" name="selected_items[]"
                                        value="{{ $ancillaryCost->id }}">
                                </td>
                                <td>{{ $ancillaryCost->type->label() }}</td>
                                <td>{{ formatDocumentNumber($ancillaryCost->invoice->number) }}</td>
                                <td>{{ $ancillaryCost->customer->name ?? '' }}</td>
                                <td>{{ $ancillaryCost->document ?? '' }}</td>
                                <td>{{ $ancillaryCost->date ? formatDate($ancillaryCost->date) : '' }}</td>
                                <td>{{ formatNumber($ancillaryCost->amount - $ancillaryCost->subtraction) }}</td>
                                <td>{{ $ancillaryCost->status?->label() }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>

            <div class="flex justify-end mt-4 gap-3">
                <div class="form-control">
                    <select name="action" id="action" class="select select-bordered select-sm">
                        @foreach (['approve', 'unapprove'] as $action)
                            <option value="{{ $action }}">{{ ucfirst($action) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
            </div>

            @if ($invoices->hasPages())
                <div class="mt-4">
                    {{ $invoices->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

    @pushonce('scripts')
        <script>
            const toggleBtn = document.getElementById('toggle-select');
            const toggleText = document.getElementById('toggle-text');

            const updateButton = () => {
                const items = document.querySelectorAll('.item-checkbox');
                const checked = document.querySelectorAll('.item-checkbox:checked');

                if (checked.length === 0) {
                    toggleText.textContent = 'Select All';
                } else if (checked.length === items.length) {
                    toggleText.textContent = 'Unselect All';
                } else {
                    toggleText.textContent = 'Unselect';
                }
            }

            toggleBtn.addEventListener('click', () => {
                const items = document.querySelectorAll('.item-checkbox');
                const checked = document.querySelectorAll('.item-checkbox:checked');

                const shouldSelect = checked.length === 0;

                items.forEach(cb => cb.checked = shouldSelect);

                updateButton();
            });

            document.addEventListener('change', e => {
                if (e.target.classList.contains('item-checkbox')) {
                    updateButton();
                }
            });

            updateButton();
        </script>
    @endpushonce

</x-app-layout>
