<?php
namespace App\Http\Controllers;

use App\Http\Requests\WorkhouseRequest;
use App\Models\Workhouse;
use Illuminate\Http\Request;

class WorkhouseController extends Controller
{
    public function index()
    {
        $workhouses = Workhouse::paginate(10); // Paginate results
        return view('workhouses.index', compact('workhouses'));
    }

    public function create()
    {
        return view('workhouses.create');
    }

    public function store(WorkhouseRequest $request)
    {
        $workhouse = Workhouse::create($request->all());
        return redirect()->route('payroll.workhouses.index')->with('success', 'Workhouse created successfully.');
    }

    public function show($id)
    {
        $workhouse = Workhouse::findOrFail($id);
        return view('workhouses.show', compact('workhouse'));
    }

    public function edit($id)
    {
        $workhouse = Workhouse::findOrFail($id);
        return view('workhouses.edit', compact('workhouse'));
    }

    public function update(WorkhouseRequest $request, $id)
    {
        $workhouse = Workhouse::findOrFail($id);
        $workhouse->update($request->all());
        return redirect()->route('payroll.workhouses.index')->with('success', 'Workhouse updated successfully.');
    }

    public function destroy($id)
    {
        $workhouse = Workhouse::findOrFail($id);
        $workhouse->delete();
        return redirect()->route('payroll.workhouses.index')->with('success', 'Workhouse deleted successfully.');
    }
}
