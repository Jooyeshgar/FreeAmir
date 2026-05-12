<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MoadianHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoadianHistoryController extends Controller
{
    public function index(Request $request, Invoice $invoice): View
    {
        $query = MoadianHistory::with('invoice');

        // if ($invoice->isEmpty()) { // TODO: check to be sure that work correctly

        // }

        $query->where('invoice_id', $invoice->id);

        if ($request->filled('status')) {
            $query->where('data->status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $moadianHistories = $query->latest()->paginate(5)->withQueryString();

        return view('moadian-histories.index', compact('moadianHistories'));
    }
}
