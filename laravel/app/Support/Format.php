<?php

namespace App\Support;

use BackedEnum;
use DateTimeInterface;

/** Display helpers, ported from client/src/lib/format.ts. */
class Format
{
    public const ROLE_LABELS = [
        'SYSTEM_ADMIN' => 'System administrator',
        'SUPER_ADMIN' => 'Super administrator',
        'HR_ADMIN' => 'HR administrator',
        'HR_MANAGER' => 'HR manager',
        'DEPARTMENT_MANAGER' => 'Department manager',
        'SUPERVISOR' => 'Supervisor',
        'PAYROLL_OFFICER' => 'Payroll officer',
        'EMPLOYEE' => 'Employee',
        'RECRUITMENT_OFFICER' => 'Recruitment officer',
    ];

    public static function role(BackedEnum|string|null $role): string
    {
        $key = $role instanceof BackedEnum ? $role->value : (string) $role;

        return self::ROLE_LABELS[$key] ?? $key;
    }

    public static function date(DateTimeInterface|string|null $value): string
    {
        if (! $value) {
            return '—';
        }
        $date = $value instanceof DateTimeInterface ? $value : date_create(substr($value, 0, 10));

        return $date ? $date->format('j M Y') : (string) $value;
    }

    public static function time(DateTimeInterface|string|null $value): string
    {
        if (! $value) {
            return '—';
        }
        $date = $value instanceof DateTimeInterface ? $value : date_create($value);

        return $date ? $date->format('H:i') : '—';
    }

    public static function money(float|int|string|null $value): string
    {
        return 'GH₵'.number_format((float) ($value ?? 0), 2);
    }

    public static function wholeDays(float|int|string|null $value): string
    {
        return (string) round((float) ($value ?? 0));
    }

    public static function title(BackedEnum|string|null $value): string
    {
        $text = $value instanceof BackedEnum ? $value->value : $value;
        if (! $text) {
            return '—';
        }

        return ucwords(strtolower(str_replace('_', ' ', $text)));
    }

    public static function fullName(?object $person): string
    {
        if (! $person) {
            return '—';
        }

        return implode(' ', array_filter([$person->first_name ?? null, $person->middle_name ?? null, $person->last_name ?? null]));
    }

    /** Badge colour for a status value. */
    public static function tone(BackedEnum|string|null $status): string
    {
        $value = $status instanceof BackedEnum ? $status->value : (string) $status;

        return match (true) {
            in_array($value, ['ACTIVE', 'APPROVED', 'FULFILLED', 'HIRED', 'COMPLETED', 'PRESENT', 'OPEN'], true) => 'green',
            in_array($value, ['PENDING', 'PENDING_MANAGER', 'PENDING_HR', 'PROBATION', 'NOTICE', 'DRAFT', 'SENT', 'LATE'], true) => 'amber',
            in_array($value, ['REJECTED', 'SUSPENDED', 'TERMINATED', 'ABSENT', 'CANCELLED'], true) => 'red',
            $value === '' => 'slate',
            default => 'blue',
        };
    }
}
