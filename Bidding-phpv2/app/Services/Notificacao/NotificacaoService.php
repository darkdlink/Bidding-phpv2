<?php

namespace App\Services\Notificacao;

use App\Models\Notificacao;
use App\Models\User;
use App\Events\NovaNotificacao;
use App\Mail\NotificacaoEmail;
use Illuminate\Support\Facades\Mail;

class NotificacaoService
{
    /**
     * Registra uma nova notificação no sistema
     *
     * @param string $tipo Tipo da notificação (nova_licitacao, mudanca_status, prazo, etc)
     * @param string $titulo Título da notificação
     * @param string $mensagem Corpo da mensagem
     * @param int $userId ID do usuário destinatário
     * @param int|null $licitacaoId ID da licitação relacionada (opcional)
     * @return Notificacao
     */
    public function notificar(string $tipo, string $titulo, string $mensagem, int $userId, ?int $licitacaoId = null): Notificacao
    {
        $notificacao = Notificacao::create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'user_id' => $userId,
            'licitacao_id' => $licitacaoId,
            'lido' => false
        ]);

        // Dispara o evento para notificação em tempo real (se implementado)
        event(new NovaNotificacao($notificacao));

        // Enviar e-mail se o usuário tiver preferência configurada para este tipo
        $this->enviarEmailSeNecessario($notificacao);

        return $notificacao;
    }

    /**
     * Notificar múltiplos usuários com a mesma mensagem
     *
     * @param string $tipo Tipo da notificação
     * @param string $titulo Título da notificação
     * @param string $mensagem Corpo da mensagem
     * @param array $userIds Array de IDs dos usuários destinatários
     * @param int|null $licitacaoId ID da licitação relacionada (opcional)
     * @return array Array de notificações criadas
     */
    public function notificarMultiplos(string $tipo, string $titulo, string $mensagem, array $userIds, ?int $licitacaoId = null): array
    {
        $notificacoes = [];

        foreach ($userIds as $userId) {
            $notificacoes[] = $this->notificar($tipo, $titulo, $mensagem, $userId, $licitacaoId);
        }

        return $notificacoes;
    }

    /**
     * Notificar todos os usuários com um determinado papel
     *
     * @param string $tipo Tipo da notificação
     * @param string $titulo Título da notificação
     * @param string $mensagem Corpo da mensagem
     * @param string $roleName Nome do papel
     * @param int|null $licitacaoId ID da licitação relacionada (opcional)
     * @return array Array de notificações criadas
     */
    public function notificarPorPapel(string $tipo, string $titulo, string $mensagem, string $roleName, ?int $licitacaoId = null): array
    {
        $users = User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->get();

        $userIds = $users->pluck('id')->toArray();

        return $this->notificarMultiplos($tipo, $titulo, $mensagem, $userIds, $licitacaoId);
    }

    /**
     * Marcar uma notificação como lida
     *
     * @param int $notificacaoId ID da notificação
     * @return bool
     */
    public function marcarComoLida(int $notificacaoId): bool
    {
        $notificacao = Notificacao::find($notificacaoId);

        if (!$notificacao) {
            return false;
        }

        $notificacao->update([
            'lido' => true,
            'data_leitura' => now()
        ]);

        return true;
    }

    /**
     * Marcar todas as notificações de um usuário como lidas
     *
     * @param int $userId ID do usuário
     * @return int Número de notificações atualizadas
     */
    public function marcarTodasComoLidas(int $userId): int
    {
        return Notificacao::where('user_id', $userId)
            ->where('lido', false)
            ->update([
                'lido' => true,
                'data_leitura' => now()
            ]);
    }

    /**
     * Envia email de notificação se o usuário tiver configurado esta preferência
     *
     * @param Notificacao $notificacao
     * @return void
     */
    private function enviarEmailSeNecessario(Notificacao $notificacao): void
    {
        $user = User::find($notificacao->user_id);

        // Verificar se o usuário quer receber emails para este tipo de notificação
        // Esta lógica depende da implementação das preferências de notificação
        if ($user && $this->usuarioDesejaReceberEmail($user, $notificacao->tipo)) {
            Mail::to($user->email)->queue(new NotificacaoEmail($notificacao));
        }
    }

    /**
     * Verifica se o usuário deseja receber este tipo de notificação por email
     * Esta é uma implementação básica que pode ser expandida conforme necessário
     *
     * @param User $user
     * @param string $tipoNotificacao
     * @return bool
     */
    private function usuarioDesejaReceberEmail(User $user, string $tipoNotificacao): bool
    {
        // Implementação básica - pode ser expandida com uma tabela de preferências
        $tiposImportantes = ['nova_licitacao', 'mudanca_status', 'prazo_critico'];

        // Por padrão, notificações importantes são enviadas por email
        return in_array($tipoNotificacao, $tiposImportantes);
    }
}
