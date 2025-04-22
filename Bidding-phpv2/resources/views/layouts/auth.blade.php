<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Sistema de Licitações') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div id="app">
        <main class="py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="text-center mb-4">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo" height="80">
                            <h1 class="mt-3">{{ config('app.name', 'Sistema de Licitações') }}</h1>
                        </div>

                        @yield('content')
                    </div>
                </div>
            </div>
        </main>

        <footer class="fixed-bottom bg-white py-3 border-top">
            <div class="container text-center">
                <p class="mb-0">&copy; {{ date('Y') }} - {{ config('app.name', 'Sistema de Licitações') }}</p>
            </div>
        </footer>
    </div>
</body>
</html>
