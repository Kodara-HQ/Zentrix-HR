<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class PayrollComponent extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'payroll_components';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => \App\Enums\PayItemKind::class,
            'taxable' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeePayItem::class, 'component_id');
    }
}
