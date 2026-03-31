<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\ChequeBook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChequeService
{
    public function someCheques(ChequeBook $chequeBook, int $perPage = 15): LengthAwarePaginator
    {
        return Cheque::query()->with(['customer', 'transaction', 'chequeBook'])->where('cheque_book_id', $chequeBook->id)->latest()->paginate($perPage);
    }

    public function create(array $data): Cheque
    {
        return Cheque::create($data);
    }

    public function update(Cheque $cheque, array $data): Cheque
    {
        $cheque->update($data);

        return $cheque->fresh(['customer', 'chequeBook', 'transaction']);
    }

    public function delete(Cheque $cheque): void
    {
        $cheque->delete();
    }
}
