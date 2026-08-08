<?php

namespace App\Http\Controllers;

use App\Models\MonthlyBudget;
use App\Models\Subject;
use App\Services\MonthlyBudgetService;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $subjects = $this->service->selectableSubjectsForMonth($selectedMonth, $subjectRestrictions);
        $subjectTree = $this->subjectService->buildSubjectTreeFromCollection($subjects);
        $oldSubjectId = (int) $request->old('subject_id');
        $selectedSubject = $oldSubjectId && ! in_array($oldSubjectId, $subjectRestrictions, true) ? Subject::query()->where('is_permanent', false)->find($oldSubjectId) : null;
        $analysis = $this->service->analysis($selectedMonth);
        $currency = config('amir.currency') ?? __('Rial');
        $incomeLinesCount = $analysis['budgetLines']->where('type', 'income')->count();
        $expenseLinesCount = $analysis['budgetLines']->where('type', 'expense')->count();
        $allBudgetLinesByForecast = $analysis['budgetLines']->values();
        $displayBudgetLines = $allBudgetLinesByForecast->take(5);
        $incomeAchievement = $analysis['actualsCalculated'] && $analysis['forecastIncome'] > 0 ? max(0, ($analysis['actualIncome'] / $analysis['forecastIncome']) * 100) : 0;
        $expenseUtilization = $analysis['actualsCalculated'] && $analysis['forecastExpense'] > 0 ? max(0, ($analysis['actualExpense'] / $analysis['forecastExpense']) * 100) : 0;
        $comparisonDatasets = [[
            'label' => __('Forecast'),
            'data' => [__('Income') => $analysis['forecastIncome'], __('Expense') => $analysis['forecastExpense']],
            'borderRadius' => 6,
        ]];

        if ($analysis['actualsCalculated']) {
            $comparisonDatasets[] = [
                'label' => __('Actual'),
                'data' => [__('Income') => $analysis['actualIncome'], __('Expense') => $analysis['actualExpense']],
                'borderRadius' => 6,
            ];
        }

        $incomeItemDatasets = $this->itemComparisonDatasets(
            $analysis['budgetLines']->where('type', 'income')->where('includedInSummary', true),
            $analysis['actualsCalculated'],
            '#2563eb',
            '#16a34a'
        );
        $expenseItemDatasets = $this->itemComparisonDatasets(
            $analysis['budgetLines']->where('type', 'expense')->where('includedInSummary', true),
            $analysis['actualsCalculated'],
            '#f59e0b',
            '#dc2626'
        );

        return view('monthly-budgets.index', array_merge(
            [
                'fiscalYear' => $fiscalYear,
                'subjects' => $subjectTree,
                'selectedSubject' => $selectedSubject,
                'currency' => $currency,
                'incomeLinesCount' => $incomeLinesCount,
                'expenseLinesCount' => $expenseLinesCount,
                'allBudgetLinesByForecast' => $allBudgetLinesByForecast,
                'displayBudgetLines' => $displayBudgetLines,
                'hasMoreBudgetLines' => $analysis['budgetLines']->count() > $displayBudgetLines->count(),
                'incomeAchievement' => $incomeAchievement,
                'incomeProgress' => min(100, $incomeAchievement),
                'expenseUtilization' => $expenseUtilization,
                'expenseProgress' => min(100, $expenseUtilization),
                'comparisonDatasets' => $comparisonDatasets,
                'incomeItemDatasets' => $incomeItemDatasets,
                'expenseItemDatasets' => $expenseItemDatasets,
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
                Rule::exists('subjects', 'id')->where('company_id', getActiveCompany())->where('is_permanent', false),
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

    private function itemComparisonDatasets(Collection $lines, bool $includeActuals, string $forecastColor, string $actualColor): array
    {
        $topLines = $lines->sortByDesc(fn (array $line) => max((float) $line['forecast'], (float) ($line['actual'] ?? 0)))->take(5);
        $forecast = $topLines->mapWithKeys(fn (array $line) => [
            formatCode($line['subject']->code).' '.$line['subject']->name => $line['forecast'],
        ])->all();
        $datasets = [[
            'label' => __('Forecast'),
            'data' => $forecast,
            'backgroundColor' => $forecastColor.'cc',
            'borderColor' => $forecastColor,
            'borderRadius' => 5,
        ]];

        if ($includeActuals) {
            $datasets[] = [
                'label' => __('Actual'),
                'data' => $topLines->mapWithKeys(fn (array $line) => [
                    formatCode($line['subject']->code).' '.$line['subject']->name => $line['actual'],
                ])->all(),
                'backgroundColor' => $actualColor.'cc',
                'borderColor' => $actualColor,
                'borderRadius' => 5,
            ];
        }

        return $datasets;
    }
}
