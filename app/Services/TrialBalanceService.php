<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function getTrialBalanceData(Request $request)
    {
        $this->validateTrialBalanceFilters($request);

        $currentParent = $request->filled('parent_id') ? Subject::findOrFail($request->integer('parent_id')) : null;

        $includeChildren = $request->boolean('include_children', false);

        $subjectName = $request->input('subject_name');

        $filters = $this->normalizeTrialBalanceFilters($request);

        $subjects = $this->buildTrialBalanceSubjects($currentParent, $includeChildren, $subjectName);

        $subjects = $subjects->map(function (Subject $subject) use ($filters) {
            // These columns describe the balance before the selected range.
            // A document's number does not identify an opening document.
            [$openingDebit, $openingCredit] = $this->aggregateSubjectColumns($subject, $filters, true);
            [$turnoverDebit, $turnoverCredit] = $this->aggregateSubjectColumns($subject, $filters);

            $subject->opening = $openingDebit + $openingCredit;
            $subject->turnover_debit = $turnoverDebit;
            $subject->turnover_credit = $turnoverCredit;

            $total = $openingDebit + $openingCredit + $turnoverDebit + $turnoverCredit;
            $subject->balance = $total;

            return $subject;
        });

        // In the two-level view parent balances already contain their children.
        // Only the current-level rows contribute to the report totals.
        $summarySubjects = $subjects->filter(fn (Subject $subject): bool => (int) ($subject->getAttribute('depth') ?? 0) === 0);

        return [
            'subjects' => $subjects,
            'totals' => [
                'opening_debit' => $summarySubjects->sum(fn (Subject $subject) => $subject->opening < 0 ? abs($subject->opening) : 0),
                'opening_credit' => $summarySubjects->sum(fn (Subject $subject) => $subject->opening > 0 ? $subject->opening : 0),
                'turnover_debit' => $summarySubjects->sum(fn (Subject $subject) => abs($subject->turnover_debit)),
                'turnover_credit' => $summarySubjects->sum('turnover_credit'),
                'balance_debit' => $summarySubjects->sum(fn (Subject $subject) => $subject->balance < 0 ? abs($subject->balance) : 0),
                'balance_credit' => $summarySubjects->sum(fn (Subject $subject) => $subject->balance > 0 ? $subject->balance : 0),
            ],
            'opening_label' => in_array(app()->getLocale(), ['fa', 'fa_IR'], true) ? 'مانده ابتدای بازه' : __('Opening Balance'),
            'subject_name' => $subjectName,
            'currentParent' => $currentParent,
            'include_children' => $includeChildren,
            'start_date' => convertToJalali($filters['start_date'], true),
            'end_date' => convertToJalali($filters['end_date'], true),
            'start_document_number' => $filters['start_document_number'],
            'end_document_number' => $filters['end_document_number'],
        ];
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->getTrialBalanceData($request);
        $filename = 'trial_balance_'.Carbon::now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                __('Account Code'), __('Account Name'),
                $data['opening_label'].' '.__('Debtor'), $data['opening_label'].' '.__('Creditor'),
                __('Sum Debit'), __('Sum Credit'), __('Remaining Debit'), __('Remaining Credit'),
            ]);

            foreach ($data['subjects'] as $subject) {
                fputcsv($handle, [
                    csvNumber($subject->code),
                    $subject->name,
                    csvNumber($subject->opening < 0 ? abs($subject->opening) : 0),
                    csvNumber($subject->opening > 0 ? $subject->opening : 0),
                    csvNumber(abs($subject->turnover_debit)),
                    csvNumber($subject->turnover_credit),
                    csvNumber($subject->balance < 0 ? abs($subject->balance) : 0),
                    csvNumber($subject->balance > 0 ? $subject->balance : 0),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function validateTrialBalanceFilters(Request $request): void
    {
        $fiscalYear = FiscalYear::findOrFail(getActiveFiscalYear());
        [$fiscalStart, $fiscalEnd] = $fiscalYear->range();
        $fiscalStartDate = $fiscalStart->toDateString();
        $fiscalEndDate = $fiscalEnd->toDateString();

        $dateRule = function (string $attribute, mixed $value, $fail): void {
            try {
                jalaliInputToGregorian((string) $value, $attribute);
            } catch (ValidationException) {
                $fail(__('validation.date_format', [
                    'attribute' => str_replace('_', ' ', $attribute),
                    'format' => 'Y/m/d',
                ]));
            }
        };

        Validator::make($request->all(), [
            'start_document_number' => 'nullable|integer|min:1',
            'end_document_number' => 'nullable|integer|min:1',
            'start_date' => ['bail', 'nullable', 'string', $dateRule],
            'end_date' => ['bail', 'nullable', 'string', $dateRule],
        ])->after(function ($validator) use ($request, $fiscalStartDate, $fiscalEndDate) {
            if ($request->filled('start_document_number') && $request->filled('end_document_number')) {
                if ((int) $request->start_document_number > (int) $request->end_document_number) {
                    $validator->errors()->add('start_document_number', __('Start document number cannot be greater than end document number.'));
                }
            }

            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $startDate = $request->filled('start_date')
                ? jalaliInputToGregorian($request->input('start_date'), 'start_date')
                : null;
            $endDate = $request->filled('end_date')
                ? jalaliInputToGregorian($request->input('end_date'), 'end_date')
                : null;

            if ($startDate && ($startDate < $fiscalStartDate || $startDate > $fiscalEndDate)) {
                $validator->errors()->add('start_date', __('The start date must be within the active fiscal year.'));
            }

            if ($endDate && ($endDate < $fiscalStartDate || $endDate > $fiscalEndDate)) {
                $validator->errors()->add('end_date', __('The end date must be within the active fiscal year.'));
            }

            if ($startDate && $endDate && $startDate > $endDate) {
                $validator->errors()->add('start_date', __('Start date cannot be greater than end date.'));
            }
        })->validate();
    }

    private function normalizeTrialBalanceFilters(Request $request): array
    {
        $fiscalYear = FiscalYear::findOrFail(getActiveFiscalYear());
        [$fiscalStart, $fiscalEnd] = $fiscalYear->range();

        return [
            'fiscal_year_id' => $fiscalYear->id,
            'fiscal_start_date' => $fiscalStart->toDateString(),
            'start_date' => $request->filled('start_date') ? jalaliInputToGregorian($request->input('start_date'), 'start_date') : $fiscalStart->toDateString(),
            'end_date' => $request->filled('end_date') ? jalaliInputToGregorian($request->input('end_date'), 'end_date') : $fiscalEnd->toDateString(),
            'start_document_number' => $request->filled('start_document_number') ? (int) $request->input('start_document_number') : 1,
            'end_document_number' => $request->filled('end_document_number') ? (int) $request->input('end_document_number') : null,
        ];
    }

    private function aggregateSubjectColumns(Subject $subject, array $filters, bool $beforeRange = false): array
    {
        $ids = $subject->getAllDescendantIds();

        $query = Transaction::query()
            ->whereIn('transactions.subject_id', $ids)
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.fiscal_year_id', $filters['fiscal_year_id'])
            ->whereBetween('documents.date', [$filters['fiscal_start_date'], $filters['end_date']])
            ->when($filters['end_document_number'], fn ($query, int $number) => $query->where('documents.number', '<=', $number));

        if ($beforeRange) {
            // Partition the selected upper-bound population once: a row is
            // either before a lower boundary or belongs to the turnover range.
            $query->where(function ($query) use ($filters) {
                $query->where('documents.date', '<', $filters['start_date'])
                    ->orWhere('documents.number', '<', $filters['start_document_number']);
            });
        } else {
            $query->where('documents.date', '>=', $filters['start_date'])
                ->where('documents.number', '>=', $filters['start_document_number']);
        }

        $sums = $query->selectRaw('
            SUM(CASE WHEN transactions.value < 0 THEN transactions.value ELSE 0 END) as debit_sum,
            SUM(CASE WHEN transactions.value > 0 THEN transactions.value ELSE 0 END) as credit_sum
        ')->first();

        return [(float) ($sums->debit_sum ?? 0), (float) ($sums->credit_sum ?? 0)];
    }

    private function buildTrialBalanceSubjects(?Subject $currentParent, bool $includeChildren, ?string $subjectName = null): Collection
    {
        $nameFilter = function ($query) use ($subjectName) {
            if ($subjectName) {
                $query->where('name', 'like', "%{$subjectName}%");
            }
        };

        if ($currentParent) {
            $currentParent->load([
                'subjectable',
                'children' => fn ($query) => $query->with('subjectable')->orderBy('code')->where($nameFilter),
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

        $rootsQuery = Subject::whereIsRoot()->with(['subjectable', 'children' => fn ($query) => $query->with('subjectable')->orderBy('code')->where($nameFilter)]);

        if ($subjectName) {
            $rootsQuery->where('name', 'like', "%{$subjectName}%");
        }

        $roots = $rootsQuery->orderBy('code')->get();

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
