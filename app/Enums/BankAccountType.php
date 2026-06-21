<?php

namespace App\Enums;

enum BankAccountType: string
{
    case CURRENT = 'current';
    case SAVINGS = 'savings';
    case QARZ_AL_HASANAH = 'qarz_al_hasanah';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CURRENT => __('current'),
            self::SAVINGS => __('savings'),
            self::QARZ_AL_HASANAH => __('qarz al hasanah'),
            self::OTHER => __('other'),
        };
    }
}
