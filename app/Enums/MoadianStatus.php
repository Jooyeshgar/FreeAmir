<?php

namespace App\Enums;

enum MoadianStatus: string
{
    case SUCCESS = 'SUCCESS';

    case FAILED = 'FAILED';

    case UNKNOWN = 'UNKNOWN';

    public static function fromData(?string $status): ?self
    {
        if ($status === null) {
            return null;
        }

        return self::tryFrom(strtoupper($status));
    }

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => __('SUCCESS'),
            self::FAILED => __('FAILED'),
            self::UNKNOWN => __('UNKNOWN'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUCCESS => 'badge-success',
            self::FAILED => 'badge-error',
            self::UNKNOWN => 'badge-warning',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }
}
