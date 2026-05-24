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
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentImportExportService
{
    private array $subjectCache = [];

    private array $result = [];

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
        ]);
    }

    public function validateImportRequest(Request $request): void
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:51200',
        ], [
            'file.required' => __('Please select a CSV file to import.'),
            'file.mimes' => __('Only CSV files are accepted.'),
            'file.max' => __('The file may not be larger than 50 MB.'),
        ]);
    }

    public function export(array $filters): StreamedResponse
    {
        $query = self::buildQuery($filters);
        $filename = 'documents_export_'.Carbon::now()->format('Ymd_His').'.csv';

        return self::exportCsv($query, $filename);
    }

    private function exportCsv(Builder $query, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'record_type', 'doc_number', 'doc_date', 'doc_title', 'doc_type', 'doc_status',
                'subject_code', 'subject_name', 'subject_parent_code', 'transaction_desc', 'debit', 'credit',
            ]);

            foreach (self::csvSubjectRows($query) as $row) {
                fputcsv($handle, $row);
            }

            (clone $query)
                ->with(['transactions.subject.parent'])
                ->orderBy('date')
                ->orderBy('number')
                ->chunk(200, function ($documents) use ($handle) {
                    foreach ($documents as $document) {
                        foreach ($document->transactions as $transaction) {
                            $subject = $transaction->subject;
                            fputcsv($handle, [
                                'TRANSACTION',
                                (string) floor($document->number),
                                $document->date->format('Y-m-d'),
                                $document->title ?? '',
                                $document->document_type,
                                $document->approved_at ? 'approved' : 'unapproved',
                                $subject->code ?? '',
                                $subject->name ?? '',
                                $subject->parent->code ?? '',
                                $transaction->desc ?? '',
                                $transaction->debit ?? 0,
                                $transaction->credit ?? 0,
                            ]);
                        }
                    }
                });

            fclose($handle);
        }, 200, $headers);
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

        $allSubjects = Subject::withoutGlobalScopes()->whereIn('id', $allIds->unique())->orderBy('code')->get()->keyBy('id');

        return $allSubjects->map(function (Subject $subject) use ($allSubjects) {
            $parent = $subject->parent_id ? ($allSubjects[$subject->parent_id] ?? null) : null;

            return [
                'SUBJECT', '', '', '', '', '',
                $subject->code,
                $subject->name,
                $parent ? $parent->code : '',
                '', '', '',
            ];
        })->values()->all();
    }


    public function importCsv(UploadedFile $file, User $user): array
    {
        $this->resetState([
            'subjects_created' => 0,
            'subjects_skipped' => 0,
            'documents_created' => 0,
            'documents_skipped' => 0,
            'rows_skipped' => 0,
            'errors' => [],
        ]);

        $rows = $this->parseCsv($file);

        DB::transaction(function () use ($rows, $user) {
            [$subjectRows, $transactionRows] = $this->separateRows($rows);
            $this->importSubjectRows($subjectRows);
            $this->importTransactionRows($transactionRows, $user);
        });

        return $this->result;
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

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $line;

                continue;
            }

            if (count($line) !== count($headers)) {
                $this->result['rows_skipped']++;

                continue;
            }

            $rows[] = array_combine($headers, $line);
        }

        fclose($handle);

        return $rows;
    }

    private function separateRows(array $rows): array
    {
        $subjectRows = [];
        $transactionRows = [];

        foreach ($rows as $row) {
            $type = strtoupper(trim($row['record_type'] ?? ''));
            if ($type === 'SUBJECT') {
                $subjectRows[] = $row;
            } elseif ($type === 'TRANSACTION') {
                $transactionRows[] = $row;
            } else {
                $this->result['rows_skipped']++;
            }
        }

        return [$subjectRows, $transactionRows];
    }

    private function importSubjectRows(array $rows): void
    {
        $before = Subject::count();

        $this->processSubjectRows(array_map(fn ($r) => [
            'code' => trim($r['subject_code'] ?? ''),
            'name' => trim($r['subject_name'] ?? ''),
            'parent_code' => trim($r['subject_parent_code'] ?? ''),
        ], $rows));

        $after = Subject::count();
        $this->result['subjects_created'] = $after - $before;
        $this->result['subjects_skipped'] = count($rows) - $this->result['subjects_created'];
    }

    private function importTransactionRows(array $rows, User $user): void
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = ($row['doc_number'] ?? '').':'.trim($row['doc_date'] ?? '');
            $groups[$key][] = $row;
        }

        foreach ($groups as $key => $group) {
            try {
                $this->importDocumentGroup($group, $user);
            } catch (\Throwable $e) {
                $this->result['errors'][] = "Document {$key}: ".$e->getMessage();
                $this->result['documents_skipped']++;
                Log::warning('DocumentImportExportService: skipped document '.$key.': '.$e->getMessage());
            }
        }
    }

    private function importDocumentGroup(array $rows, User $user): void
    {
        $first = $rows[0];
        $number = (float) ($first['doc_number'] ?? 0);
        $date = trim($first['doc_date'] ?? '');
        $title = trim($first['doc_title'] ?? '');
        $status = strtolower(trim($first['doc_status'] ?? 'unapproved'));

        if ($number <= 0 || $date === '') {
            $this->result['documents_skipped']++;

            return;
        }

        if (Document::where('number', $number)->where('date', $date)->exists()) {
            $this->result['documents_skipped']++;

            return;
        }

        $transactions = [];
        foreach ($rows as $row) {
            $subject = $this->findOrCreate(
                trim($row['subject_code'] ?? ''),
                trim($row['subject_name'] ?? ''),
                trim($row['subject_parent_code'] ?? '')
            );

            $transactions[] = [
                'subject_id' => $subject->id,
                'value' => (float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0),
                'desc' => trim($row['transaction_desc'] ?? ''),
            ];
        }

        $document = DocumentService::createDocument($user, [
            'number' => $number,
            'date' => $date,
            'title' => $title,
            'is_imported' => true,
        ], $transactions);

        if ($status === 'approved') {
            DocumentService::changeDocumentStatus($document, $user, 'approved');
        }

        $this->result['documents_created']++;
    }

    /**
     * Find or create a subject by its raw code.
     */
    public function findOrCreate(string $code, string $name, string $parentCode = ''): Subject
    {
        if (isset($this->subjectCache[$code])) {
            return $this->subjectCache[$code];
        }

        $subject = Subject::where('code', $code)->first();
        if ($subject) {
            $this->subjectCache[$code] = $subject;

            return $subject;
        }

        $parent = null;
        if ($parentCode !== '') {
            $parent = $this->subjectCache[$parentCode] ?? Subject::where('code', $parentCode)->first();
            if (! $parent) {
                Log::warning("DocumentImportExportService: parent code {$parentCode} not found when creating {$code}");
            } else {
                $this->subjectCache[$parentCode] = $parent;
            }
        }

        $parentId = $parent?->id;
        $byName = Subject::where('name', $name)
            ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'))
            ->first();

        if ($byName) {
            $this->subjectCache[$code] = $byName;

            return $byName;
        }

        $subject = $this->createSubject($code, $name, $parent);
        $this->subjectCache[$code] = $subject;

        return $subject;
    }

    /**
     * Process an ordered list of subject definitions (parents before children).
     *
     * @param  array<int, array{code: string, name: string, parent_code: string}>  $rows
     * @return array<string, Subject>
     */
    public function processSubjectRows(array $rows): array
    {
        foreach ($rows as $row) {
            $code = trim($row['code'] ?? '');
            $name = trim($row['name'] ?? '');
            $parentCode = trim($row['parent_code'] ?? '');

            if ($code === '' || $name === '') {
                continue;
            }

            $this->findOrCreate($code, $name, $parentCode);
        }

        return $this->subjectCache;
    }

    private function createSubject(string $code, string $name, ?Subject $parent): Subject
    {
        $subject = new Subject([
            'name' => $name,
            'parent_id' => $parent?->id,
            'company_id' => getActiveCompany(),
            'type' => 'both',
            'is_permanent' => false,
        ]);

        $subject->code = ! Subject::where('code', $code)->exists() ? $code : $subject->generateCode();
        $subject->save();

        Log::info("DocumentImportExportService: created subject [{$subject->code}] {$name}");

        return $subject;
    }

    public function resetCache(): void
    {
        $this->subjectCache = [];
    }

    private function resetState(array $initial): void
    {
        $this->result = $initial;
        $this->subjectCache = [];
    }
}
