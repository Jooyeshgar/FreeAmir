<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Transaction;
use App\Services\ServiceImportService;
use App\Services\ServiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceService $serviceService,
    ) {}

    public function index()
    {
        $query = Service::orderBy('code');

        if (request()->has('name') && request('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        if (request()->has('group_name') && request('group_name')) {
            $searchGroupName = request('group_name');
            $query->whereHas('serviceGroup', function ($groupName) use ($searchGroupName) {
                $groupName->where('name', 'like', '%'.$searchGroupName.'%');
            });
        }

        $services = $query->with('serviceGroup', 'cogsSubject', 'salesReturnsSubject')->paginate(12);

        return view('services.index', [
            'services' => $services,
            'csvColumns' => $this->exportColumnMapping(),
        ]);
    }

    public function create()
    {
        $groups = ServiceGroup::select('id', 'name')->limit(20)->get();

        return view('services.create', compact('groups'));
    }

    public function store(StoreServiceRequest $request)
    {
        $validatedData = $request->getValidatedData();

        $service = $this->serviceService->create($validatedData);

        return redirect()->route('services.index')->with('success', __('Service created successfully.'));
    }

    public function show(Service $service)
    {
        $service->load('serviceGroup', 'subject', 'cogsSubject', 'salesReturnsSubject');

        return view('services.show', compact('service'));
    }

    public function edit(Service $service)
    {
        $serviceGroupIdsForSelect = ServiceGroup::select('id', 'name')->limit(20)->pluck('id');
        $oldGroup = $service->serviceGroup;
        $groups = ServiceGroup::whereIn('id', $serviceGroupIdsForSelect->push($oldGroup->id)->unique())->get();

        return view('services.edit', compact('service', 'groups'));
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $validatedData = $request->getValidatedData();

        $this->serviceService->update($service, $validatedData);

        return redirect()->route('services.index')->with('success', __('Service updated successfully.'));
    }

    public function destroy(Service $service)
    {
        $this->serviceService->delete($service);

        return redirect()->route('services.index')->with('success', __('Service deleted successfully.'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columnMapping = $this->selectedExportColumns($request);
        $amountColumns = ['revenue_account', 'cogs_account', 'sales_return_account'];
        $includeSubjectAmounts = array_intersect($amountColumns, array_keys($columnMapping)) !== [];
        $filename = 'services_'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($columnMapping, $includeSubjectAmounts) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM so Excel reads translated headers and Persian text correctly.
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, array_values($columnMapping));

            Service::with('serviceGroup', 'subject', 'cogsSubject', 'salesReturnsSubject')
                ->orderBy('code')
                ->chunk(200, function ($services) use ($file, $columnMapping, $includeSubjectAmounts) {
                    $subjectTotals = $includeSubjectAmounts ? $this->serviceSubjectTotals($services) : [];

                    foreach ($services as $service) {
                        $row = [
                            'code' => $service->code,
                            'name' => $service->name,
                            'selling_price' => $service->selling_price,
                            'vat' => $service->vat,
                            'group_name' => $service->serviceGroup?->name,
                            'revenue_account' => abs($subjectTotals[$service->subject_id] ?? 0),
                            'cogs_account' => abs($subjectTotals[$service->cogs_subject_id] ?? 0),
                            'sales_return_account' => abs($subjectTotals[$service->sales_returns_subject_id] ?? 0),
                            'income_subject_code' => $service->subject?->code,
                            'cogs_subject_code' => $service->cogsSubject?->code,
                            'sales_returns_subject_code' => $service->salesReturnsSubject?->code,
                            'sstid' => $service->sstid,
                            'description' => $service->description,
                        ];

                        fputcsv($file, array_map(
                            fn (string $column) => $row[$column] ?? null,
                            array_keys($columnMapping),
                        ));
                    }
                });

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importForm(): View
    {
        return view('services.import');
    }

    public function import(Request $request, ServiceImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $importService->import($request->file('file'), getActiveCompany());

        return redirect()->route('services.index')->with('success', __('Import complete: :imported services imported, :updated updated, :groups groups created.', [
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'groups' => $result['groups_created'],
        ]));
    }

    public function searchServiceGroup()
    {
        $validated = request()->validate([
            'q' => 'required|string|max:50',
        ]);

        $q = $validated['q'];
        $serviceGroups = ServiceGroup::where('name', 'like', "%{$q}%")->select('id', 'name')->limit(20)->get();

        if ($serviceGroups->isEmpty()) {
            return response()->json([]);
        }

        $grouped = [
            0 => $serviceGroups->map(fn ($sg) => [
                'id' => $sg->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $sg->name,
                'type' => 'service group',
                'raw_data' => $sg->toArray(),
            ])->values()->all(),
        ];

        return response()->json([
            [
                'id' => 'group_service_groups',
                'headerGroup' => 'service group',
                'options' => (object) $grouped,
            ],
        ]);
    }

    private function exportColumnMapping(): array
    {
        return [
            'code' => __('Service Code'),
            'name' => __('Name'),
            'selling_price' => __('Sell price'),
            'vat' => __('VAT'),
            'group_name' => __('Service group'),
            'revenue_account' => __('Revenue account amount'),
            'cogs_account' => __('COGS account amount'),
            'sales_return_account' => __('Sales return account amount'),
            'income_subject_code' => __('Revenue subject code'),
            'cogs_subject_code' => __('COGS subject code'),
            'sales_returns_subject_code' => __('Sales returns subject code'),
            'sstid' => __('Service SSTID'),
            'description' => __('Description'),
        ];
    }

    private function selectedExportColumns(Request $request): array
    {
        $availableColumns = $this->exportColumnMapping();
        $validated = $request->validate([
            'cols_submitted' => ['nullable', 'boolean'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys($availableColumns))],
        ]);

        if (! $request->boolean('cols_submitted')) {
            return $availableColumns;
        }

        $selectedColumns = array_unique(['name', ...(array) ($validated['columns'] ?? [])]);

        return array_intersect_key($availableColumns, array_flip($selectedColumns));
    }

    private function serviceSubjectTotals(Collection $services): array
    {
        $subjectIds = $services->flatMap(fn (Service $service) => [
            $service->subject_id,
            $service->cogs_subject_id,
            $service->sales_returns_subject_id,
        ])->filter()->unique()->values();

        if ($subjectIds->isEmpty()) {
            return [];
        }

        return Transaction::query()->whereIn('subject_id', $subjectIds)->selectRaw('subject_id, SUM(value) as total')
            ->groupBy('subject_id')->pluck('total', 'subject_id')->map(fn ($value) => (float) $value)->all();
    }
}
