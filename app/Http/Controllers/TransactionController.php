<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

        $currentSubject = $request->filled('subject_id')
            ? Subject::find($request->integer('subject_id'))
            : null;

        $subjects = $this->buildSubjectOptionsForSelectBox($currentSubject);

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

    /**
     * Build a subject tree suitable for the subject-select component.
     */
    private function buildSubjectTree(Collection $subjects): array
    {
        $rootKey = 'root';
        $grouped = $subjects->groupBy(function ($subject) use ($rootKey) {
            return empty($subject->parent_id) ? $rootKey : (string) $subject->parent_id;
        });

        $buildTree = function (string $parentKey) use (&$buildTree, $grouped): array {
            $children = $grouped->get($parentKey, collect());

            return $children->map(function ($subject) use (&$buildTree) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'parent_id' => $subject->parent_id,
                    'children' => $buildTree((string) $subject->id),
                ];
            })->values()->all();
        };

        return $buildTree($rootKey);
    }

    private function buildSubjectOptionsForSelectBox(?Subject $currentSubject): array
    {
        $roots = Subject::whereIsRoot()->orderBy('code')->get(['id', 'name', 'code', 'parent_id']);

        if ($roots->isEmpty()) {
            return [];
        }

        $selectedRootId = $currentSubject?->getRoot()?->id;
        $rootSelection = $roots->take(5);

        if ($selectedRootId && ! $rootSelection->contains('id', $selectedRootId)) {
            $selectedRoot = $roots->firstWhere('id', $selectedRootId);

            if ($selectedRoot) {
                $rootSelection = $rootSelection->take(4)->push($selectedRoot);
            }
        }

        $rootCodes = $rootSelection->pluck('code')->unique()->values();

        if ($rootCodes->isEmpty()) {
            return [];
        }

        $subjects = Subject::query()->select(['id', 'name', 'code', 'parent_id'])
            ->where(function ($query) use ($rootCodes) {
                foreach ($rootCodes as $code) {
                    $query->orWhere('code', 'like', $code.'%');
                }
            })->orderBy('code')->get();

        return $this->buildSubjectTree($subjects);
    }
}
