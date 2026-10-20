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

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            transform: translateX(-100%);
            transition: transform 0.25s ease;
            background: linear-gradient(135deg, var(--primary-color) 0%, #093a72 100%);
            color: white;
            padding-top: 1rem;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: width 0.25s ease, padding 0.25s ease;
        }

        body.sidebar-open .sidebar {
            transform: translateX(0);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: var(--secondary-color);
        }

        .main-content {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        body.sidebar-collapsed .sidebar {
            width: 0;
            padding-left: 0;
            padding-right: 0;
        }

        body.sidebar-collapsed .main-content {
            margin-left: 0;
        }

        .navbar-top {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e0e0e0;
        }

        .navbar-top .navbar-brand {
            font-weight: 600;
            color: var(--primary-color);
        }

        .content {
            flex: 1;
            padding: 0.75rem;
        }

        @media (min-width: 992px) {
            .sidebar { transform: none; }

            .main-content {
                margin-left: 260px;
                transition: margin-left 0.25s ease;
            }

            .content { padding: 1rem; }

            body.sidebar-collapsed .sidebar { transform: translateX(-100%); }
            body.sidebar-collapsed .main-content { margin-left: 0; }
        }

        @media (hover: hover) {
            .sidebar .nav-link:hover {
                color: white;
                background-color: rgba(255, 255, 255, 0.1);
                border-left-color: var(--secondary-color);
            }
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
