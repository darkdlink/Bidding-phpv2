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
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Sistema de Licitações') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            @can('acessar_dashboard')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                            </li>
                            @endcan

                            @can('visualizar_licitacoes')
                            <li class="nav-item dropdown">
                                <a id="licitacoesDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ __('Licitações') }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="licitacoesDropdown">
                                    <a class="dropdown-item" href="{{ route('licitacoes.index') }}">
                                        {{ __('Lista de Licitações') }}
                                    </a>
                                    @can('criar_licitacoes')
                                    <a class="dropdown-item" href="{{ route('licitacoes.create') }}">
                                        {{ __('Nova Licitação') }}
                                    </a>
                                    @endcan
                                </div>
                            </li>
                            @endcan

                            @can('acessar_relatorios')
                            <li class="nav-item dropdown">
                                <a id="relatoriosDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ __('Relatórios') }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="relatoriosDropdown">
                                    <a class="dropdown-item" href="{{ route('relatorios.index') }}">
                                        {{ __('Meus Relatórios') }}
                                    </a>
                                    @can('criar_relatorios')
                                    <a class="dropdown-item" href="{{ route('relatorios.create') }}">
                                        {{ __('Novo Relatório') }}
                                    </a>
                                    @endcan
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('relatorios.tipo', 'licitacoes_por_status') }}">
                                        {{ __('Licitações por Status') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('relatorios.tipo', 'licitacoes_por_periodo') }}">
                                        {{ __('Licitações por Período') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('relatorios.tipo', 'desempenho_anual') }}">
                                        {{ __('Desempenho Anual') }}
                                    </a>
                                </div>
                            </li>
                            @endcan

                            @can('acessar_calendario')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('calendario.index') }}">{{ __('Calendário') }}</a>
                            </li>
                            @endcan

                            @can('gerenciar_usuarios')
                            <li class="nav-item dropdown">
                                <a id="adminDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ __('Administração') }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                                    <a class="dropdown-item" href="{{ route('users.index') }}">
                                        {{ __('Usuários') }}
                                    </a>
                                    @can('gerenciar_papeis')
                                    <a class="dropdown-item" href="{{ route('roles.index') }}">
                                        {{ __('Papéis e Permissões') }}
                                    </a>
                                    @endcan
                                    @can('gerenciar_configuracoes')
                                    <a class="dropdown-item" href="{{ route('admin.settings') }}">
                                        {{ __('Configurações') }}
                                    </a>
                                    @endcan
                                    @can('acessar_coleta')
                                    <a class="dropdown-item" href="{{ route('admin.scraping') }}">
                                        {{ __('Coleta Automática') }}
                                    </a>
                                    @endcan
                                </div>
                            </li>
                            @endcan
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif
                        @else
                            <!-- Notificações -->
                            <li class="nav-item dropdown">
                                <a id="notificationsDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    <i class="fas fa-bell"></i>
                                    <span class="badge rounded-pill bg-danger" id="notificationsCounter">0</span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 300px;">
                                    <h6 class="dropdown-header">{{ __('Notificações') }}</h6>
                                    <div id="notificationsList" style="max-height: 300px; overflow-y: auto;">
                                        <div class="dropdown-item text-center">
                                            <small>{{ __('Carregando...') }}</small>
                                        </div>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-center" href="{{ route('notificacoes.index') }}">
                                        <small>{{ __('Ver todas') }}</small>
                                    </a>
                                </div>
                            </li>

                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('profile.show') }}">
                                        {{ __('Meu Perfil') }}
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>

        <footer class="bg-light py-3 mt-auto">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; {{ date('Y') }} - {{ config('app.name', 'Sistema de Licitações') }}</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-0">Versão {{ config('app.version', '1.0.0') }}</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    @stack('scripts')

    @auth
    <script>
        // Script para carregar notificações
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();

            // Atualizar a cada 60 segundos
            setInterval(loadNotifications, 60000);
        });

        function loadNotifications() {
            fetch('{{ route("notificacoes.nao-lidas") }}')
                .then(response => response.json())
                .then(data => {
                    const counter = document.getElementById('notificationsCounter');
                    const list = document.getElementById('notificationsList');

                    counter.textContent = data.count;

                    if (data.count > 0) {
                        let html = '';
                        data.notifications.forEach(notification => {
                            html += `
                                <a class="dropdown-item" href="#">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-bell text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="small">${notification.created_at}</div>
                                            <span class="fw-bold">${notification.titulo}</span>
                                        </div>
                                    </div>
                                </a>
                            `;
                        });
                        list.innerHTML = html;
                    } else {
                        list.innerHTML = `
                            <div class="dropdown-item text-center">
                                <small>{{ __('Não há novas notificações') }}</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar notificações:', error);
                });
        }
    </script>
    @endauth
</body>
</html>
