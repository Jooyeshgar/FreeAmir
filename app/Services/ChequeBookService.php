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
        if (! isset($data['company_id']) || $data['company_id'] == null) {
            $data['company_id'] = getActiveCompany();
        }

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
            foreach ($chequeBook->cheques as $cheque) {
                $cheque->delete();
            }

            $chequeBook->delete();
        });
    }
}
