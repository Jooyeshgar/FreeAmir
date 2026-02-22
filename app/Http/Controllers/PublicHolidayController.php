<?php

namespace App\Http\Controllers;

use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicHolidayController extends Controller
{
    public function index(Request $request): View
    {
        $query = PublicHoliday::orderBy('date', 'asc');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $publicHolidays = $query->paginate(15);

        return view('public-holidays.index', compact('publicHolidays'));
    }

    public function create(): View
    {
        return view('public-holidays.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'unique:public_holidays,date'],
            'name' => ['required', 'string', 'max:200'],
        ]);

        PublicHoliday::create(array_merge(
            $validated,
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('public-holidays.index')
            ->with('success', __('Public holiday created successfully.'));
    }

    public function edit(PublicHoliday $publicHoliday): View
    {
        return view('public-holidays.edit', compact('publicHoliday'));
    }

    public function update(Request $request, PublicHoliday $publicHoliday): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'unique:public_holidays,date,' . $publicHoliday->id],
            'name' => ['required', 'string', 'max:200'],
        ]);

        $publicHoliday->update($validated);

        return redirect()->route('public-holidays.index')
            ->with('success', __('Public holiday updated successfully.'));
    }

    public function destroy(PublicHoliday $publicHoliday): RedirectResponse
    {
        $publicHoliday->delete();

        return redirect()->route('public-holidays.index')
            ->with('success', __('Public holiday deleted successfully.'));
    }
}
