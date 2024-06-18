<div class="flex gap-4 {{ $type == 'Journal' ? 'hidden' : '' }}">
    <x-text-input name="report_for" value="{{ $type }}" class="hidden"/>
    <x-text-input onkeyup_input="onCodeInputChange(event,document.getElementById('subject_id'))"
                  id_input="code_input" label_class="flex-1"
                  placeholder="{{ __('Subject Code') }}" title="{{ __('Subject') }}"></x-text-input>
    <label for="subject_id" class="flex-1">
        {{ __('Select a subject') }}
        <select onchange="onCodeSelectBoxChange(event,document.getElementById('code_input'))" name="subject_id"
                id="subject_id"
                class="codeSelectBox flex-1 rounded-md max-h-10 min-h-10 select select-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 focus:outline-none ">
            <option value="">{{ __('Select a subject') }}</option>

            @foreach ($subjects as $subject)
            <option @if($type=='subLedger' && $subject->parent_id==0) disabled
                @endif value="{{ $subject->id }}">{{ $subject->name }}</option>
            @endforeach

        </select>
    </label>
</div>

<script>
    var subjects = {!! json_encode($subjects) !!};
</script>


<script>

    var p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))

    function onCodeInputChange(e, selectBox) {
        let code = e.target.value
        code = p2e(code)
        e.target.value = code
        let itemIndex = -1
        @if($type=='subLedger')
            itemIndex = subjects.findIndex(i => (code === i.code && i.parent_id))
        @else
            itemIndex = subjects.findIndex(i => (code === i.code))
        @endif
        if (itemIndex !== -1) selectBox.value = subjects[itemIndex].id;
    }

    function onCodeSelectBoxChange(e, codeInput) {
        let id = e.target.value
        let itemIndex = subjects.findIndex(i => parseInt(id) === parseInt(i.id))

        if (itemIndex !== -1) codeInput.value = subjects[itemIndex].code;
    }
</script>
