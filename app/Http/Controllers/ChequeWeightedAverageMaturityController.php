<?php

namespace App\Http\Controllers;

use App\Enums\ChequeType;
use App\Models\Cheque;
use App\Services\ChequeWeightedAverageMaturityService;
use Illuminate\Http\Request;

class ChequeWeightedAverageMaturityController extends Controller
{
    public function __construct(private readonly ChequeWeightedAverageMaturityService $service) {}

    public function index()
    {
        $cheques = Cheque::with('party')
            ->whereNotIn('status', [ChequeType::CANCELLED, ChequeType::RETURNED])
            ->orderBy('due_date')
            ->get();

        return view('cheques.weighted-average-maturity', compact('cheques'));
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'cheque_ids' => ['required', 'array', 'min:1'],
            'cheque_ids.*' => ['integer'],
            'base_date' => ['nullable', 'string'],
            'annual_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);
        $baseDate = $request->filled('base_date') ? jalaliInputToGregorian($request->input('base_date'), 'base_date') : null;
        $cheques = Cheque::whereIn('id', $validated['cheque_ids'])->orderBy('due_date')->get();
        if ($cheques->count() !== count(array_unique($validated['cheque_ids']))) {
            abort(404);
        }
        $result = $this->service->calculate($cheques, $baseDate, (float) ($validated['annual_rate'] ?? 0));

        return view('cheques.weighted-average-maturity', compact('cheques', 'result'));
    }
}
