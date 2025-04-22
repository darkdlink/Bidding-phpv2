<?php

namespace App\Policies;

use App\Models\Licitacao;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LicitacaoPolicy
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
        return $user->hasPermission('visualizar_licitacoes');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Licitacao $licitacao)
    {
        return $user->hasPermission('visualizar_licitacoes');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasPermission('criar_licitacoes');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Licitacao $licitacao)
    {
        // Verificar se o usuário pode editar qualquer licitação
        if ($user->hasPermission('editar_licitacoes')) {
            return true;
        }

        // Verificar se o usuário é o responsável pela licitação
        return $user->id === $licitacao->responsavel_id &&
               $user->hasPermission('editar_licitacoes_proprias');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Licitacao $licitacao)
    {
        return $user->hasPermission('excluir_licitacoes');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Licitacao $licitacao)
    {
        return $user->hasPermission('excluir_licitacoes');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Licitacao $licitacao)
    {
        return $user->hasRole('administrador');
    }

    /**
     * Determine whether the user can change the status of the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function changeStatus(User $user, Licitacao $licitacao)
    {
        // Verificar se o usuário pode editar qualquer licitação
        if ($user->hasPermission('editar_licitacoes')) {
            return true;
        }

        // Verificar se o usuário é o responsável pela licitação
        return $user->id === $licitacao->responsavel_id &&
               $user->hasPermission('editar_licitacoes_proprias');
    }

    /**
     * Determine whether the user can submit a proposal.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Licitacao  $licitacao
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function submitProposal(User $user, Licitacao $licitacao)
    {
        return $user->hasPermission('criar_propostas');
    }
}
