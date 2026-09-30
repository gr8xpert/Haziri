<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#047857">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/hazri-icon.svg" type="image/svg+xml">
    <title>@yield('title', 'Hazri') — {{ $company ?? 'Hazri' }}</title>
    @vite(['resources/css/app.css', 'resources/js/me.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    @yield('content')
</body>
</html>
