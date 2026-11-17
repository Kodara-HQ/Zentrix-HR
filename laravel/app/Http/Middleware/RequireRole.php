<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: `role:HR_ADMIN,HR_MANAGER`. Super administrators always pass. */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(Access::hasRole($request->user(), $roles), 403, 'You do not have access to this page.');

        return $next($request);
    }
}
