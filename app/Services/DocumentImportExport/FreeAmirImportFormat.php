<?php

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

class FreeAmirImportFormat extends DocumentImportFormat
{
    public const ALL_COLUMNS = [
        'record_type', 'doc_number', 'doc_date', 'doc_title', 'doc_type', 'doc_status',
        'subject_root_code', 'subject_code', 'subject_name', 'subject_parent_code', 'transaction_desc', 'debit', 'credit',
    ];

    public const MANDATORY_COLUMNS = [
        'record_type', 'doc_number', 'doc_date', 'subject_code', 'subject_name', 'debit', 'credit',
    ];

    private const HEADER_NORMALIZE_MAP = [
        'نوع رکورد' => 'record_type',
        'شماره سند' => 'doc_number',
        'تاریخ سند' => 'doc_date',
        'عنوان سند' => 'doc_title',
        'نوع سند' => 'doc_type',
        'وضعیت سند' => 'doc_status',
        'کد سرفصل اصلی' => 'subject_root_code',
        'کد سرفصل کلی' => 'subject_root_code',
        'کد سرفصل' => 'subject_code',
        'کد سرفصل تفصیلی' => 'subject_code',
        'نام سرفصل' => 'subject_name',
        'کد سرفصل والد' => 'subject_parent_code',
        'کد سرفصل معین' => 'subject_parent_code',
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

    public function key(): string
    {
        return 'free_amir';
    }

    public function label(): string
    {
        return __('Free Amir');
    }

    public function matches(array $headers): bool
    {
        $normalized = array_map(fn ($h) => self::normalizeHeader($h), $headers);

        foreach (self::MANDATORY_COLUMNS as $required) {
            if (! in_array($required, $normalized, true)) {
                return false;
            }
        }

        return true;
    }

    public function import(array $rows, User $user): array
    {
        $this->initResult();

        $rows = array_map(fn ($row) => $this->normalizeRow($row), $rows);

        [$subjectRows, $transactionRows] = $this->separateRows($rows);
        $this->importSubjectRows($subjectRows);
        $this->importTransactionRows($transactionRows, $user);

        return $this->result;
    }

    public static function normalizeHeader(string $header): string
    {
        $header = trim($header);

        return self::HEADER_NORMALIZE_MAP[$header] ?? $header;
    }

    public static function parseSubjectCodes(string $dbCode): array
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

    public static function reconstructSubjectCodes(string $rootOwn, string $parentOwn, string $ownCode): array
    {
        if ($parentOwn === '') {
            return [$ownCode, ''];
        }
        if ($parentOwn === $rootOwn) {
            return [$rootOwn.$ownCode, $rootOwn];
        }

        return [$rootOwn.$parentOwn.$ownCode, $rootOwn.$parentOwn];
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[self::normalizeHeader((string) $key)] = $value;
        }

        return $normalized;
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

        $this->subjects->processSubjectRows(array_map(function ($r) {
            [$fullCode, $parentFullCode] = self::reconstructSubjectCodes(
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
                Log::warning('FreeAmirImportFormat: skipped document '.$key.': '.$e->getMessage());
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
            [$fullCode, $parentFullCode] = self::reconstructSubjectCodes(
                trim($row['subject_root_code'] ?? ''),
                trim($row['subject_parent_code'] ?? ''),
                trim($row['subject_code'] ?? '')
            );
            $subject = $this->subjects->findOrCreate(
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
}
