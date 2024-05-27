<x-login-layout>
    <header class="bg-gray-200 py-2 px-4 flex items-center justify-between">

        <div class="flex items-center">
            <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
            <h1 class="font-bold">نرم‌افزار آزاد حسابداری امیر</h1>
        </div>
        <div class="language-select">
<form action="" class="language-picker__form ">
    <select name="select select-bordered mx-2 p-3">
        <option lang="en" value="english" selected>انتخاب زبان</option>
        <option lang="fr" value="francais">Français</option>
        <option lang="it" value="italiano">Italiano</option>
    </select>
</form>

        </div>

    </header>

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 p-0 border-opacity-85  overflow-hidden   ">
        <div class="flex items-center justify-center  rounded-3xl    ">
            <div class="card w-96 p-7 mt-16	 h-373 bg-white  ">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <h1 class="font-bold text-center">ورود به حساب</h1>
                    <x-form-input title="{{ __('نام کاربری / شماره موبایل') }}" name="email"
                                  place-holder="{{ __('Enter your email') }}" :message="$errors->first('email')"/>

                    <x-form-input title="{{ __('رمز عبور') }}" name="password"
                                  place-holder="{{ __('Enter your password') }}" :message="$errors->first('password')"
                                  type="password"/>
                    <div class="flex items-center justify-between mt-4 pl-2 ">

                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white  py-2 px-8 rounded">
                            {{ __('ورود به حساب') }}
                        </button>

                        <button type="submit" class="bg-gray-300 hover:bg-gray-400 text-black  py-2 px-8 rounded">
                            {{ __('بازیابی رمز عبور') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="flex justify-center mt-4 ">
            <div class="flex space-x-4">
                <a href="#" class="ml-4 bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    شرایط استفاده از خدمات
                </a>
                <a href="#" class="mx-5 bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    سیاست‌های حفظ حریم خصوصی
                </a>
                <a href="#" class="bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    به راهنمایی نیاز دارید؟
                </a>
            </div>
        </div>
    </div>
    </div>

</x-login-layout>
