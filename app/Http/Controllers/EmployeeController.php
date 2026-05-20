<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeDutyStatus;
use App\Enums\EmployeeEducationLevel;
use App\Enums\EmployeeEmploymentType;
use App\Enums\EmployeeGender;
use App\Enums\EmployeeInsuranceType;
use App\Enums\EmployeeMaritalStatus;
use App\Enums\EmployeeNationality;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\OrganizationUnit;
use App\Models\OrgChart;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = $this->filteredEmployeeQuery($request)
            ->paginate(15)
            ->withQueryString();
        $workSites = WorkSite::orderBy('name')->get(['id', 'name']);
        $workSiteContracts = WorkSiteContract::orderBy('name')->get(['id', 'name']);

        $stats = Employee::selectRaw(
            'COUNT(*) as total_count,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN employment_type = ? THEN 1 ELSE 0 END) as full_time_count,
            SUM(CASE WHEN employment_type IN (?, ?) THEN 1 ELSE 0 END) as flexible_count,
            SUM(CASE WHEN contract_start_date >= ? THEN 1 ELSE 0 END) as new_hires_count',
            [
                EmployeeEmploymentType::PERMANENT->value,
                EmployeeEmploymentType::CONTRACT->value,
                EmployeeEmploymentType::OTHER->value,
                now()->subDays(30)->toDateString(),
            ]
        )->first();

        $totalCount = (int) $stats->total_count;
        $activeCount = (int) $stats->active_count;
        $fullTimeCount = (int) $stats->full_time_count;
        $flexibleCount = (int) $stats->flexible_count;
        $newHiresCount = (int) $stats->new_hires_count;
        $withoutSalaryDecreeCount = Employee::doesntHave('salaryDecrees')->count();

        return view('employees.index', compact(
            'employees',
            'totalCount',
            'activeCount',
            'fullTimeCount',
            'flexibleCount',
            'newHiresCount',
            'withoutSalaryDecreeCount',
            'workSites',
            'workSiteContracts'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'employees_'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($request) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, [
                __('Code'),
                __('First Name'),
                __('Last Name'),
                __('National Code'),
                __('Phone'),
                __('Work Site'),
                __('Position'),
                __('Contract'),
                __('Employment Type'),
                __('Status'),
                __('Contract Start Date'),
                __('Contract End Date'),
                __('Salary Decree Count'),
            ]);

            $this->filteredEmployeeQuery($request)
                ->chunk(200, function ($employees) use ($file) {
                    foreach ($employees as $employee) {
                        fputcsv($file, [
                            $employee->code,
                            $employee->first_name,
                            $employee->last_name,
                            $employee->national_code,
                            $employee->phone,
                            $employee->workSite?->name,
                            $employee->orgChart?->title,
                            $employee->workSiteContract?->name,
                            $employee->employment_type?->label(),
                            $employee->is_active ? __('Active') : __('Inactive'),
                            $employee->contract_start_date?->toDateString(),
                            $employee->contract_end_date?->toDateString(),
                            $employee->salary_decrees_count,
                        ]);
                    }
                });

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        $workSites = WorkSite::orderBy('name')->get(['id', 'name']);
        $orgCharts = OrgChart::orderBy('title')->get(['id', 'title']);
        $organizationUnits = OrganizationUnit::orderBy('name')->get(['id', 'name']);
        $workSiteContracts = WorkSiteContract::orderBy('name')->get(['id', 'name']);
        $workShifts = WorkShift::orderBy('name')->get(['id', 'name']);

        return view('employees.create', array_merge(
            compact('workSites', 'orgCharts', 'organizationUnits', 'workSiteContracts', 'workShifts'),
            self::enumOptions()
        ));
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create(array_merge(
            $request->validated(),
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('hr.employees.index')
            ->with('success', __('Employee created successfully.'));
    }

    public function show(Employee $employee): View
    {
        $employee->load(['workSite', 'orgChart', 'organizationUnit', 'workSiteContract', 'workShift']);

        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee): View
    {
        $workSites = WorkSite::orderBy('name')->get(['id', 'name']);
        $orgCharts = OrgChart::orderBy('title')->get(['id', 'title']);
        $organizationUnits = OrganizationUnit::orderBy('name')->get(['id', 'name']);
        $workSiteContracts = WorkSiteContract::orderBy('name')->get(['id', 'name']);
        $workShifts = WorkShift::orderBy('name')->get(['id', 'name']);

        return view('employees.edit', array_merge(
            compact('employee', 'workSites', 'orgCharts', 'organizationUnits', 'workSiteContracts', 'workShifts'),
            self::enumOptions()
        ));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();
        if ($employee->user && ($employee->first_name !== $validated['first_name'] || $employee->last_name !== $validated['last_name'])) {
            $employee->user->name = $validated['first_name'].' '.$validated['last_name'];
            $employee->user->save();
        }

        $employee->update($validated);

        return redirect()->route('hr.employees.index')
            ->with('success', __('Employee updated successfully.'));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('hr.employees.index')
            ->with('success', __('Employee deleted successfully.'));
    }

    private function filteredEmployeeQuery(Request $request): Builder
    {
        $query = Employee::with(['workSite', 'orgChart', 'workSiteContract', 'organizationUnit'])
            ->withCount([
                'salaryDecrees',
                'salaryDecrees as active_salary_decrees_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('code');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('national_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        if ($request->filled('work_site_id')) {
            $query->where('work_site_id', $request->integer('work_site_id'));
        }

        if ($request->filled('contract_framework_id')) {
            $query->where('contract_framework_id', $request->integer('contract_framework_id'));
        }

        return $query;
    }

    /** Build the shared enum options arrays used by views. */
    public static function enumOptions(): array
    {
        return [
            'nationalities' => EmployeeNationality::options(),
            'genders' => array_merge(['' => __('— Select —')], EmployeeGender::options()),
            'maritalStatuses' => array_merge(['' => __('— Select —')], EmployeeMaritalStatus::options()),
            'dutyStatuses' => array_merge(['' => __('— Select —')], EmployeeDutyStatus::options()),
            'insuranceTypes' => array_merge(['' => __('— Select —')], EmployeeInsuranceType::options()),
            'educationLevels' => array_merge(['' => __('— Select —')], EmployeeEducationLevel::options()),
            'employmentTypes' => array_merge(['' => __('— Select —')], EmployeeEmploymentType::options()),
        ];
    }
}
