<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class ClearanceItem extends Model
{
    use HasUlids;

    protected $table = 'clearance_items';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'returned' => 'boolean',
        ];
    }

    public function separation(): BelongsTo
    {
        return $this->belongsTo(Separation::class, 'separation_id');
    }
}
