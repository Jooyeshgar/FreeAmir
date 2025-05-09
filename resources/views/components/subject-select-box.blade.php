@props([
    'subjects',
    'title' => '',
    'input_name' => '',
    'placeholder' => '',
    'id_field' => 'subject_id',
    'code_field' => 'code',
    'bordered' => true,
    'level' => null,
    'allSelectable' => false,
])

<div {{ $attributes->merge(['class' => 'selfSelectBoxContainer relative flex-1 w-full']) }} x-data="{
    isSelectBoxOpen: false,

    updateSelection(name, code, id) {
        selectedName = name;
        selectedCode = code;
        selectedId = id;
        this.isSelectBoxOpen = false;
    }
}"
    @click.outside="if (!$event.target.closest('.selfSelectBox')) isSelectBoxOpen = false">

    <x-input @click="isSelectBoxOpen = true" :title="$title" :bordered="$bordered" readonly :name="$input_name" :placeholder="$placeholder" x-bind:value="selectedName"
        label_class="w-full" input_class="border-white subject_name codeSelectBox" model_name="selectedName">
    </x-input>

    <input type="hidden" x-bind:value="selectedId" name="{{ $id_field }}">
    <input type="hidden" x-bind:value="selectedCode" name="{{ $code_field }}">

    <div class="selfSelectBox absolute z-[3] top-[40px] w-full h-[300px] bg-white overflow-auto px-4 pb-4 rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]"
        x-show="isSelectBoxOpen" x-transition x-data="searchComponent({{ $allSelectable ? 'true' : 'false' }})" class="subject-select-box">
        <div class="sticky top-0 left-0 right-0 w-full bg-white py-2">
            <div class="relative">
                <x-input x-model="query" title="" @input.debounce.500ms="search(query, index)" name="" value="" label_text_class="text-gray-500"
                    label_class="w-full" input_class="pe-8 text-sm searchInput" placeholder="{{ __('Search... (heading code or name)') }}">
                </x-input>

                <span class="absolute block left-2 top-1/2 translate-y-[-50%]">
                    <svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M2 7.5C2 4.73858 4.23858 2.5 7 2.5C9.76142 2.5 12 4.73858 12 7.5C12 10.2614 9.76142 12.5 7 12.5C4.23858 12.5 2 10.2614 2 7.5ZM7 0.5C3.13401 0.5 0 3.63401 0 7.5C0 11.366 3.13401 14.5 7 14.5C8.57234 14.5 10.0236 13.9816 11.1922 13.1064L16.2929 18.2071C16.6834 18.5976 17.3166 18.5976 17.7071 18.2071C18.0976 17.8166 18.0976 17.1834 17.7071 16.7929L12.6064 11.6922C13.4816 10.5236 14 9.07234 14 7.5C14 3.63401 10.866 0.5 7 0.5Z"
                            fill="#242424" />
                    </svg>
                </span>
            </div>
        </div>

        <div class="overflow-auto h-[calc(100%-56px)] pe-1">
            <div class="flex justify-between mt-2 font-bold text-xs">
                <span>
                    {{ __('Title name') }}
                </span>

                <span>
                    {{ __('Header code') }}
                </span>
            </div>

            <div class="mt-4 text-xs searchResultDiv" x-ref="results">
            </div>
            <div class="mt-4 text-xs resultDiv" x-ref="baseResults">
                @foreach ($subjects as $subject)
                    @include('components.subject-select-box-item', [
                        'subject' => $subject,
                        'level' => $level,
                        'allSelectable' => $allSelectable,
                    ])
                @endforeach
            </div>
        </div>
    </div>

</div>
@pushOnce('scripts')
    <script>
        function searchComponent(allSelectable = false) {
            return {
                query: '',
                index: 0,
                csrf: '{{ csrf_token() }}',
                allSelectable: allSelectable,
                searchResultDivs: [],
                resultDivs: [],
                search(query, index) {
                    fetch("/subjects/search", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-Token": this.csrf,
                            },
                            body: JSON.stringify({
                                query
                            }),
                        })
                        .then((response) => {
                            if (!response.ok) throw new Error("خطا در دریافت پاسخ");
                            return response.json();
                        })
                        .then((data) => {
                            const resultDiv = this.$refs.results;
                            const baseResultsDiv = this.$refs.baseResults;

                            if (data.length === 0) {
                                baseResultsDiv.classList.remove('hidden');
                                resultDiv.innerHTML = '';
                            } else {
                                baseResultsDiv.classList.add('hidden');
                                resultDiv.innerHTML = '';
                                data.forEach((item, i) => {
                                    const {
                                        name,
                                        code,
                                        id,
                                        sub_subjects: subSubjects
                                    } = item;

                                    if (subSubjects.length === 0) {
                                        // Always selectable when it has no children
                                        resultDiv.innerHTML += `
                                        <a href="javascript:void(0)" 
                                            class="selfSelectBoxItems w-full ps-2 mb-4" 
                                            @click="updateSelection('${name}', '${code}', '${id}')">
                                            <div class="flex justify-between">
                                                <span class="selfItemTitle">${name}</span>
                                                <span class="selfItemCode">${formatCode(code)}</span>
                                            </div>
                                            <span class="selfItemId hidden">${id}</span>
                                        </a>`;
                                    } else {
                                        const subDivId = `sub-${index}-${i}`;
                                        
                                        if (this.allSelectable) {
                                            resultDiv.innerHTML += `
                                            <a href="javascript:void(0)" 
                                                class="selfSelectBoxItems w-full ps-2 mb-4" 
                                                @click="updateSelection('${name}', '${code}', '${id}')">
                                                <div class="flex justify-between">
                                                    <span class="selfItemTitle">${name}</span>
                                                    <span class="selfItemCode">${Alpine.store('utils').formatCode(code)}</span>
                                                </div>
                                                <span class="selfItemId hidden">${id}</span>
                                            </a>`;
                                        } else {
                                            resultDiv.innerHTML += `
                                            <div class="w-full ps-2 mb-4">
                                                <div class="flex justify-between">
                                                    <span>${name}</span>
                                                    <span>${Alpine.store('utils').formatCode(code)}</span>
                                                </div>
                                            </div>`;
                                        }
                                        
                                        resultDiv.innerHTML += `
                                        <div class="ps-1 mt-4">
                                            <div class="border-s-[1px] ps-7 border-[#ADB5BD]" id="${subDivId}"></div>
                                        </div>`;

                                        const subDiv = document.getElementById(subDivId);
                                        subSubjects.forEach((sub) => {
                                            subDiv.innerHTML += `
                                            <a href="javascript:void(0)" 
                                                class="selfSelectBoxItems flex justify-between mb-4" 
                                                @click="updateSelection('${sub.name}', '${sub.code}', '${sub.id}')">
                                                <span class="selfItemTitle">${sub.name}</span>
                                                <span class="codeList" data-name="${sub.name}" data-code="${sub.code}" data-id="${sub.id}" hidden></span>
                                                <span class="selfItemCode">
                                                    ${Alpine.store('utils').formatCode(sub.code)}
                                                </span>
                                                <span class="selfItemId hidden">${sub.id}</span>
                                            </a>`;
                                        });
                                    }
                                });
                            };
                        })
                        .catch((error) => {
                            console.error("خطایی رخ داده: ", error);
                        });
                }
            };
        }
    </script>
@endPushOnce
