import './bootstrap';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Inicializar tooltips e popovers
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inicializar popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Fechar alertas automaticamente após 5 segundos
    setTimeout(function() {
        var alertList = [].slice.call(document.querySelectorAll('.alert.alert-success'));
        alertList.forEach(function (alertEl) {
            var alert = new bootstrap.Alert(alertEl);
            alert.close();
        });
    }, 5000);
});

// Confirmação para exclusão de itens
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete-confirm');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const confirmMessage = this.getAttribute('data-confirm-message') || 'Tem certeza que deseja excluir este item?';

            if (confirm(confirmMessage)) {
                const form = this.closest('form');
                form.submit();
            }
        });
    });
});

// Funções para o módulo de autenticação
const authModule = (() => {
    // Validação de força de senha
    const validatePasswordStrength = (password) => {
        let strength = 0;
        const messages = [];

        // Comprimento mínimo
        if (password.length >= 8) {
            strength += 1;
        } else {
            messages.push('A senha deve ter pelo menos 8 caracteres');
        }

        // Contém letras minúsculas
        if (/[a-z]/.test(password)) {
            strength += 1;
        } else {
            messages.push('A senha deve conter pelo menos uma letra minúscula');
        }

        // Contém letras maiúsculas
        if (/[A-Z]/.test(password)) {
            strength += 1;
        } else {
            messages.push('A senha deve conter pelo menos uma letra maiúscula');
        }

        // Contém números
        if (/[0-9]/.test(password)) {
            strength += 1;
        } else {
            messages.push('A senha deve conter pelo menos um número');
        }

        // Contém caracteres especiais
        if (/[^a-zA-Z0-9]/.test(password)) {
            strength += 1;
        } else {
            messages.push('A senha deve conter pelo menos um caractere especial');
        }

        return {
            strength,
            messages,
            value: strength * 20 // Percentual (0-100)
        };
    };

    // Inicializar validador de senha em tempo real
    const initPasswordValidator = () => {
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('password-confirm');
        const strengthMeter = document.getElementById('password-strength-meter');
        const strengthText = document.getElementById('password-strength-text');

        if (passwordField && strengthMeter && strengthText) {
            passwordField.addEventListener('input', function() {
                const password = this.value;
                const result = validatePasswordStrength(password);

                // Atualizar barra de progresso
                strengthMeter.style.width = `${result.value}%`;

                // Definir cor com base na força
                if (result.value <= 40) {
                    strengthMeter.className = 'progress-bar bg-danger';
                    strengthText.textContent = 'Fraca';
                } else if (result.value <= 60) {
                    strengthMeter.className = 'progress-bar bg-warning';
                    strengthText.textContent = 'Média';
                } else if (result.value <= 80) {
                    strengthMeter.className = 'progress-bar bg-info';
                    strengthText.textContent = 'Boa';
                } else {
                    strengthMeter.className = 'progress-bar bg-success';
                    strengthText.textContent = 'Forte';
                }

                // Mostrar mensagens de validação
                const messageContainer = document.getElementById('password-strength-messages');
                if (messageContainer) {
                    messageContainer.innerHTML = '';
                    if (password.length > 0) {
                        const ul = document.createElement('ul');
                        ul.className = 'small text-muted mt-2';

                        result.messages.forEach(message => {
                            const li = document.createElement('li');
                            li.textContent = message;
                            ul.appendChild(li);
                        });

                        messageContainer.appendChild(ul);
                    }
                }
            });

            // Verificar se as senhas coincidem
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', function() {
                    if (this.value !== passwordField.value) {
                        this.setCustomValidity('As senhas não coincidem');
                    } else {
                        this.setCustomValidity('');
                    }
                });

                passwordField.addEventListener('input', function() {
                    if (confirmPasswordField.value !== '' && confirmPasswordField.value !== this.value) {
                        confirmPasswordField.setCustomValidity('As senhas não coincidem');
                    } else {
                        confirmPasswordField.setCustomValidity('');
                    }
                });
            }
        }
    };

    // Gerenciar 2FA (Autenticação de Dois Fatores)
    const init2FA = () => {
        const qrCodeContainer = document.getElementById('two-factor-qr-code');
        const setupKey = document.getElementById('two-factor-setup-key');
        const confirmButton = document.getElementById('confirm-two-factor');
        const codeInput = document.getElementById('two-factor-code');

        if (qrCodeContainer && confirmButton && codeInput) {
            // Copiar chave de configuração para a área de transferência
            if (setupKey) {
                const copyButton = document.getElementById('copy-setup-key');

                if (copyButton) {
                    copyButton.addEventListener('click', function() {
                        const key = setupKey.textContent;
                        navigator.clipboard.writeText(key).then(() => {
                            const originalText = this.textContent;
                            this.textContent = 'Copiado!';

                            setTimeout(() => {
                                this.textContent = originalText;
                            }, 2000);
                        });
                    });
                }
            }

            // Validação de código de confirmação
            confirmButton.addEventListener('click', function(e) {
                const code = codeInput.value.trim();

                if (code.length !== 6 || !/^\d+$/.test(code)) {
                    e.preventDefault();
                    alert('Por favor, insira um código válido de 6 dígitos');
                }
            });
        }
    };

    // Funções públicas
    return {
        init: function() {
            initPasswordValidator();
            init2FA();
        }
    };
})();

// Inicializar módulo de autenticação quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    authModule.init();
});
