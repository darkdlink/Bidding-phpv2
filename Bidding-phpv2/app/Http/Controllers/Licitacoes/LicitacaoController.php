<?php

namespace App\Http\Controllers\Licitacoes;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLicitacaoRequest;
use App\Http\Requests\UpdateLicitacaoRequest;
use App\Models\Categoria;
use App\Models\Licitacao;
use App\Models\Orgao;
use App\Models\Status;
use App\Services\Eventos\EventoService;
use App\Services\Notificacao\NotificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LicitacaoController extends Controller
{
    protected EventoService $eventoService;
    protected NotificacaoService $notificacaoService;

    public function __construct(
        EventoService $eventoService,
        NotificacaoService $notificacaoService
    ) {
        $this->eventoService = $eventoService;
        $this->notificacaoService = $notificacaoService;

        $this->middleware('permission:visualizar_licitacoes')->only(['index', 'show']);
        $this->middleware('permission:criar_licitacoes')->only(['create', 'store']);
        $this->middleware('permission:editar_licitacoes')->only(['edit', 'update']);
        $this->middleware('permission:excluir_licitacoes')->only(['destroy']);
    }

    /**
     * Exibe a lista de licitações
     */
    public function index(Request $request)
    {
        $query = Licitacao::with(['orgao', 'categoria', 'status', 'responsavel']);

        // Filtros
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('orgao_id')) {
            $query->where('orgao_id', $request->orgao_id);
        }

        if ($request->filled('data_inicio') && $request->filled('data_fim')) {
            $query->whereBetween('data_abertura', [$request->data_inicio, $request->data_fim]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('numero_edital', 'like', "%{$search}%")
                  ->orWhere('objeto', 'like', "%{$search}%");
            });
        }

        if ($request->filled('responsavel_id')) {
            $query->where('responsavel_id', $request->responsavel_id);
        }

        // Ordenação
        $orderBy = $request->order_by ?? 'data_abertura';
        $orderDirection = $request->order_direction ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        $licitacoes = $query->paginate(15);

        $statusList = Status::pluck('nome', 'id');
        $categorias = Categoria::pluck('nome', 'id');
        $orgaos = Orgao::pluck('nome', 'id');

        return view('licitacoes.index', compact('licitacoes', 'statusList', 'categorias', 'orgaos'));
    }

    /**
     * Mostra o formulário para criar uma nova licitação
     */
    public function create()
    {
        $statusList = Status::pluck('nome', 'id');
        $categorias = Categoria::pluck('nome', 'id');
        $orgaos = Orgao::pluck('nome', 'id');

        return view('licitacoes.create', compact('statusList', 'categorias', 'orgaos'));
    }

    /**
     * Armazena uma nova licitação no banco de dados
     */
    public function store(StoreLicitacaoRequest $request)
    {
        try {
            DB::beginTransaction();

            $licitacao = Licitacao::create([
                'numero_edital' => $request->numero_edital,
                'objeto' => $request->objeto,
                'modalidade' => $request->modalidade,
                'valor_estimado' => $request->valor_estimado,
                'data_publicacao' => $request->data_publicacao,
                'data_abertura' => $request->data_abertura,
                'orgao_id' => $request->orgao_id,
                'categoria_id' => $request->categoria_id,
                'status_id' => $request->status_id,
                'responsavel_id' => $request->responsavel_id ?? Auth::id(),
                'link_edital' => $request->link_edital,
                'observacoes' => $request->observacoes,
                'fonte' => $request->fonte ?? 'Manual'
            ]);

            // Registra o evento de criação
            $this->eventoService->registrarEvento(
                $licitacao->id,
                'criacao',
                'Licitação cadastrada',
                'A licitação foi cadastrada no sistema.',
                now(),
                Auth::id()
            );

            // Notifica o responsável, se não for o próprio usuário atual
            if ($licitacao->responsavel_id != Auth::id()) {
                $this->notificacaoService->notificar(
                    'nova_licitacao',
                    'Nova licitação atribuída',
                    "Você foi designado como responsável pela licitação {$licitacao->numero_edital}.",
                    $licitacao->responsavel_id,
                    $licitacao->id
                );
            }

            // Processa documentos, se houver
            if ($request->hasFile('documentos')) {
                foreach ($request->file('documentos') as $documento) {
                    $path = $documento->store('documentos/licitacoes/' . $licitacao->id);

                    $licitacao->documentos()->create([
                        'nome' => $documento->getClientOriginalName(),
                        'tipo' => 'edital',
                        'path' => $path,
                        'mime_type' => $documento->getMimeType(),
                        'tamanho' => $documento->getSize()
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('licitacoes.show', $licitacao)
                ->with('success', 'Licitação cadastrada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao cadastrar licitação: ' . $e->getMessage());
        }
    }

    /**
     * Exibe os detalhes de uma licitação específica
     */
    public function show(Licitacao $licitacao)
    {
        $licitacao->load([
            'orgao',
            'categoria',
            'status',
            'responsavel',
            'documentos',
            'eventos' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'propostas',
            'tarefas' => function($query) {
                $query->orderBy('prazo', 'asc');
            }
        ]);

        return view('licitacoes.show', compact('licitacao'));
    }

    /**
     * Mostra o formulário para editar uma licitação existente
     */
    public function edit(Licitacao $licitacao)
    {
        $statusList = Status::pluck('nome', 'id');
        $categorias = Categoria::pluck('nome', 'id');
        $orgaos = Orgao::pluck('nome', 'id');

        return view('licitacoes.edit', compact('licitacao', 'statusList', 'categorias', 'orgaos'));
    }

    /**
     * Atualiza uma licitação específica no banco de dados
     */
    public function update(UpdateLicitacaoRequest $request, Licitacao $licitacao)
    {
        try {
            DB::beginTransaction();

            // Guarda o status anterior para verificar mudanças
            $statusAnterior = $licitacao->status_id;

            $licitacao->update([
                'numero_edital' => $request->numero_edital,
                'objeto' => $request->objeto,
                'modalidade' => $request->modalidade,
                'valor_estimado' => $request->valor_estimado,
                'data_publicacao' => $request->data_publicacao,
                'data_abertura' => $request->data_abertura,
                'orgao_id' => $request->orgao_id,
                'categoria_id' => $request->categoria_id,
                'status_id' => $request->status_id,
                'responsavel_id' => $request->responsavel_id,
                'link_edital' => $request->link_edital,
                'observacoes' => $request->observacoes
            ]);

            // Registra o evento de atualização
            $this->eventoService->registrarEvento(
                $licitacao->id,
                'atualizacao',
                'Licitação atualizada',
                'Os dados da licitação foram atualizados.',
                now(),
                Auth::id()
            );

            // Se o status mudou, registra um evento específico
            if ($statusAnterior != $request->status_id) {
                $novoStatus = Status::find($request->status_id)->nome;

                $this->eventoService->registrarEvento(
                    $licitacao->id,
                    'mudanca_status',
                    'Status atualizado',
                    "O status da licitação foi alterado para '{$novoStatus}'.",
                    now(),
                    Auth::id()
                );

                // Notifica o responsável, se não for o próprio usuário atual
                if ($licitacao->responsavel_id != Auth::id()) {
                    $this->notificacaoService->notificar(
                        'mudanca_status',
                        'Status da licitação alterado',
                        "O status da licitação {$licitacao->numero_edital} foi alterado para '{$novoStatus}'.",
                        $licitacao->responsavel_id,
                        $licitacao->id
                    );
                }
            }

            // Processa documentos, se houver
            if ($request->hasFile('documentos')) {
                foreach ($request->file('documentos') as $documento) {
                    $path = $documento->store('documentos/licitacoes/' . $licitacao->id);

                    $licitacao->documentos()->create([
                        'nome' => $documento->getClientOriginalName(),
                        'tipo' => $request->tipo_documento ?? 'outros',
                        'path' => $path,
                        'mime_type' => $documento->getMimeType(),
                        'tamanho' => $documento->getSize()
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('licitacoes.show', $licitacao)
                ->with('success', 'Licitação atualizada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao atualizar licitação: ' . $e->getMessage());
        }
    }

    /**
     * Remove uma licitação específica do banco de dados
     */
    public function destroy(Licitacao $licitacao)
    {
        try {
            $licitacao->delete();

            return redirect()->route('licitacoes.index')
                ->with('success', 'Licitação removida com sucesso!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao remover licitação: ' . $e->getMessage());
        }
    }
}
