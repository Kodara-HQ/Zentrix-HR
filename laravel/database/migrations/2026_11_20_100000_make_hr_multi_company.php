<?php

use App\Support\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several companies in one database: every company table gets a company_id, existing rows go to the
 * company that already exists, and names that were unique system-wide become unique per company.
 *
 * Each step checks whether it already ran, so if the host stops the request part-way (Vercel allows
 * 60 seconds) running the migration again carries on where it stopped.
 */
return new class extends Migration
{
    /** Columns that were unique across the whole system and are now unique within a company. */
    private const UNIQUES = [
        'departments' => [['name'], ['code']],
        'employment_types' => [['name']],
        'work_locations' => [['name', 'branch']],
        'notification_settings' => [['event_key']],
        'document_categories' => [['name']],
        'leave_types' => [['name'], ['code']],
        'shift_patterns' => [['name']],
        'holidays' => [['name', 'date']],
        'payroll_components' => [['name'], ['code']],
        'workflow_configs' => [['key']],
        'rating_scales' => [['name']],
        'employees' => [['employee_no'], ['email']],
        'payroll_runs' => [['year', 'month']],
        'training_providers' => [['name']],
        'disciplinary_cases' => [['case_no']],
    ];

    /** Rows that may exist without a company: the system administrator and what they do outside a company. */
    private const NULLABLE = ['users', 'audit_logs', 'notifications'];

    public function up(): void
    {
        @set_time_limit(0);

        if (! Schema::hasColumn('companies', 'is_active')) {
            DB::statement('ALTER TABLE companies ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN created_at DATETIME NULL');
        }

        $companyId = DB::table('companies')->orderBy('id')->value('id');

        foreach (Tenant::TABLES as $table) {
            if (! Schema::hasColumn($table, 'company_id')) {
                $nullable = in_array($table, self::NULLABLE, true);
                // Existing rows get the existing company through the default, which is dropped straight after.
                $column = $nullable || ! $companyId
                    ? 'company_id VARCHAR(30) '.($nullable ? 'NULL' : 'NOT NULL')
                    : 'company_id VARCHAR(30) NOT NULL DEFAULT '.DB::getPdo()->quote($companyId);
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN {$column} AFTER id, ADD INDEX `{$table}_company_id_index` (company_id)");
                if ($nullable && $companyId) {
                    DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
                }
                if (! $nullable && $companyId) {
                    DB::statement("ALTER TABLE `{$table}` ALTER COLUMN company_id DROP DEFAULT");
                }
            }

            foreach (self::UNIQUES[$table] ?? [] as $columns) {
                $old = $table.'_'.implode('_', $columns).'_unique';
                $new = $table.'_company_id_'.implode('_', $columns).'_unique';
                if (! $this->hasIndex($table, $new)) {
                    $drop = $this->hasIndex($table, $old) ? "DROP INDEX `{$old}`, " : '';
                    $list = implode(', ', array_map(fn ($c) => "`{$c}`", ['company_id', ...$columns]));
                    DB::statement("ALTER TABLE `{$table}` {$drop}ADD UNIQUE `{$new}` ({$list})");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (Tenant::TABLES as $table) {
            foreach (self::UNIQUES[$table] ?? [] as $columns) {
                $new = $table.'_company_id_'.implode('_', $columns).'_unique';
                if ($this->hasIndex($table, $new)) {
                    $old = $table.'_'.implode('_', $columns).'_unique';
                    $list = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$new}`, ADD UNIQUE `{$old}` ({$list})");
                }
            }
            if (Schema::hasColumn($table, 'company_id')) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$table}_company_id_index`, DROP COLUMN company_id");
            }
        }
        if (Schema::hasColumn('companies', 'is_active')) {
            DB::statement('ALTER TABLE companies DROP COLUMN is_active, DROP COLUMN created_at');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $index);
    }
};
