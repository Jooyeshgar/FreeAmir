<div class="selfSelectBoxContainer relative flex-1 w-full pb-3" onclick="openSelectBox(this)">
    <x-text-input value="" readonly id="subject_id" label_text_class="text-gray-500" label_class="w-full" input_class="border-white subject_name codeSelectBox "></x-text-input>
    <input type="hidden" name="{{ $name }}" class="subject_id" value="{{ $value }}">
    <div class="selfSelectBox hidden absolute z-[3] top-[40px] w-full h-[300px] bg-white overflow-auto px-4 pb-4 rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]">
        <div class="sticky top-0 left-0 right-0 w-full bg-white py-2">
            <div class="relative">
                <x-text-input name="" value="" label_text_class="text-gray-500" label_class="w-full" input_class="pe-8 text-sm searchInput"
                    placeholder="{{ __('Search... (heading code or name)') }}"></x-text-input>

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

            <div class="mt-4 text-xs hidden searchResultDiv">
            </div>
            <div class="mt-4 text-xs resultDiv">
                @foreach ($subjects as $subject)
                    <div class="w-full ps-2 mb-4">
                        <div class="flex justify-between">
                            <span>
                                {{ $subject->name }}
                            </span>

                            <span>
                                {{ $subject->formattedCode() }}
                            </span>
                        </div>
                        @if ($subject->children->count() > 0)
                            <div class="ps-1 mt-4">
                                <div class="border-s-[1px] ps-7 border-[#ADB5BD]">
                                    @foreach ($subject->children as $children)
                                        <a href="javascript:void(0)" class="selfSelectBoxItems flex justify-between mb-4" onclick="fillInput(this, '0')">
                                            <span class="selfItemTitle">
                                                {{ $children->name }}
                                            </span>
                                            <span class="codeList" data-name="{{ $children->name }}" data-code="{{ $children->code }}" hidden></span>
                                            <span class="selfItemCode">
                                                {{ $children->formattedCode() }}
                                            </span>
                                            <span class="selfItemId hidden">{{ $children->id }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

</div>