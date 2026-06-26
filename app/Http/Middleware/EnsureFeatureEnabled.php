<?php

namespace App\Http\Middleware;

use App\Support\FeatureSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route when its admin-toggleable feature is disabled. Usage:
 * ->middleware('feature:check_report'). Maps a feature key to its
 * FeatureSettings check; unknown keys are treated as enabled.
 */
class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $enabled = match ($feature) {
            'check_report' => FeatureSettings::checkReportEnabled(),
            'feedback' => FeatureSettings::feedbackEnabled(),
            default => true,
        };

        abort_unless($enabled, 404);

        return $next($request);
    }
}
