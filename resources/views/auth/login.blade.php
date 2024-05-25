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

    <div class=" flex-1 border-8 border-gray-200 p-0 border-opacity-85  overflow-hidden   ">
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
                    <div class="border border-gray-400 mt-3 p-0"></div>

                    <div class="flex justify-center mt-4 pl-2">
                        <button type="submit"
                                class="flex justify-center items-center bg-gray-300 w-full hover:bg-gray-400 text-black py-2 px-5 rounded">
                            <svg class="mx-2" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M18.1726 8.36794H17.5013V8.33335H10.0013V11.6667H14.7109C14.0238 13.6071 12.1776 15 10.0013 15C7.24005 15 5.0013 12.7613 5.0013 10C5.0013 7.23877 7.24005 5.00002 10.0013 5.00002C11.2759 5.00002 12.4355 5.48085 13.3184 6.26627L15.6755 3.90919C14.1871 2.5221 12.1963 1.66669 10.0013 1.66669C5.39922 1.66669 1.66797 5.39794 1.66797 10C1.66797 14.6021 5.39922 18.3334 10.0013 18.3334C14.6034 18.3334 18.3346 14.6021 18.3346 10C18.3346 9.44127 18.2771 8.89585 18.1726 8.36794Z"
                                        fill="#FFC107"/>
                                    <path
                                        d="M2.62891 6.12127L5.36682 8.12919C6.10766 6.29502 7.90182 5.00002 10.0014 5.00002C11.276 5.00002 12.4356 5.48085 13.3185 6.26627L15.6756 3.90919C14.1872 2.5221 12.1964 1.66669 10.0014 1.66669C6.80057 1.66669 4.02474 3.47377 2.62891 6.12127Z"
                                        fill="#FF3D00"/>
                                    <path
                                        d="M10.0008 18.3333C12.1533 18.3333 14.1091 17.5096 15.5879 16.17L13.0087 13.9875C12.1439 14.6451 11.0872 15.0008 10.0008 15C7.83328 15 5.99286 13.6179 5.29953 11.6891L2.58203 13.7829C3.9612 16.4816 6.76203 18.3333 10.0008 18.3333Z"
                                        fill="#4CAF50"/>
                                    <path
                                        d="M18.1713 8.3679H17.5V8.33331H10V11.6666H14.7096C14.3809 12.5902 13.7889 13.3971 13.0067 13.9879L13.0079 13.9871L15.5871 16.1696C15.4046 16.3354 18.3333 14.1666 18.3333 9.99998C18.3333 9.44123 18.2758 8.89581 18.1713 8.3679Z"
                                        fill="#1976D2"/>
                                </svg>

                            </svg>
                            <span>{{ __('ورود با حساب گوگل') }}</span>
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
