<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Models\Customer::with('subject', 'group')->paginate(12);

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        $groups = Models\CustomerGroup::select('id', 'name')->get();

        return view('customers.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'code' => 'required|unique:customers,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'phone' => 'nullable|numeric|regex:/^09\d{9}$/',
            'fax' => 'nullable|numeric',
            'address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'postal_code' => 'nullable|integer',
            'email' => 'nullable|email|max:15',
            'ecnmcs_code' => 'nullable|integer',
            'personal_code' => 'nullable|integer',
            'web_page' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'responsible' => 'nullable',
            'group_id' => 'required|exists:customer_groups,id|integer',
            'desc' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'rep_via_email' => 'nullable|in:on,off',
            'acc_name_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_bank_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_name_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_bank_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
        ]);

        $validatedData['rep_via_email'] = $request->has('rep_via_email') ? 1 : 0;
        $validatedData['connector'] = '';
        $validatedData['cell'] = '';
        $validatedData['balance'] = 0;
        $validatedData['credit'] = 0;
        $validatedData['type_buyer'] = 0;
        $validatedData['type_seller'] = 0;
        $validatedData['type_mate'] = 0;
        $validatedData['type_agent'] = 0;
        $validatedData['commission'] = '';
        $validatedData['marked'] = 0;
        $validatedData['reason'] = '';
        $validatedData['disc_rate'] = '';
        $validatedData['address'] = empty($validatedData['address']) ? '' : $validatedData['address'];
        $validatedData['web_page'] = empty($validatedData['web_page']) ? '' : $validatedData['web_page'];
        $validatedData['responsible'] = empty($validatedData['responsible']) ? '' : $validatedData['responsible'];
        $validatedData['desc'] = empty($validatedData['desc']) ? '' : $validatedData['desc'];
        $validatedData['postal_code'] = empty($validatedData['postal_code']) ? '' : $validatedData['postal_code'];

        Models\Customer::create($validatedData);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function edit(Models\Customer $customer)
    {
        $groups = Models\CustomerGroup::select('id', 'name')->get();

        return view('customers.edit', compact('customer', 'groups'));
    }

    public function update(Request $request, Models\Customer $customer)
    {
        $validatedData = $request->validate([
            'code' => 'required|exists:customers,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'phone' => 'nullable|numeric|regex:/^09\d{9}$/',
            'fax' => 'nullable|numeric',
            'address' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'postal_code' => 'nullable|integer',
            'email' => 'nullable|email|max:15',
            'ecnmcs_code' => 'nullable|integer',
            'personal_code' => 'nullable|integer',
            'web_page' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'responsible' => 'nullable',
            'group_id' => 'required|exists:customer_groups,id|integer',
            'desc' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'rep_via_email' => 'nullable|in:on,off',
            'acc_name_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_bank_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_name_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_bank_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
        ]);

        $validatedData['rep_via_email'] = $request->has('rep_via_email') ? 1 : 0;
        $validatedData['connector'] = '';
        $validatedData['cell'] = '';
        $validatedData['balance'] = 0;
        $validatedData['credit'] = 0;
        $validatedData['type_buyer'] = 0;
        $validatedData['type_seller'] = 0;
        $validatedData['type_mate'] = 0;
        $validatedData['type_agent'] = 0;
        $validatedData['commission'] = '';
        $validatedData['marked'] = 0;
        $validatedData['reason'] = '';
        $validatedData['disc_rate'] = '';
        $validatedData['address'] = empty($validatedData['address']) ? '' : $validatedData['address'];
        $validatedData['web_page'] = empty($validatedData['web_page']) ? '' : $validatedData['web_page'];
        $validatedData['responsible'] = empty($validatedData['responsible']) ? '' : $validatedData['responsible'];
        $validatedData['desc'] = empty($validatedData['desc']) ? '' : $validatedData['desc'];
        $validatedData['postal_code'] = empty($validatedData['postal_code']) ? '' : $validatedData['postal_code'];

        $customer->update($validatedData);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Models\Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
}
