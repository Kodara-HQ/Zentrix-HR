<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keeps a model inside the current company: queries only see that company's rows and new rows are
 * stamped with it. When no company applies (system pages, guests) nothing is filtered.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if ($id = Tenant::id()) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $id);
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id && ($id = Tenant::id())) {
                $model->company_id = $id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
