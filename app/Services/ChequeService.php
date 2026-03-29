<?php

namespace App\Services;

use App\Models\Cheque;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChequeService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Cheque::query()->with(['customer', 'chequeBook', 'transaction'])->latest()->paginate($perPage);
    }

    public function create(array $data): Cheque
    {
        return Cheque::create($data);
    }

    public function update(Cheque $cheque, array $data): Cheque
    {
        $cheque->update($data);

        return $cheque->fresh(['customer', 'chequeBook', 'transaction', 'histories']);
    }

    public function delete(Cheque $cheque): void
    {
        DB::transaction(function () use ($cheque) {
            $cheque->histories()->delete();
            $cheque->delete();
        });
    }
}
