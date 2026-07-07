<x-app-layout :title="__('Roles')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form action="{{ route('roles.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <div class="w-60 [&_.input]:input-sm">
                    <x-input name="search" placeholder="{{ __('Search by name') }}" :value="request('search')" title="" />
                </div>    
                <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
            </form>
            <div class="card-actions">
                <a href="{{ route('roles.create') }}" class="btn btn-primary">{{ __('Add new Role') }}</a>
            </div>

            <table class='table w-full mt-4 overflow-auto'>
                <tr>
                    <th class="center-align">{{ __('Name') }}</th>
                    <th class="center-align">{{ __('guard') }}</th>
                    <th class="center-align"></th>
                </tr>
                @foreach ($roles as $role)
                    <tr>
                        <td class="center-align" style="direction: ltr">{{ $role->name }}</td>
                        <td class="center-align">{{ $role->guard_name }}</td>
                        <td class="center-align">
                            <a class="btn btn-sm btn-info" href="{{ route('roles.edit', $role->id) }}">
                                {{ __('Edit') }}</a>
                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('{{ __('Are you sure?') }}')"
                                    class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

</x-app-layout>
