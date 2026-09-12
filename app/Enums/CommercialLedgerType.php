<?php

namespace App\Enums;

enum CommercialLedgerType: int
{
    case ALL_SUBSIDIARY = 1;
    case ALL_GENERAL = 2;
    case DOCUMENT_SUBSIDIARY = 3;
    case DOCUMENT_GENERAL = 4;
    case MONTHLY_OPENING_SUBSIDIARY = 5;
    case MONTHLY_OPENING_GENERAL = 6;
    case MONTHLY_GENERAL = 7;

    public function label(): string
    {
        return match ($this) {
            self::ALL_SUBSIDIARY => __('Commercial Ledger without aggregation (All Documents) - Subsidiary Account Level'),
            self::ALL_GENERAL => __('Commercial Ledger without aggregation (All Documents) - General Account Level'),
            self::DOCUMENT_SUBSIDIARY => __('Commercial Ledger with document-level aggregation - Subsidiary Account Level'),
            self::DOCUMENT_GENERAL => __('Commercial Ledger with document-level aggregation - General Account Level'),
            self::MONTHLY_OPENING_SUBSIDIARY => __('Monthly aggregation with opening document breakdown - Subsidiary Account Level'),
            self::MONTHLY_OPENING_GENERAL => __('Monthly aggregation with opening document breakdown - General Account Level'),
            self::MONTHLY_GENERAL => __('Monthly aggregation without opening document breakdown - General Account Level'),
        };
    }

    public function isGeneralLevel(): bool
    {
        return in_array($this, [self::ALL_GENERAL, self::DOCUMENT_GENERAL, self::MONTHLY_OPENING_GENERAL, self::MONTHLY_GENERAL], true);
    }

    public function isDocumentAggregation(): bool
    {
        return in_array($this, [self::DOCUMENT_SUBSIDIARY, self::DOCUMENT_GENERAL], true);
    }

    public function isMonthlyAggregation(): bool
    {
        return in_array($this, [self::MONTHLY_OPENING_SUBSIDIARY, self::MONTHLY_OPENING_GENERAL, self::MONTHLY_GENERAL], true);
    }

    public function breaksDownOpeningDocuments(): bool
    {
        return in_array($this, [self::MONTHLY_OPENING_SUBSIDIARY, self::MONTHLY_OPENING_GENERAL], true);
    }
}
