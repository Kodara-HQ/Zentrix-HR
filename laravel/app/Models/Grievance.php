<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class Grievance extends Model
{
    use HasUlids;

    protected $table = 'grievances';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\CaseStatus::class,
            'confidential' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
