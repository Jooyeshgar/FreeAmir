<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Subject;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct() {}

    public function index(Request $request)
    {
        $query = Transaction::with(['document', 'subject', 'user'])
            ->orderBy('id', 'desc');

        // Filter by subject_id if provided
        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by document number if provided
        if ($request->has('document_number') && $request->document_number) {
            $query->whereHas('document', function ($q) use ($request) {
                $q->where('number', convertToInt($request->document_number));
            });
        }

        // Filter by date if provided
        if ($request->has('date') && $request->date) {
            $query->whereHas('document', function ($q) use ($request) {
                $q->where('date', convertToGregorian($request->date));
            });
        }

        // Search by transaction description
        if ($request->has('text') && $request->text) {
            $query->where('desc', 'like', '%' . $request->text . '%');
        }

        $transactions = $query->paginate(20);

        // Get all subjects for the filter dropdown in hierarchical format
        $subjects = Subject::whereIsRoot()->with('children')->orderBy('code', 'asc')->get();
        
        // Get current subject if subject_id is provided
        $currentSubject = null;
        if ($request->subject_id) {
            $currentSubject = Subject::find($request->subject_id);
        }

        return view('transactions.index', compact('transactions', 'subjects', 'currentSubject'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['document', 'subject', 'user']);
        return view('transactions.show', compact('transaction'));
    }
}
