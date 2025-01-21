<div class="selfSelectBoxContainer relative flex-1 w-full pb-3" x-data="{ isSelectBoxOpen: false }"
    @click.outside="if (!$event.target.closest('.selfSelectBox')) isSelectBoxOpen = false">
    <x-text-input @click="isSelectBoxOpen = true" readonly input_name="{{ $attributes->get('input_name') }}"
        placeholder="{{ $attributes->get('placeholder') }}" input_value="{{ $attributes->get('input_value') }}"
        id="subject_id" label_class="w-full" input_class="border-white subject_name codeSelectBox "></x-text-input>
    <div class="selfSelectBox absolute z-[3] top-[40px] w-full h-[300px] bg-white overflow-auto px-4 pb-4 rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]"
        x-show="isSelectBoxOpen" x-transition x-data="searchComponent()" class="transaction">
        <div class="sticky top-0 left-0 right-0 w-full bg-white py-2">
            <div class="relative">
                <x-text-input x-model="query" @input.debounce.500ms="search(query, index)" input_name="" value=""
                    label_text_class="text-gray-500" label_class="w-full" input_class="pe-8 text-sm searchInput"
                    placeholder="{{ __('Search... (heading code or name)') }}"></x-text-input>

                <span class="absolute block left-2 top-1/2 translate-y-[-50%]">
                    <svg width="18" height="19" viewBox="0 0 18 19" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
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
                        'level' => $attributes->get('level', null),
                        'allSelectable' => $attributes->get('allSelectable', false),
                    ])
                @endforeach
            </div>
        </div>
    </div>

</div>
<script>
    function searchComponent() {
        return {
            query: '',
            index: 0,
            csrf: '{{ csrf_token() }}',
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
                                    sub_subjects: subSubjects
                                } = item;

                                if (subSubjects.length === 0) {
                                    resultDiv.innerHTML += `
                                        <div class="w-full ps-2 mb-4">
                                            <div class="flex justify-between">
                                                <span>${name}</span>
                                                <span>${this.formatCode(code)}</span>
                                            </div>
                                        </div>`;
                                } else {
                                    const subDivId = `sub-${index}-${i}`;
                                    resultDiv.innerHTML += `
                                        <div class="w-full ps-2 mb-4">
                                            <div class="flex justify-between">
                                                <span>${name}</span>
                                                <span>${this.formatCode(code)}</span>
                                            </div>
                                        </div>
                                        <div class="ps-1 mt-4">
                                            <div class="border-s-[1px] ps-7 border-[#ADB5BD]" id="${subDivId}"></div>
                                        </div>`;

                                    const subDiv = document.getElementById(subDivId);
                                    subSubjects.forEach((sub) => {
                                        subDiv.innerHTML += `
                                            <a href="javascript:void(0)" 
                                                class="selfSelectBoxItems flex justify-between mb-4" 
                                                x-on:click="fillInput('${sub.name}', '${sub.code}', '${sub.id}')">
                                                <span class="selfItemTitle">${sub.name}</span>
                                                <span class="codeList" data-name="${sub.name}" data-code="${sub.code}" hidden></span>
                                                <span class="selfItemCode" data-en-code="${sub.code}">
                                                    ${this.formatCode(sub.code)}
                                                </span>
                                                <span class="selfItemId hidden">${sub.id}</span>
                                            </a>`;
                                    });
                                }
                            });
                        }
                    })
                    .catch((error) => {
                        console.error("خطایی رخ داده: ", error);
                    });
            },
            convertToFarsi(e) {
                let t = {
                    0: "۰",
                    1: "۱",
                    2: "۲",
                    3: "۳",
                    4: "۴",
                    5: "۵",
                    6: "۶",
                    7: "۷",
                    8: "۸",
                    9: "۹"
                };
                return e.replace(/[0-9]/g, e => t[e])
            },
            formatCode(e) {
                let t = [];
                for (let n = 0; n < e.length; n += 3) t.push(e.substring(n, n + 3));
                return e = t.join("/"), ["fa", "fa_IR"].includes("fa") && (e = this.convertToFarsi(e)), e
            },
            fillInput(name, code, id) {
                let activeTab = this.activeTab ?? 0
                document.querySelectorAll(".subject_name")[activeTab].value = name;

                if (document.querySelectorAll(".subject_id").length) {
                    document.querySelectorAll(".subject_id")[activeTab].value = id;
                    document.querySelectorAll(".codeInput")[activeTab].value = this.formatCode(code);
                }
            },
        };
    }
</script>
