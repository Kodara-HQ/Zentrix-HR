<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class Survey extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'surveys';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_open' => 'boolean',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'survey_id');
    }
}
