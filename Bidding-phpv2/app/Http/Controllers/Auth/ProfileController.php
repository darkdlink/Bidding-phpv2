<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    /**
     * Exibe o perfil do usuário
     */
    public function show()
    {
        $user = Auth::user();
        return view('auth.profile.show', compact('user'));
    }

    /**
     * Atualiza informações do perfil
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Perfil atualizado com sucesso!');
    }

    /**
     * Atualiza senha do usuário
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Senha atualizada com sucesso!');
    }

    /**
     * Atualiza preferências de notificação
     */
    public function updateNotificationPreferences(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'notification_preferences' => ['array'],
        ]);

        // Aqui seria implementada a lógica para salvar as preferências
        // Poderia ser uma tabela separada ou um campo JSON no usuário

        return redirect()->route('profile.show')
            ->with('success', 'Preferências de notificação atualizadas com sucesso!');
    }
}
