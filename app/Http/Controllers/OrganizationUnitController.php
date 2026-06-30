<?php

namespace App\Http\Controllers;

use App\Filters\OrganizationUnitFilter;
use App\Models\OrganizationUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationUnitController extends Controller
{
    public function index(OrganizationUnitFilter $filter): View
    {
        $organizationUnits = OrganizationUnit::with('parent')
            ->withCount('employees')
            ->filter($filter)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization-units.index', compact('organizationUnits'));
    }

    public function create(): View
    {
        $parents = OrganizationUnit::orderBy('name')->get(['id', 'name']);

        return view('organization-units.create', compact('parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        OrganizationUnit::create(array_merge(
            $this->validatePayload($request),
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('hr.organization-units.index')
            ->with('success', __('Organization unit created successfully.'));
    }

    public function show(OrganizationUnit $organizationUnit): View
    {
        $organizationUnit->load([
            'parent',
            'children',
            'employees' => fn ($query) => $query->with('workSite')->orderBy('code'),
        ]);

        return view('organization-units.show', compact('organizationUnit'));
    }

    public function edit(OrganizationUnit $organizationUnit): View
    {
        $parents = OrganizationUnit::where('id', '!=', $organizationUnit->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('organization-units.edit', compact('organizationUnit', 'parents'));
    }

    public function update(Request $request, OrganizationUnit $organizationUnit): RedirectResponse
    {
        $organizationUnit->update($this->validatePayload($request, $organizationUnit));

        return redirect()->route('hr.organization-units.index')
            ->with('success', __('Organization unit updated successfully.'));
    }

    public function destroy(OrganizationUnit $organizationUnit): RedirectResponse
    {
        $organizationUnit->delete();

        return redirect()->route('hr.organization-units.index')
            ->with('success', __('Organization unit deleted successfully.'));
    }

    private function validatePayload(Request $request, ?OrganizationUnit $organizationUnit = null): array
    {
        $companyId = getActiveCompany();
        $codeRule = Rule::unique('organization_units', 'code')
            ->where('company_id', $companyId);

        if ($organizationUnit !== null) {
            $codeRule->ignore($organizationUnit->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                $codeRule,
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where('company_id', $companyId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');

        return $validated;
    }
}
