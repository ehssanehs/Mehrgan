@php
    // Single source of truth for the active palette; unknown or removed
    // themes fall back to the default one.
    $activeTheme = \App\Support\SiteTheme::active();
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="{{ $activeTheme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'VPN Market'))</title>
    @yield('meta_tags')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset("themes/{$activeTheme}/css/style.css") }}">
    <link rel="stylesheet" href="{{ asset('themes/shared/theme-shell.css') }}">
    @stack('styles')
</head>
<body class="theme-shell">
    @yield('content')
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>AOS.init({ duration: 800, once: true });</script>
    @stack('scripts')
</body>
</html>
