<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class TrainingNeed extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'training_needs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }
}
