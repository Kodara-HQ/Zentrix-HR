<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class WorkingCalendar extends Model
{
    use HasUlids;

    protected $table = 'working_calendars';

    protected $guarded = ['id'];

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'hours_per_day' => 'decimal:2',
            'hours_per_month' => 'decimal:2',
            'grace_minutes' => 'integer',
            'overtime_weekday' => 'decimal:2',
            'overtime_weekend' => 'decimal:2',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
