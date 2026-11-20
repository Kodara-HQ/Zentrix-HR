<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\EmployeeService;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Fills a company that has no employees yet with the 18 demo staff, for trying the system out.
 * Demo sign-ins use first.last@<company>.gh and the password in DemoSeeder::PASSWORD.
 */
class AddDemoData extends Command
{
    protected $signature = 'hr:demo {company : Company name or id}';

    protected $description = 'Add the demo staff to a company that has no employees yet';

    public function handle(EmployeeService $employees): int
    {
        $key = trim($this->argument('company'));
        $company = Company::whereKey($key)->orWhere('name', $key)->first();
        if (! $company) {
            $this->error("No company {$key}.");

            return self::FAILURE;
        }

        $seeder = app(DemoSeeder::class)->setContainer(app())->setCommand($this);

        return $seeder->forCompany($company, $employees, self::domain($company->name)) ? self::SUCCESS : self::FAILURE;
    }

    /** "Danlice HR" -> "danlice.gh" */
    public static function domain(string $name): string
    {
        $words = array_diff(explode('-', Str::slug($name)), ['hr', 'ltd', 'limited', 'company', 'plc']);

        return (implode('', $words) ?: Str::slug($name, '')).'.gh';
    }
}
