<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class CompanyController extends Controller
{
    public $rules = [
        'name'            => 'required|max:50|string|regex:/^[\w\d\s]*$/u',
        'logo'            => 'nullable|image|mimes:jpeg,jpg,png,svg|max:10240',
        'address'         => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        'economical_code' => 'nullable|string|max:15',
        'national_code'   => 'nullable|string|max:12',
        'postal_code'     => 'nullable|integer',
        'phone_number'    => 'nullable|numeric|regex:/^09\d{9}$/',
        'fiscal_year'     => 'required|numeric'
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $companies = Company::paginate(12);

        return view('companies.index', [
            'companies' => $companies
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('companies.create', [
            'company' => null
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules);

        if ($logo = $request->file('logo')) {
            $logo = $this->storeLogo($logo);
            $validated['logo'] = $logo;
        }

        $company = Company::create($validated);
        $company->users()->attach($request->user()->id);

        return redirect(route('companies.index'))
            ->with('success', 'Company created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company): View
    {
        return view('companies.create', [
            'company' => $company
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate($this->rules);

        if ($logo = $request->file('logo')) {
            $logo = $this->storeLogo($logo, $company);
            $validated['logo'] = $logo;
        }

        if ($company->update($validated)) {
            return redirect(route('companies.index'))
                ->with('success', 'Company updated successfully.');
        }

        return redirect(route('companies.index'))
            ->with('error', 'An error occurred, Try again.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company): RedirectResponse
    {
        if ($company->delete()) {
            return redirect(route('companies.index'))
                ->with('success', 'Removed successfully.');
        }

        return redirect(route('companies.index'))
            ->with('error', 'An error occurred, Try again.');
    }

    /**
     * Store logo of a company
     */
    public function storeLogo(UploadedFile $logo, Company $company = null): string
    {
        $extension = $logo->getClientOriginalExtension();
        $uniqueName = uniqid() . '.' . $extension;

        if ($company?->logo) {
            $oldPath = 'public/' . $company->logo;
            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        $storagePath = 'public/company_logos/' . $uniqueName;
        Storage::put($storagePath, file_get_contents($logo));
        $path = "company_logos/{$uniqueName}";
        return $path;
    }

    public function setActiveCompany(Company $company): RedirectResponse
    {
        if (! $company->users->contains(auth()->id())) {
            abort(403);
        }

        session([
            'active-company-id' => $company->id,
            'active-company-name' => $company->name,
            'active-company-fiscal-year' => $company->fiscal_year
        ]);

        return redirect()->route('home');
    }
}