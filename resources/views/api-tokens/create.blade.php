<x-app-layout :title="__('API Tokens')">
    <x-show-message-bags />

    @php
        $permissionGroups = $permissions->groupBy(fn($permission) => \Illuminate\Support\Str::before($permission, '.'));
    @endphp

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" action="{{ route('api-tokens.store') }}">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5" x-data="{
                    permissionSearch: '',
                    matches(permission) {
                        return permission.toLowerCase().includes(this.permissionSearch.trim().toLowerCase());
                    },
                    hasMatches(permissions) {
                        return permissions.some((permission) => this.matches(permission));
                    },
                }">
                    <div class="col-span-4 flex items-center justify-between gap-3">
                        <span class="card-title">{{ __('Create token') }}</span>

                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-semibold">{{ __('Permissions') }}</h3>
                            <span class="badge badge-ghost">{{ $permissions->count() }}</span>
                        </div>
                    </div>

                    <div class="col-span-4 grid gap-4 md:grid-cols-2">
                        <x-input name="name" id="name" placeholder="{{ __('Token name') }}" title="" :value="old('name')" required />

                        <x-input name="permission_search" id="permission-search" placeholder="{{ __('Search') }}" title="" x-model="permissionSearch" />
                    </div>

                    <div class="col-span-4">
                        <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            @foreach ($permissionGroups as $group => $groupPermissions)
                                <section class="rounded-xl border border-base-300 bg-base-100/80 p-4 shadow-sm"
                                    x-show="hasMatches({{ Js::from($groupPermissions->values()) }})">
                                    <div class="mb-4 flex items-center justify-between gap-3 border-b border-base-200 pb-3">
                                        <h4 class="font-semibold">
                                            {{ \Illuminate\Support\Str::headline($group) }}
                                        </h4>
                                        <span class="badge badge-outline badge-sm">
                                            {{ $groupPermissions->count() }}
                                        </span>
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($groupPermissions as $permission)
                                            @php($permissionId = 'permissions-'.\Illuminate\Support\Str::slug($permission))
                                            <div
                                                class="relative flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-base-200 px-3 py-2 transition hover:border-primary/40 hover:bg-base-200/60"
                                                x-show="matches({{ Js::from($permission) }})">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <label for="{{ $permissionId }}" class="cursor-pointer truncate text-sm after:absolute after:inset-0" title="{{ $permission }}">
                                                        {{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($permission, '.')) }}
                                                    </label>
                                                    @if ($permission === 'invoices.approve')
                                                        <span class="tooltip tooltip-warning z-20 shrink-0">
                                                            <span id="invoice-approval-warning" role="tooltip" class="tooltip-content start-0! end-auto! transform-none! max-w-[min(18rem,calc(100vw-8rem))]!">
                                                                {{ __('This token can approve invoices and post accounting documents through MCP. Enable this only for clients that ask for user confirmation before approval.') }}
                                                            </span>
                                                            <button type="button" class="grid size-5 place-items-center rounded text-warning focus-visible:outline"
                                                                aria-label="{{ __('Invoice approval warning') }}" aria-describedby="invoice-approval-warning">
                                                                <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                                </svg>
                                                            </button>
                                                        </span>
                                                    @endif
                                                </span>
                                                <input class="checkbox relative z-10 shrink-0" type="checkbox" name="permissions[]" id="{{ $permissionId }}"
                                                    value="{{ $permission }}" @checked(in_array($permission, old('permissions', []), true)) />
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-span-4">
                        <button class="btn btn-primary" type="submit">{{ __('Create token') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
