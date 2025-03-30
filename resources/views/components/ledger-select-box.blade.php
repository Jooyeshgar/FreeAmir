<div class="flex gap-4" x-data="subjectCodeHandler">
    <x-text-input x-on:keyup="onCodeInputChange($event, $refs.subjectSelect)" x-ref="codeInput" id="code_input" label_class="flex-1" placeholder="{{ __('Subject Code') }}"
        title="{{ __('Subject') }}">
    </x-text-input>
    <label for="subject_id" class="flex-1">
        {{ __('Select a subject') }}
        <select x-on:change="onCodeSelectBoxChange($event, $refs.codeInput)" name="subject_id" id="subject_id" x-ref="subjectSelect"
            class="codeSelectBox flex-1 rounded-md max-h-10 min-h-10 select select-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 focus:outline-none">
            <option value="">{{ __('Select a subject') }}</option>

            @foreach ($subjects as $subject)
                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
            @endforeach
        </select>
    </label>
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('subjectCodeHandler', () => ({
                subjects: @json($subjects),

                onCodeInputChange(e, selectBox) {
                    let code = e.target.value
                    code = code.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                    e.target.value = code

                    let itemIndex = this.subjects.findIndex(i => (code === i.code))

                    if (itemIndex !== -1) selectBox.value = this.subjects[itemIndex].id
                },

                onCodeSelectBoxChange(e, codeInput) {
                    let id = e.target.value
                    let itemIndex = this.subjects.findIndex(i => parseInt(id) === parseInt(i.id))

                    if (itemIndex !== -1) codeInput.value = this.subjects[itemIndex].code
                }
            }))
        })
    </script>
@endPushOnce
