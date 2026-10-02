<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f4f5" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/hazri-icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/hazri-icon.svg">
    <title>@yield('title', 'Hazri') | {{ $company ?? 'Hazri' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/me.js'])
</head>
<body class="min-h-dvh bg-zinc-100 text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">
    @yield('content')
</body>
</html>
