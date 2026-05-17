<x-app-layout :title="__('API Tokens')">
    <x-show-message-bags />

    @php
        $permissionGroups = $permissions->groupBy(fn($permission) => \Illuminate\Support\Str::before($permission, '.'));
    @endphp

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" action="{{ route('api-tokens.store') }}">
                @csrf

                <div class="grid grid-cols-4 gap-5" x-data="{
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
                                            <label
                                                class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-base-200 px-3 py-2 transition hover:border-primary/40 hover:bg-base-200/60"
                                                x-show="matches({{ Js::from($permission) }})">
                                                <span class="truncate text-sm" title="{{ $permission }}">
                                                    {{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($permission, '.')) }}
                                                </span>
                                                <input name="permissions[]" id="permissions-{{ \Illuminate\Support\Str::slug($permission) }}" type="checkbox" class="checkbox checkbox-sm"
                                                    value="{{ $permission }}" @checked(in_array($permission, old('permissions', []), true)) />
                                            </label>
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
