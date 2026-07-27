<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct() {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $actor = auth()->user();
        $isProduction = config('app.env') === 'production';

        if ($isProduction) {
            abort_unless($actor->can('access-super-admin-panel') || $actor->hasRole(__('Admin')), 403);
        }

        $users = User::query()
            ->unless($actor->can('access-super-admin-panel'), function ($query) use ($actor) {
                $companyIds = $actor->companies()->pluck('companies.id');

                $query->whereHas('companies', fn ($query) => $query->where('companies.id', getActiveCompany()))
                    ->whereDoesntHave('companies', fn ($query) => $query->whereNotIn('companies.id', $companyIds));
            })
            ->when($isProduction && ! $actor->can('access-super-admin-panel'), fn ($query) => $query->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Super-Admin')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->input('verification') === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($request->input('verification') === 'pending', fn ($query) => $query->whereNull('email_verified_at'))
            ->with(['employee', 'roles:id,name'])
            ->withCount('companies')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $view = $request->session()->get('interface_mode') === 'management'
            && $actor->can('access-super-admin-panel')
                ? 'users.index'
                : 'users.workspace-index';

        return view($view, compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = $this->assignableRoles();
        $companies = $this->assignableCompanies();

        return view('users.create', compact('roles', 'companies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'role' => 'required|array|min:1',
            'role.*' => 'required|string|exists:roles,name',
            'company' => 'required|array|min:1',
            'company.*' => 'required|integer|exists:companies,id',
        ]);

        $this->validateAssignments($request);

        DB::transaction(function () use ($request, &$user) {
            $role = array_values($request->role);
            $company = array_values($request->company);
            $user = new User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->password = bcrypt($request->input('password'));
            $user->save();

            $user->syncRoles($role);
            $user->companies()->sync($company);
        });

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::error('Management user verification notification could not be sent.', ['user_id' => $user->id, 'exception' => $exception]);

            return redirect()->route('users.index')->with('error', __('The verification notification could not be sent. Please try again later.'));
        }

        return redirect()->route('users.index')->with('success', __('User created successfully!'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->ensureUserAccess($user);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->ensureUserAccess($user);

        $roles = $this->assignableRoles();
        $companies = $this->assignableCompanies();
        $employees = $user->employee ? collect([$user->employee]) : Employee::all();

        return view('users.edit', compact('user', 'roles', 'companies', 'employees'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->ensureUserAccess($user);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|string|min:8',
            'employee_id' => 'nullable|exists:employees,id',
            'role' => 'required|array|min:1',
            'role.*' => 'required|string|exists:roles,name',
            'company' => 'required|array|min:1',
            'company.*' => 'required|integer|exists:companies,id',
        ]);

        $this->validateAssignments($request);

        $employee = null;
        if ($request->filled('employee_id')) {
            if ($user->employee()->exists() && $user->employee->id !== (int) $request->employee_id) {
                throw ValidationException::withMessages([__('The user is already linked to an employee.')]);
            }

            $employee = Employee::whereKey($request->employee_id)->whereNull('user_id')->first();
            if (! $employee) {
                throw ValidationException::withMessages([__('The selected employee is already assigned to another user.')]);
            }
        }

        DB::transaction(function () use ($request, $user, $employee) {
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $role = array_values($request->role);
            $company = array_values($request->company);

            if ($request->input('password')) {
                $user->password = bcrypt($request->input('password'));
            }

            $user->save();

            if ($employee) {
                $employee->update(['user_id' => $user->id]);
            }

            $user->syncRoles($role);
            $user->companies()->sync($company);
        });

        return redirect()->route('users.index')->with('success', __('User updated successfully!'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->ensureUserAccess($user);

        $user->delete();

        return redirect()->route('users.index')->with('success', __('User deleted successfully!'));
    }

    public function createEmployee(Request $request, User $user): RedirectResponse
    {
        $this->ensureUserAccess($user);

        $companyId = getActiveCompany();

        $existingEmployee = $user->employee()->first();
        if ($existingEmployee) {
            return redirect()->route('hr.employees.show', $existingEmployee);
        }

        $workSite = WorkSite::query()->orderBy('id')->first();
        $workShift = WorkShift::query()->orderBy('id')->first();

        if (! $workSite || ! $workShift) {
            return redirect()->route('users.index')
                ->with('error', __('Please create a work site and work shift first.'));
        }

        [$firstName, $lastName] = $this->splitName($user->name);

        $employee = Employee::create([
            'company_id' => $companyId,
            'code' => $this->uniqueEmployeeCode($user->id),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', __('Employee created successfully.'));
    }

    private function assignableRoles()
    {
        return Role::query()
            ->when(! auth()->user()->can('access-super-admin-panel'), fn ($query) => $query->where('name', '!=', 'Super-Admin'))
            ->get();
    }

    private function assignableCompanies()
    {
        $user = auth()->user();

        return ($user->can('access-super-admin-panel') ? Company::query() : $user->companies())->get();
    }

    private function validateAssignments(Request $request): void
    {
        $allowedRoles = $this->assignableRoles()->pluck('name');
        $allowedCompanies = $this->assignableCompanies()->pluck('id')->map(fn ($id) => (string) $id);

        $invalidRoles = collect($request->input('role', []))->diff($allowedRoles);
        $invalidCompanies = collect($request->input('company', []))->map(fn ($id) => (string) $id)->diff($allowedCompanies);

        if ($invalidRoles->isNotEmpty() || $invalidCompanies->isNotEmpty()) {
            throw ValidationException::withMessages([
                'company' => __('You may only assign roles and companies available to you.'),
            ]);
        }
    }

    private function ensureUserAccess(User $user): void
    {
        $actor = auth()->user();

        if ($actor->can('access-super-admin-panel')) {
            return;
        }

        $companyIds = $actor->companies()->pluck('companies.id');
        $hasActiveCompany = $user->companies()->where('companies.id', getActiveCompany())->exists();
        $hasInaccessibleCompany = $user->companies()->whereNotIn('companies.id', $companyIds)->exists();

        abort_unless($hasActiveCompany && ! $hasInaccessibleCompany, 403);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        $firstName = $parts[0] ?? '';
        $lastName = trim(implode(' ', array_slice($parts ?? [], 1)));

        if ($lastName === '') {
            $lastName = $firstName;
        }

        return [$firstName, $lastName];
    }

    private function uniqueEmployeeCode(int $userId): string
    {
        $base = 'EMP-'.$userId;
        $code = $base;
        $counter = 1;

        while (Employee::withoutGlobalScopes()->where('code', $code)->exists()) {
            $code = Str::limit($base.'-'.$counter, 20, '');
            $counter++;
        }

        return $code;
    }
}
