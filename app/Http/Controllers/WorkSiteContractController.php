<?php

namespace App\Http\Controllers;

use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Http\Request;

class WorkSiteContractController extends Controller
{
    public function index()
    {
        $search = request('search');

        $query = WorkSiteContract::with('workSites')->orderBy('name');

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        }

        $contracts = $query->paginate(15);

        return view('work-site-contracts.index', compact('contracts', 'search'));
    }

    public function create()
    {
        $workSites = WorkSite::orderBy('name')->get();

        return view('work-site-contracts.create', compact('workSites'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_site_id' => 'required|integer|exists:work_sites,id',
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:work_site_contracts,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        WorkSiteContract::create($validated);

        return redirect()->route('work-site-contracts.index')
            ->with('success', __('Work site contract created successfully.'));
    }

    public function show(WorkSiteContract $workSiteContract)
    {
        $workSiteContract->load('workSites');

        return view('work-site-contracts.show', compact('workSiteContract'));
    }

    public function edit(WorkSiteContract $workSiteContract)
    {
        $workSites = WorkSite::orderBy('name')->get();

        return view('work-site-contracts.edit', compact('workSiteContract', 'workSites'));
    }

    public function update(Request $request, WorkSiteContract $workSiteContract)
    {
        $validated = $request->validate([
            'work_site_id' => 'required|integer|exists:work_sites,id',
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:work_site_contracts,code,'.$workSiteContract->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $workSiteContract->update($validated);

        return redirect()->route('work-site-contracts.index')
            ->with('success', __('Work site contract updated successfully.'));
    }

    public function destroy(WorkSiteContract $workSiteContract)
    {
        $workSiteContract->delete();

        return redirect()->route('work-site-contracts.index')
            ->with('success', __('Work site contract deleted successfully.'));
    }
}
