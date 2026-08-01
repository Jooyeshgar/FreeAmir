<?php

namespace App\Services;

use App\Models\Cheque;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ChequeWeightedAverageMaturityService
{
    /**
     * @return array{total_amount: float, weighted_days: float, average_due_date: Carbon, financial_cost: float, base_date: Carbon}
     */
    public function calculate(Collection $cheques, ?string $baseDate = null, float $annualRate = 0): array
    {
        if ($cheques->isEmpty()) {
            throw ValidationException::withMessages(['cheque_ids' => __('cheques validation select_cheques')]);
        }

        $base = $baseDate ? Carbon::parse($baseDate)->startOfDay() : $cheques->min('write_date')->copy()->startOfDay();
        $total = (float) $cheques->sum(fn (Cheque $cheque) => (float) $cheque->amount);
        if ($total <= 0) {
            throw ValidationException::withMessages(['cheque_ids' => __('cheques validation positive_batch')]);
        }

        $weightedDayAmount = 0.0;
        foreach ($cheques as $cheque) {
            $days = $base->diffInDays($cheque->due_date->copy()->startOfDay(), false);
            $weightedDayAmount += (float) $cheque->amount * $days;
        }

        $weightedDays = $weightedDayAmount / $total;

        return [
            'total_amount' => $total,
            'weighted_days' => $weightedDays,
            'average_due_date' => $base->copy()->addDays((int) round($weightedDays)),
            'financial_cost' => $weightedDayAmount * $annualRate / 36500,
            'base_date' => $base,
        ];
    }
}
