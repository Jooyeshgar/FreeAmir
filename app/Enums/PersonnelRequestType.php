<?php

namespace App\Enums;

use ValueError;

enum PersonnelRequestType: int
{
    case LEAVE_HOURLY = 1;
    case LEAVE_DAILY = 2;
    case SICK_LEAVE = 3;
    case LEAVE_WITHOUT_PAY = 4;
    case LEAVE_WITHOUT_PAY_HOURLY = 5;
    case MISSION_HOURLY = 6;
    case MISSION_DAILY = 7;
    case OVERTIME_ORDER = 8;
    case REMOTE_WORK = 9;
    case OTHER = 10;

    public function label(): string
    {
        return match ($this) {
            self::LEAVE_HOURLY => __('Hourly Leave'),
            self::LEAVE_DAILY => __('Daily Leave'),
            self::SICK_LEAVE => __('Sick Leave'),
            self::LEAVE_WITHOUT_PAY => __('Daily Leave Without Pay'),
            self::LEAVE_WITHOUT_PAY_HOURLY => __('Hourly Leave Without Pay'),
            self::MISSION_HOURLY => __('Hourly Mission'),
            self::MISSION_DAILY => __('Daily Mission'),
            self::OVERTIME_ORDER => __('Overtime Order'),
            self::REMOTE_WORK => __('Remote Work'),
            self::OTHER => __('Other'),
        };
    }

    public function valueName(): string
    {
        return $this->name;
    }

    public static function leaveTypes(): array
    {
        return [
            self::LEAVE_HOURLY,
            self::LEAVE_DAILY,
            self::SICK_LEAVE,
            self::LEAVE_WITHOUT_PAY,
            self::LEAVE_WITHOUT_PAY_HOURLY,
        ];
    }

    public static function missionTypes(): array
    {
        return [
            self::MISSION_HOURLY,
            self::MISSION_DAILY,
        ];
    }

    public static function workOrderTypes(): array
    {
        return [
            self::OVERTIME_ORDER,
            self::REMOTE_WORK,
        ];
    }

    public static function otherTypes(): array
    {
        return [
            self::OTHER,
        ];
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn ($case) => ['value' => $case->valueName(), 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->valueName(), self::cases());
    }

    public static function fromName(self|int|string $value): self
    {
        return self::tryFromName($value) ?? throw new ValueError(sprintf('"%s" is not a valid %s', (string) $value, self::class));
    }

    public static function tryFromName(self|int|string|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::tryFrom((int) $value);
        }

        foreach (self::cases() as $case) {
            if ($case->valueName() === $value || $case->name === $value) {
                return $case;
            }
        }

        return null;
    }
}
