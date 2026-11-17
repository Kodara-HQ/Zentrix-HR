<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every signed-in page: inactive accounts are signed out, a temporary password must be
 * changed first, and employee (ESS) accounts only reach their own pages.
 */
class HrPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'This account is no longer active.']);
        }

        if ($user->must_change_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit');
        }

        if ($user->role === Role::EMPLOYEE && ! $request->routeIs('password.*', 'logout') && ! Access::employeeMayOpen($request->path(), $user->employee_id)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
