<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Licitacao;
use App\Policies\LicitacaoPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Licitacao::class => LicitacaoPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Define gates padrão para verificar permissões
        Gate::before(function (User $user, $ability) {
            // Super-admin tem todas as permissões
            if ($user->hasRole('administrador')) {
                return true;
            }
        });

        // Definição de gates para verificação de permissões
        Gate::define('visualizar_dashboard', function (User $user) {
            return $user->hasPermission('acessar_dashboard');
        });

        Gate::define('visualizar_licitacoes', function (User $user) {
            return $user->hasPermission('visualizar_licitacoes');
        });

        Gate::define('criar_licitacoes', function (User $user) {
            return $user->hasPermission('criar_licitacoes');
        });

        Gate::define('editar_licitacoes', function (User $user) {
            return $user->hasPermission('editar_licitacoes');
        });

        Gate::define('excluir_licitacoes', function (User $user) {
            return $user->hasPermission('excluir_licitacoes');
        });

        Gate::define('gerenciar_usuarios', function (User $user) {
            return $user->hasPermission('gerenciar_usuarios');
        });

        Gate::define('gerenciar_papeis', function (User $user) {
            return $user->hasPermission('gerenciar_papeis');
        });

        Gate::define('visualizar_relatorios', function (User $user) {
            return $user->hasPermission('acessar_relatorios');
        });
    }
}
