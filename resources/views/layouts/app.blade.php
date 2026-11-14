@php
    // Single source of truth for the active palette; unknown or removed
    // themes fall back to the default one.
    $activeTheme = \App\Support\SiteTheme::active();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" data-theme="{{ $activeTheme }}">
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
    @include('layouts.navigation')
    @isset($header)
        <header class="theme-panel border-b">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
        </header>
    @endisset
    <main>{{ $slot }}</main>
    @stack('scripts')
</body>
</html>
