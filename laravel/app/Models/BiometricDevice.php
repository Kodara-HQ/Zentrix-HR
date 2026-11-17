<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class BiometricDevice extends Model
{
    use HasUlids;

    protected $table = 'biometric_devices';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
