<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class TrialBalanceService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function getTrialBalanceData(Request $request)
    {
        $this->validateTrialBalanceFilters($request);

        $currentParent = $request->filled('parent_id') ? Subject::findOrFail($request->integer('parent_id')) : null;

        $includeChildren = $request->boolean('include_children', false);

        $filters = $this->normalizeTrialBalanceFilters($request);

        $subjects = $this->buildTrialBalanceSubjects($currentParent, $includeChildren);

        $subjects = $subjects->map(function (Subject $subject) use ($filters) {
            // Opening columns: only documents 1 and 2
            [$openingDebit, $openingCredit] = $this->aggregateSubjectColumns($subject, [], [1, 2]);

            // Turnover columns: respect filters, by default starting from document 3 and excluding 1 and 2
            [$turnoverDebit, $turnoverCredit] = $this->aggregateSubjectColumns($subject, $filters);

            $subject->opening_debit = $openingDebit;
            $subject->opening_credit = $openingCredit;
            $subject->turnover_debit = $turnoverDebit;
            $subject->turnover_credit = $turnoverCredit;

            $total = $openingDebit + $openingCredit + $turnoverDebit + $turnoverCredit;
            $subject->balance = $total;

            return $subject;
        });

        return [
            'subjects' => $subjects,
            'currentParent' => $currentParent,
            'include_children' => $includeChildren,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'start_document_number' => $request->input('start_document_number', 3),
            'end_document_number' => $request->input('end_document_number'),
        ];
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
            'start_document_number' => $request->filled('start_document_number') ? (int) $request->input('start_document_number') : 3,
            'end_document_number' => $request->filled('end_document_number') ? (int) $request->input('end_document_number') : null,
        ];
    }

    private function aggregateSubjectColumns(Subject $subject, array $filters, ?array $documentNumbers = null): array
    {
        $ids = $subject->getAllDescendantIds();

        $query = \App\Models\Transaction::query()->whereIn('transactions.subject_id', $ids)->join('documents', 'documents.id', '=', 'transactions.document_id');

        if ($documentNumbers !== null) {
            $query->whereIn('documents.number', $documentNumbers);
        } else {
            $startDocument = $filters['start_document_number'] ?? 3;

            $query->where('documents.number', '>=', $startDocument);

            if ($filters['end_document_number']) {
                $query->where('documents.number', '<=', $filters['end_document_number']);
            }

            if ($filters['start_date']) {
                $query->where('documents.date', '>=', $filters['start_date']);
            }

            if ($filters['end_date']) {
                $query->where('documents.date', '<=', $filters['end_date']);
            }
        }

        $sums = $query->selectRaw('
            SUM(CASE WHEN transactions.value < 0 THEN transactions.value ELSE 0 END) as debit_sum,
            SUM(CASE WHEN transactions.value > 0 THEN transactions.value ELSE 0 END) as credit_sum
        ')->first();

        return [$sums->debit_sum ?? 0, $sums->credit_sum ?? 0];
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
}
