<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChequeHistoryRequest;
use App\Models\Cheque;
use App\Models\ChequeHistory;
use App\Services\ChequeHistoryService;

class ChequeHistoryController extends Controller
{
    public function __construct(private readonly ChequeHistoryService $service) {}

    public function index()
    {
        $histories = $this->service->paginate();

        return view('cheque-histories.index', compact('histories'));
    }

    public function create()
    {
        $cheques = Cheque::query()->pluck('serial', 'id');

        return view('cheque-histories.create', compact('cheques'));
    }

    public function store(ChequeHistoryRequest $request)
    {
        $this->service->create($request->validated());

        return redirect()->route('cheque-histories.index')->with('success', __('Cheque history created successfully.'));
    }

    public function show(ChequeHistory $chequeHistory)
    {
        $chequeHistory->load(['cheque', 'creator']);

        return view('cheque-histories.show', compact('chequeHistory'));
    }

    public function edit(ChequeHistory $chequeHistory)
    {
        $cheques = Cheque::query()->pluck('serial', 'id');

        return view('cheque-histories.edit', compact('chequeHistory', 'cheques'));
    }

    public function update(ChequeHistoryRequest $request, ChequeHistory $chequeHistory)
    {
        $this->service->update($chequeHistory, $request->validated());

        return redirect()->route('cheque-histories.index')->with('success', __('Cheque history updated successfully.'));
    }

    public function destroy(ChequeHistory $chequeHistory)
    {
        $this->service->delete($chequeHistory);

        return redirect()->route('cheque-histories.index')->with('success', __('Cheque history deleted successfully.'));
    }
}
