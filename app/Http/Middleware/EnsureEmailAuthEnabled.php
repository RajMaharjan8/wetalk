<?php

namespace App\Http\Middleware;

use App\Support\AuthSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the email/password-only auth flows (register, OTP verify, password
 * reset) when the admin has switched the app to Google-only sign-in. The login
 * page itself is NOT guarded — it still hosts the Google button.
 */
class EnsureEmailAuthEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! AuthSettings::emailAuthEnabled()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
