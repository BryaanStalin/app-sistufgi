<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', config('app.description', ''))">

    <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">

    @vite(['resources/css/guest.css', 'resources/js/guest.js'])

    <style>
        body {
            background: linear-gradient(135deg, #02182e 0%, #127cc2 100%);
            min-height: 100vh;
            min-height: 100dvh;
        }
    </style>

    @stack('head')
</head>
<body data-page="@yield('page')">
    <noscript>
        <div class="alert alert-warning text-center mb-0">
            Necesitás habilitar JavaScript para usar esta página.
        </div>
    </noscript>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>