<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products Group') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ _('Roles') }}</span>

            <a href="{{ route('roles.create') }}">
                <botton class="btn btn-primary">{{ _('Add new Role') }}</botton>
            </a>
            <form class="right" method="GET" action="{{ route('roles.index') }}">
                <input type="text" name="search" placeholder="{{ _('Search') }}"
                    class="input input-bordered w-full max-w-xs">
                <button type="submit" class="right btn-flat"><i class=" icon-search"></i></button>
            </form>

            <table class='table w-full mt-4 overflow-auto'>
                <tr>
                    <th class="center-align">{{ _('Name') }}</th>
                    <th class="center-align">{{ _('guard') }}</th>
                    <th class="center-align">{{ _('description') }}</th>
                    <th class="center-align"></th>
                </tr>
                @foreach ($roles as $role)
                    <tr>
                        <td class="center-align" style="direction: ltr">{{ $role->name }}</td>
                        <td class="center-align">{{ $role->guard_name }}</td>
                        <td class="center-align">{{ $role->description }}</td>
                        <td class="center-align">
                            <a class="btn btn-sm btn-info" href="{{ route('roles.edit', $role->id) }}">
                                {{ _('Edit') }}</a>
                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('Are you sure?')"
                                    class="btn btn-sm btn-error">{{ _('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

</x-app-layout>
