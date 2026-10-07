<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FiscalYearService;
use Cookie;
use Database\Seeders\BankSeeder;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceGroupSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

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
    public function index(Request $request): View
    {
        $user = auth()->user();
        $fiscalYears = ($user->can('access-super-admin-panel') ? FiscalYear::query() : $user->fiscalYears())
            ->join('companies', 'companies.id', '=', 'fiscal_years.company_id')
            ->select('fiscal_years.*', 'companies.name', 'companies.address', 'companies.economical_code', 'companies.national_code', 'companies.currency', 'fiscal_years.year as fiscal_year')
            ->with('closedBy:id,name')->withCount('users')->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('companies.name', 'like', "%{$search}%")
                        ->orWhere('companies.economical_code', 'like', "%{$search}%")
                        ->orWhere('companies.national_code', 'like', "%{$search}%");
                });
            })
            ->when($request->input('status') === 'open', fn ($query) => $query->whereNull('fiscal_years.closed_at'))
            ->when($request->input('status') === 'closed', fn ($query) => $query->whereNotNull('fiscal_years.closed_at'))
            ->orderByDesc('fiscal_years.year')
            ->orderBy('companies.name')
            ->paginate(12)
            ->withQueryString();

        $view = $request->session()->get('interface_mode') === 'management'
            && $user->can('access-super-admin-panel')
                ? 'companies.index'
                : 'companies.workspace-index';

        return view($view, [
            'fiscalYears' => $fiscalYears,
            'canCreateFirstCompany' => $user->can('access-super-admin-panel') && $user->fiscalYears()->doesntExist(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        // Get previous fiscal years for the current company
        $previousYears = $request->user()->fiscalYears()
            ->join('companies', 'companies.id', '=', 'fiscal_years.company_id')
            ->select('fiscal_years.*', 'companies.name', 'fiscal_years.year as fiscal_year')
            ->orderByDesc('fiscal_years.year')->get();

        return view('companies.create', [
            'company' => null,
            'previousYears' => $previousYears,
        ]);
    }

    public function createCompanyForRegisteredUser(Request $request): View|RedirectResponse
    {
        abort_if($request->user()->can('access-super-admin-panel'), 404);

        if ($request->user()->fiscalYears()->exists()) {
            return redirect()->route('home');
        }

        return view('auth.create-company');
    }

    /**
     * Display the grouped management overview for a business.
     */
    public function show(Request $request, Company $company): View
    {
        abort_unless($request->user()->can('access-super-admin-panel'), 403);

        $request->session()->put('interface_mode', 'management');

        $fiscalYears = $company->fiscalYears()
            ->withCount('users')
            ->selectSub(
                Document::withoutGlobalScopes()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('documents.fiscal_year_id', 'fiscal_years.id'),
                'documents_count'
            )
            ->selectSub(
                Invoice::withoutGlobalScopes()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('invoices.fiscal_year_id', 'fiscal_years.id'),
                'invoices_count'
            )
            ->orderByDesc('year')
            ->get();

        $users = User::query()
            ->whereHas('fiscalYears', fn ($query) => $query->where('fiscal_years.company_id', $company->id))
            ->with('roles:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        foreach ($fiscalYears as $fiscalYear) {
            $fiscalYear->setAttribute('fiscal_year', $fiscalYear->year);
        }

        return view('companies.show', [
            'business' => $company,
            'fiscalYears' => $fiscalYears,
            'users' => $users,
            'metrics' => [
                'fiscalYears' => $fiscalYears->count(),
                'openFiscalYears' => $fiscalYears->whereNull('closed_at')->count(),
                'users' => $users->count(),
                'documents' => (int) $fiscalYears->sum('documents_count'),
                'invoices' => (int) $fiscalYears->sum('invoices_count'),
            ],
        ]);
    }

    public function storeCompanyForRegisteredUser(Request $request): RedirectResponse
    {
        abort_if($request->user()->can('access-super-admin-panel'), 404);

        if ($request->user()->fiscalYears()->exists()) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[\w\d\s]*$/u'],
            'fiscal_year' => ['required', 'integer', 'digits:4'],
            'currency' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['required', 'regex:/^09\d{9}$/'],
        ]);

        $data['currency'] ??= 'Rial';

        try {
            $fiscalYear = $this->createCompany($request->user(), $data);
            Cookie::queue('active-fiscal-year-id', $fiscalYear->id, 362 * 24 * 60);

            return redirect()->route('home')->with('success', __('Company created successfully.'));
        } catch (\Throwable $e) {
            Log::error('Registered user company initialization failed.', [
                'creator_id' => $request->user()->id,
                'company_name' => $data['name'] ?? null,
                'fiscal_year' => $data['fiscal_year'] ?? null,
                'exception' => $e,
            ]);

            return back()->withInput()->with('error', __('Company initialization failed. No data was saved. Please try again.'));
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $fiscalYearRules = [
            'source_year_id' => [
                'nullable',
                Rule::exists('fiscal_year_user', 'fiscal_year_id')->where('user_id', auth()->user()->id),
            ],
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

        try {
            $fiscalYear = $this->createCompany($request->user(), $data, isset($validated['source_year_id']) ? (int) $validated['source_year_id'] : null, $validated['tables_to_copy'] ?? []);
            Cookie::queue('active-fiscal-year-id', $fiscalYear->id, 362 * 24 * 60);
        } catch (\Throwable $e) {
            Log::error('Company initialization failed.', [
                'creator_id' => $request->user()->id,
                'company_name' => $data['name'] ?? null,
                'fiscal_year' => $data['fiscal_year'] ?? null,
                'source_fiscal_year_id' => $validated['source_year_id'] ?? null,
                'exception' => $e,
            ]);

            if (! empty($data['logo'])) {
                Storage::delete('public/'.$data['logo']);
            }

            foreach (['certificate_path', 'private_key_path'] as $key) {
                if (! empty($data[$key])) {
                    Storage::delete($data[$key]);
                }
            }

            return back()->withInput()->with('error', __('Company initialization failed. No data was saved. Please try again.'));
        }

        return redirect(route('companies.index'))
            ->with('success', __('Company created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company): View
    {
        $this->ensureCompanyAccess($company);

        return view('companies.edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->ensureCompanyAccess($company);

        $certRules = [
            'certificate' => $this->certificateRules(),
            'private_key' => $this->privateKeyRules(),
        ];

        $companyRules = $this->rules;
        unset($companyRules['fiscal_year']);
        $validated = $request->validate([...$companyRules, ...$certRules]);

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

        $company->update($validated);

        return redirect(route('companies.index'))
            ->with('success', __('Company updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FiscalYear $company): RedirectResponse
    {
        $this->ensureFiscalYearAccess($company);

        $documentFilePaths = DocumentFile::withoutGlobalScopes()
            ->whereIn('document_id', Document::withoutGlobalScopes()->where('fiscal_year_id', $company->id)->select('id'))
            ->pluck('path')
            ->map(fn (string $path): string => Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path)
            ->filter()
            ->values();
        $business = $company->company;
        $deleteBusiness = $business->fiscalYears()->count() === 1;
        $keyPaths = $deleteBusiness ? collect([$business->certificate_path, $business->private_key_path])->filter()->values() : collect();

        try {
            DB::transaction(function () use ($company, $business, $deleteBusiness) {
                $company->delete();
                if ($deleteBusiness) {
                    $business->delete();
                }
            });
        } catch (\Throwable $e) {
            Log::error('Company deletion failed.', [
                'fiscal_year_id' => $company->id,
                'exception' => $e,
            ]);

            $errors = $e instanceof ValidationException
                ? $e->errors()
                : ['company' => [__('Company deletion failed: :error', ['error' => $e->getMessage()])]];

            return redirect(route('companies.index'))
                ->withErrors($errors);
        }

        $this->deleteCompanyFiles($company->id, $documentFilePaths, $keyPaths);

        return redirect(route('companies.index'))
            ->with('success', __('Company deleted successfully.'));
    }

    private function deleteCompanyFiles(int $companyId, Collection $documentFilePaths, Collection $keyPaths): void
    {
        try {
            $disk = Storage::disk('public');

            foreach ($documentFilePaths as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Company document file cleanup failed after deletion.', [
                'fiscal_year_id' => $companyId,
                'exception' => $e,
            ]);
        }

        try {
            foreach ($keyPaths as $path) {
                if (Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Company key file cleanup failed after deletion.', [
                'fiscal_year_id' => $companyId,
                'exception' => $e,
            ]);
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

    private function ensureFiscalYearAccess(FiscalYear $fiscalYear): void
    {
        $user = auth()->user();

        abort_unless($user->can('access-super-admin-panel') || $user->fiscalYears()->whereKey($fiscalYear->id)->exists(), 403);
    }

    private function ensureCompanyAccess(Company $company): void
    {
        $user = auth()->user();

        abort_unless($user->can('access-super-admin-panel') || $user->fiscalYears()->where('company_id', $company->id)->exists(), 403);
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

    private function createCompany(User $creator, array $companyData, ?int $sourceFiscalYearId = null, array $sectionsToCopy = []): FiscalYear
    {
        return DB::transaction(function () use ($creator, $companyData, $sourceFiscalYearId, $sectionsToCopy) {
            $year = (int) $companyData['fiscal_year'];
            unset($companyData['fiscal_year']);
            $business = Company::create($companyData);
            $fiscalYearData = ['company_id' => $business->id, 'year' => $year];
            $fiscalYear = $sourceFiscalYearId === null
                ? FiscalYear::create($fiscalYearData)
                : FiscalYearService::createWithCopiedData($fiscalYearData, $sourceFiscalYearId, $sectionsToCopy);
            $fiscalYear->users()->syncWithoutDetaching([$creator->id]);

            if ($sourceFiscalYearId === null) {
                foreach ([
                    SubjectSeeder::class,
                    ConfigSeeder::class,
                    BankSeeder::class,
                    CustomerGroupSeeder::class,
                    ProductGroupSeeder::class,
                    ServiceGroupSeeder::class,
                ] as $seeder) {
                    app($seeder)->run($fiscalYear->id);
                }
                Warehouse::create(['fiscal_year_id' => $fiscalYear->id, 'name' => 'انبار اصلی', 'code' => 'MAIN']);
            }

            $adminRole = Role::where('name', __('Admin'))->first();

            $requiredAdminPermissions = ['home', 'documents.show'];

            if ($adminRole === null || $adminRole->permissions()->whereIn('name', $requiredAdminPermissions)->count() !== count($requiredAdminPermissions)) {
                app(RolesAndPermissionsSeeder::class)->seedPermissionsAndRoles();
                $adminRole = Role::where('name', __('Admin'))->firstOrFail();
            }

            $creator->unsetRelation('roles');
            $creator->assignRole($adminRole);

            // The authorization check before company creation caches this user's wildcard permissions.
            $creator->forgetWildcardPermissionIndex();

            return $fiscalYear;
        });
    }

    public function setActiveCompany(FiscalYear $company, Request $request): RedirectResponse
    {
        if (! $company->users->contains(auth()->id())) {
            abort(403);
        }

        Cookie::queue('active-fiscal-year-id', $company->id, 365 * 24 * 60);

        config([
            'active-company-name' => $company->company->name,
            'active-company-fiscal-year' => $company->year,
        ]);

        if ($request->filled('document')) {
            $document = Document::withoutGlobalScopes()
                ->where('fiscal_year_id', $company->id)
                ->findOrFail($request->integer('document'));

            return redirect()->route('documents.show', $document);
        }

        return redirect()->route('home');
    }

    public function closeFiscalYear(FiscalYear $fiscalYear, Request $request): RedirectResponse
    {
        if (! $fiscalYear->users->contains($request->user()->id)) {
            abort(403);
        }

        $this->validateActiveFiscalYearForClosing($fiscalYear);

        [$newFiscalYear, $validationErrors] = FiscalYearService::closeFiscalYear($fiscalYear, $request->user());

        if (! $newFiscalYear && ! empty($validationErrors)) {
            return redirect()->back()->withErrors(implode(' ', $validationErrors));
        }

        $this->setActiveCompany($newFiscalYear, $request);

        return redirect()->route('companies.index')->with('success', __('Fiscal year closed successfully.'));
    }

    /**
     * Show the multi-step year-end closing wizard.
     */
    public function closingWizard(FiscalYear $fiscalYear, Request $request): View
    {
        if (! $fiscalYear->users->contains($request->user()->id)) {
            abort(403);
        }

        $validations = FiscalYearService::getWizardValidations($fiscalYear);
        $allPass = collect($validations)->every(fn ($v) => $v['pass']);

        $plDocument = $fiscalYear->pl_document_id && $fiscalYear->closing_recalculation_step !== 1 ? $fiscalYear->plDocument : null;
        $incomeSummaryBalance = $plDocument ? FiscalYearService::getIncomeSummaryBalance($fiscalYear) : null;
        $step3Enabled = $plDocument && $incomeSummaryBalance === 0.0;
        $nextFiscalYear = FiscalYear::query()->where('company_id', $fiscalYear->company_id)
            ->where('year', $fiscalYear->year + 1)->first();
        $openingDocument = $nextFiscalYear
            ? Document::withoutGlobalScopes()->where('fiscal_year_id', $nextFiscalYear->id)->where('number', 1)->first()
            : null;
        if ($openingDocument && ! in_array($openingDocument->title, [__('Fiscal year opening Document', [], 'en'), __('Fiscal year opening Document', [], 'fa')], true)) {
            $openingDocument = null;
        }

        return view('companies.closing-wizard', compact(
            'fiscalYear',
            'validations',
            'allPass',
            'plDocument',
            'incomeSummaryBalance',
            'step3Enabled',
            'nextFiscalYear',
            'openingDocument'
        ));
    }

    /**
     * Execute Step 1: close temporary accounts (generate Income Summary document).
     */
    public function closingWizardStep1(FiscalYear $fiscalYear, Request $request): RedirectResponse
    {
        if (! $fiscalYear->users->contains($request->user()->id)) {
            abort(403);
        }

        $this->validateActiveFiscalYearForClosing($fiscalYear);

        if ($fiscalYear->closed_at) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', __('This fiscal year is already closed.'));
        }

        if ($fiscalYear->pl_document_id && $fiscalYear->closing_recalculation_step !== 1) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', __('Step 1 has already been completed.'));
        }

        try {
            FiscalYearService::closeTemporaryAccounts($fiscalYear, $request->user());
        } catch (\Exception $e) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('companies.closing-wizard', $fiscalYear)
            ->with('success', __('Temporary accounts closed successfully. Please review and create a manual adjustment document if needed.'));
    }

    /**
     * Execute Step 3: close permanent accounts and open the new fiscal year.
     */
    public function closingWizardStep3(FiscalYear $fiscalYear, Request $request): RedirectResponse
    {
        if (! $fiscalYear->users->contains($request->user()->id)) {
            abort(403);
        }

        $this->validateActiveFiscalYearForClosing($fiscalYear);

        if ($fiscalYear->closed_at) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', __('This fiscal year is already closed.'));
        }

        if (! $fiscalYear->pl_document_id || $fiscalYear->closing_recalculation_step === 1) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', __('You must complete Step 1 before closing permanent accounts.'));
        }

        $balance = FiscalYearService::getIncomeSummaryBalance($fiscalYear);
        if ($balance !== 0.0) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', __('The Income Summary account balance must be zero before closing. Current balance: :balance', ['balance' => formatNumber($balance)]));
        }

        try {
            $isRecalculation = $fiscalYear->closing_recalculation_step === 2;
            $newFiscalYear = FiscalYearService::stepThreeCloseAndOpenNewYear($fiscalYear, $request->user());
        } catch (\Exception $e) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', $e->getMessage());
        }

        if (! $isRecalculation) {
            $this->setActiveCompany($newFiscalYear, $request);
        }

        return redirect()->route('companies.index')
            ->with('success', __($isRecalculation ? 'Fiscal year closing document recalculated successfully.' : 'Fiscal year closed successfully.'));
    }

    public function recalculateClosingDocument(FiscalYear $fiscalYear, Request $request): RedirectResponse
    {
        if (! $fiscalYear->users->contains($request->user()->id)) {
            abort(403);
        }

        $this->validateActiveFiscalYearForClosing($fiscalYear);

        try {
            FiscalYearService::recalculateClosingDocument($fiscalYear, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('companies.closing-wizard', $fiscalYear)
            ->with('success', __('Closing recalculation started. Complete all three closing steps again.'));
    }

    public function recreateOpeningDocument(FiscalYear $fiscalYear, Request $request): RedirectResponse
    {
        if (! $fiscalYear->users->contains($request->user()->id)) {
            abort(403);
        }

        $this->validateActiveFiscalYearForClosing($fiscalYear);

        try {
            FiscalYearService::recreateOpeningDocument($fiscalYear, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->route('companies.closing-wizard', $fiscalYear)->with('error', $e->getMessage());
        }

        return redirect()->route('companies.closing-wizard', $fiscalYear)
            ->with('success', __('Opening Document recreated successfully.'));
    }

    private function validateActiveFiscalYearForClosing(FiscalYear $fiscalYear): void
    {
        if ((int) config('active-fiscal-year-id') !== $fiscalYear->id) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('Select this fiscal year as the active fiscal year before closing it.'),
            ]);
        }
    }
}
