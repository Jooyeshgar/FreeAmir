<?php

namespace App\Services;

use App\Enums\CommercialLedgerType;
use App\Models\CommercialLedgerExport;
use App\Models\Company;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CommercialLedgerService
{
    public const HEADERS = [
        'Row Number',
        'Date',
        'General Account Code',
        'General Account Title',
        'Subsidiary Account Code',
        'Subsidiary Account Title',
        'Description / Narration',
        'Debit Amount (IRR)',
        'Credit Amount (IRR)',
    ];

    public function generate(array $data, int $userId): CommercialLedgerExport
    {
        $company = Company::query()->findOrFail(getActiveCompany());
        $fromDate = jalali_to_gregorian_date($data['from_date'], '-', '/');
        $toDate = jalali_to_gregorian_date($data['to_date'], '-', '/');
        $type = CommercialLedgerType::from($data['ledger_type']);
        $rows = $this->rows($fromDate, $toDate, $type);
        $extension = $data['format'];
        $path = 'commercial-ledgers/'.$company->id.'/'.Str::uuid().'.'.$extension;
        $content = $extension === 'xlsx' ? $this->xlsx($rows) : $this->csv($rows);

        if (! Storage::disk('local')->put($path, $content)) {
            throw new RuntimeException(__('The commercial ledger file could not be stored.'));
        }

        try {
            return CommercialLedgerExport::query()->create([
                'company_id' => $company->id,
                'creator_id' => $userId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'format' => $extension,
                'seal_tracking_code' => $data['seal_tracking_code'],
                'ledger_type' => $type,
                'status' => 'ready',
                'file_path' => $path,
                'row_count' => $rows->count(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function rows(string $fromDate, string $toDate, CommercialLedgerType $type): Collection
    {
        $subjects = Subject::query()->get(['id', 'code', 'name', 'parent_id'])->keyBy('id');
        $transactions = DB::table('transactions')
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'transactions.subject_id')
            ->where('documents.company_id', getActiveCompany())
            ->whereBetween('documents.date', [$fromDate, $toDate])
            ->whereNotNull('transactions.subject_id')
            ->where('subjects.company_id', getActiveCompany())
            ->orderBy('documents.date')
            ->orderBy('documents.number')
            ->orderBy('transactions.id')
            ->select([
                'transactions.id',
                'transactions.value',
                'transactions.desc',
                'transactions.subject_id',
                'documents.id as document_id',
                'documents.number as document_number',
                'documents.date',
                'documents.title as document_title',
            ])
            ->get()
            ->map(fn ($transaction) => $this->transactionRow($transaction, $subjects, $type->isGeneralLevel()));

        if ($type->isVoucherAggregation()) {
            $transactions = $this->aggregate($transactions, fn (array $row): string => 'voucher|'.$row['document_id'].'|'.$row['account_key'], __('Voucher :number'));
        } elseif ($type->isMonthlyAggregation()) {
            $transactions = $this->aggregateMonthly($transactions, $type);
        }

        return $transactions->values()->map(function (array $row, int $index): array {
            return [
                'row_number' => $index + 1,
                'date' => gregorian_to_jalali_date($row['date'], '/', '-'),
                'general_code' => $row['general_code'],
                'general_title' => $row['general_title'],
                'subsidiary_code' => $row['subsidiary_code'],
                'subsidiary_title' => $row['subsidiary_title'],
                'description' => $row['description'],
                'debit' => $row['debit_minor'] / 100,
                'credit' => $row['credit_minor'] / 100,
            ];
        });
    }

    public function delete(CommercialLedgerExport $export): void
    {
        Storage::disk('local')->delete($export->file_path);
        $export->delete();
    }

    public function filename(CommercialLedgerExport $export): string
    {
        return sprintf(
            'commercial-ledger-%s-%s-%s.%s',
            $export->company->fiscal_year,
            $export->from_date->format('Ymd'),
            $export->to_date->format('Ymd'),
            $export->format
        );
    }

    private function transactionRow(object $transaction, Collection $subjects, bool $generalLevel): array
    {
        $subject = $subjects->get($transaction->subject_id);
        $lineage = collect();
        $visited = [];

        while ($subject && ! isset($visited[$subject->id])) {
            $visited[$subject->id] = true;
            $lineage->prepend($subject);
            $subject = $subject->parent_id ? $subjects->get($subject->parent_id) : null;
        }

        $general = $lineage->first();
        $subsidiary = $generalLevel ? null : $lineage->get(1);
        $value = $this->minorUnits((string) $transaction->value);

        return [
            'transaction_id' => $transaction->id,
            'document_id' => $transaction->document_id,
            'document_number' => $transaction->document_number,
            'date' => $transaction->date,
            'general_code' => (string) ($general?->code ?? ''),
            'general_title' => (string) ($general?->name ?? ''),
            'subsidiary_code' => (string) ($subsidiary?->code ?? ''),
            'subsidiary_title' => (string) ($subsidiary?->name ?? ''),
            'account_key' => $generalLevel ? (string) ($general?->id ?? '') : (string) ($subsidiary?->id ?? $general?->id ?? ''),
            'description' => (string) ($transaction->desc ?: $transaction->document_title ?: ''),
            'document_title' => (string) ($transaction->document_title ?: ''),
            'debit_minor' => $value < 0 ? abs($value) : 0,
            'credit_minor' => $value > 0 ? $value : 0,
        ];
    }

    private function aggregate(Collection $rows, callable $key, string $fallbackDescription): Collection
    {
        return $rows->groupBy($key)->map(function (Collection $group) use ($fallbackDescription): array {
            $first = $group->first();
            $first['debit_minor'] = $group->sum('debit_minor');
            $first['credit_minor'] = $group->sum('credit_minor');
            $first['date'] = $group->max('date');
            $first['description'] = $first['document_title'] ?: __($fallbackDescription, ['number' => $first['document_number']]);

            return $first;
        })->sortBy(fn (array $row): string => $row['date'].'|'.str_pad((string) $row['document_number'], 20, '0', STR_PAD_LEFT).'|'.$row['account_key']);
    }

    private function aggregateMonthly(Collection $rows, CommercialLedgerType $type): Collection
    {
        $opening = collect();
        $monthly = $rows;

        if ($type->breaksDownOpeningVouchers()) {
            $opening = $rows->filter(fn (array $row): bool => in_array((int) $row['document_number'], [1, 2], true));
            $monthly = $rows->reject(fn (array $row): bool => in_array((int) $row['document_number'], [1, 2], true));
            $opening = $this->aggregate($opening, fn (array $row): string => 'opening|'.$row['document_id'].'|'.$row['account_key'], __('Opening voucher :number'));
        }

        $monthly = $monthly->groupBy(function (array $row): string {
            $jalaliMonth = gregorian_to_jalali_date($row['date'], '/', '-');

            return substr($jalaliMonth, 0, 7).'|'.$row['account_key'];
        })->map(function (Collection $group): array {
            $first = $group->first();
            $jalaliMonth = substr(gregorian_to_jalali_date($first['date'], '/', '-'), 0, 7);
            $first['debit_minor'] = $group->sum('debit_minor');
            $first['credit_minor'] = $group->sum('credit_minor');
            $first['date'] = $group->max('date');
            $first['description'] = __('Monthly ledger aggregation for :month', ['month' => $jalaliMonth]);

            return $first;
        });

        return $opening->concat($monthly)->sortBy(fn (array $row): string => $row['date'].'|'.$row['account_key']);
    }

    private function minorUnits(string $value): int
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minor : $minor;
    }

    private function csv(Collection $rows): string
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map('__', self::HEADERS));

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function xlsx(Collection $rows): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'commercial-ledger-');
        if ($temporaryPath === false) {
            throw new RuntimeException(__('A temporary export file could not be created.'));
        }

        $zip = new ZipArchive;
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('The Excel export could not be created.'));
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->packageRelationshipsXml());
        $zip->addFromString('docProps/app.xml', $this->appPropertiesXml());
        $zip->addFromString('docProps/core.xml', $this->corePropertiesXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();

        $content = file_get_contents($temporaryPath);
        unlink($temporaryPath);

        if ($content === false) {
            throw new RuntimeException(__('The Excel export could not be read.'));
        }

        return $content;
    }

    private function sheetXml(Collection $rows): string
    {
        $xmlRows = [$this->xlsxRow(1, array_map('__', self::HEADERS), true)];
        foreach ($rows as $index => $row) {
            $xmlRows[] = $this->xlsxRow($index + 2, array_values($row));
        }
        $lastRow = max(1, $rows->count() + 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:I'.$lastRow.'"/><sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="10" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/><col min="3" max="6" width="24" customWidth="1"/><col min="7" max="7" width="48" customWidth="1"/><col min="8" max="9" width="20" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData><autoFilter ref="A1:I'.$lastRow.'"/></worksheet>';
    }

    private function xlsxRow(int $number, array $values, bool $header = false): string
    {
        $cells = '';
        foreach (array_values($values) as $index => $value) {
            $reference = chr(65 + $index).$number;
            if (! $header && in_array($index, [0, 7, 8], true)) {
                $cells .= '<c r="'.$reference.'" s="'.($index === 0 ? 1 : 2).'" t="n"><v>'.(float) $value.'</v></c>';
            } else {
                $cells .= '<c r="'.$reference.'" s="'.($header ? 3 : 1).'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
            }
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function packageRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="'.$this->xml(__('Commercial Ledger')).'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><name val="Vazirmatn"/><sz val="11"/></font><font><name val="Vazirmatn"/><sz val="11"/><b/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>FreeAmir</Application></Properties>';
    }

    private function corePropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>FreeAmir</dc:creator><dc:title>'.$this->xml(__('Commercial Ledger')).'</dc:title></cp:coreProperties>';
    }
}
