<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DocumentExportService
{
    public function availableColumns(): array
    {
        return [
            'document_number' => 'document number',
            'document_date' => 'document date',
            'document_title' => 'document title',
            'documentable_id' => 'invoice/ancillary cost number',
            'root_code' => 'root code',
            'ledger_code' => 'ledger code',
            'detailed_code' => 'detailed code',
            'remaining_code' => 'remaining code',
            'subject_name' => 'subject name',
            'transaction_desc' => 'desc',
            'debit' => 'debit',
            'credit' => 'credit',
            'sum_debit' => 'sum debit',
            'sum_credit' => 'sum credit',
            'remaining_debit' => 'remaining debit',
            'remaining_credit' => 'remaining credit',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->availableColumns());
    }

    public function streamFromQuery(array $filters, array $columns, $handle): void
    {
        $availableColumns = $this->availableColumns();
        $columns = array_values(array_intersect($columns, array_keys($availableColumns)));

        if (empty($columns)) {
            $columns = $this->defaultColumns();
        }

        $subjectSums = DB::table('transactions')->join('documents', 'documents.id', '=', 'transactions.document_id');

        $this->applyFilters($subjectSums, $filters);

        $subjectSums->selectRaw('
            transactions.subject_id,
            SUM(CASE WHEN transactions.value < 0 THEN ABS(transactions.value) ELSE 0 END) as sum_debit,
            SUM(CASE WHEN transactions.value > 0 THEN transactions.value ELSE 0 END) as sum_credit
        ')->groupBy('transactions.subject_id');

        $query = DB::table('transactions')
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'transactions.subject_id')
            ->leftJoinSub($subjectSums, 'subject_sums', function ($join) {
                $join->on('subject_sums.subject_id', '=', 'transactions.subject_id');
            });

        $this->applyFilters($query, $filters);

        fputcsv($handle, $this->mapHeaders($columns, $availableColumns));

        $query->select([
            'documents.number as document_number',
            'documents.date',
            'documents.title',
            'documents.documentable_id',
            'transactions.desc as transaction_desc',
            'transactions.value',
            'subjects.code as subject_code',
            'subjects.name as subject_name',
            'subject_sums.sum_debit',
            'subject_sums.sum_credit',
        ]);

        $query->orderBy('documents.number')->orderBy('transactions.id')->lazy()->each(function ($row) use ($handle, $columns) {
            fputcsv($handle, $this->mapRow($row, $columns));
        });
    }

    private function mapHeaders(array $columns, array $availableColumns): array
    {
        return array_map(fn ($column) => $availableColumns[$column], $columns);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['text'])) {
            $searchText = $filters['text'];

            $query->where(function (Builder $q) use ($searchText) {
                $q->where('documents.title', 'like', "%{$searchText}%")
                    ->orWhere('transactions.desc', 'like', "%{$searchText}%");
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('documents.date', '>=', jalali_to_gregorian_date($filters['start_date']));
        }

        if (! empty($filters['end_date'])) {
            $query->where('documents.date', '<=', jalali_to_gregorian_date($filters['end_date']));
        }

        if (! empty($filters['start_document_number'])) {
            $query->where('documents.number', '>=', $filters['start_document_number']);
        }

        if (! empty($filters['end_document_number'])) {
            $query->where('documents.number', '<=', $filters['end_document_number']);
        }
    }

    private function mapRow(object $row, array $columns): array
    {
        [$rootCode, $ledgerCode, $detailedCode, $remainingCode] = $this->splitSubjectCode($row->subject_code ?? null);

        $value = (float) ($row->value ?? 0);

        $debit = $value < 0 ? abs($value) : 0;
        $credit = $value > 0 ? $value : 0;

        $sumDebit = (float) ($row->sum_debit ?? 0);
        $sumCredit = (float) ($row->sum_credit ?? 0);

        $balance = $sumCredit - $sumDebit;

        $remainingDebit = $balance < 0 ? abs($balance) : 0;
        $remainingCredit = $balance > 0 ? $balance : 0;

        $mapped = [
            'document_number' => $row->document_number,
            'document_date' => convertToJalali($row->date),
            'document_title' => $row->title,
            'documentable_id' => $row->documentable_id,
            'root_code' => $rootCode,
            'ledger_code' => $ledgerCode,
            'detailed_code' => $detailedCode,
            'remaining_code' => $remainingCode,
            'subject_name' => $row->subject_name ?? '',
            'transaction_desc' => $row->transaction_desc ?? '',
            'debit' => $debit,
            'credit' => $credit,
            'sum_debit' => $sumDebit,
            'sum_credit' => $sumCredit,
            'remaining_debit' => $remainingDebit,
            'remaining_credit' => $remainingCredit,
        ];

        return array_map(fn ($column) => $mapped[$column] ?? '', $columns);
    }

    private function splitSubjectCode(?string $code): array
    {
        $code = trim((string) $code);

        if ($code === '') {
            return ['', '', '', ''];
        }

        $parts = str_split($code, 3);

        $rootCode = $parts[0] ?? '';
        $ledgerCode = $parts[1] ?? '';
        $detailedCode = $parts[2] ?? '';
        $remainingCode = count($parts) > 3 ? implode('', array_slice($parts, 3)) : '';

        return [$rootCode, $ledgerCode, $detailedCode, $remainingCode];
    }
}
