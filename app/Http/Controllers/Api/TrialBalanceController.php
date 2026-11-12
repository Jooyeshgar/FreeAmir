<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrialBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrialBalanceController extends Controller
{
    public function __construct(private readonly TrialBalanceService $trialBalanceService) {}

    public function __invoke(Request $request, int $fiscalYear): JsonResponse
    {
        $data = $this->trialBalanceService->getTrialBalanceData($request);

        $mapped = $data['subjects']->map(function ($subject) {
            $opening = (float) ($subject->opening ?? 0);
            $turnoverDebit = (float) ($subject->turnover_debit ?? 0);
            $turnoverCredit = (float) ($subject->turnover_credit ?? 0);
            $balance = (float) ($subject->balance ?? 0);

            return [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
                'parent_id' => $subject->parent_id,
                'depth' => (int) ($subject->depth ?? 0),
                'opening' => $opening,
                'turnover_debit' => $turnoverDebit,
                'turnover_credit' => $turnoverCredit,
                'balance' => $balance,
                'opening_debit' => $opening < 0 ? abs($opening) : 0.0,
                'opening_credit' => $opening > 0 ? $opening : 0.0,
                'remaining_debit' => $balance < 0 ? abs($balance) : 0.0,
                'remaining_credit' => $balance > 0 ? $balance : 0.0,
            ];
        });

        if ($request->boolean('only_non_zero')) {
            $mapped = $mapped->filter(fn (array $row) => $row['opening'] != 0.0
                || $row['turnover_debit'] != 0.0 || $row['turnover_credit'] != 0.0 || $row['balance'] != 0.0)->values();
        } else {
            $mapped = $mapped->values();
        }

        $total = $mapped->count();
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $page = min(max((int) $request->input('page', 1), 1), max((int) ceil($total / $perPage), 1));
        $offset = ($page - 1) * $perPage;
        $pageItems = $mapped->slice($offset, $perPage)->values();
        $hasNonZero = $mapped->contains(fn (array $row) => $row['balance'] != 0.0
            || $row['turnover_debit'] != 0.0 || $row['turnover_credit'] != 0.0 || $row['opening'] != 0.0);

        return response()->json([
            'data' => [
                'subjects' => $pageItems,
                'filters' => [
                    'subject_name' => $data['subject_name'],
                    'parent_id' => $data['currentParent']?->id,
                    'include_children' => (bool) $data['include_children'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'start_document_number' => $data['start_document_number'],
                    'end_document_number' => $data['end_document_number'],
                    'only_non_zero' => $request->boolean('only_non_zero'),
                ],
                'conventions' => [
                    'debit_sign' => 'negative',
                    'credit_sign' => 'positive',
                    'balance_formula' => 'opening + turnover_debit + turnover_credit',
                ],
                'meta' => [
                    'fiscal_year_id' => getActiveFiscalYear(),
                    'subject_count' => $total,
                    'has_non_zero_balance' => $hasNonZero,
                    'message' => $hasNonZero ? null : __('No movements in selected period (or all balances are zero).'),
                ],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max((int) ceil($total / $perPage), 1),
                    'from' => $total === 0 ? null : $offset + 1,
                    'to' => $total === 0 ? null : min($offset + $perPage, $total),
                ],
            ],
        ]);
    }
}
