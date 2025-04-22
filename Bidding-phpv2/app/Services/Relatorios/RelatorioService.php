<?php

namespace App\Services\Relatorios;

use App\Models\Licitacao;
use App\Models\Relatorio;
use App\Models\User;
use App\Exports\LicitacoesExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class RelatorioService
{
    /**
     * Gera um relatório conforme parâmetros informados
     *
     * @param string $tipo Tipo do relatório
     * @param array $parametros Parâmetros específicos
     * @param string $formato Formato de saída (html, pdf, xlsx, csv)
     * @param int|null $userId ID do usuário solicitante (opcional)
     * @return mixed Dados do relatório no formato solicitado
     */
    public function gerarRelatorio(string $tipo, array $parametros, string $formato = 'html', ?int $userId = null)
    {
        try {
            switch ($tipo) {
                case 'licitacoes_por_status':
                    return $this->relatorioLicitacoesPorStatus($parametros, $formato);
                case 'licitacoes_por_periodo':
                    return $this->relatorioLicitacoesPorPeriodo($parametros, $formato);
                case 'desempenho_anual':
                    return $this->relatorioDesempenhoAnual($parametros, $formato);
                case 'analise_categorias':
                    return $this->relatorioAnaliseCategoria($parametros, $formato);
                case 'licitacoes_pendentes':
                    return $this->relatorioLicitacoesPendentes($parametros, $formato);
                case 'comparativo_periodo':
                    return $this->relatorioComparativoPeriodo($parametros, $formato);
                default:
                    throw new \InvalidArgumentException("Tipo de relatório '{$tipo}' não implementado");
            }
        } catch (\Exception $e) {
            Log::error("Erro ao gerar relatório {$tipo}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Salva as configurações de um relatório para geração futura
     *
     * @param string $nome Nome do relatório
     * @param string $descricao Descrição do relatório
     * @param string $tipo Tipo do relatório
     * @param array $parametros Parâmetros específicos
     * @param string $formato Formato padrão
     * @param int $userId ID do usuário
     * @param array|null $agendamento Configuração de agendamento (opcional)
     * @return Relatorio
     */
    public function salvarRelatorio(
        string $nome,
        string $descricao,
        string $tipo,
        array $parametros,
        string $formato,
        int $userId,
        ?array $agendamento = null
    ): Relatorio {
        return Relatorio::create([
            'nome' => $nome,
            'descricao' => $descricao,
            'tipo' => $tipo,
            'parametros' => $parametros,
            'formato' => $formato,
            'user_id' => $userId,
            'agendamento' => $agendamento
        ]);
    }

    /**
     * Relatório de licitações por status
     *
     * @param array $parametros
     * @param string $formato
     * @return mixed
     */
    protected function relatorioLicitacoesPorStatus(array $parametros, string $formato)
    {
        // Filtros
        $dataInicio = $parametros['data_inicio'] ?? now()->startOfYear();
        $dataFim = $parametros['data_fim'] ?? now();

        // Consulta base
        $query = Licitacao::whereBetween('data_publicacao', [$dataInicio, $dataFim]);

        // Aplicar filtros adicionais
        if (isset($parametros['categoria_id'])) {
            $query->where('categoria_id', $parametros['categoria_id']);
        }

        if (isset($parametros['orgao_id'])) {
            $query->where('orgao_id', $parametros['orgao_id']);
        }

        // Agrupar por status
        $dados = $query->select('status_id', DB::raw('count(*) as total'))
            ->groupBy('status_id')
            ->with('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status->nome,
                    'cor' => $item->status->cor,
                    'total' => $item->total
                ];
            })
            ->toArray();

        // Total geral
        $totalGeral = array_sum(array_column($dados, 'total'));

        // Calcular percentuais
        foreach ($dados as &$item) {
            $item['percentual'] = $totalGeral > 0 ? round(($item['total'] / $totalGeral) * 100, 2) : 0;
        }

        // Preparar dados para o relatório
        $dadosRelatorio = [
            'titulo' => 'Relatório de Licitações por Status',
            'periodo' => 'De ' . Carbon::parse($dataInicio)->format('d/m/Y') . ' até ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'dados' => $dados,
            'total_geral' => $totalGeral,
            'gerado_em' => now()->format('d/m/Y H:i:s')
        ];

        // Retornar no formato solicitado
        return $this->formatarSaida($dadosRelatorio, $formato, 'licitacoes_por_status');
    }

    /**
     * Relatório de licitações por período
     *
     * @param array $parametros
     * @param string $formato
     * @return mixed
     */
    protected function relatorioLicitacoesPorPeriodo(array $parametros, string $formato)
    {
        // Filtros
        $dataInicio = $parametros['data_inicio'] ?? now()->subMonths(12)->startOfMonth();
        $dataFim = $parametros['data_fim'] ?? now()->endOfMonth();
        $agruparPor = $parametros['agrupar_por'] ?? 'mes'; // mes, semana, dia

        // Definir formato da data no agrupamento
        $formatoData = '%Y-%m';
        $formatoLabel = 'M/Y';

        if ($agruparPor === 'semana') {
            $formatoData = '%Y-%u';
            $formatoLabel = 'Semana %u/%Y';
        } elseif ($agruparPor === 'dia') {
            $formatoData = '%Y-%m-%d';
            $formatoLabel = 'd/m/Y';
        }

        // Consulta base
        $query = Licitacao::whereBetween('data_publicacao', [$dataInicio, $dataFim]);

        // Aplicar filtros adicionais
        if (isset($parametros['status_id'])) {
            $query->where('status_id', $parametros['status_id']);
        }

        if (isset($parametros['categoria_id'])) {
            $query->where('categoria_id', $parametros['categoria_id']);
        }

        // Agrupar por período
        $dados = $query->select(
                DB::raw("DATE_FORMAT(data_publicacao, '{$formatoData}') as periodo"),
                DB::raw('count(*) as total')
            )
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->map(function ($item) use ($formatoLabel, $agruparPor) {
                $periodo = $item->periodo;

                if ($agruparPor === 'mes') {
                    list($ano, $mes) = explode('-', $periodo);
                    $data = Carbon::createFromDate($ano, $mes, 1);
                    $label = $data->format($formatoLabel);
                } elseif ($agruparPor === 'semana') {
                    list($ano, $semana) = explode('-', $periodo);
                    $label = str_replace(['%u', '%Y'], [$semana, $ano], $formatoLabel);
                } else {
                    $data = Carbon::parse($periodo);
                    $label = $data->format($formatoLabel);
                }

                return [
                    'periodo' => $periodo,
                    'label' => $label,
                    'total' => $item->total
                ];
            })
            ->toArray();

        // Total geral
        $totalGeral = array_sum(array_column($dados, 'total'));

        // Preparar dados para o relatório
        $dadosRelatorio = [
            'titulo' => 'Relatório de Licitações por Período',
            'periodo' => 'De ' . Carbon::parse($dataInicio)->format('d/m/Y') . ' até ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'dados' => $dados,
            'total_geral' => $totalGeral,
            'gerado_em' => now()->format('d/m/Y H:i:s')
        ];

        // Retornar no formato solicitado
        return $this->formatarSaida($dadosRelatorio, $formato, 'licitacoes_por_periodo');
    }

    /**
     * Relatório de desempenho anual
     *
     * @param array $parametros
     * @param string $formato
     * @return mixed
     */
    protected function relatorioDesempenhoAnual(array $parametros, string $formato)
    {
        // Filtros
        $ano = $parametros['ano'] ?? now()->year;

        // Filtrar licitações do ano especificado
        $dataInicio = Carbon::createFromDate($ano, 1, 1)->startOfDay();
        $dataFim = Carbon::createFromDate($ano, 12, 31)->endOfDay();

        // Consulta base para todas as licitações do período
        $query = Licitacao::whereBetween('data_publicacao', [$dataInicio, $dataFim]);

        // Total de licitações participadas
        $totalParticipadas = (clone $query)->count();

        // Total de licitações ganhas
        $totalGanhas = (clone $query)
            ->whereHas('status', function (Builder $q) {
                $q->where('nome', 'Ganho');
            })
            ->count();

        // Total de licitações perdidas
        $totalPerdidas = (clone $query)
            ->whereHas('status', function (Builder $q) {
                $q->where('nome', 'Perdido');
            })
            ->count();

        // Total de licitações em andamento
        $totalAndamento = (clone $query)
            ->whereHas('status', function (Builder $q) {
                $q->whereNotIn('nome', ['Ganho', 'Perdido', 'Cancelado']);
            })
            ->count();

        // Valor total das licitações ganhas
        $valorTotalGanhas = (clone $query)
            ->whereHas('status', function (Builder $q) {
                $q->where('nome', 'Ganho');
            })
            ->sum('valor_estimado');

        // Dados por mês
        $dadosMensais = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $mesInicio = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
            $mesFim = Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->endOfDay();

            $queryMes = Licitacao::whereBetween('data_publicacao', [$mesInicio, $mesFim]);

            $totalMes = (clone $queryMes)->count();
            $ganhasMes = (clone $queryMes)
                ->whereHas('status', function (Builder $q) {
                    $q->where('nome', 'Ganho');
                })
                ->count();

            $perdidasMes = (clone $queryMes)
                ->whereHas('status', function (Builder $q) {
                    $q->where('nome', 'Perdido');
                })
                ->count();

            $valorGanhasMes = (clone $queryMes)
                ->whereHas('status', function (Builder $q) {
                    $q->where('nome', 'Ganho');
                })
                ->sum('valor_estimado');

            $dadosMensais[] = [
                'mes' => Carbon::createFromDate($ano, $mes, 1)->format('F'),
                'total' => $totalMes,
                'ganhas' => $ganhasMes,
                'perdidas' => $perdidasMes,
                'taxa_sucesso' => $totalMes > 0 ? round(($ganhasMes / $totalMes) * 100, 2) : 0,
                'valor_ganhas' => $valorGanhasMes
            ];
        }

        // Resumo do desempenho
        $resumo = [
            'total_participadas' => $totalParticipadas,
            'total_ganhas' => $totalGanhas,
            'total_perdidas' => $totalPerdidas,
            'total_andamento' => $totalAndamento,
            'taxa_sucesso' => $totalParticipadas > 0 ? round(($totalGanhas / $totalParticipadas) * 100, 2) : 0,
            'valor_total_ganhas' => $valorTotalGanhas
        ];

        // Preparar dados para o relatório
        $dadosRelatorio = [
            'titulo' => "Relatório de Desempenho Anual - {$ano}",
            'ano' => $ano,
            'resumo' => $resumo,
            'dados_mensais' => $dadosMensais,
            'gerado_em' => now()->format('d/m/Y H:i:s')
        ];

        // Retornar no formato solicitado
        return $this->formatarSaida($dadosRelatorio, $formato, 'desempenho_anual');
    }

    /**
     * Relatório de análise por categoria
     *
     * @param array $parametros
     * @param string $formato
     * @return mixed
     */
    protected function relatorioAnaliseCategoria(array $parametros, string $formato)
    {
        // Implementação do relatório de análise por categoria
        // ...

        $dadosRelatorio = [
            'titulo' => 'Relatório de Análise por Categoria',
            // Outros dados aqui
        ];

        // Retornar no formato solicitado
        return $this->formatarSaida($dadosRelatorio, $formato, 'analise_categorias');
    }

    /**
     * Relatório de licitações pendentes
     *
     * @param array $parametros
     * @param string $formato
     * @return mixed
     */
    protected function relatorioLicitacoesPendentes(array $parametros, string $formato)
    {
        // Implementação do relatório de licitações pendentes
        // ...

        $dadosRelatorio = [
            'titulo' => 'Relatório de Licitações Pendentes',
            // Outros dados aqui
        ];

        // Retornar no formato solicitado
        return $this->formatarSaida($dadosRelatorio, $formato, 'licitacoes_pendentes');
    }

    /**
     * Relatório comparativo entre períodos
     *
     * @param array $parametros
     * @param string $formato
     * @return mixed
     */
    protected function relatorioComparativoPeriodo(array $parametros, string $formato)
    {
        // Implementação do relatório comparativo entre períodos
        // ...

        $dadosRelatorio = [
            'titulo' => 'Relatório Comparativo entre Períodos',
            // Outros dados aqui
        ];

        // Retornar no formato solicitado
        return $this->formatarSaida($dadosRelatorio, $formato, 'comparativo_periodo');
    }

    /**
     * Formata a saída do relatório no formato solicitado
     *
     * @param array $dados
     * @param string $formato
     * @param string $nomeArquivo
     * @return mixed
     */
    protected function formatarSaida(array $dados, string $formato, string $nomeArquivo)
    {
        switch ($formato) {
            case 'html':
                return view("relatorios.{$nomeArquivo}", $dados);

            case 'pdf':
                $pdf = PDF::loadView("relatorios.{$nomeArquivo}", $dados);
                return $pdf->download("{$nomeArquivo}.pdf");

            case 'xlsx':
                return Excel::download(new LicitacoesExport($dados), "{$nomeArquivo}.xlsx");

            case 'csv':
                return Excel::download(new LicitacoesExport($dados), "{$nomeArquivo}.csv");

            case 'json':
                return response()->json($dados);

            default:
                throw new \InvalidArgumentException("Formato de saída '{$formato}' não suportado");
        }
    }

    /**
     * Executa relatórios agendados
     *
     * @return array Resultado da execução
     */
    public function executarRelatoriosAgendados(): array
    {
        $resultado = [
            'executados' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Buscar relatórios com agendamento
        $hoje = now();
        $diaSemana = $hoje->dayOfWeek;
        $diaMes = $hoje->day;

        $relatorios = Relatorio::whereNotNull('agendamento')
            ->get()
            ->filter(function (Relatorio $relatorio) use ($diaSemana, $diaMes) {
                // Verificar se o relatório deve ser executado hoje
                $agendamento = $relatorio->agendamento;

                if (isset($agendamento['frequencia'])) {
                    switch ($agendamento['frequencia']) {
                        case 'diario':
                            return true;

                        case 'semanal':
                            return isset($agendamento['dia_semana']) && $agendamento['dia_semana'] == $diaSemana;

                        case 'mensal':
                            return isset($agendamento['dia_mes']) && $agendamento['dia_mes'] == $diaMes;

                        case 'customizado':
                            // Lógica para frequência customizada
                            return false;

                        default:
                            return false;
                    }
                }

                return false;
            });

        // Executar cada relatório agendado
        foreach ($relatorios as $relatorio) {
            try {
                // Gerar o relatório
                $this->gerarRelatorio(
                    $relatorio->tipo,
                    $relatorio->parametros,
                    $relatorio->formato,
                    $relatorio->user_id
                );

                // Atualizar a data de última execução
                $relatorio->update([
                    'ultima_execucao' => now()
                ]);

                // Enviar para o usuário se configurado
                if (isset($relatorio->agendamento['enviar_email']) && $relatorio->agendamento['enviar_email']) {
                    $this->enviarRelatorioPorEmail($relatorio);
                }

                $resultado['executados']++;
                $resultado['detalhes'][] = [
                    'id' => $relatorio->id,
                    'nome' => $relatorio->nome,
                    'status' => 'sucesso',
                    'mensagem' => 'Relatório executado com sucesso'
                ];
            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = [
                    'id' => $relatorio->id,
                    'nome' => $relatorio->nome,
                    'status' => 'erro',
                    'mensagem' => "Erro: " . $e->getMessage()
                ];

                Log::error("Erro ao executar relatório agendado {$relatorio->id}: " . $e->getMessage());
            }
        }

        return $resultado;
    }

    /**
     * Envia relatório por email
     *
     * @param Relatorio $relatorio
     * @return bool
     */
    protected function enviarRelatorioPorEmail(Relatorio $relatorio): bool
    {
        try {
            $user = User::find($relatorio->user_id);

            if (!$user) {
                throw new \Exception("Usuário não encontrado");
            }

            // Gerar o relatório
            $conteudoRelatorio = $this->gerarRelatorio(
                $relatorio->tipo,
                $relatorio->parametros,
                $relatorio->formato === 'html' ? 'pdf' : $relatorio->formato, // Converter HTML para PDF no email
                $relatorio->user_id
            );

            // Enviar o email
            // Implementação depende do sistema de email utilizado
            // ...

            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao enviar relatório por email: " . $e->getMessage());
            return false;
        }
    }
}
