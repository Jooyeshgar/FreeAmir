<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\ServiceImportService;
use App\Services\ServiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('services.index', compact('services'));
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

    public function export(): StreamedResponse
    {
        $filename = 'services_'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM so Excel reads Persian text correctly.
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ServiceImportService::COLUMNS);

            Service::with('serviceGroup', 'subject', 'cogsSubject', 'salesReturnsSubject')
                ->orderBy('code')
                ->chunk(200, function ($services) use ($file) {
                    foreach ($services as $service) {
                        fputcsv($file, [
                            $service->code,
                            $service->name,
                            $service->serviceGroup?->name,
                            $service->subject?->code,
                            $service->cogsSubject?->code,
                            $service->salesReturnsSubject?->code,
                            $service->sstid,
                            $service->selling_price,
                            $service->vat,
                            $service->description,
                        ]);
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
}
