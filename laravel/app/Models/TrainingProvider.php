<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class TrainingProvider extends Model
{
    use HasUlids;

    protected $table = 'training_providers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TrainingProgram::class, 'provider_id');
    }
}
