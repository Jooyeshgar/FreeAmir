<header class="navbar flex justify-between">
    <div class="flex-none">
        <div class="flex items-center bg-gray-200 rounded-xl mx-4 p-1">
            <img src="/images/logo.png" alt="Logo" width="50" class="">
        </div>
        <ul class="menu menu-horizontal px-1 bg-gray-200 rounded-xl">
            <x-menu />
        </ul>
    </div>

    <div class="text-right">
        <ul class="menu menu-horizontal px-1 bg-gray-200 rounded-xl">
            <li>
                <details>
                    <summary>{{ Auth::user()->name }}</summary>
                    <ul>
                        <li><a href="/logout">{{ __('Logout') }}</a></li>
                    </ul>
                </details>
            </li>
        </ul>
    </div>
</header>
