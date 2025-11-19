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
            ->whereHas('document')
            ->join('documents', 'transactions.document_id', '=', 'documents.id')
            ->orderBy('documents.number', 'asc')
            ->select('transactions.*');

        // Subject filter
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        // Date range filter (convert jalali to gregorian if needed)
        if ($request->filled('start_date')) {
            $startDate = convertToGregorian($request->input('start_date'));
            $query->whereHas('document', function ($q) use ($startDate) {
                $q->whereDate('date', '>=', $startDate);
            });
        }
        if ($request->filled('end_date')) {
            $endDate = convertToGregorian($request->input('end_date'));
            $query->whereHas('document', function ($q) use ($endDate) {
                $q->whereDate('date', '<=', $endDate);
            });
        }

        if ($request->filled('start_document_number')) {
            $startDocNum = convertToFloat($request->input('start_document_number'));
            $query->whereHas('document', function ($q) use ($startDocNum) {
                $q->where('number', '>=', $startDocNum);
            });
        }
        if ($request->filled('end_document_number')) {
            $endDocNum = convertToFloat($request->input('end_document_number'));
            $query->whereHas('document', function ($q) use ($endDocNum) {
                $q->where('number', '<=', $endDocNum);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('desc', 'like', "%{$search}%")
                    ->orWhereHas('subject', function ($qq) use ($search) {
                        $qq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('document', function ($qd) use ($search) {
                        $qd->where('title', 'like', "%{$search}%")
                            ->orWhere('number', 'like', "%{$search}%");
                    });
            });
        }

        $openingBalance = $this->calculateOpeningBalance($request);

        $transactions = $query->paginate(20)->appends($request->query());

        $this->addRunningBalance($transactions, $openingBalance);

        $subjects = Subject::whereIsRoot()->with('children')->orderBy('code', 'asc')->get();
        
        $currentSubject = null;
        if ($request->filled('subject_id')) {
            $currentSubject = Subject::find($request->integer('subject_id'));
        }

        return view('transactions.index', compact('transactions', 'subjects', 'currentSubject', 'openingBalance'));
    }

    /**
     * Calculate the opening balance before the filtered transactions
     */
    private function calculateOpeningBalance(Request $request): float
    {
        $query = Transaction::query()
            ->whereHas('document')
            ->join('documents', 'transactions.document_id', '=', 'documents.id');

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        $hasFilter = false;

        if ($request->filled('start_date')) {
            $startDate = convertToGregorian($request->input('start_date'));
            $query->whereHas('document', function ($q) use ($startDate) {
                $q->whereDate('date', '<', $startDate);
            });
            $hasFilter = true;
        }

        if ($request->filled('start_document_number')) {
            $startDocNum = convertToFloat($request->input('start_document_number'));
            $query->whereHas('document', function ($q) use ($startDocNum) {
                $q->where('number', '<', $startDocNum);
            });
            $hasFilter = true;
        }

        if (!$hasFilter) {
            return 0;
        }

        return (float) $query->sum('transactions.value');
    }

    /**
     * Add running balance to each transaction in the collection
     */
    private function addRunningBalance($transactions, float $openingBalance): void
    {
        $runningBalance = $openingBalance;

        foreach ($transactions as $transaction) {
            $runningBalance += $transaction->value;
            $transaction->balance = $runningBalance;
        }
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['document', 'subject', 'user']);
        return view('transactions.show', compact('transaction'));
    }
}
