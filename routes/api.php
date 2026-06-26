<?php

use App\Http\Controllers\Api\V1\SampleReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API (v1)
|--------------------------------------------------------------------------
|
| Read-only endpoints third-party sites may call from the browser. CORS for
| these paths is configured in config/cors.php (allowed origins controlled by
| the CORS_ALLOWED_ORIGINS env var). No authentication is required — only the
| seeded sample reports are exposed.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/samples', [SampleReportController::class, 'index'])->name('samples.index');
    Route::get('/samples/{report:slug}', [SampleReportController::class, 'show'])->name('samples.show');
});
