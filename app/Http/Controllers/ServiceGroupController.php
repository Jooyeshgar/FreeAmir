<?php

namespace App\Http\Controllers;

use App\Models\ServiceGroup;
use App\Services\ServiceGroupService;
use Illuminate\Http\Request;

class ServiceGroupController extends Controller
{
    public function __construct(
        private readonly ServiceGroupService $serviceGroupService,
    ) {}

    public function index()
    {
        $serviceGroups = ServiceGroup::paginate(12);

        return view('serviceGroups.index', compact('serviceGroups'));
    }

    public function create()
    {
        if (empty(config('amir.service_revenue'))) {
            return redirect()->route('configs.index')->with('error', __('Service Revenue Subjects are not configured. Please set them in configurations.'));
        }

        return view('serviceGroups.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
            'sstid' => 'nullable|string',
        ]);

        $this->serviceGroupService->create($validatedData);

        return redirect()->route('service-groups.index')->with('success', __('Service group created successfully.'));
    }

    public function show(ServiceGroup $serviceGroup)
    {
        $serviceGroup->debit = \App\Services\SubjectService::sumSubject($serviceGroup->subject, false, true);
        $serviceGroup->credit = \App\Services\SubjectService::sumSubject($serviceGroup->subject, false, false);
        $serviceGroup->balance = $serviceGroup->debit + $serviceGroup->credit;

        return view('serviceGroups.show', compact('serviceGroup'));
    }

    public function edit(ServiceGroup $serviceGroup)
    {
        return view('serviceGroups.edit', compact('serviceGroup'));
    }

    public function update(Request $request, ServiceGroup $serviceGroup)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
            'sstid' => 'nullable|string',
        ]);

        $this->serviceGroupService->update($serviceGroup, $validatedData);

        return redirect()->route('service-groups.index')->with('success', __('Service group updated successfully.'));
    }

    public function destroy(ServiceGroup $serviceGroup)
    {
        $this->serviceGroupService->delete($serviceGroup);

        return redirect()->route('service-groups.index')->with('success', __('Service group deleted successfully.'));
    }
}
