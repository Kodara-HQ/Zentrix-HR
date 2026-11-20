<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Jobs for hosts without a shell or a long-running scheduler (Vercel). Callers send
 * "Authorization: Bearer <CRON_SECRET>"; without a configured secret these addresses are closed.
 */
class CronController extends Controller
{
    /** Daily reminders and payroll (Vercel Cron calls this once a day). */
    public function daily(Request $request): JsonResponse
    {
        $this->authorizeCron($request);

        $results = [];
        foreach (['hr:reminders', 'hr:payroll-month'] as $command) {
            $results[$command] = Artisan::call($command) === 0 ? 'ok' : 'failed';
        }

        return response()->json($results);
    }

    /**
     * Applies database changes after a deploy. If the host stops it part-way, call it again; it carries on.
     * `?system_admin=<username>` also makes that existing account the system administrator.
     */
    public function migrate(Request $request): JsonResponse
    {
        $this->authorizeCron($request);
        @set_time_limit(0);

        $results = ['migrate' => Artisan::call('migrate', ['--force' => true]) === 0 ? 'ok' : 'failed', 'output' => trim(Artisan::output())];
        if ($login = $request->query('system_admin')) {
            $results['system_admin'] = Artisan::call('hr:system-admin', ['login' => (string) $login]) === 0 ? 'ok' : 'failed';
        }

        return response()->json($results);
    }

    private function authorizeCron(Request $request): void
    {
        $secret = (string) config('services.cron.secret');
        abort_if($secret === '' || ! hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 404);
    }
}
