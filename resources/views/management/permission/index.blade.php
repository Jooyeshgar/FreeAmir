<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Permissions') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Permissions') }}</span>

            <a href="{{ route('permissions.create') }}">
                <button class="btn btn-primary">{{ __('Add new Permission') }}</button>
            </a>
            <form class="flex items-center right" method="GET" action="{{ route('permissions.index') }}">
                <label class="input input-bordered flex items-center gap-2">
                    <input type="text" name="search" class="grow" placeholder="Search" />
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"
                        class="w-4 h-4 opacity-70">
                        <path fill-rule="evenodd"
                            d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z"
                            clip-rule="evenodd" />
                    </svg>
                </label>
            </form>


            <table class='table w-full mt-4 overflow-auto'>
                <tr>
                    <th class="center-align">{{ __('Name') }}</th>
                    <th class="center-align">{{ __('guard') }}</th>
                    <th></th>
                </tr>
                @foreach ($permissions as $permission)
                    <tr>
                        <td class="center-align" style="direction: ltr">{{ $permission->name }}</td>
                        <td class="center-align">{{ $permission->guard_name }}</td>
                        <td class="center-align">
                            <a class="btn btn-sm btn-info" href="{{ route('permissions.edit', $permission->id) }}">
                                {{ __('Edit') }}
                            </a>
                            <form action="{{ route('permissions.destroy', $permission->id) }}" method="post"
                                style="display: inline-block">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-error" type="submit"
                                    onclick="return confirm('{{ __('Are you sure?') }}')">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</x-app-layout>
