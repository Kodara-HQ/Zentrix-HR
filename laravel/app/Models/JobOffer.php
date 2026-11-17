<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class JobOffer extends Model
{
    use HasUlids;

    protected $table = 'job_offers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'start_date' => 'date',
            'status' => \App\Enums\OfferStatus::class,
            'expires_on' => 'date',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }
}
