<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class RatingScale extends Model
{
    use HasUlids;

    protected $table = 'rating_scales';

    protected $guarded = ['id'];

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'min' => 'integer',
            'max' => 'integer',
            'labels' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
