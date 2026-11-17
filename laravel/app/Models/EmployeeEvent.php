<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class EmployeeEvent extends Model
{
    use HasUlids;

    protected $table = 'employee_events';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\EventType::class,
            'from_value' => 'array',
            'to_value' => 'array',
            'effective_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
