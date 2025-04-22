<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
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
        return $user->hasPermission('gerenciar_usuarios');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, User $model)
    {
        // Usuário pode ver seu próprio perfil
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermission('gerenciar_usuarios');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasPermission('gerenciar_usuarios');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, User $model)
    {
        // Usuário pode atualizar seu próprio perfil
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermission('gerenciar_usuarios');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, User $model)
    {
        // Não pode excluir a si mesmo
        if ($user->id === $model->id) {
            return false;
        }

        // Admin não pode ser excluído
        if ($model->hasRole('administrador')) {
            return $user->id === 1; // Apenas o admin principal (ID 1) pode excluir outros admins
        }

        return $user->hasPermission('gerenciar_usuarios');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, User $model)
    {
        return $user->hasPermission('gerenciar_usuarios');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, User $model)
    {
        // Apenas administrador principal
        return $user->id === 1;
    }

    /**
     * Determine whether the user can manage roles for the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function manageRoles(User $user, User $model)
    {
        // Não pode gerenciar seus próprios papéis
        if ($user->id === $model->id) {
            return false;
        }

        // Apenas usuários com permissão para gerenciar papéis
        return $user->hasPermission('gerenciar_papeis');
    }

    /**
     * Determine whether the user can impersonate the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function impersonate(User $user, User $model)
    {
        // Não pode personificar a si mesmo
        if ($user->id === $model->id) {
            return false;
        }

        // Admin não pode ser personificado
        if ($model->hasRole('administrador')) {
            return false;
        }

        // Apenas admin pode personificar
        return $user->hasRole('administrador');
    }
}
