<?php

namespace App\Services\Scraping;

use App\Models\Licitacao;
use App\Models\Orgao;
use App\Models\Categoria;
use App\Models\Status;
use App\Services\Eventos\EventoService;
use App\Services\Notificacao\NotificacaoService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ScrapingService
{
    protected Client $httpClient;
    protected EventoService $eventoService;
    protected NotificacaoService $notificacaoService;

    // Status padrão para novas licitações coletadas
    protected int $statusPadraoId;

    public function __construct(
        EventoService $eventoService,
        NotificacaoService $notificacaoService
    ) {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false, // Desabilita verificação SSL para desenvolvimento
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);

        $this->eventoService = $eventoService;
        $this->notificacaoService = $notificacaoService;

        // Obtém o ID do status "Novo" ou cria se não existir
        $this->statusPadraoId = Status::firstOrCreate(
            ['nome' => 'Novo'],
            ['descricao' => 'Licitação recém identificada', 'cor' => '#3498db']
        )->id;
    }

    /**
     * Inicia o processo de coleta de licitações de um portal específico
     *
     * @param string $portal Identificador do portal (comprasnet, licitacoes-e, etc)
     * @param array $parametros Parâmetros específicos para o portal
     * @return array Resultado da operação
     */
    public function coletarLicitacoes(string $portal, array $parametros = []): array
    {
        $resultado = [
            'sucesso' => false,
            'mensagem' => '',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        try {
            switch ($portal) {
                case 'comprasnet':
                    $resultado = $this->coletarComprasNet($parametros);
                    break;
                case 'licitacoes-e':
                    $resultado = $this->coletarLicitacoesE($parametros);
                    break;
                case 'portal-transparencia':
                    $resultado = $this->coletarPortalTransparencia($parametros);
                    break;
                default:
                    throw new \InvalidArgumentException("Portal '{$portal}' não suportado");
            }
        } catch (\Exception $e) {
            Log::error("Erro ao coletar licitações do portal {$portal}: " . $e->getMessage());
            $resultado['mensagem'] = "Erro ao coletar licitações: " . $e->getMessage();
            $resultado['erros']++;
        }

        return $resultado;
    }

    /**
     * Coleta licitações do portal ComprasNet
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarComprasNet(array $parametros = []): array
    {
        $resultado = [
            'sucesso' => false,
            'mensagem' => '',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        try {
            // URL de busca do ComprasNet
            $url = 'https://comprasnet.gov.br/ConsultaLicitacoes/ConsLicitacaoPorData.asp';

            // Parâmetros de busca
            $dataInicio = $parametros['data_inicio'] ?? now()->subDays(7)->format('d/m/Y');
            $dataFim = $parametros['data_fim'] ?? now()->format('d/m/Y');

            // Parâmetros da requisição POST
            $postParams = [
                'form_params' => [
                    'NumDias' => 0,
                    'DataDe' => $dataInicio,
                    'DataAte' => $dataFim,
                    'Modalidade' => $parametros['modalidade'] ?? '',
                    'Situacao' => $parametros['situacao'] ?? '',
                    'Orgao' => $parametros['orgao'] ?? '',
                    'TipoLicitacao' => $parametros['tipo'] ?? ''
                ]
            ];

            // Faz a requisição
            $response = $this->httpClient->request('POST', $url, $postParams);
            $html = $response->getBody()->getContents();

            // Analisa o HTML com o Crawler
            $crawler = new Crawler($html);

            // Extrai informações das licitações
            $licitacoes = $crawler->filter('table.resultados tr')->each(function (Crawler $node) {
                // Pula a linha do cabeçalho
                if ($node->filter('th')->count() > 0) {
                    return null;
                }

                $colunas = $node->filter('td');

                // Verifica se temos células suficientes
                if ($colunas->count() < 6) {
                    return null;
                }

                // Extrai os dados
                $numeroEdital = $colunas->eq(0)->text();
                $objeto = $colunas->eq(1)->text();
                $orgao = $colunas->eq(2)->text();
                $dataAbertura = $colunas->eq(3)->text();
                $modalidade = $colunas->eq(4)->text();

                // Link para detalhes
                $link = '';
                if ($colunas->eq(5)->filter('a')->count() > 0) {
                    $link = 'https://comprasnet.gov.br' . $colunas->eq(5)->filter('a')->attr('href');
                }

                // Formata a data
                try {
                    $dataAbertura = \DateTime::createFromFormat('d/m/Y H:i', $dataAbertura);
                } catch (\Exception $e) {
                    $dataAbertura = null;
                }

                return [
                    'numero_edital' => trim($numeroEdital),
                    'objeto' => trim($objeto),
                    'orgao' => trim($orgao),
                    'data_abertura' => $dataAbertura,
                    'modalidade' => trim($modalidade),
                    'link_edital' => $link,
                    'fonte' => 'ComprasNet'
                ];
            });

            // Filtra valores nulos
            $licitacoes = array_filter($licitacoes);

            // Processa cada licitação
            foreach ($licitacoes as $dadosLicitacao) {
                $resultado['detalhes'][] = $this->processarLicitacao($dadosLicitacao);
            }

            // Atualiza estatísticas
            $resultado['sucesso'] = true;
            $resultado['mensagem'] = "Coleta concluída: {$resultado['novas_licitacoes']} novas licitações, {$resultado['atualizadas']} atualizadas, {$resultado['erros']} erros.";

            // Conta estatísticas
            foreach ($resultado['detalhes'] as $detalhe) {
                if ($detalhe['status'] === 'nova') {
                    $resultado['novas_licitacoes']++;
                } elseif ($detalhe['status'] === 'atualizada') {
                    $resultado['atualizadas']++;
                } elseif ($detalhe['status'] === 'erro') {
                    $resultado['erros']++;
                }
            }

        } catch (\Exception $e) {
            Log::error("Erro ao coletar licitações do ComprasNet: " . $e->getMessage());
            $resultado['mensagem'] = "Erro ao coletar licitações: " . $e->getMessage();
            $resultado['erros']++;
        }

        return $resultado;
    }

    /**
     * Coleta licitações do portal Licitações-e (Banco do Brasil)
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarLicitacoesE(array $parametros = []): array
    {
        // Implementação similar ao método ComprasNet, adaptada para o portal Licitações-e
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Método em implementação',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Implementação futura

        return $resultado;
    }

    /**
     * Coleta licitações do Portal da Transparência
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarPortalTransparencia(array $parametros = []): array
    {
        // Implementação similar aos métodos anteriores, adaptada para o Portal da Transparência
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Método em implementação',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Implementação futura

        return $resultado;
    }

    /**
     * Processa os dados de uma licitação, inserindo ou atualizando no banco
     *
     * @param array $dadosLicitacao
     * @return array
     */
    protected function processarLicitacao(array $dadosLicitacao): array
    {
        $resultado = [
            'numero_edital' => $dadosLicitacao['numero_edital'],
            'status' => 'erro',
            'mensagem' => ''
        ];

        try {
            // Verifica se a licitação já existe no banco
            $licitacao = Licitacao::where('numero_edital', $dadosLicitacao['numero_edital'])->first();

            // Obtém ou cria o órgão
            $orgao = Orgao::firstOrCreate(
                ['nome' => $dadosLicitacao['orgao']],
                ['sigla' => $this->gerarSigla($dadosLicitacao['orgao'])]
            );

            // Identifica a categoria com base no objeto (lógica simplificada)
            $categoria = $this->identificarCategoria($dadosLicitacao['objeto']);

            if (!$licitacao) {
                // Nova licitação
                $licitacao = Licitacao::create([
                    'numero_edital' => $dadosLicitacao['numero_edital'],
                    'objeto' => $dadosLicitacao['objeto'],
                    'modalidade' => $dadosLicitacao['modalidade'],
                    'data_abertura' => $dadosLicitacao['data_abertura'],
                    'orgao_id' => $orgao->id,
                    'categoria_id' => $categoria->id,
                    'status_id' => $this->statusPadraoId,
                    'link_edital' => $dadosLicitacao['link_edital'],
                    'fonte' => $dadosLicitacao['fonte']
                ]);

                // Registra o evento
                $this->eventoService->registrarEvento(
                    $licitacao->id,
                    'nova_licitacao',
                    'Licitação identificada automaticamente',
                    "Nova licitação identificada pelo sistema de scraping (fonte: {$dadosLicitacao['fonte']}).",
                    now(),
                    null // Sistema
                );

                // Notifica usuários com papel 'analista'
                $this->notificacaoService->notificarPorPapel(
                    'nova_licitacao',
                    'Nova licitação identificada',
                    "Uma nova licitação foi identificada: {$licitacao->numero_edital} - {$licitacao->objeto}",
                    'analista',
                    $licitacao->id
                );

                $resultado['status'] = 'nova';
                $resultado['mensagem'] = 'Nova licitação cadastrada';

            } else {
                // Atualiza licitação existente se houver novos dados
                $atualizado = false;

                // Campos que podem ser atualizados
                $camposAtualizaveis = [
                    'objeto', 'modalidade', 'data_abertura', 'link_edital'
                ];

                foreach ($camposAtualizaveis as $campo) {
                    if (isset($dadosLicitacao[$campo]) && $dadosLicitacao[$campo] && $licitacao->$campo != $dadosLicitacao[$campo]) {
                        $licitacao->$campo = $dadosLicitacao[$campo];
                        $atualizado = true;
                    }
                }

                if ($atualizado) {
                    $licitacao->save();

                    // Registra o evento
                    $this->eventoService->registrarEvento(
                        $licitacao->id,
                        'atualizacao',
                        'Licitação atualizada automaticamente',
                        "Dados da licitação atualizados pelo sistema de scraping (fonte: {$dadosLicitacao['fonte']}).",
                        now(),
                        null // Sistema
                    );

                    $resultado['status'] = 'atualizada';
                    $resultado['mensagem'] = 'Licitação atualizada';
                } else {
                    $resultado['status'] = 'ignorada';
                    $resultado['mensagem'] = 'Sem alterações nos dados';
                }
            }

        } catch (\Exception $e) {
            Log::error("Erro ao processar licitação {$dadosLicitacao['numero_edital']}: " . $e->getMessage());
            $resultado['mensagem'] = "Erro: " . $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Identifica a categoria da licitação com base no objeto
     *
     * @param string $objeto
     * @return Categoria
     */
    protected function identificarCategoria(string $objeto): Categoria
    {
        $objeto = strtolower($objeto);

        // Palavras-chave para cada categoria
        $categorias = [
            'Obras' => ['construção', 'reforma', 'obra', 'edificação', 'pavimentação'],
            'Serviços' => ['serviço', 'manutenção', 'consultoria', 'assessoria'],
            'TI' => ['software', 'computador', 'informática', 'sistema', 'tecnologia'],
            'Saúde' => ['medicamento', 'hospital', 'médico', 'saúde', 'farmacêutico'],
            'Alimentos' => ['alimentação', 'alimento', 'refeição', 'merenda'],
            'Equipamentos' => ['equipamento', 'mobiliário', 'móveis', 'máquina']
        ];

        // Procura por palavras-chave no objeto
        foreach ($categorias as $nomeCategoria => $palavrasChave) {
            foreach ($palavrasChave as $palavra) {
                if (strpos($objeto, $palavra) !== false) {
                    return Categoria::firstOrCreate(
                        ['nome' => $nomeCategoria],
                        ['descricao' => "Licitações relacionadas a {$nomeCategoria}"]
                    );
                }
            }
        }

        // Categoria padrão
        return Categoria::firstOrCreate(
            ['nome' => 'Outros'],
            ['descricao' => 'Categoria padrão para licitações não classificadas']
        );
    }

    /**
     * Gera uma sigla a partir do nome do órgão
     *
     * @param string $nomeOrgao
     * @return string
     */
    protected function gerarSigla(string $nomeOrgao): string
    {
        $palavras = explode(' ', $nomeOrgao);
        $sigla = '';

        // Ignorar artigos, preposições, etc.
        $ignorar = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'o', 'as', 'os'];

        foreach ($palavras as $palavra) {
            $palavra = strtolower(trim($palavra));
            if (strlen($palavra) > 2 && !in_array($palavra, $ignorar)) {
                $sigla .= strtoupper(substr($palavra, 0, 1));
            }
        }

        return $sigla;
    }

    /**
     * Agenda tarefas de scraping periódicas
     *
     * @param array $parametros
     * @return bool
     */
    public function agendarTarefasScraping(array $parametros = []): bool
    {
        try {
            // Implementação da lógica para agendar tarefas no Laravel Task Scheduler
            // Esta é apenas uma representação, a implementação real seria no arquivo App\Console\Kernel.php

            // Exemplo de lógica:
            /*
            $schedule->call(function () use ($parametros) {
                $service = app(ScrapingService::class);
                $service->coletarLicitacoes('comprasnet', $parametros);
            })->daily();
            */

            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao agendar tarefas de scraping: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém dados detalhados de uma licitação específica
     *
     * @param string $url URL com os detalhes da licitação
     * @return array|null Dados detalhados ou null em caso de erro
     */
    public function obterDetalhesLicitacao(string $url): ?array
    {
        try {
            // Faz a requisição para a página de detalhes
            $response = $this->httpClient->request('GET', $url);
            $html = $response->getBody()->getContents();

            // Analisa o HTML com o Crawler
            $crawler = new Crawler($html);

            // A extração dos detalhes depende da estrutura do site
            // Este é um exemplo genérico que deve ser adaptado para cada portal

            $detalhes = [
                'valor_estimado' => null,
                'data_publicacao' => null,
                'documentos' => []
            ];

            // Exemplo de extração (fictício, deve ser adaptado)
            $crawler->filter('table.detalhes tr')->each(function (Crawler $node) use (&$detalhes) {
                $label = $node->filter('td')->eq(0)->text();
                $valor = $node->filter('td')->eq(1)->text();

                if (strpos(strtolower($label), 'valor') !== false) {
                    // Limpa formatação de moeda e converte para decimal
                    $valor = str_replace(['Ri', $dataAbertura);
                } catch (\Exception $e) {
                    $dataAbertura = null;
                }

                return [
                    'numero_edital' => trim($numeroEdital),
                    'objeto' => trim($objeto),
                    'orgao' => trim($orgao),
                    'data_abertura' => $dataAbertura,
                    'modalidade' => trim($modalidade),
                    'link_edital' => $link,
                    'fonte' => 'ComprasNet'
                ];
            });

            // Filtra valores nulos
            $licitacoes = array_filter($licitacoes);

            // Processa cada licitação
            foreach ($licitacoes as $dadosLicitacao) {
                $resultado['detalhes'][] = $this->processarLicitacao($dadosLicitacao);
            }

            // Atualiza estatísticas
            $resultado['sucesso'] = true;
            $resultado['mensagem'] = "Coleta concluída: {$resultado['novas_licitacoes']} novas licitações, {$resultado['atualizadas']} atualizadas, {$resultado['erros']} erros.";

            // Conta estatísticas
            foreach ($resultado['detalhes'] as $detalhe) {
                if ($detalhe['status'] === 'nova') {
                    $resultado['novas_licitacoes']++;
                } elseif ($detalhe['status'] === 'atualizada') {
                    $resultado['atualizadas']++;
                } elseif ($detalhe['status'] === 'erro') {
                    $resultado['erros']++;
                }
            }

        } catch (\Exception $e) {
            Log::error("Erro ao coletar licitações do ComprasNet: " . $e->getMessage());
            $resultado['mensagem'] = "Erro ao coletar licitações: " . $e->getMessage();
            $resultado['erros']++;
        }

        return $resultado;
    }

    /**
     * Coleta licitações do portal Licitações-e (Banco do Brasil)
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarLicitacoesE(array $parametros = []): array
    {
        // Implementação similar ao método ComprasNet, adaptada para o portal Licitações-e
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Método em implementação',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Implementação futura

        return $resultado;
    }

    /**
     * Coleta licitações do Portal da Transparência
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarPortalTransparencia(array $parametros = []): array
    {
        // Implementação similar aos métodos anteriores, adaptada para o Portal da Transparência
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Método em implementação',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Implementação futura

        return $resultado;
    }

    /**
     * Processa os dados de uma licitação, inserindo ou atualizando no banco
     *
     * @param array $dadosLicitacao
     * @return array
     */
    protected function processarLicitacao(array $dadosLicitacao): array
    {
        $resultado = [
            'numero_edital' => $dadosLicitacao['numero_edital'],
            'status' => 'erro',
            'mensagem' => ''
        ];

        try {
            // Verifica se a licitação já existe no banco
            $licitacao = Licitacao::where('numero_edital', $dadosLicitacao['numero_edital'])->first();

            // Obtém ou cria o órgão
            $orgao = Orgao::firstOrCreate(
                ['nome' => $dadosLicitacao['orgao']],
                ['sigla' => $this->gerarSigla($dadosLicitacao['orgao'])]
            );

            // Identifica a categoria com base no objeto (lógica simplificada)
            $categoria = $this->identificarCategoria($dadosLicitacao['objeto']);

            if (!$licitacao) {
                // Nova licitação
                $licitacao = Licitacao::create([
                    'numero_edital' => $dadosLicitacao['numero_edital'],
                    'objeto' => $dadosLicitacao['objeto'],
                    'modalidade' => $dadosLicitacao['modalidade'],
                    'data_abertura' => $dadosLicitacao['data_abertura'],
                    'orgao_id' => $orgao->id,
                    'categoria_id' => $categoria->id,
                    'status_id' => $this->statusPadraoId,
                    'link_edital' => $dadosLicitacao['link_edital'],
                    'fonte' => $dadosLicitacao['fonte']
                ]);

                // Registra o evento
                $this->eventoService->registrarEvento(
                    $licitacao->id,
                    'nova_licitacao',
                    'Licitação identificada automaticamente',
                    "Nova licitação identificada pelo sistema de scraping (fonte: {$dadosLicitacao['fonte']}).",
                    now(),
                    null // Sistema
                );

                // Notifica usuários com papel 'analista'
                $this->notificacaoService->notificarPorPapel(
                    'nova_licitacao',
                    'Nova licitação identificada',
                    "Uma nova licitação foi identificada: {$licitacao->numero_edital} - {$licitacao->objeto}",
                    'analista',
                    $licitacao->id
                );

                $resultado['status'] = 'nova';
                $resultado['mensagem'] = 'Nova licitação cadastrada';

            } else {
                // Atualiza licitação existente se houver novos dados
                $atualizado = false;

                // Campos que podem ser atualizados
                $camposAtualizaveis = [
                    'objeto', 'modalidade', 'data_abertura', 'link_edital'
                ];

                foreach ($camposAtualizaveis as $campo) {
                    if (isset($dadosLicitacao[$campo]) && $dadosLicitacao[$campo] && $licitacao->$campo != $dadosLicitacao[$campo]) {
                        $licitacao->$campo = $dadosLicitacao[$campo];
                        $atualizado = true;
                    }
                }

                if ($atualizado) {
                    $licitacao->save();

                    // Registra o evento
                    $this->eventoService->registrarEvento(
                        $licitacao->id,
                        'atualizacao',
                        'Licitação atualizada automaticamente',
                        "Dados da licitação atualizados pelo sistema de scraping (fonte: {$dadosLicitacao['fonte']}).",
                        now(),
                        null // Sistema
                    );

                    $resultado['status'] = 'atualizada';
                    $resultado['mensagem'] = 'Licitação atualizada';
                } else {
                    $resultado['status'] = 'ignorada';
                    $resultado['mensagem'] = 'Sem alterações nos dados';
                }
            }

        } catch (\Exception $e) {
            Log::error("Erro ao processar licitação {$dadosLicitacao['numero_edital']}: " . $e->getMessage());
            $resultado['mensagem'] = "Erro: " . $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Identifica a categoria da licitação com base no objeto
     *
     * @param string $objeto
     * @return Categoria
     */
    protected function identificarCategoria(string $objeto): Categoria
    {
        $objeto = strtolower($objeto);

        // Palavras-chave para cada categoria
        $categorias = [
            'Obras' => ['construção', 'reforma', 'obra', 'edificação', 'pavimentação'],
            'Serviços' => ['serviço', 'manutenção', 'consultoria', 'assessoria'],
            'TI' => ['software', 'computador', 'informática', 'sistema', 'tecnologia'],
            'Saúde' => ['medicamento', 'hospital', 'médico', 'saúde', 'farmacêutico'],
            'Alimentos' => ['alimentação', 'alimento', 'refeição', 'merenda'],
            'Equipamentos' => ['equipamento', 'mobiliário', 'móveis', 'máquina']
        ];

        // Procura por palavras-chave no objeto
        foreach ($categorias as $nomeCategoria => $palavrasChave) {
            foreach ($palavrasChave as $palavra) {
                if (strpos($objeto, $palavra) !== false) {
                    return Categoria::firstOrCreate(
                        ['nome' => $nomeCategoria],
                        ['descricao' => "Licitações relacionadas a {$nomeCategoria}"]
                    );
                }
            }
        }

        // Categoria padrão
        return Categoria::firstOrCreate(
            ['nome' => 'Outros'],
            ['descricao' => 'Categoria padrão para licitações não classificadas']
        );
    }

    /**
     * Gera uma sigla a partir do nome do órgão
     *
     * @param string $nomeOrgao
     * @return string
     */
    protected function gerarSigla(string $nomeOrgao): string
    {
        $palavras = explode(' ', $nomeOrgao);
        $sigla = '';

        // Ignorar artigos, preposições, etc.
        $ignorar = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'o', 'as', 'os'];

        foreach ($palavras as $palavra) {
            $palavra = strtolower(trim($palavra));
            if (strlen($palavra) > 2 && !in_array($palavra, $ignorar)) {
                $sigla .= strtoupper(substr($palavra, 0, 1));
            }
        }

        return $sigla;
    }

    /**
     * Agenda tarefas de scraping periódicas
     *
     * @param array $parametros
     * @return bool
     */
    public function agendarTarefasScraping(array $parametros = []): bool
    {
        try {
            // Implementação da lógica para agendar tarefas no Laravel Task Scheduler
            // Esta é apenas uma representação, a implementação real seria no arquivo App\Console\Kernel.php

            // Exemplo de lógica:
            /*
            $schedule->call(function () use ($parametros) {
                $service = app(ScrapingService::class);
                $service->coletarLicitacoes('comprasnet', $parametros);
            })->daily();
            */

            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao agendar tarefas de scraping: " . $e->getMessage());
            return false, '.', ','], ['', '', '.'], $valor);
                    $detalhes['valor_estimado'] = (float) trim($valor);
                }

                if (strpos(strtolower($label), 'publicação') !== false) {
                    try {
                        $detalhes['data_publicacao'] = \DateTime::createFromFormat('d/m/Y', trim($valor));
                    } catch (\Exception $e) {
                        // Ignora erro de formatação
                    }
                }
            });

            // Extração de links para documentos
            $crawler->filter('a[href*="edital"]')->each(function (Crawler $node) use (&$detalhes) {
                $detalhes['documentos'][] = [
                    'nome' => $node->text(),
                    'url' => $node->attr('href')
                ];
            });

            return $detalhes;

        } catch (\Exception $e) {
            Log::error("Erro ao obter detalhes da licitação: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Faz download de documentos associados a uma licitação
     *
     * @param int $licitacaoId ID da licitação
     * @param array $documentos Array com URLs dos documentos
     * @return array Resultado do processamento
     */
    public function downloadDocumentos(int $licitacaoId, array $documentos): array
    {
        $resultado = [
            'sucesso' => false,
            'total' => count($documentos),
            'baixados' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        $licitacao = Licitacao::find($licitacaoId);

        if (!$licitacao) {
            $resultado['mensagem'] = "Licitação não encontrada";
            return $resultado;
        }

        foreach ($documentos as $documento) {
            try {
                $url = $documento['url'];
                $nome = $documento['nome'] ?? basename($url);

                // Cria a pasta de destino se não existir
                $pastaDestino = storage_path("app/public/documentos/licitacoes/{$licitacaoId}");
                if (!file_exists($pastaDestino)) {
                    mkdir($pastaDestino, 0755, true);
                }

                // Caminho completo do arquivo
                $caminhoArquivo = "{$pastaDestino}/{$nome}";

                // Faz o download do arquivo
                $response = $this->httpClient->request('GET', $url, [
                    'sink' => $caminhoArquivo
                ]);

                // Obtém o tipo MIME e tamanho
                $mimeType = $response->getHeaderLine('Content-Type');
                $tamanho = filesize($caminhoArquivo);

                // Registra o documento no banco de dados
                $doc = $licitacao->documentos()->create([
                    'nome' => $nome,
                    'tipo' => 'edital',
                    'path' => "documentos/licitacoes/{$licitacaoId}/{$nome}",
                    'mime_type' => $mimeType,
                    'tamanho' => $tamanho
                ]);

                $resultado['baixados']++;
                $resultado['detalhes'][] = [
                    'nome' => $nome,
                    'status' => 'sucesso',
                    'mensagem' => 'Documento baixado com sucesso'
                ];

            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = [
                    'nome' => $documento['nome'] ?? 'Desconhecido',
                    'status' => 'erro',
                    'mensagem' => "Erro: " . $e->getMessage()
                ];

                Log::error("Erro ao baixar documento: " . $e->getMessage());
            }
        }

        $resultado['sucesso'] = ($resultado['baixados'] > 0);
        $resultado['mensagem'] = "Processamento concluído: {$resultado['baixados']} documentos baixados, {$resultado['erros']} erros.";

        return $resultado;
    }
}i', $dataAbertura);
                } catch (\Exception $e) {
                    $dataAbertura = null;
                }

                return [
                    'numero_edital' => trim($numeroEdital),
                    'objeto' => trim($objeto),
                    'orgao' => trim($orgao),
                    'data_abertura' => $dataAbertura,
                    'modalidade' => trim($modalidade),
                    'link_edital' => $link,
                    'fonte' => 'ComprasNet'
                ];
            });

            // Filtra valores nulos
            $licitacoes = array_filter($licitacoes);

            // Processa cada licitação
            foreach ($licitacoes as $dadosLicitacao) {
                $resultado['detalhes'][] = $this->processarLicitacao($dadosLicitacao);
            }

            // Atualiza estatísticas
            $resultado['sucesso'] = true;
            $resultado['mensagem'] = "Coleta concluída: {$resultado['novas_licitacoes']} novas licitações, {$resultado['atualizadas']} atualizadas, {$resultado['erros']} erros.";

            // Conta estatísticas
            foreach ($resultado['detalhes'] as $detalhe) {
                if ($detalhe['status'] === 'nova') {
                    $resultado['novas_licitacoes']++;
                } elseif ($detalhe['status'] === 'atualizada') {
                    $resultado['atualizadas']++;
                } elseif ($detalhe['status'] === 'erro') {
                    $resultado['erros']++;
                }
            }

        } catch (\Exception $e) {
            Log::error("Erro ao coletar licitações do ComprasNet: " . $e->getMessage());
            $resultado['mensagem'] = "Erro ao coletar licitações: " . $e->getMessage();
            $resultado['erros']++;
        }

        return $resultado;
    }

    /**
     * Coleta licitações do portal Licitações-e (Banco do Brasil)
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarLicitacoesE(array $parametros = []): array
    {
        // Implementação similar ao método ComprasNet, adaptada para o portal Licitações-e
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Método em implementação',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Implementação futura

        return $resultado;
    }

    /**
     * Coleta licitações do Portal da Transparência
     *
     * @param array $parametros
     * @return array
     */
    protected function coletarPortalTransparencia(array $parametros = []): array
    {
        // Implementação similar aos métodos anteriores, adaptada para o Portal da Transparência
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Método em implementação',
            'novas_licitacoes' => 0,
            'atualizadas' => 0,
            'erros' => 0,
            'detalhes' => []
        ];

        // Implementação futura

        return $resultado;
    }

    /**
     * Processa os dados de uma licitação, inserindo ou atualizando no banco
     *
     * @param array $dadosLicitacao
     * @return array
     */
    protected function processarLicitacao(array $dadosLicitacao): array
    {
        $resultado = [
            'numero_edital' => $dadosLicitacao['numero_edital'],
            'status' => 'erro',
            'mensagem' => ''
        ];

        try {
            // Verifica se a licitação já existe no banco
            $licitacao = Licitacao::where('numero_edital', $dadosLicitacao['numero_edital'])->first();

            // Obtém ou cria o órgão
            $orgao = Orgao::firstOrCreate(
                ['nome' => $dadosLicitacao['orgao']],
                ['sigla' => $this->gerarSigla($dadosLicitacao['orgao'])]
            );

            // Identifica a categoria com base no objeto (lógica simplificada)
            $categoria = $this->identificarCategoria($dadosLicitacao['objeto']);

            if (!$licitacao) {
                // Nova licitação
                $licitacao = Licitacao::create([
                    'numero_edital' => $dadosLicitacao['numero_edital'],
                    'objeto' => $dadosLicitacao['objeto'],
                    'modalidade' => $dadosLicitacao['modalidade'],
                    'data_abertura' => $dadosLicitacao['data_abertura'],
                    'orgao_id' => $orgao->id,
                    'categoria_id' => $categoria->id,
                    'status_id' => $this->statusPadraoId,
                    'link_edital' => $dadosLicitacao['link_edital'],
                    'fonte' => $dadosLicitacao['fonte']
                ]);

                // Registra o evento
                $this->eventoService->registrarEvento(
                    $licitacao->id,
                    'nova_licitacao',
                    'Licitação identificada automaticamente',
                    "Nova licitação identificada pelo sistema de scraping (fonte: {$dadosLicitacao['fonte']}).",
                    now(),
                    null // Sistema
                );

                // Notifica usuários com papel 'analista'
                $this->notificacaoService->notificarPorPapel(
                    'nova_licitacao',
                    'Nova licitação identificada',
                    "Uma nova licitação foi identificada: {$licitacao->numero_edital} - {$licitacao->objeto}",
                    'analista',
                    $licitacao->id
                );

                $resultado['status'] = 'nova';
                $resultado['mensagem'] = 'Nova licitação cadastrada';

            } else {
                // Atualiza licitação existente se houver novos dados
                $atualizado = false;

                // Campos que podem ser atualizados
                $camposAtualizaveis = [
                    'objeto', 'modalidade', 'data_abertura', 'link_edital'
                ];

                foreach ($camposAtualizaveis as $campo) {
                    if (isset($dadosLicitacao[$campo]) && $dadosLicitacao[$campo] && $licitacao->$campo != $dadosLicitacao[$campo]) {
                        $licitacao->$campo = $dadosLicitacao[$campo];
                        $atualizado = true;
                    }
                }

                if ($atualizado) {
                    $licitacao->save();

                    // Registra o evento
                    $this->eventoService->registrarEvento(
                        $licitacao->id,
                        'atualizacao',
                        'Licitação atualizada automaticamente',
                        "Dados da licitação atualizados pelo sistema de scraping (fonte: {$dadosLicitacao['fonte']}).",
                        now(),
                        null // Sistema
                    );

                    $resultado['status'] = 'atualizada';
                    $resultado['mensagem'] = 'Licitação atualizada';
                } else {
                    $resultado['status'] = 'ignorada';
                    $resultado['mensagem'] = 'Sem alterações nos dados';
                }
            }

        } catch (\Exception $e) {
            Log::error("Erro ao processar licitação {$dadosLicitacao['numero_edital']}: " . $e->getMessage());
            $resultado['mensagem'] = "Erro: " . $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Identifica a categoria da licitação com base no objeto
     *
     * @param string $objeto
     * @return Categoria
     */
    protected function identificarCategoria(string $objeto): Categoria
    {
        $objeto = strtolower($objeto);

        // Palavras-chave para cada categoria
        $categorias = [
            'Obras' => ['construção', 'reforma', 'obra', 'edificação', 'pavimentação'],
            'Serviços' => ['serviço', 'manutenção', 'consultoria', 'assessoria'],
            'TI' => ['software', 'computador', 'informática', 'sistema', 'tecnologia'],
            'Saúde' => ['medicamento', 'hospital', 'médico', 'saúde', 'farmacêutico'],
            'Alimentos' => ['alimentação', 'alimento', 'refeição', 'merenda'],
            'Equipamentos' => ['equipamento', 'mobiliário', 'móveis', 'máquina']
        ];

        // Procura por palavras-chave no objeto
        foreach ($categorias as $nomeCategoria => $palavrasChave) {
            foreach ($palavrasChave as $palavra) {
                if (strpos($objeto, $palavra) !== false) {
                    return Categoria::firstOrCreate(
                        ['nome' => $nomeCategoria],
                        ['descricao' => "Licitações relacionadas a {$nomeCategoria}"]
                    );
                }
            }
        }

        // Categoria padrão
        return Categoria::firstOrCreate(
            ['nome' => 'Outros'],
            ['descricao' => 'Categoria padrão para licitações não classificadas']
        );
    }

    /**
     * Gera uma sigla a partir do nome do órgão
     *
     * @param string $nomeOrgao
     * @return string
     */
    protected function gerarSigla(string $nomeOrgao): string
    {
        $palavras = explode(' ', $nomeOrgao);
        $sigla = '';

        // Ignorar artigos, preposições, etc.
        $ignorar = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'o', 'as', 'os'];

        foreach ($palavras as $palavra) {
            $palavra = strtolower(trim($palavra));
            if (strlen($palavra) > 2 && !in_array($palavra, $ignorar)) {
                $sigla .= strtoupper(substr($palavra, 0, 1));
            }
        }

        return $sigla;
    }

    /**
     * Agenda tarefas de scraping periódicas
     *
     * @param array $parametros
     * @return bool
     */
    public function agendarTarefasScraping(array $parametros = []): bool
    {
        try {
            // Implementação da lógica para agendar tarefas no Laravel Task Scheduler
            // Esta é apenas uma representação, a implementação real seria no arquivo App\Console\Kernel.php

            // Exemplo de lógica:
            /*
            $schedule->call(function () use ($parametros) {
                $service = app(ScrapingService::class);
                $service->coletarLicitacoes('comprasnet', $parametros);
            })->daily();
            */

            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao agendar tarefas de scraping: " . $e->getMessage());
            return false
