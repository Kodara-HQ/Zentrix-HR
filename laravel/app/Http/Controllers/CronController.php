<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Daily jobs for hosts without a long-running scheduler (Vercel Cron calls this once a day).
 * Vercel sends "Authorization: Bearer <CRON_SECRET>"; without a configured secret the address is closed.
 */
class CronController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $secret = (string) config('services.cron.secret');
        abort_if($secret === '' || ! hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 404);

        $results = [];
        foreach (['hr:reminders', 'hr:payroll-month'] as $command) {
            $results[$command] = Artisan::call($command) === 0 ? 'ok' : 'failed';
        }

        return response()->json($results);
    }
}
