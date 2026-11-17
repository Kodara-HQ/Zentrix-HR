<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class Company extends Model
{
    use HasUlids;

    protected $table = 'companies';

    protected $guarded = ['id'];

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'next_employee_no' => 'integer',
        ];
    }
}
