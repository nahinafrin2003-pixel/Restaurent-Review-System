<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign in') · RestoSafe</title>
    <link rel="stylesheet" href="/css/app.css?v=3">
</head>
<body>
    @include('partials.topbar')
    <div class="auth-wrap">
        <div class="auth-card card">
            @include('partials.flash')
            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
