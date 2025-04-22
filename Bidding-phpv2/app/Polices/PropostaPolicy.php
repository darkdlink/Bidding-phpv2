<?php

namespace App\Policies;

use App\Models\Proposta;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PropostaPolicy
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
        return $user->hasPermission('visualizar_propostas');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Proposta  $proposta
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Proposta $proposta)
    {
        // Verificar se o usuário pode visualizar qualquer proposta
        if ($user->hasPermission('visualizar_propostas')) {
            return true;
        }

        // Verificar se o usuário é o responsável pela proposta
        return $user->id === $proposta->responsavel_id;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasPermission('criar_propostas');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Proposta  $proposta
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Proposta $proposta)
    {
        // Verificar se o usuário pode editar qualquer proposta
        if ($user->hasPermission('editar_propostas')) {
            return true;
        }

        // Verificar se o usuário é o responsável pela proposta
        return $user->id === $proposta->responsavel_id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Proposta  $proposta
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Proposta $proposta)
    {
        // Verificar se o usuário pode excluir qualquer proposta
        if ($user->hasPermission('excluir_propostas')) {
            return true;
        }

        // Verificar se o usuário é o responsável pela proposta
        return $user->id === $proposta->responsavel_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Proposta  $proposta
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Proposta $proposta)
    {
        return $user->hasPermission('excluir_propostas');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Proposta  $proposta
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Proposta $proposta)
    {
        return $user->hasRole('administrador');
    }

    /**
     * Determine whether the user can register the proposal result.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Proposta  $proposta
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function registerResult(User $user, Proposta $proposta)
    {
        // Verificar se o usuário pode registrar resultado
        if ($user->hasPermission('editar_propostas')) {
            return true;
        }

        // Verificar se o usuário é o responsável pela proposta
        return $user->id === $proposta->responsavel_id;
    }
}
