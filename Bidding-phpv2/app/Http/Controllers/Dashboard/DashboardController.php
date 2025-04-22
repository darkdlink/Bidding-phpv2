<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Licitacao;
use App\Models\Categoria;
use App\Models\Orgao;
use App\Models\Status;
use App\Models\Tarefa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:acessar_dashboard');
    }

    /**
     * Exibe o dashboard principal com estatísticas e gráficos
     */
    public function index()
    {
        // Licitações por status
        $licitacoesPorStatus = Licitacao::select('status_id', DB::raw('count(*) as total'))
            ->groupBy('status_id')
            ->with('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status->nome,
                    'cor' => $item->status->cor,
                    'total' => $item->total
                ];
            });

        // Licitações com abertura nos próximos 7 dias
        $proximasAberturas = Licitacao::proximasAberturas(7)
            ->with(['orgao', 'status'])
            ->orderBy('data_abertura')
            ->take(5)
            ->get();

        // Tarefas pendentes do usuário atual
        $tarefasPendentes = Tarefa::where('responsavel_id', Auth::id())
            ->where('status', '!=', 'concluida')
            ->orderBy('prazo')
            ->with('licitacao')
            ->take(5)
            ->get();

        // Licitações por categoria (para gráfico)
        $licitacoesPorCategoria = Licitacao::select('categoria_id', DB::raw('count(*) as total'))
            ->groupBy('categoria_id')
            ->with('categoria')
            ->get()
            ->map(function ($item) {
                return [
                    'categoria' => $item->categoria->nome,
                    'total' => $item->total
                ];
            });

        // Licitações por mês (últimos 6 meses)
        $licitacoesPorMes = Licitacao::select(
                DB::raw('YEAR(data_publicacao) as ano'),
                DB::raw('MONTH(data_publicacao) as mes'),
                DB::raw('count(*) as total')
            )
            ->where('data_publicacao', '>=', now()->subMonths(6))
            ->groupBy('ano', 'mes')
            ->orderBy('ano')
            ->orderBy('mes')
            ->get()
            ->map(function ($item) {
                $data = \Carbon\Carbon::createFromDate($item->ano, $item->mes, 1);
                return [
                    'mes' => $data->format('M/Y'),
                    'total' => $item->total
                ];
            });

        // Valor total estimado das licitações ativas
        $valorTotalEstimado = Licitacao::whereHas('status', function ($query) {
                $query->whereNotIn('nome', ['Concluído', 'Cancelado', 'Perdido']);
            })
            ->sum('valor_estimado');

        // Total de licitações por órgão (top 5)
        $licitacoesPorOrgao = Licitacao::select('orgao_id', DB::raw('count(*) as total'))
            ->groupBy('orgao_id')
            ->with('orgao')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'orgao' => $item->orgao->nome,
                    'total' => $item->total
                ];
            });

        // Resumo dos indicadores
        $resumo = [
            'total_licitacoes' => Licitacao::count(),
            'licitacoes_ativas' => Licitacao::whereHas('status', function ($query) {
                    $query->whereNotIn('nome', ['Concluído', 'Cancelado', 'Perdido']);
                })
                ->count(),
            'licitacoes_ganhas' => Licitacao::whereHas('status', function ($query) {
                    $query->where('nome', 'Ganho');
                })
                ->count(),
            'taxa_sucesso' => $this->calcularTaxaSucesso(),
        ];

        return view('dashboard.index', compact(
            'licitacoesPorStatus',
            'proximasAberturas',
            'tarefasPendentes',
            'licitacoesPorCategoria',
            'licitacoesPorMes',
            'valorTotalEstimado',
            'licitacoesPorOrgao',
            'resumo'
        ));
    }

    /**
     * Calcula a taxa de sucesso das licitações (ganhas / total de finalizadas)
     */
    private function calcularTaxaSucesso()
    {
        $ganhas = Licitacao::whereHas('status', function ($query) {
                $query->where('nome', 'Ganho');
            })
            ->count();

        $finalizadas = Licitacao::whereHas('status', function ($query) {
                $query->whereIn('nome', ['Ganho', 'Perdido', 'Concluído', 'Cancelado']);
            })
            ->count();

        if ($finalizadas == 0) {
            return 0;
        }

        return round(($ganhas / $finalizadas) * 100, 2);
    }
}
