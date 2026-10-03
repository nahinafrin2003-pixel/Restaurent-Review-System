@extends('layouts.auth')
@section('title', 'Two-factor verification')
@section('content')
    <h1>Check your e-mail</h1>
    <p>We sent a 6-digit code to <strong>{{ $verificationEmail }}</strong>. It expires 60 seconds after it was sent.</p>
    <p id="otp-timer" class="small" style="font-weight:600"></p>

    <form method="POST" action="/verify-2fa" onsubmit="document.getElementById('vbtn').disabled=true">
        @csrf
        <div class="field">
            <label for="verification_code">Verification code</label>
            <input type="text" id="verification_code" name="verification_code" class="otp-input" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
        </div>
        <button type="submit" class="btn block" id="vbtn">Verify &amp; sign in</button>
    </form>

    <form method="POST" action="/resend-2fa" id="resend-form" style="margin-top:12px; display:none">
        @csrf
        <button type="submit" class="btn ghost block">Send a new code</button>
    </form>
    <p class="small" style="margin-top:14px"><a href="/login">← Use a different account</a></p>

    @push('scripts')
    <script>
        (function () {
            var seconds = {{ (int) $timeRemaining }};
            var el = document.getElementById('otp-timer');
            var resend = document.getElementById('resend-form');
            function tick() {
                if (seconds <= 0) {
                    el.textContent = 'Your code has expired — request a new one below.';
                    el.style.color = '#b42318';
                    resend.style.display = 'block';
                    return;
                }
                el.textContent = 'Code expires in 0:' + String(seconds).padStart(2, '0');
                seconds--;
                setTimeout(tick, 1000);
            }
            tick();
        })();
    </script>
    @endpush
@endsection
