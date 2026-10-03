<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'RestoSafe') · Restaurant Reviews & Consumer Complaints</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/css/app.css?v=3">
    @stack('head')
</head>
<body>
    @include('partials.topbar')
    <main class="container @yield('container_class')">
        @include('partials.flash')
        @yield('content')
    </main>
    <footer class="footer">RestoSafe · Restaurant review &amp; BSTI-style consumer complaint platform · Data encrypted at rest with custom RSA-2048 / secp256k1</footer>
    @stack('scripts')
</body>
</html>
