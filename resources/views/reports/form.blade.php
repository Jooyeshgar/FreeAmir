<x-show-message-bags />
<form action="{{ route('reports.result') }}" method="get">

    <x-card>
        <div class="flex gap-4 {{ $type == 'journal' ? 'hidden' : '' }}">
            <x-text-input name="report_for" value="{{ $type }}" class="hidden" />
            <x-text-input onkeyup_input="onCodeInputChange(event,document.getElementById('subject_id'))" id_input="code_input" label_class="flex-1"
                placeholder="{{ __('Subject Code') }}" title="{{ __('Subject') }}"></x-text-input>
            <label for="subject_id" class="flex-1">
                {{ __('Select a subject') }}
                <select onchange="onCodeSelectBoxChange(event,document.getElementById('code_input'))" name="subject_id" id="subject_id"
                    class="codeSelectBox flex-1 rounded-md max-h-10 min-h-10 select select-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 focus:outline-none ">
                    <option value="">{{ __('Select a subject') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach

                </select>
            </label>
        </div>
        <hr class="{{ $type == 'journal' ? 'hidden' : '' }}">
        <div class="flex items-center">
            <div class="flex-1 gap-4">
                <label class="label cursor-pointer justify-end gap-2" dir="ltr">
                    <span class="">{{ __('all book contents') }}</span>
                    <input type="radio" name="report_type" value="all" class="radio checked:bg-red-500" checked />
                </label>
            </div>


        </div>
        <div class="flex items-center">
            <div class="flex-1 gap-4">
                <label class="label cursor-pointer justify-end gap-2" dir="ltr">
                    <span class="">{{ __('all book contents in specific date') }}</span>
                    <input type="radio" name="report_type" value="specific_date" class="radio checked:bg-red-500" />
                </label>
            </div>

            <div class="flex-1">
                <x-text-input name="specific_date" label_class="flex-1" data-jdp placeholder="{{ __('Your specific date') }}"></x-text-input>
            </div>
        </div>
        <div class="flex items-center">

            <div class="flex-1 gap-4">
                <label class="label cursor-pointer max-w-60 justify-end gap-2" dir="ltr">
                    <span class="">{{ __('Contents between dates') }}</span>
                    <input type="radio" name="report_type" value="between_dates" class="radio checked:bg-red-500" />
                </label>
            </div>

            <div class="flex-1 flex gap-2 justify-between">
                <x-text-input name="start_date" label_class="flex-1" data-jdp placeholder="{{ __('Start date') }}"></x-text-input>
                <x-text-input name="end_date" label_class="flex-1" data-jdp placeholder="{{ __('End date') }}"></x-text-input>
            </div>
        </div>
        <div class="flex items-center">

            <div class="flex-1 gap-4">
                <label class="label cursor-pointer justify-end gap-2" dir="ltr">
                    <span class="">{{ __('Contents between document numbers') }}</span>
                    <input type="radio" name="report_type" value="between_numbers" class="radio checked:bg-red-500" />
                </label>
            </div>

            <div class="flex-1 flex gap-2 justify-between">
                <x-text-input name="start_document_number" label_class="flex-1" placeholder="{{ __('Document start number') }}"></x-text-input>
                <x-text-input name="end_document_number" label_class="flex-1" placeholder="{{ __('Document end number') }}"></x-text-input>
            </div>
        </div>

        <hr>
        <div class="flex-1">
            <x-text-input label_class="flex-1 max-w-44" placeholder="{{ __('Search for documents') }}" title="{{ __('Search for documents') }}"
                name="search"></x-text-input>
        </div>
    </x-card>
    <div class="mt-2 flex gap-2 justify-end">
        <a href="{{ route('transactions.index') }}" type="submit" class="btn btn-default rounded-md">
            {{ __('Convert to CSV') }}
        </a>
        <button type="submit" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
        <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
    </div>
</form>

<script>
    var subjects = {!! json_encode($subjects) !!};

    var p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))

    function onCodeInputChange(e, selectBox) {
        let code = e.target.value
        code = p2e(code)
        e.target.value = code
        let itemIndex = subjects.findIndex(i => (code === i.code))
        if (itemIndex !== -1) selectBox.value = subjects[itemIndex].id;
    }

    function onCodeSelectBoxChange(e, codeInput) {
        let id = e.target.value
        let itemIndex = subjects.findIndex(i => parseInt(id) === parseInt(i.id))

        if (itemIndex !== -1) codeInput.value = subjects[itemIndex].code;
    }
</script>
<script type="module">
    jalaliDatepicker.startWatch({});
</script>
