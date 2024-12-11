<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2">
        <x-text-input name="title" title="{{ __('document name') }}" value="{{ old('title') ?? $document->title }}"
            placeholder="{{ __('document name') }}" label_text_class="text-gray-500" label_class="w-full"
            input_class="max-w-96"></x-text-input>
        <x-text-input value="{{ $document->id ?? '' }}" name="document_id" label_text_class="text-gray-500"
            label_class="w-full hidden"></x-text-input>
        <div class="flex-1"></div>
        <x-text-input disabled="true" value="{{ formatDocumentNumber($previousDocumentNumber) }}" name=""
            title="{{ __('previous document number') }}" placeholder="{{ __('previous document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input
            value="{{ old('number') ?? formatDocumentNumber($document->number ?? $previousDocumentNumber + 1) }}"
            name="number" title="{{ __('current document number') }}" placeholder="{{ __('current document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input data-jdp title="{{ __('date') }}" name="date" placeholder="{{ __('date') }}"
            value="{{ old('date') ?? $document->FormattedDate }}" label_text_class="text-gray-500 text-nowrap"
            input_class="datePicker"></x-text-input>
    </div>
</x-card>

<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-0">

    <div class="flex overflow-x-auto overflow-y-hidden  gap-2 items-center px-4  ">
        <div class="text-sm flex-1 max-w-8  text-center text-gray-500 pt-3 ">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            {{ __('chapter code') }}
        </div>
        <div class="text-sm flex-1 min-w-80 max-w-80 text-center text-gray-500 pt-3 ">
            {{ __('chapter title') }}
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3 ">
            {{ __('description') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            {{ __('debit') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            {{ __('credit') }}
        </div>
    </div>
    <div class="h-96 overflow-y-auto" x-data="{ transactions: [], counter: 2 }">
        <div id="transactions">
            @foreach ($transactions as $i => $transaction)
                <div class="transaction flex gap-2 items-center px-4 " id="originalTransactions" x-data="{ hover: false, lastcounter: 1 }"
                    @mouseenter="hover = true" @mouseleave="hover = false">

                    <x-text-input value="{{ $transaction->id ?? '' }}"
                        name="transactions[{{ $i }}][transaction_id]" label_text_class="text-gray-500"
                        label_class="w-full hidden"></x-text-input>

                    <div class="relative flex-1 text-center max-w-8 pt-2 pb-5 transaction-count-container">
                        <span class="transaction-count block" x-text="lastcounter"></span>

                        <button @click="transactions.splice(index, 1)" x-show="hover" type="button"
                            class="absolute left-0 top-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor"
                                class="px-2 size-8 rounded-md  h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 text-white font-bold removeTransaction">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>

                    </div>
                    <div class="flex-1 min-w-24 max-w-24 pb-3">

                        <x-text-input value="{{ $transaction->subject ? $transaction->subject->formattedCode() : '' }}"
                            id="value" name="transactions[{{ $i }}][code]"
                            label_text_class="text-gray-500" label_class="w-full"
                            input_class="border-white hover:border-slate-400 value codeInput "></x-text-input>

                    </div>
                    <x-subject-select-box :subjects="$subjects" :name="'transactions[' . $i . '][subject_id]'" :value="$transaction->subject_id ?? ''"></x-subject-select-box>
                    <div class="flex-1 w-[200px] pb-3">
                        <x-text-input value="{{ $transaction->desc }}"
                            placeholder="{{ __('this document\'s row description') }}" id="desc"
                            name="transactions[{{ $i }}][desc]" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white hover:border-slate-400 "></x-text-input>

                    </div>

                    <div class="flex-1 min-w-24 max-w-32 pb-3">
                        <x-text-input value="{{ $transaction->debit }}" placeholder="0" id="debit"
                            name="transactions[{{ $i }}][debit]" label_text_class="text-gray-500"
                            label_class="w-full"
                            input_class="border-white hover:border-slate-400 debitInput"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32 pb-3">
                        <x-text-input value="{{ $transaction->credit }}" placeholder="0" id="credit"
                            name="transactions[{{ $i }}][credit]" label_text_class="text-gray-500"
                            label_class="w-full"
                            input_class="border-white hover:border-slate-400 creditInput"></x-text-input>

                    </div>
                </div>
            @endforeach

            <div class="copied-transactions">
                <template x-for="(transaction, index) in transactions" :key="index">
                    <div class="transaction flex gap-2 items-center px-4 " x-data="{ hover: false, lastcounter: counter++ }"
                        @mouseenter="hover = true" @mouseleave="hover = false" x-html="transaction"></div>
                </template>
            </div>
        </div>

        <button class="flex justify-content gap-4 align-center w-full px-4"
            @click="transactions.push(document.getElementById('originalTransactions').innerHTML)" type="button">
            <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active"
                id="addTransaction">
                <span class="text-2xl">+</span>
                {{ __('Add Transaction') }}
            </div>
        </button>
    </div>

    <hr style="">
    <div class="flex justify-end px-4 gap-2">
        <span class="min-w-24 text-center text-gray-500" id="debitSum">0</span>
        <span class="min-w-24 text-center text-gray-500" id="creditSum">0</span>
    </div>
</x-card>
<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('documents.index') }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button type="submit" class="btn btn-default rounded-md"> {{ __('save and create new document') }} </button>
    <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('save and close form') }} </button>
</div>

<script type="module">
    jalaliDatepicker.startWatch({});
</script>
<script>
    function openSelectBox(e) {
        document.querySelectorAll(".selfSelectBox").forEach(function(e) {
            e.style.display = "none"
        }), e.querySelector(".selfSelectBox").style.display = "block"
    }

    function fillInput(e, t) {
        let n = e.querySelector(".selfItemTitle").innerText,
            a = e.querySelector(".selfItemCode").innerText,
            s = e.querySelector(".selfItemId").innerText;
        document.querySelectorAll(".subject_name")[t].value = n, document.querySelectorAll(".subject_id")[t].value = s,
            document.querySelectorAll(".value")[t].value = a
    }

    function countInputs() {
        searchInputs = document.querySelectorAll(".searchInput"), resultDivs = document.querySelectorAll(".resultDiv"),
            searchResultDivs = document.querySelectorAll(".searchResultDiv"), voidInputSearch()
    }
    // var p2e = e => e.replace(/[۰-۹]/g, e => "۰۱۲۳۴۵۶۷۸۹".indexOf(e)),
    //     codeInputs = document.getElementById("transactions").getElementsByClassName("codeInput"),
    //     codeSelectBoxs = document.getElementById("transactions").getElementsByClassName("codeSelectBox"),
    //     removeButtons = document.getElementById("transactions").getElementsByClassName("removeTransaction"),
    //     debitInputs = document.getElementById("transactions").getElementsByClassName("debitInput"),
    //     creditInputs = document.getElementById("transactions").getElementsByClassName("creditInput");
    // const csrf = document.querySelector('meta[name="csrf_token"]').getAttribute("content");
    // let searchInputs = document.querySelectorAll(".searchInput"),
    //     resultDivs = document.querySelectorAll(".resultDiv"),
    //     searchResultDivs = document.querySelectorAll(".searchResultDiv");

    // function onCodeInputChange(e, t) {
    //     let n = e.target.value;
    //     n = p2e(n), e.target.value = n;
    //     let a = subjects.findIndex(e => n === e.code && e.parent_id); - 1 !== a && (t.value = subjects[a].id)
    // }

    // function onCodeSelectBoxChange(e, t) {
    //     let n = e.target.value,
    //         a = subjects.findIndex(e => parseInt(n) === parseInt(e.id)); - 1 !== a && (t.value = subjects[a].code)
    // }

    // function deleteAction() {
    //     document.getElementsByClassName("removeTransaction").length > 1 && (this.parentNode.parentNode.remove(),
    //         updateTransactionCounter())
    // }

    // function activeRow(e) {
    //     console.log(e.currentTarget), deactivateAllTransactionRow(), e.currentTarget.classList.remove(
    //         "deactivated-transaction-row")
    // }

    // function debitInputChange(e, t) {
    //     let n = e.target.value;
    //     n = p2e(n), e.target.value = parseInt(n) > 0 ? parseInt(n) : null, n <= 0 ? e.target.value = null : n > 0 && (t
    //         .value = null), updateSumCalculation()
    // }

    // function creditInputChange(e, t) {
    //     let n = e.target.value;
    //     n = p2e(n), e.target.value = parseInt(n) > 0 ? parseInt(n) : null, n <= 0 ? e.target.value = null : n > 0 && (t
    //         .value = null), updateSumCalculation()
    // }

    // function updateSumCalculation() {
    //     let e = Array.from(document.getElementsByClassName("debitInput")),
    //         t = Array.from(document.getElementsByClassName("creditInput")),
    //         n = 0,
    //         a = 0;
    //     e.map(e => e.value > 0 ? n += parseInt(e.value) : ""), t.map(e => e.value > 0 ? a += parseInt(e.value) : ""),
    //         document.getElementById("creditSum").innerText = a, document.getElementById("debitSum").innerText = n
    // }
    // updateSumCalculation();
    // for (var i = 0; i < codeInputs.length; i++) {
    //     let e = codeInputs[i],
    //         t = codeSelectBoxs[i],
    //         n = removeButtons[i],
    //         a = debitInputs[i],
    //         s = creditInputs[i];
    //     e.addEventListener("keyup", e => onCodeInputChange(e, t)), t.addEventListener("change", t =>
    //         onCodeSelectBoxChange(t, e)), n.addEventListener("click", deleteAction), a.addEventListener("keyup",
    //         e => debitInputChange(e, s)), s.addEventListener("keyup", e => creditInputChange(e, a))
    // }

    // function deactivateAllTransactionRow() {
    //     Array.from(document.getElementById("transactions").getElementsByClassName("transaction")).map(e => e.classList
    //         .add("deactivated-transaction-row"))
    // }

    // function updateTransactionCounter() {
    //     Array.from(document.getElementsByClassName("transaction-count")).map((e, t) => e.innerText = t + 1)
    // }

    // function openSelectBox(e) {
    //     document.querySelectorAll(".selfSelectBox").forEach(function(e) {
    //         e.style.display = "none"
    //     }), e.querySelector(".selfSelectBox").style.display = "block"
    // }

    // function fillInput(e, t) {
    //     let n = e.querySelector(".selfItemTitle").innerText,
    //         a = e.querySelector(".selfItemCode").innerText,
    //         s = e.querySelector(".selfItemId").innerText;
    //     document.querySelectorAll(".subject_name")[t].value = n, document.querySelectorAll(".subject_id")[t].value = s,
    //         document.querySelectorAll(".value")[t].value = a
    // }

    // function countInputs() {
    //     searchInputs = document.querySelectorAll(".searchInput"), resultDivs = document.querySelectorAll(".resultDiv"),
    //         searchResultDivs = document.querySelectorAll(".searchResultDiv"), voidInputSearch()
    // }

    // function voidInputSearch() {
    //     searchInputs.forEach((e, t) => {
    //         e.addEventListener("input", e => debouncedSearch(e, t))
    //     })
    // }

    // function debounce(e, t) {
    //     let n;
    //     return function(...a) {
    //         clearTimeout(n), n = setTimeout(() => {
    //             e.apply(this, a)
    //         }, t)
    //     }
    // }

    // function formatCode(e) {
    //     let t = [];
    //     for (let n = 0; n < e.length; n += 3) t.push(e.substring(n, n + 3));
    //     return e = t.join("/"), ["fa", "fa_IR"].includes("fa") && (e = convertToFarsi(e)), e
    // }

    // function convertToFarsi(e) {
    //     let t = {
    //         0: "۰",
    //         1: "۱",
    //         2: "۲",
    //         3: "۳",
    //         4: "۴",
    //         5: "۵",
    //         6: "۶",
    //         7: "۷",
    //         8: "۸",
    //         9: "۹"
    //     };
    //     return e.replace(/[0-9]/g, e => t[e])
    // }

    // function searchQuery(e, t) {
    //     fetch("/subjects/search", {
    //         method: "POST",
    //         headers: {
    //             "Content-Type": "application/json",
    //             "X-CSRF-Token": csrf
    //         },
    //         body: JSON.stringify({
    //             query: e
    //         })
    //     }).then(e => {
    //         if (!e.ok) throw Error("خطا در دریافت پاسخ");
    //         return e.json()
    //     }).then(e => {
    //         resultDivs[t].style.display = "none", searchResultDivs[t].style.display = "block", 0 == e.length ?
    //             searchResultDivs[t].innerHTML = '<span class="block text-center">چیزی پیدا نشد!</span>' : e
    //             .forEach(e => {
    //                 let n = e.name,
    //                     a = e.code;
    //                 if (0 == e.sub_subjects.length) {
    //                     let s = `
    //                     <div class="w-full ps-2 mb-4">
    //                         <div class="flex justify-between">
    //                             <span>
    //                                 ${n}
    //                             </span>

    //                             <span>
    //                                 ${formatCode(a)}
    //                             </span>
    //                         </div>
    //                     </div>
    //                     `;
    //                     searchResultDivs[t].innerHTML = s
    //                 } else {
    //                     let l = e.sub_subjects,
    //                         r = `
    //                     <div class="w-full ps-2 mb-4">
    //                         <div class="flex justify-between">
    //                             <span>
    //                                 ${n}
    //                             </span>

    //                             <span>
    //                                 ${formatCode(a)}
    //                             </span>
    //                         </div>
    //                     </div>
    //                     <div class="ps-1 mt-4">
    //                         <div class="border-s-[1px] ps-7 border-[#ADB5BD]" id="sub-${t}"></div>
    //                     </div>
    //                     `;
    //                     searchResultDivs[t].innerHTML = r, l.forEach(e => {
    //                         let n = document.getElementById(`sub-${t}`),
    //                             a = `
    //                                 <a href="javascript:void(0)"
    //                                     class="selfSelectBoxItems flex justify-between mb-4"
    //                                     onclick="fillInput(this, '${t}')">
    //                                     <span class="selfItemTitle">
    //                                         ${e.name}
    //                                     </span>
    //                                     <span class="selfItemCode">
    //                                         ${formatCode(e.code)}
    //                                     </span>
    //                                     <span class="selfItemId hidden">${e.id}</span>
    //                                 </a>
    //                                 `;
    //                         n.innerHTML += a
    //                     })
    //                 }
    //             })
    //     }).catch(e => {
    //         console.error("خطایی رخ داده: ", e)
    //     })
    // }
    // document.getElementById("addTransaction").addEventListener("click", function() {
    //     var e = document.getElementById("transactions"),
    //         t = e.getElementsByClassName("transaction"),
    //         n = t[t.length - 1].cloneNode(!0),
    //         a = n.querySelector(".transaction-count").innerText;
    //     n.querySelectorAll(".selfSelectBoxItems").forEach(e => {
    //         e.setAttribute("onclick", `fillInput(this, '${a}')`)
    //     }), deactivateAllTransactionRow(), n.classList.remove("deactivated-transaction-row");
    //     for (var s = n.getElementsByTagName("select"), l = 0; l < s.length; l++) s[l].name = s[l].name.replace(
    //         /\[\d+\]/, "[" + t.length + "]"), s[l].value = "";
    //     for (var r = n.getElementsByTagName("input"), l = 0; l < r.length; l++) r[l].name = r[l].name.replace(
    //         /\[\d+\]/, "[" + t.length + "]"), r[l].value = "";
    //     n.getElementsByClassName("removeTransaction")[0].addEventListener("click", deleteAction);
    //     var c = n.getElementsByClassName("codeInput")[0],
    //         o = n.getElementsByClassName("codeSelectBox")[0];
    //     c.addEventListener("keyup", e => onCodeInputChange(e, o)), o.addEventListener("change", e =>
    //         onCodeSelectBoxChange(e, c));
    //     var u = n.getElementsByClassName("debitInput")[0],
    //         d = n.getElementsByClassName("creditInput")[0];
    //     u.addEventListener("keyup", e => debitInputChange(e, d)), d.addEventListener("keyup", e =>
    //         creditInputChange(e, u)), e.appendChild(n), updateTransactionCounter(), countInputs()
    // }), document.addEventListener("click", function(e) {
    //     e.target.closest(".selfSelectBoxContainer") || document.querySelectorAll(".selfSelectBox").forEach(
    //         function(e) {
    //             e.style.display = "none", resultDivs.forEach((e, t) => {
    //                 searchInputs[t].value = "", e.style.display = "block", searchResultDivs[t].style
    //                     .display = "none"
    //             })
    //         })
    // });
    // const debouncedSearch = debounce(function(e, t) {
    //     let n = e.target.value;
    //     n ? searchQuery(n, t) : (resultDivs[t].style.display = "block", searchResultDivs[t].style.display =
    //         "none")
    // }, 500);
    // voidInputSearch();
</script>
