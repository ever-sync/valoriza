<?php

namespace App\Models\Receita;

use App\Models\BaseModel;

class ProrrogacaoModel extends BaseModel
{
    protected string $table = 'tbl_receitas_prorrogacoes';
    protected string $alias = 'prr';

    protected bool $multiTenant = true;
    protected bool $registrarAutoria = true;

    protected array $default = [
        'prr.status_sistema' => 'incluido',
    ];

    protected array $columns = [
        'id' => [
            'db' => 'prr.id',
            'select' => true
        ],
        'receita_id'               => [
            'db' => 'prr.receita_id',
            'select' => true
        ],
        'contrato_id'              => [
            'db' => 'prr.contrato_id',
            'select' => true
        ],
        'empresa_id'               => [
            'db' => 'prr.empresa_id',
            'select' => true
        ],
        'numero_contrato'          => [
            'db' => 'prr.numero_contrato',
            'select' => true
        ],
        'parcela_numero'           => [
            'db' => 'prr.parcela_numero',
            'select' => true
        ],
        'total_parcelas'           => [
            'db' => 'prr.total_parcelas',
            'select' => true
        ],
        'cliente_nome'             => [
            'db' => 'prr.cliente_nome',
            'select' => true
        ],
        'cliente_documento'        => [
            'db' => 'prr.cliente_documento',
            'select' => true
        ],
        'data_vencimento_anterior' => [
            'db' => 'prr.data_vencimento_anterior',
            'select' => true
        ],
        'data_vencimento_nova'     => [
            'db' => 'prr.data_vencimento_nova',
            'select' => true
        ],
        'valor_anterior'           => [
            'db' => 'prr.valor_anterior',
            'select' => true
        ],
        'valor_recebido'           => [
            'db' => 'prr.valor_recebido',
            'select' => true
        ],
        'desconto'                 => [
            'db' => 'prr.desconto',
            'select' => true
        ],
        'juros_atualizacao'        => [
            'db' => 'prr.juros_atualizacao',
            'select' => true
        ],
        'juros_mora'               => [
            'db' => 'prr.juros_mora',
            'select' => true
        ],
        'multa_atraso'             => [
            'db' => 'prr.multa_atraso',
            'select' => true
        ],
        'valor_devido'             => [
            'db' => 'prr.valor_devido',
            'select' => true
        ],
        'valor_novo'               => [
            'db' => 'prr.valor_novo',
            'select' => true
        ],
        'justificativa'            => [
            'db' => 'prr.justificativa',
            'select' => true
        ],
        'cadastrado_por'           => [
            'db' => 'prr.cadastrado_por',
            'select' => true
        ],
        'data_cadastro'            => [
            'db' => 'prr.data_cadastro',
            'select' => true
        ],
        'status_sistema'           => [
            'db' => 'prr.status_sistema',
            'select' => true
        ],
    ];
}
