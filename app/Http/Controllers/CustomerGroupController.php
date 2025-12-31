<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Models\Subject;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $customerGroups = CustomerGroup::paginate(12);

        return view('customerGroups.index', compact('customerGroups'));
    }

    public function create()
    {
        $subjects = Subject::whereIsRoot()->with('children')->orderBy('code', 'asc')->get();

        return view('customerGroups.create', compact('subjects'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        ]);

        CustomerGroup::create($validatedData);

        return redirect()->route('customer-groups.index')->with('success', __('Customer group created successfully.'));
    }

    public function show(CustomerGroup $customerGroup)
    {
        return view('customerGroups.show', compact('customerGroup'));
    }

    public function edit(CustomerGroup $customerGroup)
    {
        $subjects = Subject::whereIsRoot()->with('children')->orderBy('code', 'asc')->get();

        return view('customerGroups.edit', compact('customerGroup', 'subjects'));
    }

    public function update(Request $request, CustomerGroup $customerGroup)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        ]);

        $customerGroup->update($validatedData);

        return redirect()->route('customer-groups.index')->with('success', __('Customer group updated successfully.'));
    }

    public function destroy(CustomerGroup $customerGroup)
    {
        $customerGroup->delete();

        return redirect()->route('customer-groups.index')->with('success', __('Customer group deleted successfully.'));
    }
}
