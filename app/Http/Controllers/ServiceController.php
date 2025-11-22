<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\ServiceService;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceService $serviceService,
    ) {}

    public function index()
    {
        $services = Service::with('serviceGroup')->paginate(12);

        return view('services.index', compact('services'));
    }

    public function create()
    {
        $groups = ServiceGroup::Some()->get(['id', 'name']);

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
        $service->load('serviceGroup');

        return view('services.show', compact('service'));
    }

    public function edit(Service $service)
    {
        $groups = ServiceGroup::Some()->get(['id', 'name']);

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
}
