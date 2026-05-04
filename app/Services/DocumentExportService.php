<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\LazyCollection;

class DocumentExportService
{
    public function __construct(private readonly TrialBalanceService $trialBalanceService) {}

    public function stream(LazyCollection $documents, $handle): void
    {
        fputcsv($handle, [
            'document number', 'document date', 'document title', 'invoice/ancillary cost number',
            'root code', 'ledger code', 'detailed code', 'remaining code', 'subject name',
            'desc', 'debit', 'credit', 'sum debit', 'sum credit', 'remaining debit', 'remaining credit',
        ]);

        foreach ($documents as $document) {
            foreach ($document->transactions as $transaction) {
                $this->writeRow($handle, $document, $transaction);
            }
        }
    }

    private function writeRow($handle, Document $document, $transaction): void
    {
        $subject = $transaction->subject;

        $code = $subject?->code ?? '';
        $parts = str_split($code, 3);

        $rootCode = $parts[0] ?? 0;
        $ledgerCode = $parts[1] ?? 0;
        $detailedCode = $parts[2] ?? 0;
        $remainingCode = isset($parts[3]) ? implode('', array_slice($parts, 3)) : 0;

        $debit = $transaction->value < 0 ? abs($transaction->value) : 0;
        $credit = $transaction->value > 0 ? $transaction->value : 0;

        fputcsv($handle, [
            $document->number,
            $document->date,
            $document->title,
            $document->documentable?->number,
            $rootCode,
            $ledgerCode,
            $detailedCode,
            $remainingCode,
            $subject?->name,
            $transaction->desc,
            $debit,
            $credit,
            '',
            '',
            '',
            '',
        ]);
    }
}
