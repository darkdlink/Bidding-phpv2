<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Licitacao extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'licitacoes';

    protected $fillable = [
        'numero_edital',
        'objeto',
        'modalidade',
        'valor_estimado',
        'data_publicacao',
        'data_abertura',
        'orgao_id',
        'categoria_id',
        'status_id',
        'responsavel_id',
        'link_edital',
        'observacoes',
        'fonte'
    ];

    protected $casts = [
        'data_publicacao' => 'datetime',
        'data_abertura' => 'datetime',
        'valor_estimado' => 'decimal:2'
    ];

    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class);
    }

    public function tarefas(): HasMany
    {
        return $this->hasMany(Tarefa::class);
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class);
    }

    public function scopeAtivas($query)
    {
        return $query->whereHas('status', function($q) {
            $q->whereNotIn('nome', ['Concluído', 'Cancelado', 'Perdido']);
        });
    }

    public function scopeProximasAberturas($query, $dias = 7)
    {
        $hoje = now();
        $limite = $hoje->copy()->addDays($dias);

        return $query->whereBetween('data_abertura', [$hoje, $limite]);
    }
}

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descricao'
    ];

    public function licitacoes(): HasMany
    {
        return $this->hasMany(Licitacao::class);
    }
}

class Orgao extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'sigla',
        'endereco',
        'telefone',
        'email',
        'site'
    ];

    public function licitacoes(): HasMany
    {
        return $this->hasMany(Licitacao::class);
    }
}

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descricao',
        'cor'
    ];

    public function licitacoes(): HasMany
    {
        return $this->hasMany(Licitacao::class);
    }
}

class Documento extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'licitacao_id',
        'nome',
        'tipo',
        'path',
        'mime_type',
        'tamanho'
    ];

    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class);
    }
}

class Proposta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'licitacao_id',
        'valor',
        'data_envio',
        'observacoes',
        'resultado',
        'motivo_resultado',
        'responsavel_id'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_envio' => 'datetime'
    ];

    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }
}

class Evento extends Model
{
    use HasFactory;

    protected $fillable = [
        'licitacao_id',
        'tipo',
        'titulo',
        'descricao',
        'data',
        'responsavel_id'
    ];

    protected $casts = [
        'data' => 'datetime'
    ];

    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }
}

class Notificacao extends Model
{
    use HasFactory;

    protected $table = 'notificacoes';

    protected $fillable = [
        'tipo',
        'titulo',
        'mensagem',
        'user_id',
        'licitacao_id',
        'lido',
        'data_leitura'
    ];

    protected $casts = [
        'lido' => 'boolean',
        'data_leitura' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class);
    }
}

class Tarefa extends Model
{
    use HasFactory;

    protected $fillable = [
        'licitacao_id',
        'titulo',
        'descricao',
        'prazo',
        'prioridade',
        'responsavel_id',
        'status',
        'concluida_em'
    ];

    protected $casts = [
        'prazo' => 'datetime',
        'concluida_em' => 'datetime'
    ];

    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }
}

class Relatorio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descricao',
        'tipo',
        'parametros',
        'formato',
        'user_id',
        'agendamento',
        'ultima_execucao'
    ];

    protected $casts = [
        'parametros' => 'json',
        'agendamento' => 'json',
        'ultima_execucao' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
