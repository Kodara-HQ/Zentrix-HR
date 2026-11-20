<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class Applicant extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'applicants';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\ApplicantStatus::class,
            'screening_score' => 'decimal:2',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class, 'vacancy_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'applicant_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class, 'applicant_id');
    }
}
