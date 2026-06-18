<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** Locales the UI is translated into. */
    public const SUPPORTED = ['en', 'ne'];

    /**
     * Apply the user's chosen UI language (from the `locale` cookie) for the
     * request. Falls back to the app default when unset or unsupported.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->cookie('locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
