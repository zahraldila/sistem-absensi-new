{{-- ===================== --}}
{{-- SESSION TIMEOUT HANDLER --}}
{{-- Mirrors the server-side SessionTimeout middleware. --}}
{{-- Auto-redirects to login when session expires — no modal. --}}
{{-- ===================== --}}

@php
    // Sync with App\Http\Middleware\SessionTimeout::$timeout
    $sessionTimeoutSeconds = 1800; // set kecil untuk testing, ubah ke 1800 saat production
@endphp

<script>
(function () {
    var SESSION_TIMEOUT_MS = {{ $sessionTimeoutSeconds }} * 1000;
    var logoutTimer        = null;

    function redirectToLogin() {
        // Redirect langsung ke halaman login (tanpa submit form CSRF yang sudah kedaluwarsa)
        window.location.href = '{{ route('login') }}';
    }

    function scheduleTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(redirectToLogin, SESSION_TIMEOUT_MS);
    }

    // Reset timer on any user activity (debounced to max once per minute)
    var lastReset = Date.now();
    function onActivity() {
        var now = Date.now();
        if (now - lastReset < 60000) return;
        lastReset = now;
        scheduleTimer();
    }

    ['click', 'keydown', 'scroll', 'mousemove', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, onActivity, { passive: true });
    });

    scheduleTimer();
})();
</script>
