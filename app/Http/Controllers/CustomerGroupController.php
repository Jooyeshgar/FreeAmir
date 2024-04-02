<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function index()
    {
        $customerGroups = Models\CustomerGroup::paginate(12);
        $cols = [
            'code', 'name', 'description',
        ];
        return view('customerGroups.index', compact('customerGroups', 'cols'));
    }

    public function create()
    {
        $fields = $this->fields();
        return view('customerGroups.create', compact('fields'));
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|unique:customer_groups,code',
            'name' => 'required|max:20',
            'description' => 'required',
        ]);

        Models\CustomerGroup::create($validatedData);

        return redirect()->route('customer-groups.index')->with('success', 'Customer group created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit(Models\CustomerGroup $customerGroup)
    {
        $fields = $this->fields();
        return view('customerGroups.edit', compact('customerGroup', 'fields'));
    }

    public function update(Request $request, Models\CustomerGroup $customerGroup)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|exists:customer_groups,code',
            'name' => 'required|max:20',
            'description' => 'required',
        ]);

        $customerGroup->update($validatedData);

        return redirect()->route('customer-groups.index')->with('success', 'Customer group updated successfully.');
    }

    public function destroy(Models\CustomerGroup $customerGroup)
    {
        $customerGroup->delete();

        return redirect()->route('customer-groups.index')->with('success', 'Customer group deleted successfully.');
    }

    public function fields(): array
    {
        return [
            'code' => ['label' => 'کد طرف حساب', 'type' => 'text'],
            'name' => ['label' => 'نام', 'type' => 'text'],
            'description' => ['label' => 'توضیحات', 'type' => 'textarea']
        ];
    }
}
