<?php

namespace App\Http\Controllers;

use App\Models\OrgChart;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    public function index()
    {
        $search = request('search');

        $query = OrgChart::with('parent')->orderBy('title');

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $orgCharts = $query->paginate(15);

        return view('org-charts.index', compact('orgCharts', 'search'));
    }

    public function create()
    {
        $parents = OrgChart::orderBy('title')->get();

        return view('org-charts.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'parent_id' => 'nullable|integer|exists:org_charts,id',
            'description' => 'nullable|string',
        ]);

        OrgChart::create(array_merge(
            $validated,
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('org-charts.index')
            ->with('success', __('Organization chart node created successfully.'));
    }

    public function show(OrgChart $orgChart)
    {
        $orgChart->load('parent', 'children');

        return view('org-charts.show', compact('orgChart'));
    }

    public function edit(OrgChart $orgChart)
    {
        $parents = OrgChart::where('id', '!=', $orgChart->id)
            ->orderBy('title')
            ->get();

        return view('org-charts.edit', compact('orgChart', 'parents'));
    }

    public function update(Request $request, OrgChart $orgChart)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'parent_id' => 'nullable|integer|exists:org_charts,id',
            'description' => 'nullable|string',
        ]);

        $orgChart->update($validated);

        return redirect()->route('org-charts.index')
            ->with('success', __('Organization chart node updated successfully.'));
    }

    public function destroy(OrgChart $orgChart)
    {
        $orgChart->delete();

        return redirect()->route('org-charts.index')
            ->with('success', __('Organization chart node deleted successfully.'));
    }
}
