<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct() {}

    public function index(Request $request)
    {
        $query = Transaction::with(['document', 'subject', 'user'])
            ->whereHas('document')
            ->join('documents', 'transactions.document_id', '=', 'documents.id')
            ->orderBy('documents.date', 'desc')
            ->orderBy('documents.number', 'desc')
            ->orderBy('transactions.id', 'desc')
            ->select('transactions.*');

        if ($request->filled('subject_id')) {
            $subject = Subject::findOrFail($request->integer('subject_id'));
            $query->whereIn('subject_id', $subject->getAllDescendantIds());
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $startDate = convertToGregorian($request->input('start_date'));
            $query->whereHas('document', fn ($q) => $q->whereDate('date', '>=', $startDate)
            );
        }

        if ($request->filled('end_date')) {
            $endDate = convertToGregorian($request->input('end_date'));
            $query->whereHas('document', fn ($q) => $q->whereDate('date', '<=', $endDate)
            );
        }

        if ($request->filled('start_document_number')) {
            $startDocNum = convertToFloat($request->input('start_document_number'));
            $query->whereHas('document', fn ($q) => $q->where('number', '>=', $startDocNum)
            );
        }

        if ($request->filled('end_document_number')) {
            $endDocNum = convertToFloat($request->input('end_document_number'));
            $query->whereHas('document', fn ($q) => $q->where('number', '<=', $endDocNum)
            );
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('transactions.desc', 'like', "%{$search}%")
                    ->orWhereHas('subject', fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                    )
                    ->orWhereHas('document', fn ($qd) => $qd->where('title', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%")
                    );
            });
        }

        $openingBalance = $this->calculateOpeningBalance($request);

        $clonedQuery = clone $query;

        $transactions = $query->paginate(20)->appends($request->query());

        $balanceBeforePage = $this->calculateBalanceBeforePage(
            $clonedQuery,
            $transactions,
            $openingBalance
        );

        $this->addRunningBalance($transactions, $balanceBeforePage);

        $subjects = Subject::whereIsRoot()
            ->with('children')
            ->orderBy('code', 'asc')
            ->get();

        $currentSubject = $request->filled('subject_id')
            ? Subject::find($request->integer('subject_id'))
            : null;

        return view('transactions.index', compact(
            'transactions',
            'subjects',
            'currentSubject',
            'openingBalance'
        ));
    }

    /**
     * Opening balance for DESC order
     */
    private function calculateOpeningBalance(Request $request): float
    {
        $query = Transaction::query()
            ->whereHas('document')
            ->join('documents', 'transactions.document_id', '=', 'documents.id');

        if ($request->filled('subject_id')) {
            $subject = Subject::findOrFail($request->integer('subject_id'));
            $query->whereIn('subject_id', $subject->getAllDescendantIds());
        }

        $hasFilter = false;

        if ($request->filled('end_date')) {
            $endDate = convertToGregorian($request->input('end_date'));
            $query->whereHas('document', fn ($q) => $q->whereDate('date', '>', $endDate)
            );
            $hasFilter = true;
        }

        if ($request->filled('end_document_number')) {
            $endDocNum = convertToFloat($request->input('end_document_number'));
            $query->whereHas('document', fn ($q) => $q->where('number', '>', $endDocNum)
            );
            $hasFilter = true;
        }

        return $hasFilter
            ? (float) $query->sum('transactions.value')
            : 0;
    }

    /**
     * Balance before current page (DESC)
     */
    private function calculateBalanceBeforePage($query, $transactions, float $openingBalance): float
    {
        if ($transactions->isEmpty()) {
            return $openingBalance;
        }

        $currentPage = $transactions->currentPage();
        $perPage = $transactions->perPage();
        $total = $transactions->total();

        $itemsAfterCurrentPage = $total - ($currentPage * $perPage);

        if ($itemsAfterCurrentPage <= 0) {
            return $openingBalance;
        }

        $sumAfterPage = $query->offset($currentPage * $perPage)
            ->limit($itemsAfterCurrentPage)
            ->pluck('transactions.value')->sum();

        return $openingBalance + (float) $sumAfterPage;
    }

    /**
     * Running balance for DESC order
     */
    private function addRunningBalance($transactions, float $openingBalance): void
    {
        $runningBalance = $openingBalance;

        foreach ($transactions->reverse() as $transaction) {
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
