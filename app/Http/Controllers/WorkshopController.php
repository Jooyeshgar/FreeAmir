<?php
namespace App\Http\Controllers;

use App\Http\Requests\WorkshopRequest;
use App\Models\Workshop;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    public function index()
    {
        $workshops = Workshop::paginate(10); // Paginate results
        return view('workshops.index', compact('workshops'));
    }

    public function create()
    {
        return view('workshops.create');
    }

    public function store(WorkshopRequest $request)
    {
        $workshop = Workshop::create($request->all());
        return redirect()->route('payroll.workshops.index')->with('success', 'Workshop created successfully.');
    }

    public function show($id)
    {
        $workshop = Workshop::findOrFail($id);
        return view('workshops.show', compact('workshop'));
    }

    public function edit($id)
    {
        $workshop = Workshop::findOrFail($id);
        return view('workshops.edit', compact('workshop'));
    }

    public function update(WorkshopRequest $request, $id)
    {
        $workshop = Workshop::findOrFail($id);
        $workshop->update($request->all());
        return redirect()->route('payroll.workshops.index')->with('success', 'Workshop updated successfully.');
    }

    public function destroy($id)
    {
        $workshop = Workshop::findOrFail($id);
        $workshop->delete();
        return redirect()->route('payroll.workshops.index')->with('success', 'Workshop deleted successfully.');
    }
}
