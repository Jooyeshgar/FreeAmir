<?php

namespace App\Http\Controllers;

use App\Models\MonthlyBudget;
use App\Models\Subject;
use App\Services\MonthlyBudgetService;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyBudgetController extends Controller
{
    public function __construct(private readonly MonthlyBudgetService $service, private readonly SubjectService $subjectService) {}

    public function index(Request $request)
    {
        return $this->renderIndex($request, false);
    }

    public function calculateExpenses(Request $request)
    {
        return $this->renderIndex($request, true);
    }

    public function searchSubjects(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);

        $subjects = Subject::query()->where('is_permanent', false)->where('name', 'like', '%'.$validated['q'].'%')->orderBy('code')->limit(25)->get(['id', 'name', 'code']);

        return response()->json($subjects->map(fn (Subject $subject) => [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'parent_id' => null,
            'children' => [],
        ])->values());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('company_id', getActiveCompany())->where('is_permanent', false),
            ],
            'budget_type' => ['required', Rule::in(['income', 'expense'])],
            'forecast_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
        ]);
        $month = (int) $validated['month'];

        $this->service->saveForecast($month, $validated);

        return redirect()->route('budgets.index', ['month' => $month])->with('success', __('Forecast saved successfully.'));
    }

    public function rollover(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
        ]);
        $month = (int) $validated['month'];
        $this->service->rollover($month);

        return redirect()->route('budgets.index', ['month' => $month])->with('success', __('Previous month forecast copied successfully.'));
    }

    public function destroy(MonthlyBudget $monthlyBudget)
    {
        $month = $monthlyBudget->month;
        $monthlyBudget->delete();

        return redirect()->route('budgets.index', ['month' => $month])->with('success', __('Forecast line deleted successfully.'));
    }

    private function renderIndex(Request $request, bool $calculateExpenses)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $fiscalYear = $this->activeFiscalYear();
        $selectedMonth = (int) ($validated['month'] ?? $this->defaultMonth($fiscalYear));
        $subjects = Subject::query()->where('is_permanent', false)->orderBy('code')->get(['id', 'code', 'name', 'parent_id']);
        $subjectTree = $this->subjectService->buildSubjectTreeFromCollection($subjects);
        $selectedSubject = Subject::query()->where('is_permanent', false)->find($request->old('subject_id'));

        return view('monthly-budgets.index', array_merge(
            [
                'fiscalYear' => $fiscalYear,
                'subjects' => $subjectTree,
                'selectedSubject' => $selectedSubject,
            ],
            $this->service->analysis($selectedMonth, $calculateExpenses)
        ));
    }

    private function activeFiscalYear(): int
    {
        return (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
    }

    private function defaultMonth(int $fiscalYear): int
    {
        $currentYear = (int) toEnglish(jdate('Y'));

        return $fiscalYear === $currentYear ? (int) toEnglish(jdate('n')) : 1;
    }
}
