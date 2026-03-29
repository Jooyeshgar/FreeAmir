<?php

namespace App\Services;

use App\Models\ChequeBook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChequeBookService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ChequeBook::query()->with(['bankAccount', 'cheques'])->latest()->paginate($perPage);
    }

    public function create(array $data): ChequeBook
    {
        return ChequeBook::create($data);
    }

    public function update(ChequeBook $chequeBook, array $data): ChequeBook
    {
        $chequeBook->update($data);

        return $chequeBook->fresh(['bankAccount', 'cheques']);
    }

    public function delete(ChequeBook $chequeBook): void
    {
        DB::transaction(function () use ($chequeBook) {
            $chequeBook->load('cheques.histories');

            foreach ($chequeBook->cheques as $cheque) {
                $cheque->histories()->delete();
                $cheque->delete();
            }

            $chequeBook->delete();
        });
    }
}
