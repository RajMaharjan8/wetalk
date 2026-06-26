<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Allow the request through only for authenticated admin users.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Allow full admins (is_admin / super-admin) and any staff member who
        // holds at least one role — their per-page permission is enforced by the
        // route's own `can:` gate.
        if (! $user || (! $user->isAdmin() && $user->roles->isEmpty())) {
            abort(403, 'Admins only.');
        }

        return $next($request);
    }
}
