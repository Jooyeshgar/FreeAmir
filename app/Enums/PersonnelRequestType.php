<?php

namespace App\Enums;

enum PersonnelRequestType: string
{
    case LEAVE_HOURLY = 'LEAVE_HOURLY';
    case LEAVE_DAILY = 'LEAVE_DAILY';
    case SICK_LEAVE = 'SICK_LEAVE';
    case LEAVE_WITHOUT_PAY = 'LEAVE_WITHOUT_PAY';
    case MISSION_HOURLY = 'MISSION_HOURLY';
    case MISSION_DAILY = 'MISSION_DAILY';
    case OVERTIME_ORDER = 'OVERTIME_ORDER';
    case REMOTE_WORK = 'REMOTE_WORK';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::LEAVE_HOURLY => __('Hourly Leave'),
            self::LEAVE_DAILY => __('Daily Leave'),
            self::SICK_LEAVE => __('Sick Leave'),
            self::LEAVE_WITHOUT_PAY => __('Leave Without Pay'),
            self::MISSION_HOURLY => __('Hourly Mission'),
            self::MISSION_DAILY => __('Daily Mission'),
            self::OVERTIME_ORDER => __('Overtime Order'),
            self::REMOTE_WORK => __('Remote Work'),
            self::OTHER => __('Other'),
        };
    }

    /** Returns all leave-related types. */
    public static function leaveTypes(): array
    {
        return [
            self::LEAVE_HOURLY,
            self::LEAVE_DAILY,
            self::SICK_LEAVE,
            self::LEAVE_WITHOUT_PAY,
        ];
    }

    /** Returns all mission-related types. */
    public static function missionTypes(): array
    {
        return [
            self::MISSION_HOURLY,
            self::MISSION_DAILY,
        ];
    }

    /** Returns all work-order-related types. */
    public static function workOrderTypes(): array
    {
        return [
            self::OVERTIME_ORDER,
            self::REMOTE_WORK,
        ];
    }

    /** Returns other types. */
    public static function otherTypes(): array
    {
        return [
            self::OTHER,
        ];
    }

    /** Returns an associative array of value => label for all cases. */
    public static function options(): array
    {
        return array_column(
            array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
