<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Welcome') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <main class="mt-10">
        <div>
            <h1 class="text-[#495057] text-[24px]">
                داشبورد
            </h1>
        </div>

        <section class="flex gap-4 max-[850px]:flex-wrap">
            <div class="w-1/3 max-[850px]:w-full bg-[#E9ECEF] rounded-[16px] shadow-[0px_43px_27px_0px_#00000012] relative">
                <div class="flex justify-between items-center max-[850px]:flex-col max-[850px]:mt-4">
                    <div>
                        <h2 class="text-[#495057] ms-3">
                            موجودی صندوق
                        </h2>
                    </div>

                    <div class="flex bg-[#DEE2E6] rounded-[16px] m-1 overflow-hidden">
                        <select class="select bg-[#DEE2E6] text-[#495057] w-[140px] max-w-xs">
                            <option disabled selected>صندوق اصلی</option>
                            <option>تست</option>
                            <option>تست</option>
                        </select>

                        <select class="select bg-[#DEE2E6] text-[#495057] w-[120px] max-w-xs">
                            <option disabled selected>یک ساله</option>
                            <option>تست</option>
                            <option>تست</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-[10px] px-5 text-[#495057] mt-3">
                        <span>483٬750٬600</span>

                        <span class="flex gap-1">110,154,700 نسبت به ماه قبل <span class="flex text-[#20C997BF]">(11.6 <svg xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>)</span></span>
                    </div>

                    <div class="p-2">
                        <canvas id="lineChart" class="bg-white rounded-[16px]"></canvas>
                    </div>
                </div>
            </div>

            <div class="gaugeChartContainer w-1/3 max-[850px]:w-full relative bg-[#E9ECEF] rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]">
                <div class="flex justify-between items-center h-[62px]">
                    <h2 class="text-[#495057] ms-3">
                        وضعیت اهداف ماهانه
                    </h2>
                </div>

                <div class="px-4">
                    <canvas id="gaugeChart" class="absolute left-0 right-0 w-full ps-2 max-[1200px]:static"></canvas>
                </div>
            </div>

            <div class="w-1/3 max-[850px]:w-full bg-[#E9ECEF] rounded-[16px] shadow-[0px_43px_27px_0px_#00000012] relative">
                <div class="flex justify-between items-center h-[62px]">
                    <h2 class="text-[#495057] ms-3">
                        دسترسی سریع
                    </h2>
                </div>

                <div class="flex flex-wrap text-[#212529] mt-4 max-[850px]:mb-4">
                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            امور مالی
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            صدور سند دستی
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            مدیریت
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            گزارشات آماری
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            امور مالی
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            صدور فاکتور
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            صدور فاکتور
                        </a>
                    </div>

                    <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                        <a href="">
                            فاکتور تک فروشی
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mt-4 mb-16">
            <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-[#495057] ms-3">
                            موجودی حساب بانکی
                        </h2>
                    </div>

                    <div class="flex bg-[#DEE2E6] rounded-[16px] m-1 overflow-hidden">
                        <a href="#" class="flex items-center justify-center bg-[#DEE2E6] text-[#242424] font-bold rounded-[16px] w-[72px] h-[56px]">
                            ...
                        </a>
                    </div>
                </div>

                <div class="text-[#495057] mt-4">
                    <div class="flex justify-between mx-4 border-b-2 border-b-[#CED4DA] pb-3 mb-4">
                        <p>
                            نام صندوق
                        </p>

                        <p>
                            موجودی
                        </p>
                    </div>

                    <div class="flex justify-between mx-4 text-[13px]">
                        <div>
                            <p class="mb-4">
                                امورات جاری
                            </p>

                            <p class="mb-4">
                                سرمایه‌گذاری شرکا
                            </p>

                            <p class="mb-4">
                                اصلی
                            </p>

                            <p class="mb-4">
                                سرمایه‌گذاری‌های متفرقه
                            </p>
                        </div>

                        <div>
                            <p class="mb-4">
                                245٬578٬350
                            </p>

                            <p class="mb-4">
                                245٬578٬350
                            </p>

                            <p class="mb-4">
                                245٬578٬350
                            </p>

                            <p class="mb-4">
                                245٬578٬350
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]">
                <div class="flex justify-between items-center max-[850px]:flex-col max-[850px]:mt-4">
                    <div>
                        <h2 class="text-[#495057] ms-3">
                            موجودی حساب بانکی
                        </h2>
                    </div>

                    <div class="flex bg-[#DEE2E6] rounded-[16px] m-1 overflow-hidden">
                        <select class="select bg-[#DEE2E6] text-[#495057] w-full max-w-xs">
                            <option disabled selected>حساب ملت ۳۴۵۳</option>
                            <option>تست</option>
                            <option>تست</option>
                        </select>

                        <select class="select bg-[#DEE2E6] text-[#495057] w-[120px] max-w-xs">
                            <option disabled selected>یک ساله</option>
                            <option>تست</option>
                            <option>تست</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-[10px] px-5 text-[#495057] mt-3">
                        <span>483٬750٬600</span>

                        <span class="flex gap-1">110,154,700 نسبت به ماه قبل <span class="flex text-[#20C997BF]">(11.6 <svg xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>)</span></span>
                    </div>

                    <div class="p-2">
                        <canvas id="midLineChart" class="bg-white rounded-[16px]"></canvas>
                    </div>
                </div>
            </div>
        </section>
    </main>

</x-app-layout>
