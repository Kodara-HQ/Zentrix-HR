<?php

namespace App\Support;

use App\Models\Department;
use App\Models\DocumentCategory;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\JobTitle;
use App\Models\LeaveType;
use App\Models\PayrollComponent;
use App\Models\ShiftPattern;
use App\Models\WorkLocation;

/**
 * The simple lists under System settings, described once so one controller and one view handle them all.
 * Field types: text, number, date, time, select, bool, days (work-day checkboxes).
 */
class SettingsLists
{
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];

    public static function all(): array
    {
        return [
            'departments' => [
                'label' => 'Departments', 'model' => Department::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required|unique'],
                    'code' => ['Code', 'text', 'required|unique'],
                    'description' => ['Description', 'text'],
                ],
            ],
            'job-titles' => [
                'label' => 'Job titles', 'model' => JobTitle::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required'],
                    'department_id' => ['Department', 'select', 'nullable', fn () => Department::orderBy('name')->pluck('name', 'id')->all()],
                    'grade' => ['Grade', 'text'],
                    'description' => ['Description', 'text'],
                ],
            ],
            'employment-types' => [
                'label' => 'Employment types', 'model' => EmploymentType::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required|unique'],
                    'description' => ['Description', 'text'],
                ],
            ],
            'locations' => [
                'label' => 'Locations', 'model' => WorkLocation::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required'],
                    'branch' => ['Branch', 'text', 'required'],
                    'city' => ['City', 'text', 'required'],
                    'region' => ['Region', 'text', 'required'],
                    'address' => ['Address', 'text'],
                ],
            ],
            'leave-types' => [
                'label' => 'Leave types', 'model' => LeaveType::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required|unique'],
                    'code' => ['Code', 'text', 'required|unique'],
                    'days_per_year' => ['Days a year', 'number', 'required|min:0|max:366'],
                    'count_mode' => ['Counted in', 'select', 'required', fn () => ['WORKING_DAYS' => 'Working days', 'CALENDAR_DAYS' => 'Calendar days']],
                    'gender' => ['Only for', 'select', 'nullable', fn () => ['FEMALE' => 'Women', 'MALE' => 'Men']],
                    'affects_balance' => ['Uses a balance', 'bool'],
                    'paid' => ['Paid', 'bool'],
                    'requires_reason' => ['Reason required', 'bool'],
                ],
            ],
            'shifts' => [
                'label' => 'Shifts', 'model' => ShiftPattern::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required|unique'],
                    'start_time' => ['Reporting time', 'time', 'required'],
                    'end_time' => ['Closing time', 'time', 'required|after:start_time'],
                    'grace_minutes' => ['Grace minutes', 'number', 'required|integer|min:0|max:240'],
                    'work_days' => ['Work days', 'days', 'required'],
                ],
            ],
            'holidays' => [
                'label' => 'Holidays', 'model' => Holiday::class, 'order' => 'date',
                'fields' => [
                    'name' => ['Name', 'text', 'required'],
                    'date' => ['Date', 'date', 'required'],
                    'recurring' => ['Same date every year', 'bool'],
                ],
            ],
            'payroll-components' => [
                'label' => 'Pay components', 'model' => PayrollComponent::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required|unique'],
                    'code' => ['Code', 'text', 'required|unique'],
                    'kind' => ['Kind', 'select', 'required', fn () => ['ALLOWANCE' => 'Allowance', 'DEDUCTION' => 'Deduction (after tax)', 'RELIEF' => 'Tax relief']],
                    'taxable' => ['Taxable', 'bool'],
                    'description' => ['Description', 'text'],
                ],
            ],
            'document-categories' => [
                'label' => 'Document categories', 'model' => DocumentCategory::class, 'order' => 'name',
                'fields' => [
                    'name' => ['Name', 'text', 'required|unique'],
                    'description' => ['Description', 'text'],
                    'confidential' => ['Confidential', 'bool'],
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        abort_unless(array_key_exists($key, self::all()), 404);

        return self::all()[$key];
    }

    /** How a stored value reads in the list. */
    public static function display(array $field, mixed $value): string
    {
        [, $type] = $field;
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return match ($type) {
            'bool' => $value ? 'Yes' : 'No',
            'date' => Format::date($value),
            'days' => implode(', ', array_map(fn ($d) => self::DAYS[$d] ?? $d, array_intersect(array_keys(self::DAYS), (array) $value))),
            'select' => ($field[3] ?? null) ? ((($field[3])())[$value] ?? '—') : (string) $value,
            default => $value === null || $value === '' ? '—' : (string) $value,
        };
    }
}
