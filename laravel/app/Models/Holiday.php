<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class Holiday extends Model
{
    use HasUlids;

    protected $table = 'holidays';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recurring' => 'boolean',
        ];
    }
}
