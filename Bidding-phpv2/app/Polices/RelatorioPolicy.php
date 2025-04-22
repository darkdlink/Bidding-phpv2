<?php

namespace App\Policies;

use App\Models\Relatorio;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RelatorioPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->hasPermission('acessar_relatorios');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Relatorio $relatorio)
    {
        // Verificar se o usuário pode ver qualquer relatório
        if ($user->hasPermission('acessar_relatorios')) {
            // Para relatórios privados, apenas o criador pode ver
            if ($relatorio->is_private) {
                return $user->id === $relatorio->user_id;
            }

            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasPermission('criar_relatorios');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Relatorio $relatorio)
    {
        // Verificar se o usuário pode editar qualquer relatório
        if ($user->hasPermission('editar_relatorios')) {
            return true;
        }

        // Verificar se o usuário é o criador do relatório
        return $user->id === $relatorio->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Relatorio $relatorio)
    {
        // Verificar se o usuário pode excluir qualquer relatório
        if ($user->hasPermission('excluir_relatorios')) {
            return true;
        }

        // Verificar se o usuário é o criador do relatório
        return $user->id === $relatorio->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Relatorio $relatorio)
    {
        return $user->hasPermission('excluir_relatorios');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Relatorio $relatorio)
    {
        return $user->hasRole('administrador');
    }

    /**
     * Determine whether the user can execute the report.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function execute(User $user, Relatorio $relatorio)
    {
        // Verificar se o usuário pode executar qualquer relatório
        if ($user->hasPermission('acessar_relatorios')) {
            // Para relatórios privados, apenas o criador pode executar
            if ($relatorio->is_private) {
                return $user->id === $relatorio->user_id;
            }

            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can schedule the report.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Relatorio  $relatorio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function schedule(User $user, Relatorio $relatorio)
    {
        // Verificar se o usuário pode agendar relatórios
        if ($user->hasPermission('agendar_relatorios')) {
            return true;
        }

        // Verificar se o usuário é o criador do relatório
        return $user->id === $relatorio->user_id;
    }
}
