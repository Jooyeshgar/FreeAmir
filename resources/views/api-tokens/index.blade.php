<x-app-layout :title="__('API Tokens')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-4">
                <span class="card-title">{{ __('API Tokens') }}</span>

                <a href="{{ route('api-tokens.create') }}">
                    <button class="btn btn-primary">{{ __('Create token') }}</button>
                </a>
            </div>

            @if (session('plainTextToken'))
                <div class="alert alert-warning">
                    <div>
                        <div>{{ __('Copy this token now. It will not be shown again.') }}</div>
                        <code class="break-all">{{ session('plainTextToken') }}</code>
                    </div>
                </div>
            @endif

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Permissions') }}</th>
                        <th class="px-4 py-2">{{ __('Last used') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        <tr>
                            <td class="px-4 py-2">{{ $token->name }}</td>
                            <td class="px-4 py-2">{{ implode(', ', $token->abilities ?? []) }}</td>
                            <td class="px-4 py-2">{{ $token->last_used_at }}</td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('api-tokens.destroy', $token->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-ghost text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-2">{{ __('No API token found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
