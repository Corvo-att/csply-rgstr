<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('admin.role:admin,manager')
 *
 * "Hiding a button" is not security (guide §10/§12) - this is what actually stops a
 * judge from calling a manager-only URL by hand.
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = Auth::guard('admin')->user();

        abort_unless($admin && in_array($admin->role, $roles, true), 403, 'You do not have permission to do that.');

        return $next($request);
    }
}
