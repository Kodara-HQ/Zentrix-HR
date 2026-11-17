<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class ManpowerRequisition extends Model
{
    use HasUlids;

    protected $table = 'manpower_requisitions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'headcount' => 'integer',
            'status' => \App\Enums\RequisitionStatus::class,
        ];
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class, 'requisition_id');
    }
}
