{{-- Google One Tap: an auto-prompt sign-in popup shown to signed-out visitors.
     Renders nothing unless the admin has enabled it AND a Google client id is
     configured. The returned credential (an ID token) is posted to the One Tap
     endpoint, which verifies it and signs the user in. --}}
@guest
    @if (\App\Support\AuthSettings::googleOneTapEnabled())
        <div id="g_id_onload"
             data-client_id="{{ \App\Support\AuthSettings::googleClientId() }}"
             data-login_uri="{{ route('auth.google.one-tap') }}"
             data-cancel_on_tap_outside="false"
             data-auto_prompt="true"></div>

        <script src="https://accounts.google.com/gsi/client" async defer></script>

        {{-- One Tap posts `credential` to login_uri as a normal form POST. Laravel
             needs the CSRF token, so we intercept and submit it ourselves. --}}
        <script>
            window.handleGoogleOneTap = function (response) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = @json(route('auth.google.one-tap'));

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = @json(csrf_token());
                form.appendChild(token);

                const credential = document.createElement('input');
                credential.type = 'hidden';
                credential.name = 'credential';
                credential.value = response.credential;
                form.appendChild(credential);

                document.body.appendChild(form);
                form.submit();
            };

            window.addEventListener('load', function () {
                if (!window.google || !google.accounts) return;

                google.accounts.id.initialize({
                    client_id: @json(\App\Support\AuthSettings::googleClientId()),
                    callback: window.handleGoogleOneTap,
                    cancel_on_tap_outside: false,
                });
                google.accounts.id.prompt();
            });
        </script>
    @endif
@endguest
