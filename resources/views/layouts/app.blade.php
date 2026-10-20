<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', config('app.description', ''))">
    
    <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')

    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ecf0f1;
        }
    </style>

    @yield('styles')
</head>
<body data-page="@yield('script')">
    <div class="d-flex min-vh-100">
        @include('partials.sidebar')

        <div class="main-content flex-grow-1 d-flex flex-column">
            @include('partials.navbar')

            <div class="content flex-grow-1">
                @yield('content')
            </div>
        </div>
    </div>
                
    @stack('scripts')
</body>
</html>
