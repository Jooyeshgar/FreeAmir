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
        'doc_number', 'doc_date', 'doc_title', 'doc_type', 'doc_status',
        'subject_root_code', 'subject_moein_code', 'subject_tafsili_code', 'subject_name',
        'transaction_desc', 'debit', 'credit',
    ];

    public const MANDATORY_COLUMNS = [
        'doc_number', 'doc_date', 'subject_root_code', 'subject_name', 'debit', 'credit',
    ];

    private const HEADER_NORMALIZE_MAP = [
        'شماره سند' => 'doc_number',
        'تاریخ سند' => 'doc_date',
        'عنوان سند' => 'doc_title',
        'نوع سند' => 'doc_type',
        'وضعیت سند' => 'doc_status',
        'کد ریشه' => 'subject_root_code',
        'کد سرفصل اصلی' => 'subject_root_code',
        'کد سرفصل کلی' => 'subject_root_code',
        'کد معین' => 'subject_moein_code',
        'کد سرفصل معین' => 'subject_moein_code',
        'کد سرفصل والد' => 'subject_moein_code',
        'کد تفصیلی' => 'subject_tafsili_code',
        'کد سرفصل تفصیلی' => 'subject_tafsili_code',
        'کد سرفصل' => 'subject_tafsili_code',
        'نام سرفصل' => 'subject_name',
        'شرح ردیف' => 'transaction_desc',
        'بدهکار' => 'debit',
        'بستانکار' => 'credit',
        'Document Number' => 'doc_number',
        'Document Date' => 'doc_date',
        'Document Title' => 'doc_title',
        'Document Type' => 'doc_type',
        'Document Status' => 'doc_status',
        'Root Account Code' => 'subject_root_code',
        'Moein Account Code' => 'subject_moein_code',
        'Tafsili Account Code' => 'subject_tafsili_code',
        'Account Code' => 'subject_tafsili_code',
        'Account Name' => 'subject_name',
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
        $before = Subject::count();
        $rows = array_map(fn ($row) => $this->normalizeRow($row), $rows);
        $this->subjects->setKnownSubjects($this->buildKnownSubjects($rows));
        $this->importTransactionRows($rows, $user);
        $this->result['subjects_created'] = max(0, Subject::count() - $before);

        return $this->result;
    }

    /**
     * Index every subject referenced by the file as full code => name, so an ancestor that is not
     * yet in the database can be reconstructed from whichever row introduces it.
     *
     * @return array<string,string>
     */
    private function buildKnownSubjects(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            [$fullCode] = self::reconstructSubjectCodes(
                $row['subject_root_code'] ?? '',
                $row['subject_moein_code'] ?? '',
                $row['subject_tafsili_code'] ?? ''
            );
            $name = trim($row['subject_name'] ?? '');
            if ($fullCode !== '' && $name !== '' && ! isset($map[$fullCode])) {
                $map[$fullCode] = $name;
            }
        }

        return $map;
    }

    public static function normalizeHeader(string $header): string
    {
        $header = trim($header);

        return self::HEADER_NORMALIZE_MAP[$header] ?? $header;
    }

    /**
     * Split a stored DB code into its three-digit level chunks: [root, moein, tafsili].
     * Levels that the subject does not have are returned as empty strings.
     */
    public static function parseSubjectCodes(string $dbCode): array
    {
        $dbCode = trim($dbCode);
        if ($dbCode === '') {
            return ['', '', ''];
        }

        $root = substr($dbCode, 0, 3);
        $moein = strlen($dbCode) > 3 ? substr($dbCode, 3, 3) : '';
        $tafsili = strlen($dbCode) > 6 ? substr($dbCode, 6, 3) : '';

        return [$root, $moein, $tafsili];
    }

    /**
     * Rebuild the full code and its parent code from the per-level chunks.
     *
     * @return array{0:string,1:string} [fullCode, parentFullCode]
     */
    public static function reconstructSubjectCodes(string $root, string $moein, string $tafsili): array
    {
        $root = trim(toEnglish($root));
        $moein = trim(toEnglish($moein));
        $tafsili = trim(toEnglish($tafsili));

        if ($tafsili !== '') {
            return [$root.$moein.$tafsili, $root.$moein];
        }

        if ($moein !== '') {
            return [$root.$moein, $root];
        }

        return [$root, ''];
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[self::normalizeHeader((string) $key)] = $value;
        }

        return $normalized;
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
                $this->result['errors'][] = __('Document :key was not imported: :reason', ['key' => $key, 'reason' => $e->getMessage()]);
                $this->result['documents_skipped']++;
                Log::warning('FreeAmirImportFormat: skipped document '.$key.': '.$e->getMessage());
            }
        }
    }

    private function importDocumentGroup(array $rows, User $user): void
    {
        $first = $rows[0];
        $number = convertToFloat($first['doc_number'] ?? 0);
        $date = $this->parseImportedDate((string) ($first['doc_date'] ?? ''));
        $title = trim($first['doc_title'] ?? '');

        if ($number <= 0 || $date === '') {
            $this->result['documents_skipped']++;
            $this->result['errors'][] = __('A document was skipped because its number or date is invalid (number: :num, date: :date).', [
                'num' => trim((string) ($first['doc_number'] ?? '')),
                'date' => trim((string) ($first['doc_date'] ?? '')),
            ]);

            return;
        }

        if (Document::where('number', $number)->where('date', $date)->exists()) {
            $this->result['documents_skipped']++;
            $this->result['errors'][] = __('Document number :num (:date) already exists and was skipped.', [
                'num' => trim((string) ($first['doc_number'] ?? '')),
                'date' => trim((string) ($first['doc_date'] ?? '')),
            ]);

            return;
        }

        $transactions = [];
        foreach ($rows as $row) {
            [$fullCode, $parentFullCode] = self::reconstructSubjectCodes(
                $row['subject_root_code'] ?? '',
                $row['subject_moein_code'] ?? '',
                $row['subject_tafsili_code'] ?? ''
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

        if ($this->isApprovedStatus((string) ($first['doc_status'] ?? ''))) {
            DocumentService::changeDocumentStatus($document, $user, 'approved');
        }

        $this->result['documents_created']++;
    }

    /**
     * Normalize an imported document date (Jalali or Gregorian, Persian or Latin digits) to a Gregorian 'Y-m-d' string.
     */
    private function parseImportedDate(string $raw): string
    {
        $raw = trim(toEnglish($raw));
        if ($raw === '') {
            return '';
        }

        $parts = preg_split('#[/\-]#', $raw);
        $year = (int) ($parts[0] ?? 0);

        // Jalali years are far below 1700; treat those as Jalali and convert to Gregorian.
        if ($year > 0 && $year < 1700) {
            return jalali_to_gregorian_date(str_replace('-', '/', $raw), '-');
        }

        return str_replace('/', '-', $raw);
    }

    private function isApprovedStatus(string $status): bool
    {
        $status = mb_strtolower(trim(toEnglish($status)));

        return in_array($status, ['approved', '1', mb_strtolower(__('approved'))], true);
    }
}
