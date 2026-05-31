<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentImportExportService
{
    public const ALL_COLUMNS = FreeAmirImportFormat::ALL_COLUMNS;

    public const MANDATORY_COLUMNS = FreeAmirImportFormat::MANDATORY_COLUMNS;

    public function __construct(private readonly DocumentImportFormatRegistry $formats = new DocumentImportFormatRegistry) {}

    public function buildQuery(array $filters): Builder
    {
        $query = Document::orderBy('date')->orderBy('number');

        if (! empty($filters['number'])) {
            $query->where('number', convertToFloat($filters['number']));
        }

        if (! empty($filters['date'])) {
            $query->where('date', convertToGregorian($filters['date']));
        }

        if (! empty($filters['text'])) {
            $text = $filters['text'];
            $query->where(function ($q) use ($text) {
                $q->where('title', 'like', '%'.$text.'%')
                    ->orWhereHas('transactions', fn ($sq) => $sq->where('desc', 'like', '%'.$text.'%'));
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            match ($filters['status']) {
                'approved' => $query->whereNotNull('approved_at'),
                'unapproved' => $query->whereNull('approved_at'),
                default => null,
            };
        }

        if (! empty($filters['start_document_number'])) {
            $query->where('number', '>=', $filters['start_document_number']);
        }

        if (! empty($filters['end_document_number'])) {
            $query->where('number', '<=', $filters['end_document_number']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('date', '>=', jalali_to_gregorian_date($filters['start_date']));
        }

        if (! empty($filters['end_date'])) {
            $query->where('date', '<=', jalali_to_gregorian_date($filters['end_date']));
        }

        if (! empty($filters['subject_id'])) {
            $subject = Subject::find($filters['subject_id']);
            if ($subject) {
                $ids = $subject->getAllDescendantIds();
                $query->whereHas('transactions', fn ($q) => $q->whereIn('subject_id', $ids));
            }
        }

        return $query;
    }

    public function validateExportRequest(Request $request): array
    {
        return $request->validate([
            'number' => 'nullable|numeric',
            'date' => 'nullable|string',
            'text' => 'nullable|string|max:255',
            'status' => 'nullable|in:all,approved,unapproved',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'start_document_number' => 'nullable|numeric',
            'end_document_number' => 'nullable|numeric',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'columns_selected' => 'nullable|boolean',
            'columns' => 'nullable|array',
            'columns.*' => 'string|in:'.implode(',', self::ALL_COLUMNS),
        ]);
    }

    public function validateImportRequest(Request $request): void
    {
        $request->validate([
            'format' => 'required|in:'.implode(',', $this->formats->keys()),
            'file' => 'required|file|mimes:csv,txt|max:51200',
        ], [
            'format.required' => __('Please select an import format.'),
            'format.in' => __('Please select a valid import format.'),
            'file.required' => __('Please select a CSV file to import.'),
            'file.mimes' => __('Only CSV files are accepted.'),
            'file.max' => __('The file may not be larger than 50 MB.'),
        ]);
    }

    public function export(array $filters): StreamedResponse
    {
        $query = self::buildQuery($filters);
        $filename = 'documents_export_'.Carbon::now()->format('Ymd_His').'.csv';
        $columns = $this->resolveExportColumns($filters);

        return self::exportCsv($query, $filename, $columns);
    }

    private function resolveExportColumns(array $filters): array
    {
        $mandatory = self::MANDATORY_COLUMNS;
        $optional = array_values(array_diff(self::ALL_COLUMNS, $mandatory));

        $selectedOptional =
            ! empty($filters['columns_selected'])
            ? array_values(array_intersect($optional, (array) ($filters['columns'] ?? [])))
            : $optional;

        return array_values(array_filter(
            self::ALL_COLUMNS,
            fn ($col) => in_array($col, $mandatory, true) || in_array($col, $selectedOptional, true)
        ));
    }

    private function exportCsv(Builder $query, string $filename, array $columns = self::ALL_COLUMNS): StreamedResponse
    {
        $httpHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, array_map(fn ($col) => __($col), $columns));

            foreach (self::csvSubjectRows($query) as $row) {
                fputcsv($handle, $this->filterRow($row, $columns));
            }

            (clone $query)
                ->with(['transactions.subject'])
                ->orderBy('date')
                ->orderBy('number')
                ->chunk(200, function ($documents) use ($handle, $columns) {
                    foreach ($documents as $document) {
                        foreach ($document->transactions as $transaction) {
                            $subject = $transaction->subject;
                            [$rootOwn, $parentOwn, $ownCode] = FreeAmirImportFormat::parseSubjectCodes($subject->code ?? '');
                            $row = [
                                'record_type' => 'TRANSACTION',
                                'doc_number' => csvNumber(floor($document->number)),
                                'doc_date' => $document->date->format('Y-m-d'),
                                'doc_title' => $document->title ?? '',
                                'doc_type' => $document->document_type,
                                'doc_status' => $document->approved_at ? 'approved' : 'unapproved',
                                'subject_root_code' => $rootOwn,
                                'subject_code' => $ownCode,
                                'subject_name' => $subject->name ?? '',
                                'subject_parent_code' => $parentOwn,
                                'transaction_desc' => $transaction->desc ?? '',
                                'debit' => csvNumber($transaction->debit ?? 0),
                                'credit' => csvNumber($transaction->credit ?? 0),
                            ];
                            fputcsv($handle, $this->filterRow($row, $columns));
                        }
                    }
                });

            fclose($handle);
        }, 200, $httpHeaders);
    }

    private function filterRow(array $row, array $columns): array
    {
        return array_map(fn ($col) => $row[$col] ?? '', $columns);
    }

    /**
     * Collect every subject (including ancestors) touched by the filtered documents, ordered by code so parents always appear before children.
     */
    private function csvSubjectRows(Builder $query): array
    {
        $subjectIds = (clone $query)->join('transactions', 'documents.id', '=', 'transactions.document_id')->distinct()->pluck('transactions.subject_id')->filter();

        if ($subjectIds->isEmpty()) {
            return [];
        }

        $allIds = collect($subjectIds->toArray());
        $subjects = Subject::withoutGlobalScopes()->whereIn('id', $subjectIds)->get();

        foreach ($subjects as $subject) {
            $current = $subject;
            while ($current->parent_id) {
                $allIds->push($current->parent_id);
                $current = $current->parent;
            }
        }

        $allSubjects = Subject::withoutGlobalScopes()->whereIn('id', $allIds->unique())->orderBy('code')->get();

        return $allSubjects->map(function (Subject $subject) {
            [$rootOwn, $parentOwn, $ownCode] = FreeAmirImportFormat::parseSubjectCodes($subject->code);

            return [
                'record_type' => 'SUBJECT',
                'doc_number' => '',
                'doc_date' => '',
                'doc_title' => '',
                'doc_type' => '',
                'doc_status' => '',
                'subject_root_code' => $rootOwn,
                'subject_code' => $ownCode,
                'subject_name' => $subject->name,
                'subject_parent_code' => $parentOwn,
                'transaction_desc' => '',
                'debit' => '',
                'credit' => '',
            ];
        })->values()->all();
    }

    /**
     * Import a CSV file using the explicitly selected format.
     *
     * @return array{subjects_created:int, subjects_skipped:int, documents_created:int, documents_skipped:int, rows_skipped:int, errors:array<int,string>}
     */
    public function importCsv(UploadedFile $file, User $user, string $formatKey): array
    {
        $format = $this->formats->get($formatKey);

        [$headers, $rows, $skipped] = $this->parseCsv($file);

        if (! $format->matches($headers)) {
            throw ValidationException::withMessages([
                'file' => __('The uploaded file does not match the selected ":format" format.', ['format' => $format->label()]),
            ]);
        }

        $result = DB::transaction(fn () => $format->import($rows, $user));

        $result['rows_skipped'] = ($result['rows_skipped'] ?? 0) + $skipped;

        return $result;
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($handle);
        }

        $headers = null;
        $rows = [];
        $skipped = 0;

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => trim($h), $line);

                continue;
            }

            if (count($line) !== count($headers)) {
                $skipped++;

                continue;
            }

            $rows[] = array_combine($headers, $line);
        }

        fclose($handle);

        return [$headers ?? [], $rows, $skipped];
    }
}
