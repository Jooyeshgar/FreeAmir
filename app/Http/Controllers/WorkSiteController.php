<?php

namespace App\Http\Controllers;

use App\Models\WorkSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkSiteController extends Controller
{
    public function index(): View
    {
        $workSites = WorkSite::orderBy('name')->paginate(15);

        return view('work-sites.index', compact('workSites'));
    }

    public function create(): View
    {
        return view('work-sites.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:work_sites,code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        WorkSite::create(array_merge(
            $validated,
            [
                'company_id' => getActiveCompany(),
                'is_active' => $request->boolean('is_active', true),
            ]
        ));

        return redirect()->route('work-sites.index')
            ->with('success', __('Work site created successfully.'));
    }

    public function show(WorkSite $workSite): View
    {
        return view('work-sites.show', compact('workSite'));
    }

    public function edit(WorkSite $workSite): View
    {
        return view('work-sites.edit', compact('workSite'));
    }

    public function update(Request $request, WorkSite $workSite): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:work_sites,code,'.$workSite->id,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $workSite->update(array_merge(
            $validated,
            ['is_active' => $request->boolean('is_active', false)]
        ));

        return redirect()->route('work-sites.index')
            ->with('success', __('Work site updated successfully.'));
    }

    public function destroy(WorkSite $workSite): RedirectResponse
    {
        $workSite->delete();

        return redirect()->route('work-sites.index')
            ->with('success', __('Work site deleted successfully.'));
    }
}
