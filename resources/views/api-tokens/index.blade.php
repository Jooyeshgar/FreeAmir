<x-app-layout :title="__('API Tokens')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-base-200">
                <div class="flex items-center gap-3">
                    <span class="card-title">{{ __('API Tokens') }}</span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if (\Illuminate\Support\Facades\Route::has('mcp.handle'))
                        <!-- MCP Connection Box in the Corner -->
                        <div class="flex items-center gap-2 rounded-xl border border-primary/20 bg-primary/5 px-3 py-1.5 text-xs shadow-sm"
                             x-data="{ copied: false, endpoint: '{{ url('/api/mcp') }}' }">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-primary">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                {{ __('MCP Server URL') }}:
                            </span>
                            <code class="font-mono text-xs bg-base-100 px-2 py-1 rounded border border-base-300 text-base-content select-all tracking-tight" dir="ltr">{{ url('/api/mcp') }}</code>
                            <button type="button"
                                    class="btn btn-xs btn-ghost text-primary hover:bg-primary/20 gap-1 font-normal"
                                    @click="navigator.clipboard.writeText(endpoint); copied = true; setTimeout(() => copied = false, 2000)"
                                    :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'">
                                <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                                <svg x-show="copied" class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" class="text-success font-semibold">{{ __('Copied!') }}</span>
                            </button>

                            <details class="dropdown dropdown-end">
                                <summary class="btn btn-xs btn-circle btn-ghost text-base-content/60" title="{{ __('More Information') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </summary>
                                <div class="dropdown-content z-50 mt-2 w-80 rounded-xl border border-base-300 bg-base-100 p-3 shadow-xl text-xs space-y-2">
                                    <div class="font-semibold text-base-content border-b border-base-200 pb-1">
                                        {{ __('MCP Connection Endpoints') }}
                                    </div>
                                    <div>
                                        <span class="text-base-content/70 block mb-0.5 font-medium">HTTP Endpoint (POST):</span>
                                        <code class="block font-mono bg-base-200 p-1.5 rounded text-[11px] select-all break-all" dir="ltr">{{ url('/api/mcp') }}</code>
                                    </div>
                                    <div>
                                        <span class="text-base-content/70 block mb-0.5 font-medium">SSE Endpoint (GET):</span>
                                        <code class="block font-mono bg-base-200 p-1.5 rounded text-[11px] select-all break-all" dir="ltr">{{ url('/api/mcp/sse') }}</code>
                                    </div>
                                    <p class="text-[11px] text-base-content/60 pt-1 border-t border-base-200">
                                        {{ __('Connect AI agents (Claude Desktop, Cursor, etc.) using Bearer token authentication.') }}
                                    </p>
                                </div>
                            </details>
                        </div>
                    @endif

                    <a href="{{ route('api-tokens.create') }}">
                        <button class="btn btn-primary">{{ __('Create token') }}</button>
                    </a>
                </div>
            </div>

            @if (session('plainTextToken'))
                <div class="alert alert-warning shadow-md" x-data="{ tokenCopied: false }">
                    <div class="w-full space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-sm">{{ __('Copy this token now. It will not be shown again.') }}</span>
                            <button type="button" class="btn btn-xs btn-outline bg-base-100/50 hover:bg-base-100 gap-1"
                                    @click="navigator.clipboard.writeText('{{ session('plainTextToken') }}'); tokenCopied = true; setTimeout(() => tokenCopied = false, 2000)">
                                <span x-show="!tokenCopied">{{ __('Copy Token') }}</span>
                                <span x-show="tokenCopied" class="text-success font-bold">{{ __('Copied!') }}</span>
                            </button>
                        </div>
                        <div class="rounded-lg bg-base-100/80 p-2 border border-warning/40">
                            <code class="break-all font-mono text-xs select-all text-base-content block" dir="ltr">{{ session('plainTextToken') }}</code>
                        </div>
                        @if (\Illuminate\Support\Facades\Route::has('mcp.handle'))
                            <div class="text-xs text-base-content/80 flex flex-wrap items-center gap-1.5 pt-1">
                                <span>{{ __('Connect your AI agent using this token and the MCP URL:') }}</span>
                                <code class="font-mono bg-base-100/80 px-2 py-0.5 rounded border border-warning/40 text-xs" dir="ltr">{{ url('/api/mcp') }}</code>
                            </div>
                        @endif
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
