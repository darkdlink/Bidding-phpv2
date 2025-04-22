<?php

namespace App\Services\Eventos;

use App\Models\Evento;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class EventoService
{
    /**
     * Registra um novo evento no sistema
     *
     * @param int $licitacaoId ID da licitação relacionada
     * @param string $tipo Tipo do evento (criacao, atualizacao, mudanca_status, etc)
     * @param string $titulo Título do evento
     * @param string $descricao Descrição detalhada do evento
     * @param Carbon|null $data Data do evento (opcional, padrão é now())
     * @param int|null $responsavelId ID do usuário responsável (opcional, padrão é usuário autenticado)
     * @return Evento
     */
    public function registrarEvento(
        int $licitacaoId,
        string $tipo,
        string $titulo,
        string $descricao,
        ?Carbon $data = null,
        ?int $responsavelId = null
    ): Evento {
        $data = $data ?? now();
        $responsavelId = $responsavelId ?? Auth::id();

        return Evento::create([
            'licitacao_id' => $licitacaoId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'data' => $data,
            'responsavel_id' => $responsavelId
        ]);
    }

    /**
     * Recupera o histórico de eventos de uma licitação
     *
     * @param int $licitacaoId ID da licitação
     * @param array|null $tiposEvento Array de tipos de evento para filtrar (opcional)
     * @param int $limite Limite de registros a retornar (0 para todos)
     * @param string $ordenacao Direção da ordenação (asc/desc)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function historicoLicitacao(
        int $licitacaoId,
        ?array $tiposEvento = null,
        int $limite = 0,
        string $ordenacao = 'desc'
    ) {
        $query = Evento::with('responsavel')
            ->where('licitacao_id', $licitacaoId)
            ->orderBy('data', $ordenacao);

        if ($tiposEvento) {
            $query->whereIn('tipo', $tiposEvento);
        }

        if ($limite > 0) {
            return $query->take($limite)->get();
        }

        return $query->get();
    }

    /**
     * Recupera eventos importantes para exibição em um calendário
     *
     * @param Carbon $dataInicio Data inicial
     * @param Carbon $dataFim Data final
     * @param int|null $licitacaoId ID da licitação (opcional para filtrar por licitação)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function eventosCalendario(
        Carbon $dataInicio,
        Carbon $dataFim,
        ?int $licitacaoId = null
    ) {
        $query = Evento::with(['licitacao', 'responsavel'])
            ->whereBetween('data', [$dataInicio, $dataFim]);

        if ($licitacaoId) {
            $query->where('licitacao_id', $licitacaoId);
        }

        return $query->get();
    }

    /**
     * Registra eventos automáticos baseados em datas importantes da licitação
     *
     * @param int $licitacaoId ID da licitação
     * @return array Array com eventos criados
     */
    public function registrarEventosAutomaticos(int $licitacaoId)
    {
        $licitacao = \App\Models\Licitacao::find($licitacaoId);
        $eventos = [];

        if (!$licitacao) {
            return $eventos;
        }

        // Evento para a data de abertura
        if ($licitacao->data_abertura) {
            $eventos[] = $this->registrarEvento(
                $licitacaoId,
                'data_importante',
                'Data de Abertura',
                'Data de abertura das propostas.',
                $licitacao->data_abertura
            );

            // Alerta 2 dias antes da abertura
            $alertaAbertura = $licitacao->data_abertura->copy()->subDays(2);
            if ($alertaAbertura > now()) {
                $eventos[] = $this->registrarEvento(
                    $licitacaoId,
                    'alerta',
                    'Alerta de Abertura',
                    'A abertura das propostas ocorrerá em 2 dias.',
                    $alertaAbertura
                );
            }
        }

        // Pode adicionar outros eventos automáticos como:
        // - Data limite para recursos
        // - Data de assinatura de contrato
        // - Data de homologação
        // etc.

        return $eventos;
    }

    /**
     * Recupera todos os eventos de um usuário específico
     *
     * @param int $userId ID do usuário
     * @param int $limite Limite de registros a retornar
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function eventosUsuario(int $userId, int $limite = 10)
    {
        return Evento::with('licitacao')
            ->where('responsavel_id', $userId)
            ->orderBy('data', 'desc')
            ->take($limite)
            ->get();
    }

    /**
     * Exporta o histórico de eventos para um formato específico
     *
     * @param int $licitacaoId ID da licitação
     * @param string $formato Formato de exportação (csv, pdf, json)
     * @return mixed Dados exportados no formato solicitado
     */
    public function exportarHistorico(int $licitacaoId, string $formato = 'json')
    {
        $eventos = $this->historicoLicitacao($licitacaoId);

        switch ($formato) {
            case 'csv':
                return $this->exportarParaCSV($eventos);
            case 'pdf':
                return $this->exportarParaPDF($eventos);
            case 'json':
            default:
                return $eventos->toJson();
        }
    }

    /**
     * Exporta eventos para CSV
     *
     * @param \Illuminate\Database\Eloquent\Collection $eventos
     * @return string Conteúdo CSV
     */
    private function exportarParaCSV($eventos)
    {
        $cabecalho = ['ID', 'Tipo', 'Título', 'Descrição', 'Data', 'Responsável'];
        $linhas = [];

        foreach ($eventos as $evento) {
            $linhas[] = [
                $evento->id,
                $evento->tipo,
                $evento->titulo,
                $evento->descricao,
                $evento->data->format('d/m/Y H:i'),
                $evento->responsavel->name ?? 'Sistema'
            ];
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $cabecalho);

        foreach ($linhas as $linha) {
            fputcsv($csv, $linha);
        }

        rewind($csv);
        $conteudo = stream_get_contents($csv);
        fclose($csv);

        return $conteudo;
    }

    /**
     * Exporta eventos para PDF
     *
     * @param \Illuminate\Database\Eloquent\Collection $eventos
     * @return mixed Conteúdo do PDF ou caminho do arquivo
     */
    private function exportarParaPDF($eventos)
    {
        // Implementação depende da biblioteca PDF escolhida
        // Exemplo com uma implementação fictícia:

        $pdf = new \App\Services\PDF\PDFGenerator();
        $pdf->setTitle('Histórico de Eventos');

        $pdf->addTable([
            'headers' => ['Tipo', 'Título', 'Descrição', 'Data', 'Responsável'],
            'data' => $eventos->map(function ($evento) {
                return [
                    $evento->tipo,
                    $evento->titulo,
                    $evento->descricao,
                    $evento->data->format('d/m/Y H:i'),
                    $evento->responsavel->name ?? 'Sistema'
                ];
            })->toArray()
        ]);

        return $pdf->generate();
    }
}
