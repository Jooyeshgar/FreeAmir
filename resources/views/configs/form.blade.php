<div>
    <fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
        @if ($config->key == 'cust_subject')
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
        @elseif ($config->key == 'bank')
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
                        placeholder="{{ $subjects->where('id', config('amir.bank'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'cash_book')
            <div class="col-span-2 md:col-span-1">
                <div class="flex gap-4" x-data="{
                                selectedName: '',
                                selectedCode: '',
                                selectedId: '',
                            }">
                    <div class="w-1/3 hidden">
                        <x-input name="cash_book" id="cash_book" placeholder="{{ __('Select Subject Code') }}"
                            title="{{ $subjects->where('id', config('amir.cash_book'))->first()?->name ?? __('Subject Code') }}"
                            x-bind:value="$store.utils.formatCode(selectedCode)">
                        </x-input>
                    </div>
                    <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Cash balances') }}"
                        id_field="cash_book"
                        placeholder="{{ $subjects->where('id', config('amir.cash_book'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'income')
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
                        placeholder="{{ $subjects->where('id', config('amir.income'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'cash')
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
                        placeholder="{{$subjects->where('id', config('amir.cash'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'buy_discount')
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
                        id_field="buy_discount"
                        placeholder="{{ $subjects->where('id', config('amir.buy_discount'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'sell_discount')
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
                        id_field="sell_discount"
                        placeholder="{{ $subjects->where('id', config('amir.sell_discount'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'sell_vat')
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
                    <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Sell Vat') }}"
                        id_field="sell_vat"
                        placeholder="{{ $subjects->where('id', config('amir.sell_vat'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'buy_vat')
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
                    <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Buy Vat') }}" id_field="buy_vat"
                        placeholder="{{ $subjects->where('id', config('amir.buy_vat'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @elseif ($config->key == 'product')
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
                    <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Product') }}" id_field="product"
                        placeholder="{{ $subjects->where('id', config('amir.inventory'))->first()?->name ?? __('Select a subject') }}"
                        allSelectable="true"></x-subject-select-box>
                </div>
            </div>
        @endif
    </fieldset>
</div>