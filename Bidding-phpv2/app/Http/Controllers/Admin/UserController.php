<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Construtor do controller
     */
    public function __construct()
    {
        $this->middleware('permission:gerenciar_usuarios');
    }

    /**
     * Exibe lista de usuários
     */
    public function index()
    {
        $users = User::with('roles')->get();
        return view('admin.users.index', compact('users'));
    }

    /**
     * Mostra formulário para criar novo usuário
     */
    public function create()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Armazena um novo usuário
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => ['required', 'array']
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now()
            ]);

            $user->roles()->sync($request->roles);

            return redirect()->route('users.index')
                ->with('success', 'Usuário criado com sucesso!');

        } catch (\Exception $e) {
            Log::error('Erro ao criar usuário: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    /**
     * Exibe detalhes de um usuário
     */
    public function show(User $user)
    {
        $user->load('roles');
        return view('admin.users.show', compact('user'));
    }

    /**
     * Mostra formulário para editar um usuário
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $userRoles = $user->roles->pluck('id')->toArray();

        return view('admin.users.edit', compact('user', 'roles', 'userRoles'));
    }

    /**
     * Atualiza um usuário existente
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
        }

        try {
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            if ($request->filled('password')) {
                $user->update([
                    'password' => Hash::make($request->password),
                ]);
            }

            return redirect()->route('users.index')
                ->with('success', 'Usuário atualizado com sucesso!');

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }

    /**
     * Atualiza os papéis de um usuário
     */
    public function updateRoles(Request $request, User $user)
    {
        $request->validate([
            'roles' => ['required', 'array']
        ]);

        try {
            $user->roles()->sync($request->roles);

            return redirect()->route('users.show', $user)
                ->with('success', 'Papéis atualizados com sucesso!');

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar papéis do usuário: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erro ao atualizar papéis: ' . $e->getMessage());
        }
    }

    /**
     * Remove um usuário
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();

            return redirect()->route('users.index')
                ->with('success', 'Usuário removido com sucesso!');

        } catch (\Exception $e) {
            Log::error('Erro ao remover usuário: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erro ao remover usuário: ' . $e->getMessage());
        }
    }
}
