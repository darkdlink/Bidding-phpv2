<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Licitacoes\LicitacaoController;
use App\Http\Controllers\Licitacoes\DocumentoController;
use App\Http\Controllers\Licitacoes\PropostaController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Relatorios\RelatorioController;
use App\Http\Controllers\Calendario\CalendarioController;
use App\Http\Controllers\Notificacoes\NotificacaoController;
use App\Http\Controllers\Scraping\ScrapingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rotas públicas
Route::get('/', function () {
    return redirect()->route('login');
});

// Rotas de autenticação
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// Rotas protegidas por autenticação
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Perfil do usuário
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/notifications', [ProfileController::class, 'updateNotificationPreferences'])->name('profile.notifications');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:acessar_dashboard')
        ->name('dashboard');

    // Licitações
    Route::prefix('licitacoes')->middleware('permission:visualizar_licitacoes')->group(function () {
        Route::get('/', [LicitacaoController::class, 'index'])->name('licitacoes.index');
        Route::get('/create', [LicitacaoController::class, 'create'])
            ->middleware('permission:criar_licitacoes')
            ->name('licitacoes.create');
        Route::post('/', [LicitacaoController::class, 'store'])
            ->middleware('permission:criar_licitacoes')
            ->name('licitacoes.store');
        Route::get('/{licitacao}', [LicitacaoController::class, 'show'])->name('licitacoes.show');
        Route::get('/{licitacao}/edit', [LicitacaoController::class, 'edit'])
            ->middleware('permission:editar_licitacoes')
            ->name('licitacoes.edit');
        Route::put('/{licitacao}', [LicitacaoController::class, 'update'])
            ->middleware('permission:editar_licitacoes')
            ->name('licitacoes.update');
        Route::delete('/{licitacao}', [LicitacaoController::class, 'destroy'])
            ->middleware('permission:excluir_licitacoes')
            ->name('licitacoes.destroy');

        // Documentos
        Route::get('/{licitacao}/documentos', [DocumentoController::class, 'index'])->name('documentos.index');
        Route::post('/{licitacao}/documentos', [DocumentoController::class, 'store'])
            ->middleware('permission:editar_licitacoes')
            ->name('documentos.store');
        Route::get('/documentos/{documento}/download', [DocumentoController::class, 'download'])->name('documentos.download');
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])
            ->middleware('permission:editar_licitacoes')
            ->name('documentos.destroy');

        // Propostas
        Route::get('/{licitacao}/propostas', [PropostaController::class, 'index'])->name('propostas.index');
        Route::get('/{licitacao}/propostas/create', [PropostaController::class, 'create'])
            ->middleware('permission:criar_propostas')
            ->name('propostas.create');
        Route::post('/{licitacao}/propostas', [PropostaController::class, 'store'])
            ->middleware('permission:criar_propostas')
            ->name('propostas.store');
        Route::get('/propostas/{proposta}', [PropostaController::class, 'show'])->name('propostas.show');
        Route::get('/propostas/{proposta}/edit', [PropostaController::class, 'edit'])
            ->middleware('permission:editar_propostas')
            ->name('propostas.edit');
        Route::put('/propostas/{proposta}', [PropostaController::class, 'update'])
            ->middleware('permission:editar_propostas')
            ->name('propostas.update');
        Route::post('/propostas/{proposta}/resultado', [PropostaController::class, 'resultado'])
            ->middleware('permission:editar_propostas')
            ->name('propostas.resultado');
    });

    // Relatórios
    Route::prefix('relatorios')->middleware('permission:acessar_relatorios')->group(function () {
        Route::get('/', [RelatorioController::class, 'index'])->name('relatorios.index');
        Route::get('/create', [RelatorioController::class, 'create'])->name('relatorios.create');
        Route::post('/', [RelatorioController::class, 'store'])->name('relatorios.store');
        Route::get('/{relatorio}', [RelatorioController::class, 'show'])->name('relatorios.show');
        Route::get('/{relatorio}/edit', [RelatorioController::class, 'edit'])->name('relatorios.edit');
        Route::put('/{relatorio}', [RelatorioController::class, 'update'])->name('relatorios.update');
        Route::delete('/{relatorio}', [RelatorioController::class, 'destroy'])->name('relatorios.destroy');
        Route::get('/{relatorio}/execute', [RelatorioController::class, 'execute'])->name('relatorios.execute');
        Route::get('/tipos/{tipo}', [RelatorioController::class, 'gerarPorTipo'])->name('relatorios.tipo');

        // Exportações específicas
        Route::get('/exportar/licitacoes', [RelatorioController::class, 'exportarLicitacoes'])->name('relatorios.exportar.licitacoes');
        Route::get('/exportar/propostas', [RelatorioController::class, 'exportarPropostas'])->name('relatorios.exportar.propostas');
    });

    // Calendário
    Route::prefix('calendario')->middleware('permission:acessar_calendario')->group(function () {
        Route::get('/', [CalendarioController::class, 'index'])->name('calendario.index');
        Route::get('/eventos', [CalendarioController::class, 'eventos'])->name('calendario.eventos');
        Route::post('/eventos', [CalendarioController::class, 'store'])
            ->middleware('permission:criar_eventos')
            ->name('calendario.store');
        Route::put('/eventos/{evento}', [CalendarioController::class, 'update'])
            ->middleware('permission:editar_eventos')
            ->name('calendario.update');
        Route::delete('/eventos/{evento}', [CalendarioController::class, 'destroy'])
            ->middleware('permission:excluir_eventos')
            ->name('calendario.destroy');
    });

    // Notificações
    Route::prefix('notificacoes')->group(function () {
        Route::get('/', [NotificacaoController::class, 'index'])->name('notificacoes.index');
        Route::get('/nao-lidas', [NotificacaoController::class, 'naoLidas'])->name('notificacoes.nao-lidas');
        Route::put('/{notificacao}/marcar-como-lida', [NotificacaoController::class, 'marcarComoLida'])->name('notificacoes.marcar-lida');
        Route::put('/marcar-todas-como-lidas', [NotificacaoController::class, 'marcarTodasComoLidas'])->name('notificacoes.marcar-todas');
        Route::delete('/{notificacao}', [NotificacaoController::class, 'destroy'])->name('notificacoes.destroy');
    });

    // Administração
    Route::prefix('admin')->middleware('role:administrador')->group(function () {
        // Usuários
        Route::resource('users', UserController::class);

        // Papéis e Permissões
        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);
        Route::post('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions');
        Route::post('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles');

        // Configurações
        Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
        Route::put('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

        // Logs do sistema
        Route::get('/logs', [SettingsController::class, 'logs'])->name('admin.logs');

        // Scraping (coleta)
        Route::get('/scraping', [ScrapingController::class, 'index'])->name('admin.scraping');
        Route::post('/scraping/executar', [ScrapingController::class, 'executar'])->name('admin.scraping.executar');
        Route::get('/scraping/historico', [ScrapingController::class, 'historico'])->name('admin.scraping.historico');
        Route::post('/scraping/agendar', [ScrapingController::class, 'agendar'])->name('admin.scraping.agendar');
    });
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    // API para o dashboard
    Route::get('/dashboard/metricas', [DashboardController::class, 'metricas']);
    Route::get('/dashboard/licitacoes-proximas', [DashboardController::class, 'licitacoesProximas']);
    Route::get('/dashboard/tarefas-pendentes', [DashboardController::class, 'tarefasPendentes']);

    // API para calendário
    Route::get('/calendario/eventos', [CalendarioController::class, 'getEventos']);

    // API para notificações
    Route::get('/notificacoes/nao-lidas/count', [NotificacaoController::class, 'contarNaoLidas']);

    // API para licitações
    Route::get('/licitacoes/por-filtro', [LicitacaoController::class, 'getLicitacoesPorFiltro']);
    Route::get('/licitacoes/{licitacao}/timeline', [LicitacaoController::class, 'getTimeline']);

    // API para relatórios
    Route::get('/relatorios/dados/{tipo}', [RelatorioController::class, 'getDadosRelatorio']);
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
