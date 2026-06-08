<?php

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

class ParsianImportFormat extends DocumentImportFormat
{
    public function key(): string
    {
        return 'parsian';
    }

    public function label(): string
    {
        return __('Parsian');
    }

    public function matches(array $headers): bool
    {
        $headers = array_map('trim', $headers);

        return $this->isTransactionFile($headers) || $this->isTrialBalanceFile($headers);
    }

    public function import(array $rows, User $user): array
    {
        $this->initResult();

        $headers = empty($rows) ? [] : array_map('trim', array_keys($rows[0]));

        if ($this->isTransactionFile($headers)) {
            $before = Subject::count();
            $this->importTransactionRows($rows, $user);
            $this->result['subjects_created'] = Subject::count() - $before;
        } elseif ($this->isTrialBalanceFile($headers)) {
            $this->importTrialBalanceRows($rows);
        }

        return $this->result;
    }

    private function isTransactionFile(array $headers): bool
    {
        return in_array('Sanad_Num', $headers, true);
    }

    private function isTrialBalanceFile(array $headers): bool
    {
        return in_array('KolCode', $headers, true) && in_array('KolName', $headers, true);
    }

    private function importTransactionRows(array $rows, User $user): void
    {
        $moenNames = [];
        foreach ($rows as $row) {
            $kol = (int) ($row['KolCode'] ?? 0);
            $moen = (int) ($row['MoeenCode'] ?? 0);
            $taf = (int) ($row['TafsiliCode'] ?? 0);
            if ($taf === 0 && $kol > 0 && $moen > 0) {
                $key = $kol.'.'.$moen;
                if (! isset($moenNames[$key]) && trim($row['HesabName'] ?? '') !== '') {
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
                $this->importDocumentGroup($group, $user, $moenNames);
            } catch (\Throwable $e) {
                $this->result['errors'][] = __('Document :key was not imported: :reason', ['key' => $key, 'reason' => $e->getMessage()]);
                $this->result['documents_skipped']++;
                Log::warning('ParsianImportFormat: skipped document '.$key.': '.$e->getMessage());
            }
        }
    }

    private function importDocumentGroup(array $rows, User $user, array $moenNames): void
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

            $subject = $this->resolveSubject($kol, $moen, $taf, $name, $moenNames);

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

    private function resolveSubject(int $kol, int $moen, int $taf, string $name, array $moenNames): Subject
    {
        $kolCode = str_pad($kol, 3, '0', STR_PAD_LEFT);
        $moenCode = $kolCode.str_pad($moen, 3, '0', STR_PAD_LEFT);

        $this->subjects->findOrCreate($kolCode, ImportSubjectResolver::synthesizeName($kolCode), '');

        if ($taf > 0) {
            $tafCode = $moenCode.str_pad($taf, 3, '0', STR_PAD_LEFT);
            $moenName = $moenNames[$kol.'.'.$moen] ?? ImportSubjectResolver::synthesizeName($moenCode);
            $this->subjects->findOrCreate($moenCode, $moenName, $kolCode);

            return $this->subjects->findOrCreate($tafCode, $name, $moenCode);
        }

        return $this->subjects->findOrCreate($moenCode, $name, $kolCode);
    }

    private function importTrialBalanceRows(array $rows): void
    {
        $before = Subject::count();

        foreach ($rows as $row) {
            $kol = (int) ($row['KolCode'] ?? 0);
            $name = trim($row['KolName'] ?? '');
            if ($kol <= 0 || $name === '') {
                continue;
            }
            $this->subjects->findOrCreate(str_pad($kol, 3, '0', STR_PAD_LEFT), $name, '');
        }

        $this->result['subjects_created'] = Subject::count() - $before;
        $this->result['subjects_skipped'] = count($rows) - $this->result['subjects_created'];
    }
}
