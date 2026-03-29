<?php

namespace App\Services;

use App\Models\ChequeHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ChequeHistoryService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ChequeHistory::query()->with(['cheque', 'creator'])->latest()->paginate($perPage);
    }

    public function create(array $data): ChequeHistory
    {
        $data['created_by'] = Auth::id();

        return ChequeHistory::create($data);
    }

    public function update(ChequeHistory $chequeHistory, array $data): ChequeHistory
    {
        $chequeHistory->update($data);

        return $chequeHistory->fresh(['cheque', 'creator']);
    }

    public function delete(ChequeHistory $chequeHistory): void
    {
        $chequeHistory->delete();
    }
}
