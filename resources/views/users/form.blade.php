@php
    $oldRoles = old('role', $user?->roles->pluck('name')->toArray() ?? []);
    $oldCompanies = old('company', $user?->companies->pluck('id')->map(fn($id) => (string) $id)->toArray() ?? []);
@endphp

<div class="grid grid-cols-2 gap-6">
    <x-input title="{{ __('Name') }}" name="name" :value="old('name', $user->name ?? '')" />
    <x-input title="{{ __('Email') }}" name="email" :value="old('email', $user->email ?? '')" type="email" />
    <x-input title="{{ __('Password') }}" type="password" name="password" />
    <x-input title="{{ __('Confirm Password') }}" type="password" name="password_confirmation" />
</div>

@can('management.roles.*')
    <div class="divider"></div>
    <h3 class="label">{{ __('Roles') }}</h3>
    <div class="grid gap-3 grid-cols-5">
        @foreach ($roles as $role)
            <x-checkbox :title="$role->name" name="role[]" :value="$role->name" id="role-{{ $role->id }}"
                :checked="in_array($role->name, $oldRoles)" />
        @endforeach
    </div>
@endcan

<div class="divider"></div>
<h3 class="label">{{ __('Companies') }}</h3>
<div class="grid gap-3 grid-cols-5">
    @foreach ($companies as $company)
        <x-checkbox :title="$company->name" name="company[]" :value="$company->id" id="company-{{ $company->id }}"
            :checked="in_array((string) $company->id, $oldCompanies)" />
    @endforeach
</div>
