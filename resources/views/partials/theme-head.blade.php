{{-- Apply the saved theme before paint (no flash of the wrong theme).
     Source of truth order: the `theme` cookie (works on every page, including
     standalone ones), then localStorage, then the OS preference. Toggling in
     the app writes both the cookie and localStorage (see resources/js/app.js). --}}
<script>
    (function () {
        try {
            var m = document.cookie.match(/(?:^|;\s*)theme=(dark|light)/);
            var t = m ? m[1] : localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    })();
</script>
