<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="app-body">
    @include('partials.sidebar')

    <div class="sidebar-scrim" data-sidebar-scrim></div>

    <div class="app-main">
        @include('partials.topbar')

        <main class="app-content">
            @include('partials.flash')

            @yield('content')
        </main>
    </div>

    @include('partials.mobile-nav')
    @include('partials.confirm-modal')

    @stack('modals')
</body>
</html>
