<?php

namespace App\Http\Controllers;

use App\Enums\CommercialLedgerType;
use App\Models\CommercialLedgerExport;
use App\Models\Company;
use App\Services\CommercialLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommercialLedgerController extends Controller
{
    public function __construct(private readonly CommercialLedgerService $service) {}

    public function index(): View
    {
        $company = Company::query()->findOrFail(getActiveCompany());
        [$fiscalStart, $fiscalEnd] = $company->fiscalYearRange();

        return view('commercial-ledgers.index', [
            'exports' => CommercialLedgerExport::query()->latest()->paginate(15),
            'ledgerTypes' => CommercialLedgerType::cases(),
            'defaultFromDate' => gregorian_to_jalali_date($fiscalStart->toDateString(), '/', '-'),
            'defaultToDate' => gregorian_to_jalali_date($fiscalEnd->toDateString(), '/', '-'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'from_date' => str_replace('-', '/', toEnglish(trim((string) $request->input('from_date')))),
            'to_date' => str_replace('-', '/', toEnglish(trim((string) $request->input('to_date')))),
            'seal_tracking_code' => trim((string) $request->input('seal_tracking_code')),
        ]);

        $jalaliDate = function (string $attribute, mixed $value, $fail): void {
            $parts = array_map('intval', explode('/', (string) $value));
            if (count($parts) !== 3 || ! jcheckdate($parts[1] ?? 0, $parts[2] ?? 0, $parts[0] ?? 0)) {
                $fail(__('validation.date_format', ['attribute' => __($attribute), 'format' => 'Y/m/d']));
            }
        };

        $validator = Validator::make($request->all(), [
            'from_date' => ['bail', 'required', 'string', $jalaliDate],
            'to_date' => ['bail', 'required', 'string', $jalaliDate],
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
            'seal_tracking_code' => ['required', 'string', 'max:100'],
            'ledger_type' => ['required', Rule::enum(CommercialLedgerType::class)],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($validator->errors()->hasAny(['from_date', 'to_date'])) {
                return;
            }

            $fromDate = jalali_to_gregorian_date($request->input('from_date'), '-', '/');
            $toDate = jalali_to_gregorian_date($request->input('to_date'), '-', '/');

            if ($fromDate > $toDate) {
                $validator->errors()->add('from_date', __('From date cannot be greater than to date.'));
            }
        });

        $export = $this->service->generate($validator->validate(), $request->user()->id);

        return redirect()->route('commercial-ledgers.index')->with('success', __('Commercial ledger generated successfully. :count rows are ready.', ['count' => $export->row_count]));
    }

    public function show(Request $request, CommercialLedgerExport $commercialLedger): View
    {
        $allRows = $this->service->rows(
            $commercialLedger->from_date->toDateString(),
            $commercialLedger->to_date->toDateString(),
            $commercialLedger->ledger_type
        );
        $page = max(1, $request->integer('page', 1));
        $perPage = 100;
        $rows = new LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('commercial-ledgers.show', compact('commercialLedger', 'rows'));
    }

    public function download(CommercialLedgerExport $commercialLedger): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($commercialLedger->file_path), 404);

        return Storage::disk('local')->download(
            $commercialLedger->file_path,
            $this->service->filename($commercialLedger),
            ['Content-Type' => $commercialLedger->format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8']
        );
    }

    public function destroy(CommercialLedgerExport $commercialLedger): RedirectResponse
    {
        $this->service->delete($commercialLedger);

        return redirect()->route('commercial-ledgers.index')->with('success', __('Commercial ledger deleted successfully.'));
    }
}
