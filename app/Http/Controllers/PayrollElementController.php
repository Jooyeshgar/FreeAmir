<?php

namespace App\Http\Controllers;

use App\Models\PayrollElement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollElementController extends Controller
{
    public function index(Request $request): View
    {
        $query = PayrollElement::orderBy('category')->orderBy('title');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('title')) {
            $query->where('title', 'like', '%'.$request->title.'%');
        }

        $payrollElements = $query->paginate(15);

        return view('payroll-elements.index', compact('payrollElements'));
    }

    public function create(): View
    {
        return view('payroll-elements.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:100'],
            'system_code'    => ['required', Rule::in([
                'CHILD_ALLOWANCE', 'HOUSING_ALLOWANCE', 'FOOD_ALLOWANCE', 'MARRIAGE_ALLOWANCE',
                'OVERTIME', 'FRIDAY_PAY', 'HOLIDAY_PAY', 'MISSION_PAY',
                'INSURANCE_EMP', 'INSURANCE_EMP2', 'UNEMPLOYMENT_INS',
                'INCOME_TAX', 'ABSENCE_DEDUCTION', 'OTHER',
            ])],
            'category'       => ['required', Rule::in(['earning', 'deduction'])],
            'calc_type'      => ['required', Rule::in(['fixed', 'formula', 'percentage'])],
            'formula'        => ['nullable', 'string', 'max:500'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_taxable'     => ['boolean'],
            'is_insurable'   => ['boolean'],
            'show_in_payslip' => ['boolean'],
            'gl_account_code' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['is_taxable']     = $request->boolean('is_taxable');
        $validated['is_insurable']   = $request->boolean('is_insurable');
        $validated['show_in_payslip'] = $request->boolean('show_in_payslip');

        PayrollElement::create(array_merge(
            $validated,
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('payroll-elements.index')
            ->with('success', __('Payroll element created successfully.'));
    }

    public function edit(PayrollElement $payrollElement): View
    {
        return view('payroll-elements.edit', compact('payrollElement'));
    }

    public function update(Request $request, PayrollElement $payrollElement): RedirectResponse
    {
        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:100'],
            'system_code'    => ['required', Rule::in([
                'CHILD_ALLOWANCE', 'HOUSING_ALLOWANCE', 'FOOD_ALLOWANCE', 'MARRIAGE_ALLOWANCE',
                'OVERTIME', 'FRIDAY_PAY', 'HOLIDAY_PAY', 'MISSION_PAY',
                'INSURANCE_EMP', 'INSURANCE_EMP2', 'UNEMPLOYMENT_INS',
                'INCOME_TAX', 'ABSENCE_DEDUCTION', 'OTHER',
            ])],
            'category'       => ['required', Rule::in(['earning', 'deduction'])],
            'calc_type'      => ['required', Rule::in(['fixed', 'formula', 'percentage'])],
            'formula'        => ['nullable', 'string', 'max:500'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_taxable'     => ['boolean'],
            'is_insurable'   => ['boolean'],
            'show_in_payslip' => ['boolean'],
            'gl_account_code' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['is_taxable']     = $request->boolean('is_taxable');
        $validated['is_insurable']   = $request->boolean('is_insurable');
        $validated['show_in_payslip'] = $request->boolean('show_in_payslip');

        $payrollElement->update($validated);

        return redirect()->route('payroll-elements.index')
            ->with('success', __('Payroll element updated successfully.'));
    }

    public function destroy(PayrollElement $payrollElement): RedirectResponse
    {
        if ($payrollElement->is_system_locked) {
            return redirect()->route('payroll-elements.index')
                ->with('error', __('This payroll element is system-locked and cannot be deleted.'));
        }

        $payrollElement->delete();

        return redirect()->route('payroll-elements.index')
            ->with('success', __('Payroll element deleted successfully.'));
    }
}
