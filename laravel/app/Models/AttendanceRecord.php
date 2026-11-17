<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class AttendanceRecord extends Model
{
    use HasUlids;

    protected $table = 'attendance_records';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'status' => \App\Enums\AttendanceStatus::class,
            'late_minutes' => 'integer',
            'early_departure_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'source' => \App\Enums\AttendanceSource::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'attendance_id');
    }
}
