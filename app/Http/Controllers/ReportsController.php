<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reports.ledger', ['only' => ['ledger']]);
        $this->middleware('permission:reports.journal', ['only' => ['journal']]);
        $this->middleware('permission:reports.sub-ledger', ['only' => ['subLedger']]);
        $this->middleware('permission:reports.trial-balance', ['only' => ['trialBalance']]);
        $this->middleware('permission:reports.documents', ['only' => ['documents']]);
        $this->middleware('permission:reports.result', ['only' => ['result']]);
    }

    public function ledger()
    {
        $subjects = Subject::orderBy('code', 'asc')->whereIsRoot()->get();

        return view('reports.ledger', compact('subjects'));
    }

    public function journal()
    {
        $subjects = [];

        return view('reports.journal', compact('subjects'));
    }

    public function subLedger()
    {
        $subjects = Subject::orderBy('code', 'asc')->get();

        return view('reports.subLedger', compact('subjects'));
    }

    public function documents()
    {
        return view('reports.documents');
    }

    private function getTrialBalanceData(Request $request)
    {
        $this->validateTrialBalanceFilters($request);

        $currentParent = $request->filled('parent_id')
            ? Subject::findOrFail($request->integer('parent_id'))
            : null;

        $includeChildren = $request->boolean('include_children', false);
        $columns_number = $this->resolveColumnsNumber($request);
        $filters = $this->normalizeTrialBalanceFilters($request);

        $subjects = $this->buildTrialBalanceSubjects($currentParent, $includeChildren);

        $subjects = $subjects->map(function (Subject $subject) use ($filters) {
            $subject->credit = $this->sumSubjectByDirection($subject, false, $filters);
            $subject->debit = $this->sumSubjectByDirection($subject, true, $filters);
            $subject->balance = $subject->credit + $subject->debit;

            return $subject;
        });

        return [
            'subjects' => $subjects,
            'currentParent' => $currentParent,
            'columns_number' => $columns_number,
            'include_children' => $includeChildren,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'start_document_number' => $request->input('start_document_number'),
            'end_document_number' => $request->input('end_document_number'),
        ];
    }

    public function trialBalance(Request $request)
    {
        return view('reports.trialBalance', $this->getTrialBalanceData($request));
    }

    public function printTrialBalance(Request $request)
    {
        return view('reports.trialBalancePrint', $this->getTrialBalanceData($request));
    }

    private function resolveColumnsNumber(Request $request): int
    {
        $twoColumns = $request->boolean('two_columns') || (int) $request->input('columns_number') === 2;

        return $twoColumns ? 2 : 4;
    }

    private function validateTrialBalanceFilters(Request $request): void
    {
        Validator::make($request->all(), [
            'start_document_number' => 'nullable|numeric',
            'end_document_number' => 'nullable|numeric',
            'start_date' => 'nullable|date_format:Y/m/d',
            'end_date' => 'nullable|date_format:Y/m/d',
        ])->after(function ($validator) use ($request) {
            if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
                if ((int) $request->start_document_number > (int) $request->end_document_number) {
                    $validator->errors()->add('start_document_number', __('Start document number cannot be greater than end document number.'));
                }
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                if (jalali_to_gregorian_date($request->start_date) > jalali_to_gregorian_date($request->end_date)) {
                    $validator->errors()->add('start_date', __('Start date cannot be greater than end date.'));
                }
            }
        })->validate();
    }

    private function normalizeTrialBalanceFilters(Request $request): array
    {
        return [
            'start_date' => $request->filled('start_date') ? jalali_to_gregorian_date($request->input('start_date')) : null,
            'end_date' => $request->filled('end_date') ? jalali_to_gregorian_date($request->input('end_date')) : null,
            'start_document_number' => $request->filled('start_document_number') ? (int) $request->input('start_document_number') : null,
            'end_document_number' => $request->filled('end_document_number') ? (int) $request->input('end_document_number') : null,
        ];
    }

    private function buildTrialBalanceSubjects(?Subject $currentParent, bool $includeChildren): Collection
    {
        if ($currentParent) {
            $currentParent->load([
                'subjectable',
                'children' => fn ($query) => $query->with('subjectable')->orderBy('code'),
            ]);

            if ($includeChildren) {
                $currentParent->setAttribute('depth', 0);
                $children = $currentParent->children->map(function (Subject $child) {
                    $child->setAttribute('depth', 1);

                    return $child;
                });

                return collect([$currentParent])->merge($children);
            }

            return $currentParent->children->map(function (Subject $child) {
                $child->setAttribute('depth', 0);

                return $child;
            });
        }

        $roots = Subject::whereIsRoot()->with(['subjectable', 'children' => fn ($query) => $query->with('subjectable')->orderBy('code')])->orderBy('code')->get();

        if (! $includeChildren) {
            return $roots->map(function (Subject $root) {
                $root->setAttribute('depth', 0);

                return $root;
            });
        }

        return $roots->flatMap(function (Subject $root) {
            $root->setAttribute('depth', 0);
            $children = $root->children->map(function (Subject $child) {
                $child->setAttribute('depth', 1);

                return $child;
            });

            return collect([$root])->merge($children);
        });
    }

    private function sumSubjectByDirection(Subject $subject, bool $debit, array $filters): int
    {
        $ids = $subject->getAllDescendantIds();

        $query = Transaction::query()->whereIn('subject_id', $ids)->join('documents', 'documents.id', '=', 'transactions.document_id');

        if ($filters['start_date']) {
            $query->where('documents.date', '>=', $filters['start_date']);
        }

        if ($filters['end_date']) {
            $query->where('documents.date', '<=', $filters['end_date']);
        }

        if ($filters['start_document_number']) {
            $query->where('documents.number', '>=', $filters['start_document_number']);
        }

        if ($filters['end_document_number']) {
            $query->where('documents.number', '<=', $filters['end_document_number']);
        }

        $directionOperator = $debit ? '<' : '>';

        return (int) $query->where('transactions.value', $directionOperator, 0)->sum('transactions.value');
    }

    public function result(Request $request)
    {
        if ($request->input('action') === 'preview') {
            return redirect()->route('transactions.index', $request->except(['action', 'report_for']));
        }

        $rules = [
            'report_for' => 'required|in:Journal,Ledger,subLedger,Document',
            'start_document_number' => 'nullable|numeric',
            'end_document_number' => 'nullable|numeric',
            'start_date' => 'nullable|date_format:Y/m/d',
            'end_date' => 'nullable|date_format:Y/m/d',
        ];

        if ($request->report_for != 'Journal' && $request->report_for != 'Document') {
            $rules['subject_id'] = 'required';
        }

        Validator::make($request->all(), $rules)->after(function ($validator) use ($request) {
            // Optional consistency checks
            if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
                if ((int) $request->start_document_number > (int) $request->end_document_number) {
                    $validator->errors()->add('start_document_number', __('Start document number cannot be greater than end document number.'));
                }
            }
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $start = jalali_to_gregorian_date($request->start_date);
                $end = jalali_to_gregorian_date($request->end_date);
                if ($start > $end) {
                    $validator->errors()->add('start_date', __('Start date cannot be greater than end date.'));
                }
            }
        })->validate();

        if ($request->report_for == 'Document') {
            $documents = Document::query();
            // Document number filters
            if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
                $documents->whereBetween('number', [$request->start_document_number, $request->end_document_number]);
            } elseif ($request->filled('start_document_number')) {
                $documents->where('number', '>=', $request->start_document_number);
            } elseif ($request->filled('end_document_number')) {
                $documents->where('number', '<=', $request->end_document_number);
            }

            // Date filters (convert Jalali -> Gregorian)
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = jalali_to_gregorian_date($request->start_date);
                $endDate = jalali_to_gregorian_date($request->end_date);
                $documents->whereBetween('date', [$startDate, $endDate]);
            } elseif ($request->filled('start_date')) {
                $startDate = jalali_to_gregorian_date($request->start_date);
                $documents->where('date', '>=', $startDate);
            } elseif ($request->filled('end_date')) {
                $endDate = jalali_to_gregorian_date($request->end_date);
                $documents->where('date', '<=', $endDate);
            }

            if ($request->search) {
                $documents->where('title', 'like', '%'.$request->search.'%');
            }

            $documents->orderBy('date', 'asc')->orderBy('number', 'asc');

            $documents = $documents->with(['transactions.subject', 'creator', 'approver'])->get();

            return view('reports.documentReport', compact('documents'));
        }

        $transactions = Transaction::query();
        $subject = null;
        if ($request->subject_id) {
            if ($request->subject_id) {
                $subject = Subject::findOrFail($request->subject_id);

                if ($request->report_for == 'subLedger' || $request->report_for == 'Ledger') {
                    $subjectIds = $subject->getAllDescendantIds();
                    $transactions = $transactions->whereIn('subject_id', $subjectIds);
                }
            }
        }

        if ($request->search) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%');
            });
        }

        // Dynamic combined filters for transaction's related document
        if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->whereBetween('number', [$request->start_document_number, $request->end_document_number]);
            });
        } elseif ($request->filled('start_document_number')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('number', '>=', $request->start_document_number);
            });
        } elseif ($request->filled('end_document_number')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('number', '<=', $request->end_document_number);
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('date', '>=', jalali_to_gregorian_date($request->start_date))
                    ->where('date', '<=', jalali_to_gregorian_date($request->end_date));
            });
        } elseif ($request->filled('start_date')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('date', '>=', jalali_to_gregorian_date($request->start_date));
            });
        } elseif ($request->filled('end_date')) {
            $transactions = $transactions->whereHas('document', function ($q) use ($request) {
                $q->where('date', '<=', jalali_to_gregorian_date($request->end_date));
            });
        }

        $transactions = $transactions->with('document', 'subject')
            ->orderBy(
                Document::whereColumn('id', 'transactions.document_id')->select('date')
            )
            ->orderBy(
                Document::whereColumn('id', 'transactions.document_id')->select('number')
            )
            ->get();

        if ($request->input('action') === 'export_csv') {
            $filename = $request->report_for.'_report_'.date('YmdHis').'.csv';

            return $this->streamCsvResponse($transactions, $filename);
        }

        $transactionsChunk = $transactions->chunk(env('REPORT_ROW_SIZE', 25));

        if ($request->report_for == 'Journal') {
            return view('reports.journalReport', compact('transactionsChunk', 'subject'));
        }

        return view('reports.ledgerReport', compact('transactionsChunk', 'subject'));
    }

    /**
     * Generates and streams a CSV response for a collection of transactions.
     */
    private function streamCsvResponse(Collection $transactions, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                __('Date'),
                __('Document #'),
                __('Subject Code'),
                __('Subject Name'),
                __('Description'),
                __('Debit'),
                __('Credit'),
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    formatDate($transaction->document->date),
                    formatDocumentNumber($transaction->document->number),
                    formatCode($transaction->subject->code),
                    $transaction->subject->name,
                    $transaction->desc ?? '',
                    $transaction->debit ?? 0,
                    $transaction->credit ?? 0,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
