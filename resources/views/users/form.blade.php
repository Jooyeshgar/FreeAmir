@php
    $oldCompanyRoles = old('company_roles', $companyRoles ?? []);
@endphp

<div class="grid grid-cols-2 gap-6">
    <x-input title="{{ __('Name') }}" name="name" :value="old('name', $user->name ?? '')" />
    <x-input title="{{ __('Email') }}" name="email" :value="old('email', $user->email ?? '')" type="email" />
    <x-input title="{{ __('Password') }}" type="password" name="password" />
    <x-input title="{{ __('Confirm Password') }}" type="password" name="password_confirmation" />
    @php
        $employeeOptions = ($employees ?? collect())->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray();
        $hasEmployees = isset($employees) && $employees->isNotEmpty();
    @endphp

    @if ($user && auth()->user()->cannot('access-super-admin-panel'))
        <div>
            <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :options="$employeeOptions"
                :selected="old('employee_id') ?? $user?->employee?->id" :disabled="$user?->employee || !$hasEmployees" />

            <div class="text-warning mt-1">
                {{ $user?->employee ? __('The user is already linked to an employee.') : (!$hasEmployees ? __('There are no unlinked employees available. Please create a new employee first.') : '') }}
                @if (! $user?->employee)
                    <a class="inline-flex items-center align-middle text-blue-500 hover:underline dark:text-sky-300" href="{{ route('hr.employees.create') }}" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0M19.5 8.25v4.5M21.75 10.5h-4.5" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>

<div class="divider"></div>
<h3 class="label">{{ __('Company and fiscal-year roles') }}</h3>
<p class="mb-4 text-sm text-slate-500">{{ __('Select at least one role in each company the user may access. Clear all roles to remove access to that company.') }}</p>
<div class="space-y-4">
    @foreach ($companies as $company)
        @php($selectedRoles = $oldCompanyRoles[$company->id] ?? $oldCompanyRoles[(string) $company->id] ?? [])
        <section class="rounded-xl border border-base-300 p-4">
            <h4 class="mb-3 font-semibold">
                {{ $company->name }} - {{ localizeNumber($company->fiscal_year) }}
            </h4>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($roles as $role)
                    @continue($role->name === 'Super-Admin' && ! auth()->user()->hasRole('Super-Admin'))
                    <x-checkbox :title="$role->name" name="company_roles[{{ $company->id }}][]" :value="$role->name"
                        id="company-{{ $company->id }}-role-{{ $role->id }}"
                        :checked="in_array($role->name, $selectedRoles, true)" />
                @endforeach
            </div>
        </section>
    @endforeach
</div>
