<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class WorkflowConfig extends Model
{
    use HasUlids;

    protected $table = 'workflow_configs';

    protected $guarded = ['id'];

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'steps' => 'array',
        ];
    }
}
