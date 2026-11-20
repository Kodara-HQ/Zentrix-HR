<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\CompanySetup;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/**
 * Settings every company needs. Creates a first company on a new installation, then fills in any
 * missing settings for every company. Safe to run more than once.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(CompanySetup $setup): void
    {
        if (! Company::exists()) {
            $setup->create(['name' => 'Zentrix HR', 'legal_name' => 'Zentrix HR', 'city' => 'Accra']);
        }

        foreach (Company::all() as $company) {
            Tenant::run($company->id, fn () => $setup->addReferenceData());
        }
    }
}