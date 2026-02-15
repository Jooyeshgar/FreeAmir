<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subsidiary Ledger Report') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            {{ __('Subsidiary Ledger Report') }}
        </span>
    </div>
    <x-show-message-bags />

    <form action="{{ route('reports.result') }}" method="get" x-data="{ subjectCode: '' }">
        <x-card>
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
                subjectsTree: @js($subjects ?? []),
                normalize(code) {
                    if (!code) return '';

                    const englishCode = $store.utils.convertToEnglish(code);
                    const cleaned = englishCode.replace(/[^0-9/]/g, '');
                    if (!cleaned) return '';

                    if (!cleaned.includes('/')) {
                        const digits = cleaned.replace(/\D/g, '');
                        if (!digits) return '';

                        if (digits.length < 3) {
                            return digits.padStart(3, '0');
                        }

                        const remainder = digits.length % 3;
                        if (remainder === 0) return digits;

                        const head = digits.slice(0, digits.length - remainder);
                        const tail = digits.slice(-remainder);

                        if (digits.startsWith('0')) {
                            return head + tail;
                        }

                        return head + tail.padStart(3, '0');
                    }

                    const parts = cleaned.split('/').filter(part => part.length);
                    if (!parts.length) return '';

                    const padded = parts.map(part => {
                        const digits = part.replace(/\D/g, '');
                        return digits ? digits.padStart(3, '0') : '';
                    });

                    return padded.join('');
                },
                normalizeForTyping(code) {
                    if (!code) return '';

                    const englishCode = $store.utils.convertToEnglish(code);
                    const cleaned = englishCode.replace(/[^0-9/]/g, '');
                    if (!cleaned) return '';

                    if (!cleaned.includes('/')) {
                        return cleaned.replace(/\D/g, '');
                    }

                    const parts = cleaned.split('/').filter(part => part.length);
                    if (!parts.length) return '';

                    return parts.map(part => part.replace(/\D/g, '')).join('');
                },
                findByCode(code) {
                    const key = this.normalize(code);
                    if (!key) return null;

                    const walk = (nodes) => {
                        for (const node of nodes || []) {
                            if (this.normalize(node.code) === key) {
                                return node;
                            }
                            const match = walk(node.children || []);
                            if (match) return match;
                        }
                        return null;
                    };

                    return walk(this.subjectsTree);
                },
                applySubject(match) {
                    if (match) {
                        this.selectedId = match.id;
                        this.selectedName = match.name;
                        this.selectedCode = this.normalize(match.code);
                    } else {
                        this.selectedId = '';
                        this.selectedName = '';
                    }
                },
                syncSubjectByCode() {
                    this.applySubject(this.findByCode(this.selectedCode));
                }
            }">
                <div class="w-1/3">
                    <x-input name="code_input" id="code_input" placeholder="{{ __('Subject Code') }}" title="{{ __('Subject') }}"
                        x-bind:value="$store.utils.formatCode(selectedCode)"
                        @input="
                            selectedCode = normalizeForTyping($event.target.value);
                            $event.target.value = $store.utils.formatCode(selectedCode);
                        "
                        @keydown.enter.prevent="
                            selectedCode = normalize($event.target.value);
                            $event.target.value = $store.utils.formatCode(selectedCode);
                            syncSubjectByCode();
                        "
                        @blur="
                            selectedCode = normalize($event.target.value);
                            $event.target.value = $store.utils.formatCode(selectedCode);
                            syncSubjectByCode();
                        ">
                    </x-input>
                </div>
                <x-subject-select class="w-2/3" :subjects="$subjects" title="{{ __('Subject name') }}" placeholder="{{ __('Select a subject') }}"
                    x-bind:selected_id="selectedId" x-bind:selected_name="selectedName" x-bind:selected_code="selectedCode"
                    @selected="
                        selectedName = $event.detail.name;
                        selectedCode = normalize($event.detail.code);
                        selectedId = $event.detail.id;
                    " />
                <input type="hidden" name="subject_id" x-bind:value="selectedId">
                <input type="hidden" name="code" x-bind:value="selectedCode">
            </div>

            @include('reports.form', ['type' => 'subLedger'])
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <button type="submit" name="action" value="export_csv" class="btn btn-default rounded-md">
                {{ __('Convert to CSV') }}
            </button>
            <button type="submit" name="action" value="print" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
            <button type="submit" name="action" value="preview" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
