<?php

namespace App\Http\Controllers;

use App\Models\MonthlyBudget;
use App\Models\Subject;
use App\Services\MonthlyBudgetService;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonthlyBudgetController extends Controller
{
    public function __construct(private readonly MonthlyBudgetService $service, private readonly SubjectService $subjectService) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $fiscalYear = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
        $currentYear = (int) toEnglish(jdate('Y'));
        $selectedMonth = (int) ($validated['month'] ?? ($fiscalYear === $currentYear ? toEnglish(jdate('n')) : 1));
        $subjectRestrictions = $this->service->subjectSelectionRestrictions($selectedMonth);
        $subjects = $this->subjectService->buildSubjectTreeFromCollection($this->service->selectableSubjects($subjectRestrictions));
        $oldSubjectId = (int) $request->old('subject_id');
        $selectedSubject = $oldSubjectId && ! in_array($oldSubjectId, $subjectRestrictions, true) ? Subject::query()->where('is_permanent', false)->find($oldSubjectId) : null;
        $profitability = $this->service->profitabilityAnalysis($selectedMonth);
        $analysis = $profitability['isCurrentMonth']
            ? $this->service->analysisThroughDay($selectedMonth, $profitability['elapsedDay'])
            : $this->service->analysis($selectedMonth);
        $previousAccounting = $selectedMonth > 1
            ? ($profitability['isCurrentMonth']
                ? $this->service->analysisThroughDay($selectedMonth - 1, $profitability['elapsedDay'])
                : $this->service->analysis($selectedMonth - 1))
            : null;
        $categoryLines = $this->service->categoryActuals($analysis);
        $previousLines = $previousAccounting === null ? collect() : $this->service->categoryActuals($previousAccounting)->keyBy(fn (array $line) => (int) $line['subject']->id);
        $categoryDrivers = [];
        foreach (['income', 'expense'] as $type) {
            $total = abs((float) ($type === 'income' ? ($analysis['actualIncome'] ?? 0) : ($analysis['actualExpense'] ?? 0)));
            $categoryDrivers[$type] = $categoryLines
                ->filter(fn (array $line) => $line['type'] === $type && $line['includedInSummary'] && ! is_null($line['actual']))
                ->sortByDesc(fn (array $line) => abs((float) $line['actual']))
                ->take(5)
                ->map(function (array $line) use ($previousLines, $total) {
                    $priorLine = $previousLines->get((int) $line['subject']->id);
                    $prior = is_null($priorLine['actual'] ?? null) ? null : abs((float) $priorLine['actual']);
                    $amount = abs((float) $line['actual']);

                    return [
                        'subject' => $line['subject'],
                        'amount' => $amount,
                        'share' => $total > 0 ? ($amount / $total) * 100 : null,
                        'previous' => $prior,
                        'change' => is_null($prior) ? null : $amount - $prior,
                        'percent' => is_null($prior) || $prior <= 0 ? null : (($amount - $prior) / $prior) * 100,
                    ];
                })->values();
        }
        $profitability['accountingNet'] = is_null($analysis['actualIncome']) || is_null($analysis['actualExpense'])
            ? null
            : (float) $analysis['actualIncome'] + (float) $analysis['actualExpense'];
        $profitability['accountingMargin'] = ! is_null($profitability['accountingNet']) && (float) $analysis['actualIncome'] != 0
            ? $profitability['accountingNet'] / (float) $analysis['actualIncome'] * 100
            : null;
        $profitability['accountingComparisons'] = collect(['income' => 'actualIncome', 'expense' => 'actualExpense'])
            ->mapWithKeys(function (string $key, string $metric) use ($analysis, $previousAccounting) {
                $previous = $previousAccounting[$key] ?? null;
                $current = $analysis[$key];

                return [$metric => [
                    'previous' => is_null($previous) ? null : abs((float) $previous),
                    'change' => is_null($previous) || is_null($current) ? null : abs((float) $current) - abs((float) $previous),
                    'percent' => is_null($previous) || is_null($current) || abs((float) $previous) <= 0 ? null : ((abs((float) $current) - abs((float) $previous)) / abs((float) $previous)) * 100,
                ]];
            })->all();
        $previousNet = is_null($previousAccounting['actualIncome'] ?? null) || is_null($previousAccounting['actualExpense'] ?? null)
            ? null
            : (float) $previousAccounting['actualIncome'] + (float) $previousAccounting['actualExpense'];
        $previousAccountingMargin = ! is_null($previousNet) && (float) $previousAccounting['actualIncome'] != 0
            ? $previousNet / (float) $previousAccounting['actualIncome'] * 100
            : null;
        $profitability['accountingComparisons']['accountingMargin'] = [
            'previous' => $previousAccountingMargin,
            'change' => is_null($previousAccountingMargin) || is_null($profitability['accountingMargin']) ? null : $profitability['accountingMargin'] - $previousAccountingMargin,
            'percent' => null,
        ];
        $profitability['accountingComparisons']['accountingNet'] = [
            'previous' => $previousNet,
            'change' => is_null($previousNet) || is_null($profitability['accountingNet']) ? null : $profitability['accountingNet'] - $previousNet,
            'percent' => is_null($previousNet) || is_null($profitability['accountingNet']) || $previousNet <= 0 ? null : (($profitability['accountingNet'] - $previousNet) / $previousNet) * 100,
        ];
        $currency = config('amir.currency') ?? __('Rial');
        $incomeAchievement = $analysis['actualsCalculated'] && $analysis['forecastIncome'] > 0 ? max(0, ($analysis['actualIncome'] / $analysis['forecastIncome']) * 100) : 0;
        $expenseUtilization = $analysis['actualsCalculated'] && $analysis['forecastExpense'] < 0 ? max(0, ($analysis['actualExpense'] / $analysis['forecastExpense']) * 100) : 0;
        $comparisonDatasets = [[
            'label' => __('Forecast'),
            'data' => [__('Income') => abs($analysis['forecastIncome']), __('Expense') => abs($analysis['forecastExpense'])],
            'borderRadius' => 6,
        ]];

        if ($analysis['actualsCalculated']) {
            $comparisonDatasets[] = [
                'label' => __('Actual'),
                'data' => [__('Income') => abs($analysis['actualIncome']), __('Expense') => abs($analysis['actualExpense'])],
                'borderRadius' => 6,
            ];
        }

        return view('monthly-budgets.index', array_merge(
            [
                'subjects' => $subjects,
                'selectedSubject' => $selectedSubject,
                'currency' => $currency,
                'incomeAchievement' => $incomeAchievement,
                'expenseUtilization' => $expenseUtilization,
                'comparisonDatasets' => $comparisonDatasets,
                'profitability' => $profitability,
                'categoryDrivers' => $categoryDrivers,
            ],
            $analysis
        ));
    }

    public function searchSubjects(Request $request, string $month, ?string $scope = null)
    {
        $request->merge(['month' => $month, 'scope' => $scope]);
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'scope' => ['nullable', Rule::in(['all'])],
        ]);
        $unavailableSubjectIds = ($validated['scope'] ?? null) === 'all' ? [] : $this->service->subjectSelectionRestrictions((int) $validated['month']);

        $subjects = Subject::query()->whereIn('id', $this->service->forecastableSubjectIds())->whereNotIn('id', $unavailableSubjectIds)
            ->where('name', 'like', '%'.$validated['q'].'%')->orderBy('code')->limit(25)->get(['id', 'name', 'code', 'parent_id']);

        return response()->json($subjects->map(fn (Subject $subject) => [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'parent_id' => $subject->parent_id,
            'children' => [],
        ])->values());
    }

    public function store(Request $request)
    {
        $isBulkForecast = $request->input('source') === 'cost-income';
        $validated = $request->validate(array_merge([
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('fiscal_year_id', getActiveFiscalYear())->where('is_permanent', 0),
            ],
            'forecast_amount' => ['required', 'numeric', 'regex:/^-?\d+(\.\d{1,2})?$/', 'not_in:0', 'between:-9999999999999999.99,9999999999999999.99'],
            'source' => ['nullable', Rule::in(['cost-income'])],
        ], $isBulkForecast ? [
            'months' => ['required', 'array', 'min:1'],
            'months.*' => ['required', 'integer', 'distinct', 'between:1,12'],
        ] : [
            'month' => ['required', 'integer', 'between:1,12'],
        ]));
        $subject = Subject::query()->findOrFail((int) $validated['subject_id']);
        $forecast = array_merge($validated, $this->service->normalizeSignedForecast($subject, $validated['forecast_amount']));

        if ($isBulkForecast) {
            $this->service->saveForecastForMonths($validated['months'], $forecast);

            return redirect()->route('reports.cost-income')->with('success', __('Forecast saved successfully for the selected months.'));
        }

        $month = (int) $validated['month'];

        if (! $this->service->subjectIsAvailableForForecast($month, (int) $validated['subject_id'])) {
            throw ValidationException::withMessages(['subject_id' => __('The selected subject already has a forecast for this month.')]);
        }

        $this->service->saveForecast($month, $forecast);

        return redirect()->route('budgets.index', ['month' => $month])->with('success', __('Forecast saved successfully.'));
    }

    public function rollover(Request $request)
    {
        $month = (int) $request->validate(['month' => ['required', 'integer', 'between:1,12']])['month'];
        $this->service->rollover($month);

        return redirect()->route('budgets.index', ['month' => $month])->with('success', __('Previous month forecast copied successfully.'));
    }

    public function destroy(MonthlyBudget $monthlyBudget)
    {
        $month = $monthlyBudget->month;
        $monthlyBudget->delete();

        return redirect()->route('budgets.index', ['month' => $month])->with('success', __('Forecast deleted successfully.'));
    }
}
