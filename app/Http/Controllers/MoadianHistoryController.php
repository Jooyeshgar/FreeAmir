<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MoadianHistory;
use App\Services\MoadianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoadianHistoryController extends Controller
{
    public function __construct(private readonly MoadianService $moadianService) {}

    public function index(Request $request): View
    {
        $query = MoadianHistory::with('invoice');

        if ($request->filled('status')) {
            $query->where('data->status', $request->input('status'));
        }

        if ($request->filled('invoice_number')) {
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('number', $request->input('invoice_number')));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $moadianHistories = $query->latest()->paginate(10)->withQueryString();

        $latestHistoryIds = MoadianHistory::whereIn('invoice_id', $moadianHistories->pluck('invoice_id')->unique())
            ->selectRaw('MAX(id) as id')
            ->groupBy('invoice_id')
            ->pluck('id')
            ->all();

        return view('moadian-histories.index', compact('moadianHistories', 'latestHistoryIds'));
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $latestHistory = $invoice->moadianHistories()->latest()->first();
        $latestHistoryStatus = $latestHistory ? strtoupper($latestHistory->data['status'] ?? 'UNKNOWN') : null;

        $query = MoadianHistory::with('invoice')->where('invoice_id', $invoice->id);

        if ($request->filled('status')) {
            $query->where('data->status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $moadianHistories = $query->latest()->paginate(10)->withQueryString();

        return view('moadian-histories.show', compact('moadianHistories', 'invoice', 'latestHistoryStatus'));
    }

    public function checkStatus(Invoice $invoice): RedirectResponse
    {
        $latestHistory = $invoice->moadianHistories()->latest()->first();

        if (! $latestHistory || ! isset($latestHistory->data['referenceNumber'])) {
            return redirect()->back()->with('error', __('No reference number available to check status.'));
        }

        $statusData = $this->moadianService->moadianStatus($latestHistory->data['referenceNumber'], $invoice);

        if (strtoupper($statusData['status'] ?? '') === 'FAILED') {
            return redirect()->back()->with('error', __('Failed to check status from Moadian. Please try again.'));
        }

        return redirect()->back()->with('success', __('Status checked and updated successfully.'));
    }
}
