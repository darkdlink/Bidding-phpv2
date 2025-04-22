<?php

namespace App\Services\Propostas;

use App\Models\Licitacao;
use App\Models\Proposta;
use App\Models\Status;
use App\Services\Eventos\EventoService;
use App\Services\Notificacao\NotificacaoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PropostaService
{
    protected EventoService $eventoService;
    protected NotificacaoService $notificacaoService;

    public function __construct(
        EventoService $eventoService,
        NotificacaoService $notificacaoService
    ) {
        $this->eventoService = $eventoService;
        $this->notificacaoService = $notificacaoService;
    }

    /**
     * Registra uma nova proposta para uma licitação
     *
     * @param int $licitacaoId ID da licitação
     * @param float $valor Valor da proposta
     * @param Carbon $dataEnvio Data de envio da proposta
     * @param string|null $observacoes Observações sobre a proposta
     * @param int|null $responsavelId ID do responsável (opcional)
     * @param array $arquivos Arquivos relacionados à proposta (opcional)
     * @return Proposta
     */
    public function registrarProposta(
        int $licitacaoId,
        float $valor,
        Carbon $dataEnvio,
        ?string $observacoes = null,
        ?int $responsavelId = null,
        array $arquivos = []
    ): Proposta {
        try {
            DB::beginTransaction();

            $licitacao = Licitacao::findOrFail($licitacaoId);
            $responsavelId = $responsavelId ?? Auth::id();

            // Criar a proposta
            $proposta = Proposta::create([
                'licitacao_id' => $licitacaoId,
                'valor' => $valor,
                'data_envio' => $dataEnvio,
                'observacoes' => $observacoes,
                'responsavel_id' => $responsavelId
            ]);

            // Registrar evento
            $this->eventoService->registrarEvento(
                $licitacaoId,
                'proposta_enviada',
                'Proposta Enviada',
                "Proposta enviada no valor de R$ " . number_format($valor, 2, ',', '.'),
                $dataEnvio,
                $responsavelId
            );

            // Atualizar status da licitação para "Proposta Enviada" se necessário
            $statusPropostaEnviada = Status::where('nome', 'Proposta Enviada')->first();

            if ($statusPropostaEnviada) {
                $statusAtuais = ['Em Análise', 'Novo', 'Orçamento em Elaboração'];

                $atualizarStatus = Status::whereIn('nome', $statusAtuais)
                    ->where('id', $licitacao->status_id)
                    ->exists();

                if ($atualizarStatus) {
                    $licitacao->update([
                        'status_id' => $statusPropostaEnviada->id
                    ]);

                    // Registrar mudança de status
                    $this->eventoService->registrarEvento(
                        $licitacaoId,
                        'mudanca_status',
                        'Status Atualizado',
                        "Status atualizado para 'Proposta Enviada'",
                        now(),
                        $responsavelId
                    );
                }
            }

            // Processar arquivos da proposta
            if (!empty($arquivos)) {
                $this->processarArquivosProposta($proposta, $arquivos);
            }

            // Notificar gestores sobre a proposta enviada
            $this->notificacaoService->notificarPorPapel(
                'proposta_enviada',
                "Proposta enviada para licitação: {$licitacao->numero_edital}",
                "Uma proposta no valor de R$ " . number_format($valor, 2, ',', '.') . " foi enviada para a licitação {$licitacao->numero_edital}.",
                'gestor',
                $licitacaoId
            );

            DB::commit();

            return $proposta;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao registrar proposta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Processa os arquivos anexados à proposta
     *
     * @param Proposta $proposta
     * @param array $arquivos
     * @return void
     */
    protected function processarArquivosProposta(Proposta $proposta, array $arquivos): void
    {
        $licitacaoId = $proposta->licitacao_id;
        $pastaDestino = "documentos/licitacoes/{$licitacaoId}/propostas/{$proposta->id}";

        foreach ($arquivos as $arquivo) {
            $caminho = $arquivo->store($pastaDestino);

            // Registrar documento no sistema
            $proposta->licitacao->documentos()->create([
                'nome' => $arquivo->getClientOriginalName(),
                'tipo' => 'proposta',
                'path' => $caminho,
                'mime_type' => $arquivo->getMimeType(),
                'tamanho' => $arquivo->getSize()
            ]);
        }
    }

    /**
     * Registra o resultado de uma proposta
     *
     * @param int $propostaId ID da proposta
     * @param string $resultado Resultado (ganho, perdido)
     * @param string|null $motivoResultado Motivo do resultado
     * @param int|null $responsavelId ID do responsável (opcional)
     * @return Proposta
     */
    public function registrarResultado(
        int $propostaId,
        string $resultado,
        ?string $motivoResultado = null,
        ?int $responsavelId = null
    ): Proposta {
        try {
            DB::beginTransaction();

            $proposta = Proposta::findOrFail($propostaId);
            $licitacao = $proposta->licitacao;
            $responsavelId = $responsavelId ?? Auth::id();

            // Validar resultado
            $resultadosValidos = ['ganho', 'perdido'];
            $resultado = strtolower($resultado);

            if (!in_array($resultado, $resultadosValidos)) {
                throw new \InvalidArgumentException("Resultado inválido. Valores aceitos: ganho, perdido");
            }

            // Atualizar proposta
            $proposta->update([
                'resultado' => $resultado,
                'motivo_resultado' => $motivoResultado
            ]);

            // Atualizar status da licitação conforme resultado
            $novoStatusNome = $resultado === 'ganho' ? 'Ganho' : 'Perdido';
            $novoStatus = Status::where('nome', $novoStatusNome)->first();

            if ($novoStatus) {
                $licitacao->update([
                    'status_id' => $novoStatus->id
                ]);
            }

            // Registrar evento
            $this->eventoService->registrarEvento(
                $licitacao->id,
                'resultado',
                "Licitação {$novoStatusNome}",
                "A licitação foi {$resultado}" . ($motivoResultado ? ". Motivo: {$motivoResultado}" : ""),
                now(),
                $responsavelId
            );

            // Notificar sobre o resultado
            $mensagemNotificacao = "A licitação {$licitacao->numero_edital} foi {$resultado}";
            if ($motivoResultado) {
                $mensagemNotificacao .= ". Motivo: {$motivoResultado}";
            }

            // Notificar responsável pela licitação
            if ($licitacao->responsavel_id && $licitacao->responsavel_id != $responsavelId) {
                $this->notificacaoService->notificar(
                    'resultado_licitacao',
                    "Resultado da Licitação: {$licitacao->numero_edital}",
                    $mensagemNotificacao,
                    $licitacao->responsavel_id,
                    $licitacao->id
                );
            }

            // Notificar gestores
            $this->notificacaoService->notificarPorPapel(
                'resultado_licitacao',
                "Resultado da Licitação: {$licitacao->numero_edital}",
                $mensagemNotificacao,
                'gestor',
                $licitacao->id
            );

            DB::commit();

            return $proposta;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao registrar resultado da proposta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcula a diferença percentual entre o valor estimado e o valor da proposta
     *
     * @param float $valorEstimado
     * @param float $valorProposta
     * @return float Percentual de diferença
     */
    public function calcularDiferencaPercentual(float $valorEstimado, float $valorProposta): float
    {
        if ($valorEstimado <= 0) {
            return 0;
        }

        return round((($valorProposta - $valorEstimado) / $valorEstimado) * 100, 2);
    }

    /**
     * Recupera o histórico de propostas para uma licitação
     *
     * @param int $licitacaoId ID da licitação
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function historicoPropostas(int $licitacaoId)
    {
        return Proposta::where('licitacao_id', $licitacaoId)
            ->with('responsavel')
            ->orderBy('data_envio', 'desc')
            ->get();
    }

    /**
     * Cria modelo de proposta para uma licitação
     *
     * @param int $licitacaoId ID da licitação
     * @param string $formato Formato do modelo (docx, pdf)
     * @return string Caminho do arquivo gerado
     */
    public function gerarModeloProposta(int $licitacaoId, string $formato = 'docx'): string
    {
        $licitacao = Licitacao::with(['orgao', 'categoria'])->findOrFail($licitacaoId);

        // Implementação depende da biblioteca utilizada para gerar documentos
        // Esta é uma implementação fictícia

        $conteudo = "PROPOSTA DE PREÇOS\n\n";
        $conteudo .= "Licitação: {$licitacao->numero_edital}\n";
        $conteudo .= "Órgão: {$licitacao->orgao->nome}\n";
        $conteudo .= "Objeto: {$licitacao->objeto}\n\n";
        $conteudo .= "Data de Abertura: {$licitacao->data_abertura->format('d/m/Y H:i')}\n\n";
        $conteudo .= "Valor Proposto: R$ _________________\n\n";
        $conteudo .= "Validade da Proposta: 60 dias\n\n";
        $conteudo .= "Data: " . now()->format('d/m/Y') . "\n\n";
        $conteudo .= "Assinatura: ________________________\n";

        // Salvar arquivo em storage
        $nomeArquivo = "modelo_proposta_{$licitacao->id}." . $formato;
        $caminho = "arquivos/modelos/{$nomeArquivo}";

        Storage::put($caminho, $conteudo);

        return $caminho;
    }

    /**
     * Calcula a taxa de sucesso de propostas por período
     *
     * @param Carbon $dataInicio
     * @param Carbon $dataFim
     * @param array $filtros Filtros adicionais
     * @return array Estatísticas calculadas
     */
    public function calcularTaxaSucesso(Carbon $dataInicio, Carbon $dataFim, array $filtros = []): array
    {
        // Consulta base
        $query = Proposta::whereBetween('data_envio', [$dataInicio, $dataFim])
            ->whereNotNull('resultado')
            ->with('licitacao');

        // Aplicar filtros
        if (!empty($filtros)) {
            $query->whereHas('licitacao', function ($q) use ($filtros) {
                if (isset($filtros['categoria_id'])) {
                    $q->where('categoria_id', $filtros['categoria_id']);
                }

                if (isset($filtros['modalidade'])) {
                    $q->where('modalidade', $filtros['modalidade']);
                }
            });
        }

        // Obter resultados
        $propostas = $query->get();

        $total = $propostas->count();
        $ganhas = $propostas->where('resultado', 'ganho')->count();
        $perdidas = $propostas->where('resultado', 'perdido')->count();

        // Calcular taxa
        $taxaSucesso = 0;
        if ($total > 0) {
            $taxaSucesso = round(($ganhas / $total) * 100, 2);
        }

        // Agrupar por mês
        $porMes = $propostas->groupBy(function ($proposta) {
            return $proposta->data_envio->format('Y-m');
        })->map(function ($grupo) {
            $totalMes = $grupo->count();
            $ganhasMes = $grupo->where('resultado', 'ganho')->count();

            return [
                'total' => $totalMes,
                'ganhas' => $ganhasMes,
                'taxa' => $totalMes > 0 ? round(($ganhasMes / $totalMes) * 100, 2) : 0
            ];
        });

        // Resultado final
        return [
            'periodo' => [
                'inicio' => $dataInicio->format('d/m/Y'),
                'fim' => $dataFim->format('d/m/Y')
            ],
            'estatisticas' => [
                'total' => $total,
                'ganhas' => $ganhas,
                'perdidas' => $perdidas,
                'taxa_sucesso' => $taxaSucesso
            ],
            'por_mes' => $porMes
        ];
    }

    /**
     * Calcula a margem média de desconto/acréscimo nas propostas
     *
     * @param Carbon $dataInicio
     * @param Carbon $dataFim
     * @param string $resultado Filtrar por resultado (todos, ganho, perdido)
     * @return array Estatísticas calculadas
     */
    public function calcularMargemMedia(Carbon $dataInicio, Carbon $dataFim, string $resultado = 'todos'): array
    {
        // Consulta base
        $query = Proposta::whereBetween('data_envio', [$dataInicio, $dataFim])
            ->with(['licitacao' => function ($q) {
                $q->whereNotNull('valor_estimado');
            }]);

        // Filtrar por resultado
        if ($resultado !== 'todos') {
            $query->where('resultado', $resultado);
        }

        $propostas = $query->get()
            ->filter(function ($proposta) {
                // Filtrar apenas propostas com valor estimado válido
                return $proposta->licitacao && $proposta->licitacao->valor_estimado > 0;
            });

        // Calcular diferenças
        $diferencas = $propostas->map(function ($proposta) {
            $diferenca = $this->calcularDiferencaPercentual(
                $proposta->licitacao->valor_estimado,
                $proposta->valor
            );

            return [
                'proposta_id' => $proposta->id,
                'licitacao_id' => $proposta->licitacao_id,
                'numero_edital' => $proposta->licitacao->numero_edital,
                'valor_estimado' => $proposta->licitacao->valor_estimado,
                'valor_proposta' => $proposta->valor,
                'diferenca_percentual' => $diferenca,
                'resultado' => $proposta->resultado
            ];
        });

        // Calcular estatísticas
        $total = $diferencas->count();

        if ($total === 0) {
            return [
                'total_propostas' => 0,
                'media_geral' => 0,
                'media_ganhas' => 0,
                'media_perdidas' => 0,
                'detalhes' => []
            ];
        }

        $mediaGeral = $diferencas->avg('diferenca_percentual');

        $ganhas = $diferencas->where('resultado', 'ganho');
        $mediaGanhas = $ganhas->count() > 0 ? $ganhas->avg('diferenca_percentual') : 0;

        $perdidas = $diferencas->where('resultado', 'perdido');
        $mediaPerdidas = $perdidas->count() > 0 ? $perdidas->avg('diferenca_percentual') : 0;

        return [
            'total_propostas' => $total,
            'media_geral' => round($mediaGeral, 2),
            'media_ganhas' => round($mediaGanhas, 2),
            'media_perdidas' => round($mediaPerdidas, 2),
            'detalhes' => $diferencas->toArray()
        ];
    }
}
