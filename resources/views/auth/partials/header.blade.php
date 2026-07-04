<header class="bg-gray-200 py-2 px-4 flex items-center justify-between">
    <div class="flex items-center">
        <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
        <h1 class="font-bold">{{ __('Amirs free accounting software') }}</h1>
    </div>
    <div class="language-select">
        <form action="" class="language-picker__form">
            <select name="language" class="locale select pr-10 pl-3 py-2">
                <option lang="fa" value="fa" selected>{{ __('Farsi') }}</option>
                <option lang="en" value="en">{{ __('English') }}</option>
            </select>
        </form>
    </div>
</header>