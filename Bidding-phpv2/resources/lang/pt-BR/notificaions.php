<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Linhas de Idioma para Notificações
    |--------------------------------------------------------------------------
    |
    | As linhas de idioma a seguir são usadas para notificações no sistema de
    | licitações. Elas são usadas para diferentes tipos de notificações
    | enviadas aos usuários.
    |
    */

    'greeting' => 'Olá!',
    'salutation' => 'Atenciosamente',
    'footer' => 'Se você está tendo problemas ao clicar no botão ":actionText", copie e cole a URL abaixo no seu navegador: [:actionURL](:actionURL)',

    'user_created' => [
        'subject' => 'Bem-vindo ao Sistema de Licitações',
        'intro' => 'Sua conta foi criada com sucesso.',
        'action' => 'Acessar Sistema',
        'outro' => 'Para acessar o sistema, utilize o e-mail e senha fornecidos.',
    ],

    'password_reset' => [
        'subject' => 'Redefinição de Senha',
        'intro' => 'Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para sua conta.',
        'action' => 'Redefinir Senha',
        'outro' => 'Se você não solicitou uma redefinição de senha, nenhuma ação adicional é necessária.',
        'expiration' => 'Este link de redefinição de senha expirará em :count minutos.',
    ],

    'email_verification' => [
        'subject' => 'Verificação de E-mail',
        'intro' => 'Por favor, clique no botão abaixo para verificar seu endereço de e-mail.',
        'action' => 'Verificar E-mail',
        'outro' => 'Se você não criou uma conta, nenhuma ação adicional é necessária.',
    ],

    'role_updated' => [
        'subject' => 'Alteração de Permissões',
        'intro' => 'Suas permissões no sistema foram atualizadas.',
        'action' => 'Ver Detalhes',
        'outro' => 'Entre em contato com o administrador se tiver alguma dúvida.',
    ],

    'licitacao_atribuida' => [
        'subject' => 'Nova Licitação Atribuída',
        'intro' => 'Uma nova licitação foi atribuída a você.',
        'action' => 'Ver Licitação',
        'outro' => 'Por favor, verifique os detalhes e prazos desta licitação.',
    ],

    'licitacao_prazo' => [
        'subject' => 'Alerta de Prazo de Licitação',
        'intro' => 'Uma licitação está com prazo próximo ao vencimento.',
        'action' => 'Ver Licitação',
        'outro' => 'Por favor, verifique e tome as providências necessárias.',
    ],

    'licitacao_status' => [
        'subject' => 'Alteração de Status de Licitação',
        'intro' => 'O status de uma licitação que você acompanha foi alterado.',
        'action' => 'Ver Detalhes',
        'outro' => 'Verifique os detalhes e atualizações desta licitação.',
    ],

    'view_all' => 'Ver Todas',
    'no_notifications' => 'Não há novas notificações',
    'mark_all_as_read' => 'Marcar todas como lidas',
    'unread_notifications' => 'Notificações não lidas',
];
