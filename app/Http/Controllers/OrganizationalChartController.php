<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationalChartRequest;
use App\Models\OrganizationalChart;
use Illuminate\Http\Request;

class OrganizationalChartController extends Controller
{
    public function index()
    {
        $organizationalCharts = OrganizationalChart::paginate(10);
        return view('organizational_charts.index', compact('organizationalCharts'));
    }

    public function create()
    {
        return view('organizational_charts.create');
    }

    public function store(OrganizationalChartRequest $request)
    {
        OrganizationalChart::create($request->all());
        return redirect()->route('payroll.organizational_charts.index')->with('success', 'Organizational Chart created successfully.');
    }

    public function show($id)
    {
        $organizationalChart = OrganizationalChart::findOrFail($id);
        return view('organizational_charts.show', compact('organizationalChart'));
    }

    public function edit($id)
    {
        $organizationalChart = OrganizationalChart::findOrFail($id);
        return view('organizational_charts.edit', compact('organizationalChart'));
    }

    public function update(OrganizationalChartRequest $request, $id)
    {
        $organizationalChart = OrganizationalChart::findOrFail($id);
        $organizationalChart->update($request->all());
        return redirect()->route('payroll.organizational_charts.index')->with('success', 'Organizational Chart updated successfully.');
    }

    public function destroy($id)
    {
        $organizationalChart = OrganizationalChart::findOrFail($id);
        $organizationalChart->delete();
        return redirect()->route('payroll.organizational_charts.index')->with('success', 'Organizational Chart deleted successfully.');
    }
}
