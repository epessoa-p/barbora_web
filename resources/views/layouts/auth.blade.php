<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login - Barbora')</title>
    @include('layouts.partials.assets')
    @stack('styles')
</head>
<body class="auth-page">
    @yield('content')


    @stack('scripts')
</body>
</html>
