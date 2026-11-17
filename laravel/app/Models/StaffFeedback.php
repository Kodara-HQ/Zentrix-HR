<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class StaffFeedback extends Model
{
    use HasUlids;

    protected $table = 'staff_feedbacks';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'anonymous' => 'boolean',
        ];
    }
}
