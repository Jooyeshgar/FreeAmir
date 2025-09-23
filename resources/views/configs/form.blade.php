<div>
    <div class="grid grid-cols-1 md:grid-cols-4">

    </div>

    <x-card class="bg-yellow-50 border-l-4 border-yellow-400 mb-5">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong class="font-bold">{{ __('Caution') }}: </strong>
                    <span
                        class="font-medium">{{ __('Changes to these settings may affect your fiscal data integrity. Please proceed with care.') }}</span>
                </p>
            </div>
        </div>
    </x-card>

    <fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
        <legend>{{ __('Subject Info') }}</legend>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="cust_subject" id="cust_subject" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Customers') }}"
                    id_field="cust_subject"
                    placeholder="{{ $subjects->where('id', config('amir.cust_subject'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="bank" id="bank" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Banks') }}" id_field="bank"
                    placeholder="{{ $subjects->where('id', config('amir.bank'))->first()?->name ?? __('Select a subject') }}" allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="cash_book" id="cash_book" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ $subjects->where('id', config('amir.cash_book'))->first()?->name ?? __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Cash balances') }}"
                    id_field="cash_book" placeholder="{{ $subjects->where('id', config('amir.cash_book'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="income" id="income" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Income') }}" id_field="income"
                    placeholder="{{ $subjects->where('id', config('amir.income'))->first()?->name ?? __('Select a subject') }}" allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="cash" id="cash" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Cash') }}" id_field="cash"
                    placeholder="{{$subjects->where('id', config('amir.cash'))->first()?->name ?? __('Select a subject') }}" allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="buy_discount" id="buy_discount" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Buy Discount') }}"
                    id_field="buy_discount" placeholder="{{ $subjects->where('id', config('amir.buy_discount'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="sell_discount" id="sell_discount" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Sell Discount') }}"
                    id_field="sell_discount" placeholder="{{ $subjects->where('id', config('amir.sell_discount'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="sell_vat" id="sell_vat" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Sell VAT') }}"
                    id_field="sell_vat" placeholder="{{ $subjects->where('id', config('amir.sell_vat'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="buy_vat" id="buy_vat" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Buy VAT') }}"
                    id_field="buy_vat" placeholder="{{ $subjects->where('id', config('amir.buy_vat'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3 hidden">
                    <x-input name="product" id="product" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Product Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Product') }}"
                    id_field="product" placeholder="{{ $subjects->where('id', config('amir.product'))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
    </fieldset>
</div>
