<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class Separation extends Model
{
    use HasUlids;

    protected $table = 'separations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\SeparationType::class,
            'notice_date' => 'date',
            'last_working_day' => 'date',
            'status' => \App\Enums\SeparationStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function clearance(): HasMany
    {
        return $this->hasMany(ClearanceItem::class, 'separation_id');
    }
}
