<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Company;
use App\Support\Access;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every signed-in page: inactive accounts (or companies) are signed out, a temporary password
 * must be changed first, employee (ESS) accounts only reach their own pages, and a system administrator
 * stays on the Companies pages until they open a company.
 */
class HrPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $own = $request->routeIs('password.*', 'logout');

        if (! $user->is_active) {
            return $this->signOut($request, 'This account is no longer active.');
        }

        if ($user->must_change_password && ! $own) {
            return redirect()->route('password.edit');
        }

        if ($user->isSystemAdmin()) {
            $companyId = $request->session()->get(Tenant::SESSION_KEY);
            if ($companyId && Company::whereKey($companyId)->exists()) {
                $user->actAsCompanyAdmin($companyId);

                return $next($request);
            }
            $request->session()->forget(Tenant::SESSION_KEY);

            return $request->routeIs('system.*') || $own ? $next($request) : redirect()->route('system.companies.index');
        }

        abort_if($request->routeIs('system.*'), 403, 'Only the system administrator can manage companies.');

        if (! Company::whereKey($user->company_id)->where('is_active', true)->exists()) {
            return $this->signOut($request, "Your company's account is suspended. Contact the system administrator.");
        }

        if ($user->role === Role::EMPLOYEE && ! $own && ! Access::employeeMayOpen($request->path(), $user->employee_id)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }

    private function signOut(Request $request, string $message): Response
    {
        auth()->logout();
        $request->session()->invalidate();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
