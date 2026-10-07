<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\FiscalYear;
use App\Models\Warehouse;
use App\Services\FiscalYearService;
use Database\Seeders\BankSeeder;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\ServiceGroupSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FiscalYearController extends Controller
{
    public function index(Request $request): View
    {
        $years = $this->accessibleYears($request)
            ->with('company:id,name')
            ->withCount(['users', 'documents'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->whereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"));
            })
            ->when($request->input('status') === 'open', fn ($query) => $query->whereNull('closed_at'))
            ->when($request->input('status') === 'closed', fn ($query) => $query->whereNotNull('closed_at'))
            ->orderByDesc('fiscal_years.year')
            ->orderByDesc('fiscal_years.id')
            ->paginate(12)
            ->withQueryString();

        return view('fiscal-years.index', ['years' => $years]);
    }

    public function create(Request $request): View
    {
        $companies = $this->accessibleCompanies($request)->orderBy('name')->get();
        $sources = $this->accessibleYears($request)->with('company:id,name')->orderByDesc('year')->get();

        return view('fiscal-years.create', compact('companies', 'sources'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'year' => ['required', 'integer', 'digits:4', Rule::unique('fiscal_years')->where('company_id', $request->input('company_id'))],
            'source_year_id' => ['nullable', 'integer', Rule::exists('fiscal_years', 'id')->where('company_id', $request->input('company_id'))],
            'tables_to_copy' => ['array'],
            'tables_to_copy.*' => ['string', Rule::in(array_column(FiscalYearSection::cases(), 'value'))],
        ]);

        $company = $this->accessibleCompanies($request)->findOrFail($data['company_id']);
        if (isset($data['source_year_id'])) {
            $source = $this->accessibleYears($request)->findOrFail($data['source_year_id']);
            abort_unless($source->company_id === $company->id, 403);
        }

        $year = DB::transaction(function () use ($request, $data, $company) {
            $attributes = ['company_id' => $company->id, 'year' => $data['year']];
            $year = isset($data['source_year_id'])
                ? FiscalYearService::createWithCopiedData($attributes, (int) $data['source_year_id'], $data['tables_to_copy'] ?? array_column(FiscalYearSection::cases(), 'value'))
                : FiscalYear::create($attributes);

            $year->users()->syncWithoutDetaching([$request->user()->id]);

            if (! isset($data['source_year_id'])) {
                foreach ([SubjectSeeder::class, ConfigSeeder::class, BankSeeder::class,
                    CustomerGroupSeeder::class, ProductGroupSeeder::class, ServiceGroupSeeder::class] as $seeder) {
                    app($seeder)->run($year->id);
                }
                Warehouse::create(['fiscal_year_id' => $year->id, 'name' => 'انبار اصلی', 'code' => 'MAIN']);
            }

            return $year;
        });

        return redirect()->route('fiscal-years.show', $year)->with('success', __('Fiscal year created successfully.'));
    }

    public function show(Request $request, FiscalYear $fiscalYear): View
    {
        $this->authorizeYear($request, $fiscalYear);

        return view('fiscal-years.show', ['fiscalYear' => $fiscalYear->load('company:id,name', 'closedBy:id,name')->loadCount(['users', 'documents'])]);
    }

    public function edit(Request $request, FiscalYear $fiscalYear): View
    {
        $this->authorizeYear($request, $fiscalYear);

        return view('fiscal-years.edit', ['fiscalYear' => $fiscalYear->load('company:id,name')]);
    }

    public function update(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorizeYear($request, $fiscalYear);
        $data = $request->validate([
            'year' => ['required', 'integer', 'digits:4', Rule::unique('fiscal_years')->where('company_id', $fiscalYear->company_id)->ignore($fiscalYear->id)],
        ]);

        $fiscalYear->update($data);

        return redirect()->route('fiscal-years.show', $fiscalYear)->with('success', __('Fiscal year updated successfully.'));
    }

    public function destroy(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorizeYear($request, $fiscalYear);
        $paths = DocumentFile::withoutGlobalScopes()
            ->whereIn('document_id', Document::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYear->id)->select('id'))
            ->pluck('path')
            ->map(fn (string $path) => Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path);

        DB::transaction(fn () => $fiscalYear->delete());

        try {
            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('Fiscal year document file cleanup failed after deletion.', [
                'fiscal_year_id' => $fiscalYear->id,
                'exception' => $e,
            ]);
        }

        return redirect()->route('fiscal-years.index')->with('success', __('Fiscal year deleted successfully.'));
    }

    private function accessibleYears(Request $request)
    {
        return $request->user()->can('access-super-admin-panel') ? FiscalYear::query() : $request->user()->fiscalYears();
    }

    private function accessibleCompanies(Request $request)
    {
        return $request->user()->can('access-super-admin-panel')
            ? Company::query()
            : Company::whereHas('fiscalYears.users', fn ($query) => $query->whereKey($request->user()->id));
    }

    private function authorizeYear(Request $request, FiscalYear $fiscalYear): void
    {
        abort_unless($request->user()->can('access-super-admin-panel') || $request->user()->fiscalYears()->whereKey($fiscalYear->id)->exists(), 403);
    }
}
