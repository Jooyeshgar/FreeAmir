<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function index()
    {
        $customerGroups = Models\CustomerGroup::paginate(12);

        return view('customerGroups.index', compact('customerGroups'));
    }

    public function create()
    {
        return view('customerGroups.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|unique:customer_groups,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        ]);

        Models\CustomerGroup::create($validatedData);

        return redirect()->route('customer-groups.index')->with('success', 'Customer group created successfully.');
    }

    public function edit(Models\CustomerGroup $customerGroup)
    {
        return view('customerGroups.edit', compact('customerGroup'));
    }

    public function update(Request $request, Models\CustomerGroup $customerGroup)
    {
        $validatedData = $request->validate([
            'code' => 'required|exists:customer_groups,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
        ]);

        $customerGroup->update($validatedData);

        return redirect()->route('customer-groups.index')->with('success', 'Customer group updated successfully.');
    }

    public function destroy(Models\CustomerGroup $customerGroup)
    {
        $customerGroup->delete();

        return redirect()->route('customer-groups.index')->with('success', 'Customer group deleted successfully.');
    }
}
