@php
    // Single source of truth for the active palette; unknown or removed
    // themes fall back to the default one.
    $activeTheme = \App\Support\SiteTheme::active();
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="{{ $activeTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'VPN Market') }}</title>
    <link rel="stylesheet" href="{{ asset("themes/{$activeTheme}/css/style.css") }}">
    <link rel="stylesheet" href="{{ asset('themes/shared/theme-shell.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="theme-shell">
    <main class="theme-auth-page">{{ $slot }}</main>
</body>
</html>
