<?php

namespace App\Support;

use App\Models\Department;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobTitle;
use App\Models\PayrollComponent;
use App\Models\WorkLocation;

/** Option lists for forms, ported from GET /settings/lookups. */
class Lookups
{
    public const GENDERS = ['FEMALE', 'MALE', 'OTHER', 'UNDISCLOSED'];

    public const STATUSES = ['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED', 'INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'];

    public static function departments(): array
    {
        return Department::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function jobTitles(): array
    {
        return JobTitle::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function employmentTypes(): array
    {
        return EmploymentType::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function locations(): array
    {
        return WorkLocation::orderBy('name')->get()->mapWithKeys(fn ($l) => [$l->id => "{$l->name}, {$l->city}"])->all();
    }

    public static function managers(): array
    {
        return Employee::whereNotIn('status', ['INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'])
            ->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->first_name} {$e->last_name}"])->all();
    }

    public static function categories(): array
    {
        return DocumentCategory::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function payComponents(): array
    {
        return PayrollComponent::orderBy('name')->pluck('name', 'id')->all();
    }
}
