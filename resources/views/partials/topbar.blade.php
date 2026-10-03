@php
    $u = $authUser ?? null;
    $path = '/' . ltrim(request()->path(), '/');
    $is = fn ($prefix) => $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
@endphp
<header class="topbar">
    <div class="topbar-inner">
        <a href="{{ $u ? $u->homePath() : '/' }}" class="logo"><span class="logo-mark">RS</span> <span>RestoSafe</span></a>
        <button class="btn light sm nav-toggle" type="button" onclick="document.getElementById('mainnav').classList.toggle('open')" aria-label="Menu">☰</button>
        <nav class="nav" id="mainnav">
            @if(! $u)
                <a href="/" class="{{ $is('/') ? 'active' : '' }}">Home</a>
                <a href="/restaurants" class="{{ $is('/restaurants') ? 'active' : '' }}">Restaurants</a>
                <a href="/transparency" class="{{ $is('/transparency') ? 'active' : '' }}">Transparency</a>
            @elseif($u->isAdmin())
                <a href="/admin/dashboard" class="{{ $is('/admin/dashboard') ? 'active' : '' }}">Admin</a>
                <a href="/admin/pending-requests" class="{{ $is('/admin/pending') ? 'active' : '' }}">Review queue</a>
                <a href="/officer" class="{{ $path === '/officer' || $is('/officer/complaints') ? 'active' : '' }}">Complaints</a>
                <a href="/officer/analytics" class="{{ $is('/officer/analytics') || $is('/officer/heatmap') ? 'active' : '' }}">Analytics</a>
                <a href="/admin/officers" class="{{ $is('/admin/officers') || $is('/admin/users') ? 'active' : '' }}">People</a>
                <a href="/admin/audit" class="{{ $is('/admin/audit') ? 'active' : '' }}">Audit log</a>
                <a href="/admin/keys" class="{{ $is('/admin/keys') ? 'active' : '' }}">Keys</a>
            @elseif($u->isOfficer())
                <a href="/officer" class="{{ $path === '/officer' || $is('/officer/complaints') ? 'active' : '' }}">Verification queue</a>
                <a href="/officer/analytics" class="{{ $is('/officer/analytics') ? 'active' : '' }}">Analytics</a>
                <a href="/officer/heatmap" class="{{ $is('/officer/heatmap') ? 'active' : '' }}">Heatmap</a>
                <a href="/restaurants" class="{{ $is('/restaurants') ? 'active' : '' }}">Restaurants</a>
                <a href="/transparency" class="{{ $is('/transparency') ? 'active' : '' }}">Transparency</a>
            @else
                <a href="/dashboard" class="{{ $is('/dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="/complaints" class="{{ $is('/complaints') ? 'active' : '' }}">My complaints</a>
                <a href="/reviews" class="{{ $is('/reviews') || $is('/user/reviews') ? 'active' : '' }}">Reviews</a>
                <a href="/restaurants" class="{{ $is('/restaurants') ? 'active' : '' }}">Restaurants</a>
                <a href="/rewards" class="{{ $is('/rewards') ? 'active' : '' }}">Rewards</a>
                <a href="/transparency" class="{{ $is('/transparency') ? 'active' : '' }}">Transparency</a>
            @endif
        </nav>
        <div class="nav-right">
            @if($u)
                <a href="/profile" class="whoami" style="color:inherit;text-decoration:none">
                    <strong>{{ $u->username ?: 'Account' }}</strong>
                    <span class="role-chip">{{ $u->roleLabel() }}</span>
                </a>
                <form method="POST" action="/logout" class="inline">@csrf<button class="btn light sm" type="submit">Log out</button></form>
            @else
                <a href="/login" class="btn light sm">Log in</a>
                <a href="/register" class="btn accent sm">Register</a>
            @endif
        </div>
    </div>
</header>
