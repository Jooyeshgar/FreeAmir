<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\CompanyOverviewService;
use App\Services\DocumentImportExport\DocumentImportExportService;
use App\Services\SubjectService;
use App\Services\TrialBalanceService;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly SubjectService $subjectService,
        private readonly DocumentImportExportService $documentImportExportService,
        private readonly CompanyOverviewService $companyOverviewService
    ) {}

    public function companyOverview(Request $request)
    {
        $user = auth()->user();

        if ($user->can('access-super-admin-panel')) {
            $hasCurrentWorkspace = $user->companies()->whereKey(getActiveCompany())->where('fiscal_year', toEnglish(jdate('Y')))->exists();

            if (! $hasCurrentWorkspace) {
                return redirect()->route('management.dashboard');
            }
        }

        $request->session()->put('interface_mode', 'workspace');

        // Use can() (not Spatie's hasAnyPermission) so AppServiceProvider's
        // platform-access capability hook is honored.

        $BUSINESS_PERMISSIONS = [
            'documents.show',
            'products.index',
            'services.index',
            'invoices.index',
            'customers.index',
            'bank-accounts.index',
            'reports.ledger',
        ];

        $hasBusinessPerms = collect($BUSINESS_PERMISSIONS)->contains(fn ($perm) => $user->can($perm));
        $canSeePersonalPortal = $user->can('employee-portal.dashboard') && ! $hasBusinessPerms;

        $canFinancial = $user->can('documents.show');
        $canSales = $user->can('invoices.index') || $user->can('products.index');
        $canInventory = $user->can('products.index');
        $canPopularItems = $user->can('products.index') || $user->can('services.index');

        if (! $hasBusinessPerms && ! $canSeePersonalPortal) {
            abort(403);
        }

        $cashTypes = ['both', 'bank', 'cash_book'];

        $data = [
            'cashTypes' => $cashTypes,
            'hasBusinessPerms' => $hasBusinessPerms,
            'canSeePersonalPortal' => $canSeePersonalPortal,
            'canFinancial' => $canFinancial,
            'canSales' => $canSales,
            'canInventory' => $canInventory,
            'canPopularItems' => $canPopularItems,
            'hasDocument' => Document::exists(),
            'isDebugMode' => config('app.debug') && config('app.env') !== 'production',
        ];

        if ($canFinancial) {
            [$bankAccounts, $topTenBankAccountBalances] = $this->companyOverviewService->topTenBanksAccountBalances();

            ['incomeData' => $totalIncomesData, 'costData' => $totalCostsData, 'profit' => $profit] =
                $this->companyOverviewService->profitFromNonPermanentSubjects();

            $data += [
                'bankAccounts' => $bankAccounts,
                'topTenBankAccountBalances' => $topTenBankAccountBalances,
                'monthlyIncome' => $this->companyOverviewService->getMonthlyIncome(),
                'monthlyCost' => $this->companyOverviewService->getMonthlyCost(),
                'totalIncomesData' => $totalIncomesData,
                'totalCostsData' => $totalCostsData,
                'profit' => $profit,
            ];
        }

        if ($canSales) {
            $data['monthlySellAmount'] = $this->companyOverviewService->getMonthlyProductsStat();
            $data['sellAmountPerProducts'] = $this->companyOverviewService->getSellAmountPerProducts();
            $data['totalBuyAmount'] = $this->companyOverviewService->totalBuyAmount();
        }

        if ($canInventory) {
            $data['monthlyWarehouse'] = $this->companyOverviewService->getMonthlyWarehouse();
            $data['totalWarehouseValue'] = $this->companyOverviewService->totalWarehouseValue();
        }

        if ($canPopularItems) {
            $data['popularProductsAndServices'] = $this->companyOverviewService->popularProductsAndServices();
        }

        if ($canSeePersonalPortal) {
            $personal = $this->companyOverviewService->employeePersonalData($user);

            if ($personal) {
                $data += $personal;
                $data['hasPersonalData'] = true;
            } else {
                $data['hasPersonalData'] = false;
            }
        }

        return view('reports.company-overview', $data);
    }

    public function seedDemoData()
    {
        abort_if(! config('app.debug') || config('app.env') === 'production', 404);

        $companyId = (int) getActiveCompany();
        $user = auth()->user();

        abort_unless($user->can('access-super-admin-panel') || $user->companies()->whereKey($companyId)->exists(), 403);

        if (! Company::withoutGlobalScopes()->whereKey($companyId)->exists()) {
            return redirect()->route('home')->with('error', __('Please select a valid company first.'));
        }

        // Seeders use this value when no HTTP cookie is available (for example when they are invoked through Artisan from this request).
        config(['active-company-id' => $companyId]);

        if (Document::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return redirect()->route('home')->with('error', __('Cannot add demo data to a non-empty database.'));
        }

        try {
            app(DemoSeeder::class)->run($companyId);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('An error occurred while seeding demo data.'));
        }

        return redirect()->route('home')->with('success', __('Demo data has been added to the database.'));
    }

    public function refreshDatabase()
    {
        abort_if(! config('app.debug') || config('app.env') === 'production', 404);

        try {
            Artisan::call('migrate:fresh', ['--seed' => true]);
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('An error occurred while refreshing the database.'));
        }

        return redirect()->route('home')->with('success', __('Refresh database completed successfully.'));
    }

    public function cashAndBanksBalances(Request $request)
    {
        $data = $request->validate(
            [
                'duration' => 'required|integer|in:1,2,3,4',
                'type' => 'required|in:cash_book,bank,both',
            ]
        );

        return $this->companyOverviewService->cashAndBanksBalances($data['type'], intval($data['duration']));
    }

    public function bankAccount(Request $request)
    {
        $data = $request->validate(
            [
                'subject_id' => 'required|integer|exists:subjects,id',
                'duration' => 'required|integer|in:1,2,3,4',
            ]
        );

        return $this->companyOverviewService->balanceForSubjectIds([$data['subject_id']], intval($data['duration']));
    }

    public function ledger()
    {
        $subjects = Subject::orderBy('code', 'asc')->whereIsRoot()->get();

        return view('reports.ledger', compact('subjects'));
    }

    public function journal()
    {
        $subjects = [];

        return view('reports.journal', compact('subjects'));
    }

    public function subLedger()
    {
        $subjects = Subject::orderBy('code')->get(['id', 'name', 'code', 'parent_id']);
        $subjects = $this->subjectService->buildSubjectTreeFromCollection($subjects);

        return view('reports.subLedger', compact('subjects'));
    }

    public function documents()
    {
        return view('reports.documents');
    }

    public function trialBalance(Request $request, TrialBalanceService $trialBalanceService)
    {
        return view('reports.trialBalance', $trialBalanceService->getTrialBalanceData($request));
    }

    public function exportTrialBalanceCsv(Request $request, TrialBalanceService $trialBalanceService): StreamedResponse
    {
        return $trialBalanceService->exportCsv($request);
    }

    public function printTrialBalance(Request $request, TrialBalanceService $trialBalanceService)
    {
        return view('reports.trialBalancePrint', $trialBalanceService->getTrialBalanceData($request));
    }

    public function result(Request $request)
    {
        $request->merge([
            'start_date' => $this->normalizeReportDateInput($request->input('start_date')),
            'end_date' => $this->normalizeReportDateInput($request->input('end_date')),
        ]);

        if ($request->input('action') === 'preview') {
            return redirect()->route('transactions.index', $request->except(['action', 'report_for']));
        }

        $dateRule = function (string $attribute, mixed $value, $fail): void {
            try {
                jalaliInputToGregorian((string) $value, $attribute);
            } catch (ValidationException) {
                $fail(__('validation.date_format', [
                    'attribute' => str_replace('_', ' ', $attribute),
                    'format' => 'Y/m/d',
                ]));
            }
        };

        $rules = [
            'report_for' => 'required|in:Journal,Ledger,subLedger,Document',
            'start_document_number' => 'nullable|numeric',
            'end_document_number' => 'nullable|numeric',
            'start_date' => ['bail', 'nullable', 'string', $dateRule],
            'end_date' => ['bail', 'nullable', 'string', $dateRule],
        ];

        if ($request->report_for != 'Journal' && $request->report_for != 'Document') {
            $rules['subject_id'] = 'required';
        }

        $validated = Validator::make($request->all(), $rules)->after(function ($validator) use ($request) {
            // Optional consistency checks
            if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
                if ((int) $request->start_document_number > (int) $request->end_document_number) {
                    $validator->errors()->add('start_document_number', __('Start document number cannot be greater than end document number.'));
                }
            }
        })->validate();

        $startDate = isset($validated['start_date']) ? jalaliInputToGregorian($validated['start_date'], 'start_date') : null;
        $endDate = isset($validated['end_date']) ? jalaliInputToGregorian($validated['end_date'], 'end_date') : null;

        if ($startDate && $endDate && Carbon::parse($startDate)->isAfter(Carbon::parse($endDate))) {
            throw ValidationException::withMessages([
                'start_date' => __('Start date cannot be greater than end date.'),
            ]);
        }

        if ($request->report_for == 'Journal' && $request->input('action') === 'export_csv') {
            return $this->documentImportExportService->export([
                'start_document_number' => $request->input('start_document_number'),
                'end_document_number' => $request->input('end_document_number'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'text' => $request->input('search'),
                'columns_selected' => $request->input('columns_selected'),
                'columns' => $request->input('columns', []),
            ]);
        }

        if ($request->report_for == 'Document') {
            if ($request->input('action') === 'export_csv') {
                return $this->documentImportExportService->export([
                    'start_document_number' => $request->input('start_document_number'),
                    'end_document_number' => $request->input('end_document_number'),
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'text' => $request->input('search'),
                ]);
            }

            $documents = Document::query();
            // Document number filters
            if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
                $documents->whereBetween('number', [$request->start_document_number, $request->end_document_number]);
            } elseif ($request->filled('start_document_number')) {
                $documents->where('number', '>=', $request->start_document_number);
            } elseif ($request->filled('end_document_number')) {
                $documents->where('number', '<=', $request->end_document_number);
            }

            // Date filters (convert Jalali -> Gregorian)
            if ($startDate && $endDate) {
                $documents->whereBetween('date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $documents->where('date', '>=', $startDate);
            } elseif ($endDate) {
                $documents->where('date', '<=', $endDate);
            }

            if ($request->search) {
                $documents->where('title', 'like', '%'.$request->search.'%');
            }

            $documents->orderBy('date', 'asc')->orderBy('number', 'asc');

            $documents = $documents->with(['transactions.subject', 'creator', 'approver'])->get();

            return view('reports.documentReport', compact('documents'));
        }

        $transactions = Transaction::query();
        $subject = null;
        if ($request->subject_id) {
            if ($request->subject_id) {
                $subject = Subject::findOrFail($request->subject_id);

                if ($request->report_for == 'subLedger' || $request->report_for == 'Ledger') {
                    $subjectIds = $subject->getAllDescendantIds();
                    $transactions = $transactions->whereIn('subject_id', $subjectIds);
                }
            }
        }

        if ($request->search) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%');
            });
        }

        // Dynamic combined filters for transaction's related document
        if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->whereBetween('number', [$request->start_document_number, $request->end_document_number]);
            });
        } elseif ($request->filled('start_document_number')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('number', '>=', $request->start_document_number);
            });
        } elseif ($request->filled('end_document_number')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('number', '<=', $request->end_document_number);
            });
        }

        if ($startDate && $endDate) {
            $transactions = $transactions->whereHas('document', function ($q) use ($startDate, $endDate) {
                $q->where('date', '>=', $startDate)->where('date', '<=', $endDate);
            });
        } elseif ($startDate) {
            $transactions = $transactions->whereHas('document', function ($q) use ($startDate) {
                $q->where('date', '>=', $startDate);
            });
        } elseif ($endDate) {
            $transactions = $transactions->whereHas('document', function ($q) use ($endDate) {
                $q->where('date', '<=', $endDate);
            });
        }

        $transactions = $transactions->with('document', 'subject')
            ->orderBy(
                Document::whereColumn('id', 'transactions.document_id')->select('date')
            )
            ->orderBy(
                Document::whereColumn('id', 'transactions.document_id')->select('number')
            )
            ->get();

        if ($request->input('action') === 'export_csv') {
            $filename = $request->report_for.'_report_'.date('YmdHis').'.csv';

            return $this->streamCsvResponse($transactions, $filename);
        }

        $transactionsChunk = $transactions->chunk(env('REPORT_ROW_SIZE', 25));

        if ($request->report_for == 'Journal') {
            return view('reports.journalReport', compact('transactionsChunk', 'subject'));
        }

        return view('reports.ledgerReport', compact('transactionsChunk', 'subject'));
    }

    /**
     * Generates and streams a CSV response for a collection of transactions.
     */
    private function streamCsvResponse(Collection $transactions, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                __('Date'),
                __('Document #'),
                __('Subject Code'),
                __('Subject Name'),
                __('Description'),
                __('Debit'),
                __('Credit'),
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    formatDate($transaction->document->date),
                    formatDocumentNumber($transaction->document->number),
                    formatCode($transaction->subject->code),
                    $transaction->subject->name,
                    $transaction->desc ?? '',
                    $transaction->debit ?? 0,
                    $transaction->credit ?? 0,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function normalizeReportDateInput(mixed $date): mixed
    {
        if (! is_string($date)) {
            return $date;
        }

        $normalized = toEnglish(str_replace('-', '/', trim($date)));

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $matches)) {
            return sprintf('%04d/%02d/%02d', $matches[1], $matches[2], $matches[3]);
        }

        return $normalized;
    }
}
