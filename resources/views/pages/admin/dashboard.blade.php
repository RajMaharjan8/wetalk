<?php

use App\Models\Download;
use App\Models\Feedback;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuthSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    /** Whether email + password login/registration is offered (else Google-only). */
    public bool $emailAuthEnabled = true;

    /** Whether the Google One Tap prompt shows on the landing & login pages. */
    public bool $googleOneTapEnabled = false;

    public function mount(): void
    {
        $this->emailAuthEnabled = AuthSettings::emailAuthEnabled();
        $this->googleOneTapEnabled = AuthSettings::googleOneTapEnabled();
    }

    /** Toggle email/password auth on or off (live, from the switch). */
    public function updatedEmailAuthEnabled(bool $value): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        Setting::set('email_auth_enabled', $value ? '1' : '0');

        session()->flash('limits-saved', $value
            ? 'Email & password sign-in enabled.'
            : 'Email & password sign-in disabled — users sign in with Google only.');
    }

    /** Toggle the Google One Tap prompt on or off (live, from the switch). */
    public function updatedGoogleOneTapEnabled(bool $value): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        Setting::set('google_one_tap_enabled', $value ? '1' : '0');

        session()->flash('limits-saved', $value
            ? 'Google One Tap prompt enabled.'
            : 'Google One Tap prompt disabled.');
    }

    public function getStatsProperty(): array
    {
        return [
            'users' => User::count(),
            'online' => User::where('last_active_at', '>=', now()->subMinutes(5))->count(),
            'suspended' => User::whereNotNull('suspended_at')->count(),
            'reports' => Report::count(),
            'feedback' => Feedback::count(),
            'downloads' => Download::count(),
            'revenue' => (float) Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount'),
        ];
    }

    /**
     * Download counts per cover type, in a fixed display order with zero
     * defaults so every category always shows.
     *
     * @return array<string, int>
     */
    public function getDownloadBreakdownProperty(): array
    {
        $counts = Download::query()
            ->selectRaw('cover_type, count(*) as total')
            ->groupBy('cover_type')
            ->pluck('total', 'cover_type');

        $breakdown = [];

        foreach (Download::COVER_LABELS as $key => $label) {
            $breakdown[$key] = (int) ($counts[$key] ?? 0);
        }

        return $breakdown;
    }

    /** @return \Illuminate\Support\Collection<int, Payment> */
    public function getRecentTransactionsProperty()
    {
        return Payment::with(['user', 'report'])
            ->where('status', Payment::STATUS_COMPLETED)
            ->latest()
            ->take(5)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Feedback> */
    public function getRecentFeedbackProperty()
    {
        return Feedback::with('user')->latest()->take(5)->get();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function getRecentUsersProperty()
    {
        return User::latest()->take(5)->get();
    }

    /**
     * Stat cards with their resolved icon path and accent classes.
     *
     * @return list<array{label: string, value: int, badge: string, icon: string}>
     */
    public function getCardsProperty(): array
    {
        $stats = $this->stats;

        return [
            ['label' => 'Total users', 'value' => $stats['users'], 'badge' => 'bg-indigo-50 text-indigo-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />'],
            ['label' => 'Online now', 'value' => $stats['online'], 'badge' => 'bg-green-50 text-green-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0112 12.75z" />'],
            ['label' => 'Suspended', 'value' => $stats['suspended'], 'badge' => 'bg-red-50 text-red-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />'],
            ['label' => 'Reports', 'value' => $stats['reports'], 'badge' => 'bg-slate-100 text-slate-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />'],
            ['label' => 'Feedback', 'value' => $stats['feedback'], 'badge' => 'bg-amber-50 text-amber-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />'],
            ['label' => 'Downloads', 'value' => $stats['downloads'], 'badge' => 'bg-sky-50 text-sky-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />'],
        ];
    }
}; ?>

@php($title = 'Dashboard')

<div class="space-y-8" x-data="{ settingsOpen: false }">
    @if (session('limits-saved'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            {{ session('limits-saved') }}
        </div>
    @endif

    {{-- ============ Hero header ============ --}}
    <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-indigo-600 via-indigo-600 to-violet-700 px-6 py-7 text-white shadow-lg sm:px-8">
        <div class="absolute -right-10 -top-10 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-12 right-24 h-40 w-40 rounded-full bg-white/5 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-100">{{ now()->format('l, F j, Y') }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">Welcome back, {{ auth()->user()->name }}</h1>
                <p class="mt-1 text-sm text-indigo-100">Here's what's happening across {{ config('app.name') }} today.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <p class="text-xs font-medium text-indigo-100">Revenue</p>
                    <p class="text-lg font-bold">Rs. {{ number_format($this->stats['revenue'], 0) }}</p>
                </div>
                @can('settings.manage')
                <button type="button" x-on:click="settingsOpen = !settingsOpen"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-3 text-sm font-semibold backdrop-blur transition hover:bg-white/25">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Settings
                </button>
                @endcan
            </div>
        </div>
    </div>

    {{-- ============ Settings panel (collapsible) ============ --}}
    @can('settings.manage')
    <div x-show="settingsOpen" x-collapse x-cloak>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            {{-- Email/password auth toggle --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Email &amp; password sign-in</h2>
                        <p class="mt-1 text-sm text-slate-500">When off, users can only sign in / sign up with Google. Registration, OTP and password-reset pages are closed.</p>
                    </div>
                    <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                        <input type="checkbox" wire:model.live="emailAuthEnabled" class="peer sr-only">
                        <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
                    </label>
                </div>
                <p class="mt-4 inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-medium {{ $emailAuthEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $emailAuthEnabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    {{ $emailAuthEnabled ? 'Email & password enabled' : 'Google-only mode' }}
                </p>
            </div>

            {{-- Google One Tap toggle --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Google One Tap prompt</h2>
                        <p class="mt-1 text-sm text-slate-500">Shows a one-tap Google sign-in popup to signed-out visitors on the landing &amp; login pages. Requires a configured Google client id.</p>
                    </div>
                    <label class="relative inline-flex shrink-0 cursor-pointer items-center {{ AuthSettings::googleClientId() ? '' : 'pointer-events-none opacity-50' }}">
                        <input type="checkbox" wire:model.live="googleOneTapEnabled" @disabled(! AuthSettings::googleClientId()) class="peer sr-only">
                        <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
                    </label>
                </div>
                <p class="mt-4 inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-medium {{ $googleOneTapEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $googleOneTapEnabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    {{ AuthSettings::googleClientId() ? ($googleOneTapEnabled ? 'One Tap enabled' : 'One Tap disabled') : 'Google client id not configured' }}
                </p>
            </div>
        </div>
    </div>
    @endcan

    {{-- ============ Stat cards ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($this->cards as $card)
            <div class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $card['badge'] }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">{!! $card['icon'] !!}</svg>
                </span>
                <p class="mt-4 text-2xl font-bold tracking-tight text-slate-900">{{ number_format($card['value']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-slate-500">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Downloads by cover type + recent transactions --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Downloads by cover</h2>
            @php($totalDownloads = array_sum($this->downloadBreakdown))
            <div class="mt-5 space-y-4">
                @foreach ($this->downloadBreakdown as $type => $count)
                    @php($pct = $totalDownloads > 0 ? round($count / $totalDownloads * 100) : 0)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700">{{ \App\Models\Download::COVER_LABELS[$type] }}</span>
                            <span class="text-slate-500">{{ number_format($count) }} <span class="text-slate-300">·</span> {{ $pct }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ ['tu' => 'bg-indigo-500', 'london_met' => 'bg-sky-500', 'custom' => 'bg-emerald-500'][$type] }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-500">
                Total downloads <span class="font-semibold text-slate-900">{{ number_format($totalDownloads) }}</span>
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Recent transactions</h2>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500">Revenue <span class="font-semibold text-slate-900">Rs. {{ number_format($this->stats['revenue'], 2) }}</span></span>
                    <a href="{{ route('admin.transactions') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all</a>
                </div>
            </div>

            @if ($this->recentTransactions->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-400">No completed transactions yet.</div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($this->recentTransactions as $tx)
                        <li class="flex items-center gap-3 px-6 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $tx->user?->initials() ?? '—' }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $tx->user?->name ?? 'Unknown user' }}</p>
                                <p class="truncate text-xs text-slate-400">{{ ucfirst($tx->gateway) }} &middot; {{ $tx->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-slate-900">Rs. {{ number_format($tx->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Recent feedback</h2>
                <a href="{{ route('admin.feedback') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all</a>
            </div>

            @if ($this->recentFeedback->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-400">No feedback submitted yet.</div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($this->recentFeedback as $item)
                        <li class="px-6 py-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-900">{{ $item->user?->name ?? 'Anonymous' }}</span>
                                <span class="text-xs text-slate-400">{{ $item->created_at->diffForHumans() }}</span>
                            </div>
                            @if ($item->working)
                                <p class="mt-1 text-sm text-slate-600"><span class="font-medium text-green-700">Working:</span> {{ \Illuminate\Support\Str::limit($item->working, 140) }}</p>
                            @endif
                            @if ($item->not_working)
                                <p class="mt-0.5 text-sm text-slate-600"><span class="font-medium text-red-700">Not working:</span> {{ \Illuminate\Support\Str::limit($item->not_working, 140) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Newest users</h2>
                <a href="{{ route('admin.users') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Manage</a>
            </div>

            @if ($this->recentUsers->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-400">No users yet.</div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($this->recentUsers as $user)
                        <li class="flex items-center gap-3 px-6 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $user->initials() }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-slate-400">{{ $user->email }}</p>
                            </div>
                            @if ($user->isSuspended())
                                <span class="ml-auto rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Suspended</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
