<?php

namespace App\Http\Controllers;

use App\Enums\PayrollElementCalcType;
use App\Enums\PayrollElementCategory;
use App\Enums\PayrollElementSystemCode;
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
            $category = PayrollElementCategory::tryFromName($request->category);

            if ($category !== null) {
                $query->where('category', $category);
            }
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
            'title' => ['required', 'string', 'max:100'],
            'system_code' => ['required', Rule::in(PayrollElementSystemCode::valueNames())],
            'category' => ['required', Rule::in(PayrollElementCategory::valueNames())],
            'calc_type' => ['required', Rule::in(PayrollElementCalcType::valueNames())],
            'formula' => ['nullable', 'string', 'max:500'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'is_insurable' => ['boolean'],
            'show_in_payslip' => ['boolean'],
            'gl_account_code' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['is_taxable'] = $request->boolean('is_taxable');
        $validated['is_insurable'] = $request->boolean('is_insurable');
        $validated['show_in_payslip'] = $request->boolean('show_in_payslip');
        $validated['system_code'] = PayrollElementSystemCode::fromName($validated['system_code']);
        $validated['category'] = PayrollElementCategory::fromName($validated['category']);
        $validated['calc_type'] = PayrollElementCalcType::fromName($validated['calc_type']);

        PayrollElement::create(array_merge(
            $validated,
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('salary.payroll-elements.index')
            ->with('success', __('Payroll element created successfully.'));
    }

    public function edit(PayrollElement $payrollElement): View
    {
        return view('payroll-elements.edit', compact('payrollElement'));
    }

    public function update(Request $request, PayrollElement $payrollElement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'system_code' => ['required', Rule::in(PayrollElementSystemCode::valueNames())],
            'category' => ['required', Rule::in(PayrollElementCategory::valueNames())],
            'calc_type' => ['required', Rule::in(PayrollElementCalcType::valueNames())],
            'formula' => ['nullable', 'string', 'max:500'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'is_insurable' => ['boolean'],
            'show_in_payslip' => ['boolean'],
            'gl_account_code' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['is_taxable'] = $request->boolean('is_taxable');
        $validated['is_insurable'] = $request->boolean('is_insurable');
        $validated['show_in_payslip'] = $request->boolean('show_in_payslip');
        $validated['system_code'] = PayrollElementSystemCode::fromName($validated['system_code']);
        $validated['category'] = PayrollElementCategory::fromName($validated['category']);
        $validated['calc_type'] = PayrollElementCalcType::fromName($validated['calc_type']);

        $payrollElement->update($validated);

        return redirect()->route('salary.payroll-elements.index')
            ->with('success', __('Payroll element updated successfully.'));
    }

    public function destroy(PayrollElement $payrollElement): RedirectResponse
    {
        if ($payrollElement->is_system_locked) {
            return redirect()->route('salary.payroll-elements.index')
                ->with('error', __('This payroll element is system-locked and cannot be deleted.'));
        }

        $payrollElement->delete();

        return redirect()->route('salary.payroll-elements.index')
            ->with('success', __('Payroll element deleted successfully.'));
    }
}
