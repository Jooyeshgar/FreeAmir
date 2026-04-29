<x-app-layout :title="__('Dashboard')">
    <x-show-message-bags />

    <main class="mt-10">

        @hasrole('Super-Admin')
            @if ($isDebugMode && $hasDocument)
                <div class="alert alert-warning">
                    <p>جدول‌های دیتابیس شما خالی هستند. آیا مایل هستید داده‌های آزمایشی در دیتابیس شما بارگذاری شود؟</p>
                    <form method="POST" action="{{ route('home.seed-demo-data') }}" class="inline-block m-0">
                        @csrf
                        <button type="submit" class="btn btn-ghost">پر کردن دیتابیس</button>
                    </form>
                </div>
            @endif

            @if ($isDebugMode)
                <div role="alert" class="alert alert-warning flex flex-col mt-4 mb-4">
                    <div class="w-full flex items-center gap-2">
                        <p class="m-0">این ابزار فقط در حالت دیباگ فعال است و تمام داده‌های فعلی را حذف می‌کند.</p>

                        <form method="POST" action="{{ route('home.refresh-database') }}" class="inline-block m-0"
                            onsubmit="return confirm('این عملیات تمام جداول و داده‌های فعلی را حذف کرده و دیتابیس را از اول با داده‌های آزمایشی اولیه دوباره می‌سازد. ادامه می‌دهید؟')">
                            @csrf
                            <button type="submit" class="btn btn-error btn-sm">ریفرش کامل دیتابیس</button>
                        </form>
                    </div>

                    <p class="text-sm opacity-80 w-full">
                        برای غیرفعال کردن این امکانات، مقدار
                        <span class="font-mono">APP_DEBUG=false</span>
                        را در فایل
                        <span class="font-mono" dir="ltr">.env</span>
                        قرار دهید.
                    </p>
                </div>
            @endif
        @endhasrole

        <div>
            <h1 class="text-[#495057] text-[24px]">
                {{ __('Dashboard') }}
            </h1>
        </div>

        <section class="flex gap-4 max-[850px]:flex-wrap mb-4">
            @include('home.cash-and-banks')

            @can('documents.show')
                @include('home.income')
                @include('home.profit')
            @elsecan('products.index')
                @include('home.sell')
                @include('home.sold-amount')
                @include('home.quick-access')
            @endcan
        </section>

        @can('documents.show')
            <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mb-4">
                @include('home.bank-account-list')
                @include('home.bank-account-chart')
            </section>
        @endcan
        @canany(['documents.show', 'products.index', 'services.index'])
            <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mb-4">
                @include('home.popular-products')
                @include('home.warehouse')
            </section>
        @endcanany
    </main>

</x-app-layout>
