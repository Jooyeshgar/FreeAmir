<x-app-layout :title="__('API tokens')">
    <div class="container mx-auto max-w-4xl space-y-6">
        <h1 class="text-2xl font-bold">{{ __('API tokens') }}</h1>

        @if (session('plainTextToken'))
            <div class="alert alert-warning">
                <div>
                    <div>{{ __('Copy this token now. It will not be shown again.') }}</div>
                    <code class="break-all">{{ session('plainTextToken') }}</code>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('api-tokens.store') }}" class="space-y-4 rounded-box bg-base-100 p-4 shadow">
            @csrf
            <label class="form-control">
                <span class="label-text">{{ __('Token name') }}</span>
                <input class="input input-bordered" name="name" required value="{{ old('name') }}">
            </label>

            <div>
                <div class="mb-2 font-medium">{{ __('Permissions') }}</div>
                <div class="grid gap-2 md:grid-cols-2">
                    @foreach ($permissions as $permission)
                        <label class="label cursor-pointer justify-start gap-2">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm"
                                name="permissions[]"
                                value="{{ $permission->name }}"
                                @checked(in_array($permission->name, old('permissions', []), true))
                            >
                            <span class="label-text">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button class="btn btn-primary">{{ __('Create token') }}</button>
        </form>

        <div class="overflow-x-auto rounded-box bg-base-100 shadow">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Permissions') }}</th>
                        <th>{{ __('Last used') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td>{{ implode(', ', $token->abilities ?? []) }}</td>
                            <td>{{ $token->last_used_at }}</td>
                            <td>
                                <form method="POST" action="{{ route('api-tokens.destroy', $token->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-error btn-sm">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">{{ __('No API token found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
