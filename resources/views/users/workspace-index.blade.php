<x-app-layout :title="__('Users')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('users.create')
                    <a href="{{ route('users.create') }}" class="btn btn-primary">{{ __('Add New User') }}</a>
                @endcan
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Email') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-4 py-2">{{ $user->name }}</td>
                            <td class="px-4 py-2">{{ $user->email }}</td>
                            <td class="px-4 py-2">
                                @can('access-super-admin-panel')
                                    <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-ghost text-blue-600 hover:text-blue-900">{{ __('View') }}</a>
                                @endcan
                                @can('users.edit')
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-ghost text-yellow-600 hover:text-yellow-900">{{ __('Edit') }}</a>
                                @endcan
                                @if (auth()->user()->canImpersonateUser($user))
                                    <form action="{{ route('users.impersonate', $user) }}" method="post" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to impersonate this user?') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-ghost text-violet-600 hover:text-violet-900">{{ __('Impersonate') }}</button>
                                    </form>
                                @endif
                                @if ($user->employee)
                                    @canany(['hr.employees.show', 'users.show'])
                                        <a href="{{ route('hr.employees.show', $user->employee) }}" class="btn btn-sm btn-outline btn-success">{{ __('View Employee') }}</a>
                                    @endcanany
                                @else
                                    @can('users.create-employee')
                                        <form action="{{ route('users.create-employee', $user) }}" method="post" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline btn-primary">{{ __('Create Employee') }}</button>
                                        </form>
                                    @endcan
                                @endif
                                @can('users.destroy')
                                    <form action="{{ route('users.destroy', $user) }}" method="post" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this user?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $users->links() !!}
        </div>
    </div>
</x-app-layout>
