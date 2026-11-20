<?php

namespace App\Support;

use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Makes `exists:` and `unique:` validation rules look only at the current company's rows, so a form
 * cannot point at another company's records and names only need to be unique within a company.
 * Sign-in names and emails on `users` stay unique across the whole system.
 */
class TenantPresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table)
    {
        $query = parent::table($table);
        if ($table !== 'users' && Tenant::isTenantTable($table) && ($id = Tenant::id())) {
            $query->where('company_id', $id);
        }

        return $query;
    }
}
