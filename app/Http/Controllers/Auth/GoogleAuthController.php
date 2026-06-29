<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthSettings;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = $this->upsertGoogleUser(
            googleId: $googleUser->getId(),
            email: $googleUser->getEmail(),
            name: $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google user',
            avatar: $googleUser->getAvatar(),
        );

        Auth::login($user, remember: true);

        return redirect()->intended(route('reports.index'));
    }

    /**
     * Handle a Google One Tap sign-in: verify the ID-token credential posted by
     * the browser widget, then sign the matching user in (creating them on
     * first use, exactly like the OAuth callback).
     */
    public function oneTap(Request $request): RedirectResponse
    {
        abort_unless(AuthSettings::googleOneTapEnabled(), 404);

        $request->validate(['credential' => ['required', 'string']]);

        $claims = $this->verifyGoogleIdToken($request->string('credential')->toString());

        if ($claims === null || empty($claims['sub']) || empty($claims['email'])) {
            return redirect()->route('login')->with('error', 'Google sign-in failed. Please try again.');
        }

        $user = $this->upsertGoogleUser(
            googleId: (string) $claims['sub'],
            email: (string) $claims['email'],
            name: ($claims['name'] ?? null) ?: 'Google user',
            avatar: $claims['picture'] ?? null,
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('reports.index'));
    }

    /**
     * Verify a Google One Tap ID token (JWT): validates the signature against
     * Google's published public keys and checks the issuer and audience. Returns
     * the decoded claims, or null when verification fails.
     *
     * @return array<string, mixed>|null
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $certs = Cache::remember('google_oauth_certs', now()->addHours(6), function () {
                return Http::get('https://www.googleapis.com/oauth2/v3/certs')->throw()->json();
            });

            $keys = JWK::parseKeySet($certs);

            $claims = (array) JWT::decode($idToken, $keys);

            $validIssuer = in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
            $validAudience = ($claims['aud'] ?? null) === AuthSettings::googleClientId();

            if (! $validIssuer || ! $validAudience) {
                return null;
            }

            return $claims;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find or create the local user for a verified Google identity, backfilling
     * the google_id/avatar on an existing email-matched account.
     */
    private function upsertGoogleUser(string $googleId, string $email, string $name, ?string $avatar): User
    {
        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user === null) {
            return User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'avatar_url' => $avatar,
                'email_verified_at' => now(),
            ]);
        }

        if ($user->google_id === null) {
            $user->forceFill([
                'google_id' => $googleId,
                'avatar_url' => $avatar ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        return $user;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
