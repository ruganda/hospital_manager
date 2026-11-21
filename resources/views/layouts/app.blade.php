<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('clinic.name') }}</title>
    @include('partials.head')
    @stack('styles')
</head>
<body>
    <a href="#main" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-white" style="z-index:2000">Skip to content</a>
    @include('partials.sidebar')

    <div class="cc-main d-flex flex-column min-vh-100">
        @include('partials.topbar')

        <main id="main" class="cc-content flex-grow-1">
            @include('partials.flash')
            @yield('content')
        </main>

        <footer class="cc-footer d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; {{ date('Y') }} {{ config('clinic.name') }}</span>
            <span>{{ config('clinic.phone') }} · {{ config('clinic.hours') }}</span>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
