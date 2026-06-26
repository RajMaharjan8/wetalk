<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\SetLocale;
use App\Models\CoverTemplate;
use App\Models\CustomPage;
use App\Models\Payment;
use App\Models\Report;
use App\Support\Payments\PaymentSettings;
use App\Support\ReportCompiler;
use App\Support\ReportWord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Public marketing landing page. Signed-in users skip it and go straight to
// their dashboard.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('reports.index')
        : view('landing');
})->name('home');

// The landing page reachable directly (the logo links here), so signed-in users
// can view it too instead of being bounced to their dashboard like "/" does.
Route::get('/landing', fn () => view('landing'))->name('landing');

// Switch the UI language. Stored in a long-lived cookie and applied by the
// SetLocale middleware on every request. Available to guests too (login page).
Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

    return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale.switch');

// Public, SEO-friendly preview of a seeded sample report. Rendered with the
// same output view (Paged.js) the dashboard uses, but read-only and free — no
// auth, no toolbar, no payment gate. Shown to visitors inside an iframe from
// the landing page's sample cards.
Route::get('/samples/{report:slug}', function (Report $report) {
    abort_unless($report->is_sample, 404);

    return view('reports.output', [
        'report' => $report,
        'compiler' => ReportCompiler::for($report->load('sections')),
        'paymentRequired' => false,
        'downloadUnlocked' => true,
        'enabledGateways' => [],
        'downloadPrice' => 0,
        'sample' => true,
    ]);
})->name('samples.show');

// Guest auth: email/password sign-in & registration (with email OTP) alongside
// Google. Already-authenticated users are bounced to the app by each component.
Route::livewire('/login', 'pages::auth.login')->name('login');

// Email/password-only flows — redirected to /login when the admin has switched
// the app to Google-only sign-in (see EnsureEmailAuthEnabled).
Route::middleware('email-auth')->group(function () {
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/verify-otp', 'pages::auth.verify-otp')->name('verify-otp');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('forgot-password');
});

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

// Admin sign-in is password based (regular users use Google), so it lives
// outside the auth group and the admin gate.
Route::livewire('/admin/login', 'pages::admin.login')->name('admin.login');

Route::middleware(['auth', 'admin'])->group(function () {
    // The dashboard is the admin landing — open to any admin-area user. Its
    // settings actions are permission-checked inside the component.
    Route::livewire('/admin', 'pages::admin.dashboard')->name('admin.dashboard');

    // Each section is gated by its own permission (super-admins bypass via Gate::before).
    Route::livewire('/admin/users', 'pages::admin.users')->name('admin.users')->can('users.manage');
    Route::livewire('/admin/roles', 'pages::admin.roles')->name('admin.roles')->can('roles.manage');
    Route::livewire('/admin/feedback', 'pages::admin.feedback')->name('admin.feedback')->can('feedback.manage');
    Route::livewire('/admin/landing', 'pages::admin.landing')->name('admin.landing')->can('landing.manage');
    Route::livewire('/admin/transactions', 'pages::admin.transactions')->name('admin.transactions')->can('transactions.view');
    Route::livewire('/admin/mail', 'pages::admin.mail')->name('admin.mail')->can('mail.manage');
    Route::livewire('/admin/payments', 'pages::admin.payments')->name('admin.payments')->can('payments.manage');
    Route::livewire('/admin/settings', 'pages::admin.settings')->name('admin.settings')->can('settings.manage');
    Route::livewire('/admin/pages', 'pages::admin.pages')->name('admin.pages')->can('settings.manage');

    // Personal — any admin-area user can change their own password.
    Route::livewire('/admin/password', 'pages::admin.password')->name('admin.password');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'pages::reports-index')->name('reports.index');

    Route::livewire('/check', 'pages::report-check')->name('reports.check')->middleware('feature:check_report');

    Route::livewire('/reports/{report}/format-check', 'pages::report-live-check')
        ->name('reports.live-check')
        ->can('view', 'report');

    Route::livewire('/reports/create', 'pages::report-form')->name('reports.create');

    Route::livewire('/cover-templates', 'pages::cover-templates')->name('cover.templates');

    Route::livewire('/reports/{report}/edit', 'pages::report-form')
        ->name('reports.edit')
        ->can('update', 'report');

    Route::livewire('/reports/{report}/sections', 'pages::report-sections')
        ->name('reports.sections')
        ->can('update', 'report');

    Route::get('/reports/{report}/cover', function (Report $report, Request $request) {
        return view('reports.cover', [
            'report' => $report,
            'coverTemplates' => CoverTemplate::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    })->name('reports.cover')->can('update', 'report');

    Route::get('/reports/{report}/output', function (Report $report, Request $request) {
        // Payment gate: as long as at least one gateway is enabled, the
        // download (browser print) is locked until a verified payment arms the
        // one-shot session unlock — regardless of the configured price. Only
        // when BOTH gateways are disabled is the download free.
        $paymentRequired = PaymentSettings::anyEnabled();
        $unlockId = $request->session()->get(PaymentController::unlockKey($report));
        $downloadUnlocked = ! $paymentRequired
            || Payment::where('id', $unlockId)
                ->where('report_id', $report->id)
                ->get()
                ->contains(fn (Payment $payment) => $payment->isRedeemable());

        return view('reports.output', [
            'report' => $report,
            'compiler' => ReportCompiler::for($report->load('sections')),
            'paymentRequired' => $paymentRequired,
            'downloadUnlocked' => $downloadUnlocked,
            'enabledGateways' => PaymentSettings::enabledGateways(),
            'downloadPrice' => PaymentSettings::price(),
        ]);
    })->name('reports.output')->can('view', 'report');

    Route::get('/reports/{report}/docx', function (Report $report) {
        return ReportWord::download($report);
    })->name('reports.docx')->can('view', 'report');

    // Pay for a report download through an enabled gateway. The user must own
    // the report (view ability); each completed payment unlocks a single
    // download (spent via the consume endpoint when the print is taken).
    Route::post('/reports/{report}/pay/{gateway}', [PaymentController::class, 'start'])
        ->name('reports.pay')->can('view', 'report');

    Route::get('/reports/{report}/pay/esewa/callback', [PaymentController::class, 'esewaCallback'])
        ->name('reports.pay.esewa.callback')->can('view', 'report');

    Route::get('/reports/{report}/pay/khalti/callback', [PaymentController::class, 'khaltiCallback'])
        ->name('reports.pay.khalti.callback')->can('view', 'report');

    Route::post('/reports/{report}/download/consume', [PaymentController::class, 'consume'])
        ->name('reports.download.consume')->can('view', 'report');

    Route::post('/reports/{report}/cover/settings', function (Report $report, Request $request) {
        $request->validate([
            'abstract' => 'nullable|string|max:5000',
            'section_label' => 'nullable|string|max:30',
            'heading_align' => 'nullable|in:left,center,right',
            'heading_uppercase' => 'nullable|boolean',
            'page_number_align' => 'nullable|in:left,center,right',
            'margin_top' => 'nullable|numeric|min:0.25|max:3',
            'margin_right' => 'nullable|numeric|min:0.25|max:3',
            'margin_bottom' => 'nullable|numeric|min:0.25|max:3',
            'margin_left' => 'nullable|numeric|min:0.25|max:3',
        ]);

        $update = [];

        // Fields whose empty value falls back to a default rather than null.
        $defaults = ['page_number_align' => 'right', 'heading_align' => 'center'];

        foreach (['abstract', 'section_label', 'page_number_align', 'heading_align'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = trim((string) $request->input($field));
            $update[$field] = $value === '' ? ($defaults[$field] ?? null) : $value;
        }

        // The capitalize checkbox only submits when ticked, so resolve it from
        // the heading form's presence (signalled by the always-present select).
        if ($request->has('heading_align')) {
            $update['heading_uppercase'] = $request->boolean('heading_uppercase');
        }

        foreach (['margin_top', 'margin_right', 'margin_bottom', 'margin_left'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $update[$field] = (float) $request->input($field);
        }

        $report->update($update);

        return redirect()
            ->route('reports.cover', ['report' => $report])
            ->with('cover-saved', 'Saved.');
    })->name('reports.cover.settings')->can('update', 'report');

    // Persist hand-edited front pages (cover, declaration, recommendation,
    // certificate) from the preview's "Edit pages" mode.
    Route::post('/reports/{report}/front-overrides', function (Report $report, Request $request) {
        // No length cap: section/cover HTML can embed large base64 images, and
        // a tight limit silently rejected saves (the edit appeared to revert).
        $validated = $request->validate([
            'blocks' => 'required|array',
            'blocks.*' => 'nullable|string',
            'sections' => 'nullable|array',
            'sections.*' => 'nullable|string',
        ]);

        $allowed = $report->editableFrontBlocks();
        $overrides = $report->front_overrides ?? [];

        foreach ($validated['blocks'] as $key => $html) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $html = trim((string) $html);

            if ($html === '') {
                unset($overrides[$key]);
            } else {
                $overrides[$key] = $html;
            }
        }

        $report->update(['front_overrides' => $overrides === [] ? null : $overrides]);

        // Inline body / front-page edits save straight to the section source so
        // the compiler can re-apply heading numbers and citations on render.
        foreach ($validated['sections'] ?? [] as $id => $html) {
            $report->sections()->whereKey((int) $id)->first()?->update(['content' => (string) $html]);
        }

        return redirect()
            ->route('reports.output', ['report' => $report])
            ->with('cover-saved', 'Your edits were saved.');
    })->name('reports.front-overrides.save')->can('update', 'report');

    // Discard all hand-edits and fall back to the generated templates.
    Route::post('/reports/{report}/front-overrides/reset', function (Report $report) {
        $report->update(['front_overrides' => null]);

        return redirect()
            ->route('reports.output', ['report' => $report])
            ->with('cover-saved', 'Pages reset to the generated template.');
    })->name('reports.front-overrides.reset')->can('update', 'report');

    // Apply one of the user's saved custom covers to this report.
    Route::post('/reports/{report}/cover/use-template', function (Report $report, Request $request) {
        $validated = $request->validate(['template_id' => 'required|integer']);

        $template = CoverTemplate::where('user_id', $request->user()->id)
            ->findOrFail($validated['template_id']);

        // Wrap the designer's content in a self-contained A4 sheet so it
        // renders the same everywhere the cover override is shown.
        $overrides = $report->front_overrides ?? [];
        $overrides['cover'] = '<div class="cover-sheet-custom cover-custom">'.$template->html.'</div>';
        $report->update(['front_overrides' => $overrides]);

        return redirect()
            ->route('reports.cover', ['report' => $report])
            ->with('cover-saved', 'Applied your custom cover “'.$template->name.'”.');
    })->name('reports.cover.use-template')->can('update', 'report');
});

/*
|--------------------------------------------------------------------------
| Temporary deploy helpers (NO SHELL ACCESS)
|--------------------------------------------------------------------------
| Browser-triggered Artisan commands for hosts without SSH. Remove once the
| deploy is done.
*/
Route::get('/migrate', function () {
    Artisan::call('migrate', ['--force' => true]);

    return response('<pre>'.e(Artisan::output()).'</pre>');
});

Route::get('/seed', function () {
    Artisan::call('db:seed', ['--force' => true]);

    return response('<pre>'.e(Artisan::output()).'</pre>');
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');

    return response('<pre>'.e(Artisan::output()).'</pre>');
});

Route::get('/clear', function () {
    $log = [];

    foreach (['view:clear', 'route:clear', 'config:clear', 'cache:clear', 'event:clear'] as $command) {
        Artisan::call($command);
        $log[] = $command.' -> ok';
    }

    // Livewire 4 caches a compiled component manifest; stale entries from a
    // different machine break component discovery. Delete the whole cache dir.
    foreach ([storage_path('framework/cache/livewire-components.php'), storage_path('framework/views')] as $path) {
        if (File::isDirectory($path)) {
            foreach (File::glob($path.'/*.php') as $file) {
                File::delete($file);
            }
            $log[] = 'cleared dir '.$path;
        } elseif (File::exists($path)) {
            File::delete($path);
            $log[] = 'deleted '.$path;
        }
    }

    // Bootstrap caches (route/config/services) compiled on another machine.
    foreach (File::glob(base_path('bootstrap/cache').'/*.php') as $file) {
        if (! str_contains($file, 'packages.php') && ! str_contains($file, 'services.php')) {
            File::delete($file);
            $log[] = 'deleted '.$file;
        }
    }

    return response('<pre>'.e(implode("\n", $log))."\n\nDone. Try the app again.</pre>");
});

// Lists the page-component filenames as the server actually stored them, with
// the hex of the leading bytes so a mangled ⚡ (should be E2 9A A1) is visible.
Route::get('/debug-pages', function () {
    $dir = resource_path('views/pages');
    $rows = [];

    foreach (File::allFiles($dir) as $file) {
        $name = $file->getFilename();
        $hex = strtoupper(bin2hex(substr($name, 0, 6)));
        $rows[] = $hex.'   '.$name;
    }

    return response('<pre>'.e(implode("\n", $rows)).'</pre>');
});

/*
|--------------------------------------------------------------------------
| Admin-built custom pages (Privacy Policy, Terms, …)
|--------------------------------------------------------------------------
| Root-level catch-all — MUST stay last so it only matches slugs no other
| route claimed. Only published pages resolve; drafts and unknown slugs 404.
| Reserved slugs can never be saved (see CustomPage::RESERVED_SLUGS).
*/
Route::get('/{slug}', function (string $slug) {
    $page = CustomPage::published()->where('slug', $slug)->firstOrFail();

    return view('pages.custom-page', ['page' => $page]);
})->where('slug', '[a-z0-9-]+')->name('custom-page');
