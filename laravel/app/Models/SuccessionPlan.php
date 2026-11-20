<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class SuccessionPlan extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'succession_plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'critical' => 'boolean',
        ];
    }

    public function incumbent(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'incumbent_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'successor_id');
    }
}
