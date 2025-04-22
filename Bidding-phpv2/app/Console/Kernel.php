<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\Scraping\ScrapingService;
use App\Services\Relatorios\RelatorioService;
use App\Services\Prazos\PrazoService;
use App\Jobs\EnviarEmailLicitacoesJob;
use App\Jobs\SincronizarStatusLicitacoesJob;
use App\Jobs\LimparArquivosTemporariosJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Verificação diária de prazos de licitações (manhã)
        $schedule->call(function () {
            app(PrazoService::class)->verificarPrazosLicitacoes(3);
        })->dailyAt('08:00')
          ->name('verificar_prazos_licitacoes')
          ->withoutOverlapping();

        // Verificação diária de prazos de tarefas (manhã)
        $schedule->call(function () {
            app(PrazoService::class)->verificarPrazosTarefas(2);
        })->dailyAt('08:15')
          ->name('verificar_prazos_tarefas')
          ->withoutOverlapping();

        // Verificação diária de prazos vencidos (tarde)
        $schedule->call(function () {
            app(PrazoService::class)->verificarPrazosVencidos();
        })->dailyAt('14:00')
          ->name('verificar_prazos_vencidos')
          ->withoutOverlapping();

        // Scraping de licitações (2x ao dia)
        $schedule->call(function () {
            app(ScrapingService::class)->coletarLicitacoes('comprasnet', [
                'data_inicio' => now()->subDays(1)->format('d/m/Y'),
                'data_fim' => now()->format('d/m/Y')
            ]);
        })->twiceDaily(9, 15)
          ->name('coletar_licitacoes_comprasnet')
          ->withoutOverlapping()
          ->runInBackground();

        // Scraping semanal mais abrangente (segunda-feira)
        $schedule->call(function () {
            app(ScrapingService::class)->coletarLicitacoes('comprasnet', [
                'data_inicio' => now()->subDays(7)->format('d/m/Y'),
                'data_fim' => now()->format('d/m/Y')
            ]);
        })->weekly()->mondays()->at('23:00')
          ->name('coletar_licitacoes_semanal')
          ->withoutOverlapping()
          ->runInBackground();

        // Executar relatórios agendados (diariamente)
        $schedule->call(function () {
            app(RelatorioService::class)->executarRelatoriosAgendados();
        })->dailyAt('00:30')
          ->name('executar_relatorios_agendados')
          ->withoutOverlapping();

        // Enviar email com licitações do dia (de segunda a sexta)
        $schedule->job(new EnviarEmailLicitacoesJob())
                ->weekdays()
                ->dailyAt('07:30')
                ->name('enviar_email_licitacoes');

        // Sincronizar status de licitações (diariamente)
        $schedule->job(new SincronizarStatusLicitacoesJob())
                ->dailyAt('01:00')
                ->name('sincronizar_status_licitacoes');

        // Limpar arquivos temporários (semanalmente)
        $schedule->job(new LimparArquivosTemporariosJob())
                ->weekly()
                ->sundays()
                ->at('02:00')
                ->name('limpar_arquivos_temporarios');

        // Backup do banco de dados (diário)
        $schedule->command('backup:run --only-db')
                ->dailyAt('03:00')
                ->name('backup_diario');

        // Backup completo (semanal)
        $schedule->command('backup:run')
                ->weekly()
                ->saturdays()
                ->at('04:00')
                ->name('backup_semanal');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
