<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class StatutoryConfig extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'statutory_configs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'employee_rate' => 'decimal:5',
            'employer_rate' => 'decimal:5',
            'tier1_employer_rate' => 'decimal:5',
            'tier2_rate' => 'decimal:5',
            'bands' => 'array',
        ];
    }
}
