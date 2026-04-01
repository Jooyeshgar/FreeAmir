<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Services\FiscalYearService;
use Cookie;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public $rules = [
        'name' => 'required|max:50|string|regex:/^[\w\d\s]*$/u',
        'logo' => 'nullable|image|mimes:jpeg,jpg,png,svg|max:10240',
        'address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        'economical_code' => 'nullable|string|max:15',
        'national_code' => 'nullable|string|max:12',
        'postal_code' => 'nullable|integer',
        'phone_number' => 'nullable|numeric|regex:/^09\d{9}$/',
        'fiscal_year' => 'required|numeric',
        'currency' => 'required|string|max:50',
    ];

    public function __construct() {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $companies = Company::paginate(12);

        return view('companies.index', [
            'companies' => $companies,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Get previous fiscal years for the current company
        $previousYears = Company::all();

        return view('companies.create', [
            'company' => null,
            'previousYears' => $previousYears,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $fiscalYearRules = [
            'source_year_id' => 'required|exists:companies,id',
            'tables_to_copy' => 'array',
            'tables_to_copy.*' => 'string|in:'.implode(',', array_map(fn ($case) => $case->value, FiscalYearSection::cases())),
        ];

        $validated = $request->validate(array_merge($this->rules, $fiscalYearRules));

        if ($logo = $request->file('logo')) {
            $logo = $this->storeLogo($logo);
            $validated['logo'] = $logo;
        }
        $data = $validated;
        unset($data['source_year_id']);
        unset($data['tables_to_copy']);

        $company = FiscalYearService::createWithCopiedData(
            $data,
            $validated['source_year_id'],
            $validated['tables_to_copy'] ?? []
        );
        $company->users()->attach($request->user()->id);

        return redirect(route('companies.index'))
            ->with('success', __('Company created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company): View
    {
        return view('companies.edit', [
            'company' => $company,
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
                ->with('success', __('Company updated successfully.'));
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
                ->with('success', __('Company deleted successfully.'));
        }

        return redirect(route('companies.index'))
            ->with('error', 'An error occurred, Try again.');
    }

    /**
     * Store logo of a company
     */
    public function storeLogo(UploadedFile $logo, ?Company $company = null): string
    {
        $extension = $logo->getClientOriginalExtension();
        $uniqueName = uniqid().'.'.$extension;

        if ($company?->logo) {
            $oldPath = 'public/'.$company->logo;
            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        $storagePath = 'public/company_logos/'.$uniqueName;
        Storage::put($storagePath, file_get_contents($logo));
        $path = "company_logos/{$uniqueName}";

        return $path;
    }

    public function setActiveCompany(Company $company): RedirectResponse
    {
        if (! $company->users->contains(auth()->id())) {
            abort(403);
        }

        Cookie::queue('active-company-id', $company->id, 365 * 24 * 60);

        config([
            'active-company-name' => $company->name,
            'active-company-fiscal-year' => $company->fiscal_year,
        ]);

        return redirect()->route('home');
    }

    public function closeFiscalYear(Company $company, Request $request): RedirectResponse
    {
        if (! $company->users->contains($request->user()->id)) {
            abort(403);
        }

        [$newFiscalYear, $validationErrors] = FiscalYearService::closeFiscalYear($company, $request->user());

        if (! $newFiscalYear && ! empty($validationErrors)) {
            return redirect()->back()->withErrors(implode(' ', $validationErrors));
        }

        $this->setActiveCompany($newFiscalYear);

        return redirect()->route('companies.index')->with('success', __('Fiscal year closed successfully.'));
    }

    /**
     * Show the multi-step year-end closing wizard.
     */
    public function closingWizard(Company $company, Request $request): \Illuminate\Contracts\View\View
    {
        if (! $company->users->contains($request->user()->id)) {
            abort(403);
        }

        $validations = FiscalYearService::getWizardValidations($company);
        $allPass = collect($validations)->every(fn ($v) => $v['pass']);

        $plDocument = $company->pl_document_id ? $company->plDocument : null;
        $incomeSummaryBalance = $plDocument ? FiscalYearService::getIncomeSummaryBalance($company) : null;
        $step3Enabled = $plDocument && $incomeSummaryBalance === 0.0;

        return view('companies.closing-wizard', compact(
            'company',
            'validations',
            'allPass',
            'plDocument',
            'incomeSummaryBalance',
            'step3Enabled'
        ));
    }

    /**
     * Execute Step 1: close temporary accounts (generate Income Summary document).
     */
    public function closingWizardStep1(Company $company, Request $request): RedirectResponse
    {
        if (! $company->users->contains($request->user()->id)) {
            abort(403);
        }

        if ($company->closed_at) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', __('This fiscal year is already closed.'));
        }

        if ($company->pl_document_id) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', __('Step 1 has already been completed.'));
        }

        try {
            FiscalYearService::stepOneCloseTemporaryAccounts($company, $request->user());
        } catch (\Exception $e) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('companies.closing-wizard', $company)
            ->with('success', __('Temporary accounts closed successfully. Please review and create a manual adjustment document if needed.'));
    }

    /**
     * Execute Step 3: close permanent accounts and open the new fiscal year.
     */
    public function closingWizardStep3(Company $company, Request $request): RedirectResponse
    {
        if (! $company->users->contains($request->user()->id)) {
            abort(403);
        }

        if ($company->closed_at) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', __('This fiscal year is already closed.'));
        }

        if (! $company->pl_document_id) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', __('You must complete Step 1 before closing permanent accounts.'));
        }

        $balance = FiscalYearService::getIncomeSummaryBalance($company);
        if ($balance !== 0.0) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', __('The Income Summary account balance must be zero before closing. Current balance: :balance', ['balance' => formatNumber($balance)]));
        }

        try {
            $newFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($company, $request->user());
        } catch (\Exception $e) {
            return redirect()->route('companies.closing-wizard', $company)
                ->with('error', $e->getMessage());
        }

        $this->setActiveCompany($newFiscalYear);

        return redirect()->route('companies.index')
            ->with('success', __('Fiscal year closed successfully.'));
    }
}
