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

    public const ALL_COLUMNS = [
        'record_type', 'doc_number', 'doc_date', 'doc_title', 'doc_type', 'doc_status',
        'subject_root_code', 'subject_code', 'subject_name', 'subject_parent_code', 'transaction_desc', 'debit', 'credit',
    ];

    public const IMPORT_REQUIRED_COLUMNS = [
        'record_type', 'doc_number', 'doc_date', 'subject_code', 'subject_name', 'debit', 'credit',
    ];

    /** Maps all known translated/alias header variants back to internal column keys for import. */
    private const HEADER_NORMALIZE_MAP = [
        'نوع رکورد' => 'record_type',
        'شماره سند' => 'doc_number',
        'تاریخ سند' => 'doc_date',
        'عنوان سند' => 'doc_title',
        'نوع سند' => 'doc_type',
        'وضعیت سند' => 'doc_status',
        'کد سرفصل اصلی' => 'subject_root_code',
        'کد سرفصل' => 'subject_code',
        'نام سرفصل' => 'subject_name',
        'کد سرفصل والد' => 'subject_parent_code',
        'شرح ردیف' => 'transaction_desc',
        'بدهکار' => 'debit',
        'بستانکار' => 'credit',
        'Record Type' => 'record_type',
        'Document Number' => 'doc_number',
        'Document Date' => 'doc_date',
        'Document Title' => 'doc_title',
        'Document Type' => 'doc_type',
        'Document Status' => 'doc_status',
        'Root Account Code' => 'subject_root_code',
        'Account Code' => 'subject_code',
        'Account Name' => 'subject_name',
        'Parent Account Code' => 'subject_parent_code',
        'Transaction Description' => 'transaction_desc',
        'Debit' => 'debit',
        'Credit' => 'credit',
    ];

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
            'columns' => 'nullable|array',
            'columns.*' => 'string|in:'.implode(',', self::ALL_COLUMNS),
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
        $columns = ! empty($filters['columns']) ? $filters['columns'] : self::ALL_COLUMNS;

        return self::exportCsv($query, $filename, $columns);
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
                            [$rootOwn, $parentOwn, $ownCode] = $this->parseSubjectCodes($subject->code ?? '');
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
     * Splits a concatenated DB subject code into its 3-char own-segment parts.
     * Returns [rootOwn, parentOwn, ownCode].
     * e.g. "011004001" → ["011","004","001"], "011004" → ["011","011","004"], "011" → ["011","","011"]
     */
    private function parseSubjectCodes(string $dbCode): array
    {
        if ($dbCode === '') {
            return ['', '', ''];
        }
        $len = strlen($dbCode);
        if ($len <= 3) {
            return [substr($dbCode, 0, 3), '', substr($dbCode, 0, 3)];
        }
        $root = substr($dbCode, 0, 3);
        if ($len <= 6) {
            return [$root, $root, substr($dbCode, 3, 3)];
        }

        return [$root, substr($dbCode, 3, 3), substr($dbCode, 6, 3)];
    }

    /**
     * Reconstructs the full DB code and parent's full DB code from 3-char own-segment parts.
     * Returns [fullCode, parentFullCode].
     */
    private function reconstructSubjectCodes(string $rootOwn, string $parentOwn, string $ownCode): array
    {
        if ($parentOwn === '') {
            return [$ownCode, ''];
        }
        if ($parentOwn === $rootOwn) {
            return [$rootOwn.$ownCode, $rootOwn];
        }

        return [$rootOwn.$parentOwn.$ownCode, $rootOwn.$parentOwn];
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
            [$rootOwn, $parentOwn, $ownCode] = $this->parseSubjectCodes($subject->code);

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
            $format = $this->detectCsvFormat($rows);

            if ($format === 'parsian') {
                $before = Subject::count();
                $this->importParsianTransactionRows($rows, $user);
                $this->result['subjects_created'] = Subject::count() - $before;
            } elseif ($format === 'parsian_trial_balance') {
                $this->importParsianTrialBalanceRows($rows);
            } else {
                [$subjectRows, $transactionRows] = $this->separateRows($rows);
                $this->importSubjectRows($subjectRows);
                $this->importTransactionRows($transactionRows, $user);
            }
        });

        return $this->result;
    }

    private function detectCsvFormat(array $rows): string
    {
        if (empty($rows)) {
            return 'standard';
        }
        $headers = array_keys($rows[0]);
        if (in_array('Sanad_Num', $headers)) {
            return 'parsian';
        }
        if (in_array('KolCode', $headers) && in_array('KolName', $headers)) {
            return 'parsian_trial_balance';
        }

        return 'standard';
    }

    private function importParsianTransactionRows(array $rows, User $user): void
    {
        $moenNames = [];
        foreach ($rows as $row) {
            $kol = (int) ($row['KolCode'] ?? 0);
            $moen = (int) ($row['MoeenCode'] ?? 0);
            $taf = (int) ($row['TafsiliCode'] ?? 0);
            if ($taf === 0 && $kol > 0 && $moen > 0) {
                $key = $kol.'.'.$moen;
                if (! isset($moenNames[$key])) {
                    $moenNames[$key] = trim($row['HesabName'] ?? '');
                }
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $num = trim($row['Sanad_Num'] ?? '');
            $date = trim($row['SanadDate'] ?? '');
            if ($num === '' || $date === '') {
                $this->result['rows_skipped']++;

                continue;
            }
            $groups[$num.':'.$date][] = $row;
        }

        foreach ($groups as $key => $group) {
            try {
                $this->importParsianDocumentGroup($group, $user, $moenNames);
            } catch (\Throwable $e) {
                $this->result['errors'][] = "Document {$key}: ".$e->getMessage();
                $this->result['documents_skipped']++;
                Log::warning('DocumentImportExportService: skipped Parsian document '.$key.': '.$e->getMessage());
            }
        }
    }

    private function importParsianDocumentGroup(array $rows, User $user, array $moenNames): void
    {
        $first = $rows[0];
        $number = (float) ($first['Sanad_Num'] ?? 0);
        $jalaliDate = trim($first['SanadDate'] ?? '');

        if ($number <= 0 || $jalaliDate === '') {
            $this->result['documents_skipped']++;

            return;
        }

        $date = jalali_to_gregorian_date($jalaliDate, '-');

        if ($date === '' || Document::where('number', $number)->where('date', $date)->exists()) {
            $this->result['documents_skipped']++;

            return;
        }

        $transactions = [];
        foreach ($rows as $row) {
            $kol = (int) ($row['KolCode'] ?? 0);
            $moen = (int) ($row['MoeenCode'] ?? 0);
            $taf = (int) ($row['TafsiliCode'] ?? 0);
            $name = trim($row['HesabName'] ?? '');
            $debit = (float) ($row['Bed'] ?? 0);
            $credit = (float) ($row['Bes'] ?? 0);
            $desc = trim($row['Comment'] ?? '');

            $subject = $this->resolveParsianSubject($kol, $moen, $taf, $name, $moenNames);

            $transactions[] = [
                'subject_id' => $subject->id,
                'value' => $credit - $debit,
                'desc' => $desc,
            ];
        }

        DocumentService::createDocument($user, [
            'number' => $number,
            'date' => $date,
            'title' => '',
            'is_imported' => true,
        ], $transactions);

        $this->result['documents_created']++;
    }

    private function resolveParsianSubject(int $kol, int $moen, int $taf, string $name, array $moenNames): Subject
    {
        $kolCode = str_pad($kol, 3, '0', STR_PAD_LEFT);
        $moenCode = $kolCode.str_pad($moen, 3, '0', STR_PAD_LEFT);

        $this->findOrCreate($kolCode, $kolCode, '');

        if ($taf > 0) {
            $tafCode = $moenCode.str_pad($taf, 3, '0', STR_PAD_LEFT);
            $moenName = $moenNames[$kol.'.'.$moen] ?? $moenCode;
            $this->findOrCreate($moenCode, $moenName, $kolCode);

            return $this->findOrCreate($tafCode, $name, $moenCode);
        }

        return $this->findOrCreate($moenCode, $name, $kolCode);
    }

    private function importParsianTrialBalanceRows(array $rows): void
    {
        $before = Subject::count();

        foreach ($rows as $row) {
            $kol = (int) ($row['KolCode'] ?? 0);
            $name = trim($row['KolName'] ?? '');
            if ($kol <= 0 || $name === '') {
                continue;
            }
            $this->findOrCreate(str_pad($kol, 3, '0', STR_PAD_LEFT), $name, '');
        }

        $after = Subject::count();
        $this->result['subjects_created'] = $after - $before;
        $this->result['subjects_skipped'] = count($rows) - $this->result['subjects_created'];
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
                $headers = array_map(
                    fn ($h) => self::HEADER_NORMALIZE_MAP[trim($h)] ?? trim($h),
                    $line
                );

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

        $this->processSubjectRows(array_map(function ($r) {
            [$fullCode, $parentFullCode] = $this->reconstructSubjectCodes(
                trim($r['subject_root_code'] ?? ''),
                trim($r['subject_parent_code'] ?? ''),
                trim($r['subject_code'] ?? '')
            );

            return [
                'code' => $fullCode,
                'name' => trim($r['subject_name'] ?? ''),
                'parent_code' => $parentFullCode,
            ];
        }, $rows));

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
        $number = convertToFloat($first['doc_number'] ?? 0);
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
            [$fullCode, $parentFullCode] = $this->reconstructSubjectCodes(
                trim($row['subject_root_code'] ?? ''),
                trim($row['subject_parent_code'] ?? ''),
                trim($row['subject_code'] ?? '')
            );
            $subject = $this->findOrCreate(
                $fullCode,
                trim($row['subject_name'] ?? ''),
                $parentFullCode
            );

            $transactions[] = [
                'subject_id' => $subject->id,
                'value' => convertToFloat($row['credit'] ?? 0) - convertToFloat($row['debit'] ?? 0),
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
