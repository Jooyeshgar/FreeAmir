<x-app-layout :title="__('API Tokens')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            @if (\Illuminate\Support\Facades\Route::has('mcp.handle'))
                <div class="mb-4 rounded-lg border border-info/30 bg-info/10 p-4" x-data="{ copied: false, failed: false }">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <label for="mcp-url" class="font-semibold">{{ __('MCP Server URL') }}</label>
                            <input id="mcp-url" x-ref="value" readonly value="{{ route('mcp.handle') }}"
                                class="input input-bordered mt-2 w-full font-mono text-sm text-left" dir="ltr" @click="$el.select()">
                        </div>
                        <button type="button" class="btn btn-sm btn-info btn-outline shrink-0 gap-2"
                            @click="(async () => { try { await navigator.clipboard.writeText($refs.value.value); failed = false; copied = true; setTimeout(() => copied = false, 1500) } catch { copied = false; failed = true; $refs.value.focus(); $refs.value.select() } })()">
                            <span aria-live="polite" x-text="copied ? @js(__('Copied')) : @js(__('Copy URL'))">{{ __('Copy URL') }}</span>
                        </button>
                    </div>
                    <p x-cloak x-show="failed" role="status" class="mt-2 text-sm">{{ __('Automatic copy failed. Copy the selected text manually.') }}</p>
                </div>
            @endif

            <div class="flex items-center justify-between gap-4">
                <span class="card-title">{{ __('API Tokens') }}</span>

                <a href="{{ route('api-tokens.create') }}">
                    <button class="btn btn-primary">{{ __('Create token') }}</button>
                </a>
            </div>

            @if (session('plainTextToken'))
                <div class="rounded-lg border border-warning/30 bg-warning/10 p-4" x-data="{ copied: false, failed: false }">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <label for="new-api-token" class="font-semibold">{{ __('Copy this token now. It will not be shown again.') }}</label>
                            <input id="new-api-token" x-ref="value" readonly value="{{ session('plainTextToken') }}"
                                class="input input-bordered mt-2 w-full font-mono text-sm text-left" dir="ltr" @click="$el.select()">
                        </div>
                        <button type="button" class="btn btn-sm btn-warning btn-outline shrink-0 gap-2"
                            @click="(async () => { try { await navigator.clipboard.writeText($refs.value.value); failed = false; copied = true; setTimeout(() => copied = false, 1500) } catch { copied = false; failed = true; $refs.value.focus(); $refs.value.select() } })()">
                            <span aria-live="polite" x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </div>
                    <p x-cloak x-show="failed" role="status" class="mt-2 text-sm">{{ __('Automatic copy failed. Copy the selected text manually.') }}</p>
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
