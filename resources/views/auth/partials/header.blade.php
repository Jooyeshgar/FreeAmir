<header class="bg-gray-200 py-2 px-4 flex items-center justify-between">
    <div class="flex items-center">
        <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
        <h1 class="font-bold">{{ __('Amirs free accounting software') }}</h1>
    </div>
    <div class="language-select">
        <form method="POST" action="{{ route('locale') }}" class="language-picker__form">
            @csrf
            <label for="auth-locale" class="sr-only">{{ __('Language') }}</label>
            <select id="auth-locale" name="locale" class="locale select pr-10 pl-3 py-2" onchange="this.form.submit()">
                <option lang="fa" value="fa" @selected(app()->isLocale('fa'))>{{ __('Farsi') }}</option>
                <option lang="en" value="en" @selected(app()->isLocale('en'))>{{ __('English') }}</option>
            </select>
        </form>
    </div>
</header>