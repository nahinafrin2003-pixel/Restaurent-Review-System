@extends('layouts.app')
@section('title', 'Profile')
@section('content')
    <div class="page-head"><div><h1>Your profile</h1><p class="sub">{{ $user->roleLabel() }} · member since {{ optional($user->created_at)->format('M Y') }}. Username, e-mail and phone are stored RSA-encrypted with SHA-256 lookup hashes.</p></div></div>
    <div class="split">
        <form method="POST" action="/profile" class="card">
            @csrf
            <h2>Account details</h2>
            <div class="form-grid">
                <div class="field"><label>Username</label><input name="username" value="{{ old('username', $user->username) }}" maxlength="10" required></div>
                <div class="field"><label>Mobile</label><input name="contact_info" value="{{ old('contact_info', $user->contact_info) }}" maxlength="11" required></div>
            </div>
            <div class="field"><label>E-mail</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
            <hr>
            <div class="form-grid">
                <div class="field"><label>New password <span class="muted">(optional)</span></label><input type="password" name="password" minlength="12" autocomplete="new-password"></div>
                <div class="field"><label>Confirm new password</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
            </div>
            <div class="field"><label>Current password</label><input type="password" name="current_password" required autocomplete="current-password"><div class="hint">Required to save any change. Changing your password signs out your other sessions.</div></div>
            <button class="btn">Save</button>
        </form>
        <div class="stack">
            @if($user->isCustomer())
                <div class="card">
                    <h3>Rewards · {{ (int) $user->points }} points</h3>
                    @foreach($catalog as $key => $b)
                        <div class="badge-card {{ in_array($key, $badges) ? '' : 'locked' }}" style="margin-bottom:8px"><div class="medal">{{ $b['icon'] }}</div><div><strong>{{ $b['label'] }}</strong><div class="small muted">{{ $b['rule'] }}</div></div></div>
                    @endforeach
                </div>
            @endif
            <div class="card">
                <h3>Active sessions</h3>
                @foreach($sessions as $s)
                    <div style="padding:6px 0;border-bottom:1px solid var(--line)" class="small">
                        Signed in {{ $s->created_at->diffForHumans() }} · last active {{ optional($s->last_activity)->diffForHumans() }}
                        @if($s->session_id === $currentSession)<span class="badge good">this device</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
