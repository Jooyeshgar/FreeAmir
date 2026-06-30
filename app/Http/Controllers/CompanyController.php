<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\FiscalYearService;
use Cookie;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'currency' => 'nullable|string|max:50',
        'moadian_username' => 'nullable|string|max:20',
        'tax_id' => 'nullable|string|max:20',
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
            'source_year_id' => 'nullable|exists:companies,id',
            'tables_to_copy' => 'array',
            'tables_to_copy.*' => 'string|in:'.implode(',', array_map(fn ($case) => $case->value, FiscalYearSection::cases())),
            'certificate' => $this->certificateRules(),
            'private_key' => $this->privateKeyRules(),
        ];

        $validated = $request->validate([...$this->rules, ...$fiscalYearRules]);

        if ($logo = $request->file('logo')) {
            $logo = $this->storeLogo($logo);
            $validated['logo'] = $logo;
        }

        if ($certFile = $request->file('certificate')) {
            $validated['certificate_path'] = $this->storeCertFile($certFile);
        }
        unset($validated['certificate']);

        if ($keyFile = $request->file('private_key')) {
            $validated['private_key_path'] = $this->storeCertFile($keyFile);
        }
        unset($validated['private_key']);

        $data = $validated;
        unset($data['source_year_id']);
        unset($data['tables_to_copy']);

        $data['currency'] ??= 'Rial'; // default

        if (! empty($validated['source_year_id'])) {
            FiscalYearService::createWithCopiedData(
                $data,
                $validated['source_year_id'],
                $validated['tables_to_copy'] ?? []
            );
        } else {
            FiscalYearService::importData([], $data);
        }

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
        $certRules = [
            'certificate' => $this->certificateRules(),
            'private_key' => $this->privateKeyRules(),
        ];

        $validated = $request->validate([...$this->rules, ...$certRules]);

        if ($logo = $request->file('logo')) {
            $logo = $this->storeLogo($logo, $company);
            $validated['logo'] = $logo;
        }

        if ($certFile = $request->file('certificate')) {
            $validated['certificate_path'] = $this->storeCertFile($certFile, $company->certificate_path);
        }
        unset($validated['certificate']);

        if ($keyFile = $request->file('private_key')) {
            $validated['private_key_path'] = $this->storeCertFile($keyFile, $company->private_key_path);
        }
        unset($validated['private_key']);

        $validated['currency'] ??= 'Rial'; // default

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
        try {
            DB::transaction(function () use ($company) {
                $documentIds = Document::withoutGlobalScopes()->where('company_id', $company->id)->pluck('id');

                $disk = Storage::disk('public');
                DocumentFile::withoutGlobalScopes()->whereIn('document_id', $documentIds)->pluck('path')->each(function (string $path) use ($disk) {
                    $normalized = Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;
                    if ($normalized && $disk->exists($normalized)) {
                        $disk->delete($normalized);
                    }
                });

                foreach ([$company->certificate_path, $company->private_key_path] as $keyPath) {
                    if ($keyPath && Storage::exists($keyPath)) {
                        Storage::delete($keyPath);
                    }
                }

                $company->delete();
            });

            return redirect(route('companies.index'))
                ->with('success', __('Company deleted successfully.'));
        } catch (\Throwable $e) {
            return redirect(route('companies.index'))
                ->with('error', __('An error occurred, try again.'));
        }
    }

    private function certificateRules(): array
    {
        return ['nullable', 'file', 'extensions:crt,cer', function ($_, $value, $fail) {
            $content = file_get_contents($value->getRealPath());

            // Try PEM as-is
            $certificate = @openssl_x509_read($content);

            if ($certificate === false) {
                // Try bare base64 (base64 content without PEM headers)
                $stripped = preg_replace('/\s+/', '', $content);
                $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split($stripped, 64, "\n")."-----END CERTIFICATE-----\n";
                $certificate = @openssl_x509_read($pem);
            }

            if ($certificate === false) {
                // Try DER (binary) format
                $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($content), 64, "\n")."-----END CERTIFICATE-----\n";
                $certificate = @openssl_x509_read($pem);
            }

            if ($certificate === false) {
                $fail(__('The certificate file must contain a valid X.509 certificate.'));
            }
        }];
    }

    private function privateKeyRules(): array
    {
        return ['nullable', 'file', 'extensions:pem', function ($_, $value, $fail) {
            if (! preg_match('/-----BEGIN\s+[\w\s]+-----/', file_get_contents($value->getRealPath()))) {
                $fail(__('The private key file must contain valid PEM-formatted content.'));
            }
        }];
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

    /**
     * Store a certificate or private key file under storage/app/keys.
     */
    private function storeCertFile(UploadedFile $file, ?string $oldPath = null): string
    {
        if ($oldPath && Storage::exists($oldPath)) {
            Storage::delete($oldPath);
        }

        $extension = $file->getClientOriginalExtension();
        $uniqueName = uniqid().'.'.$extension;
        $path = 'keys/'.$uniqueName;
        Storage::put($path, Crypt::encryptString(file_get_contents($file)));

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
    public function closingWizard(Company $company, Request $request): View
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
            FiscalYearService::closeTemporaryAccounts($company, $request->user());
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
