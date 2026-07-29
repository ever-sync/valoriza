<?php

namespace App\Models\Receita;

use App\Models\BaseModel;

class ReceitaModel extends BaseModel
{
    protected string $table = 'tbl_receitas';
    protected string $alias = 'rec';

    protected bool $multiTenant = true;
    protected bool $registrarAutoria = true;

    protected array $default = [
        'rec.status_sistema' => 'incluido',
    ];



    protected array $columns = [
        'id'                        => [
            'db' => 'rec.id',
            'select' => true
        ],
        'empresa_id'                => [
            'db' => 'rec.empresa_id',
            'select' => true
        ],
        'data_recebimento'          => [
            'db' => 'rec.data_recebimento',
            'select' => true
        ],
        'data_vencimento'           => [
            'db' => 'rec.data_vencimento',
            'select' => true
        ],
        'descricao'                 => [
            'db' => 'rec.descricao',
            'select' => true
        ],
        'valor_recebido'            => [
            'db' => 'rec.valor_recebido',
            'select' => true
        ],
        'conta_bancaria_destino_id' => [
            'db' => 'rec.conta_bancaria_destino_id',
            'select' => true
        ],
        'tipo_pagador'              => [
            'db' => 'rec.tipo_pagador',
            'select' => true
        ],
        'tipo_pessoa'               => [
            'db' => 'rec.tipo_pessoa',
            'select' => true
        ],
        'pagador_id'                => [
            'db' => 'rec.pagador_id',
            'select' => true
        ],
        'nome_pagador_manual'       => [
            'db' => 'rec.nome_pagador_manual',
            'select' => true
        ],
        'tipo_comprovante_fiscal'   => [
            'db' => 'rec.tipo_comprovante_fiscal',
            'select' => true
        ],
        'numero_comprovante'        => [
            'db' => 'rec.numero_comprovante',
            'select' => true
        ],
        'categoria_id'              => [
            'db' => 'rec.categoria_id',
            'select' => true
        ],
        'forma_recebimento'         => [
            'db' => 'rec.forma_recebimento',
            'select' => true
        ],
        'status'                    => [
            'db' => 'rec.status',
            'select' => true
        ],
        'observacoes'               => [
            'db' => 'rec.observacoes',
            'select' => true
        ],
        'contrato_id'               => [
            'db' => 'rec.contrato_id',
            'select' => true
        ],
        'parcela_numero'            => [
            'db' => 'rec.parcela_numero',
            'select' => true
        ],
        'data_cadastro'             => [
            'db' => 'rec.data_cadastro',
            'select' => true
        ],
        'data_atualizacao'          => [
            'db' => 'rec.data_atualizacao',
            'select' => true
        ],
        'status_sistema'            => [
            'db' => 'rec.status_sistema',
            'select' => true
        ],
        'cadastrado_por'            => [
            'db' => 'rec.cadastrado_por',
            'select' => true
        ],
        'atualizado_por'            => [
            'db' => 'rec.atualizado_por',
            'select' => true
        ],

        'conta_nome' => [
            'db' => 'bnc.banco',
            'select' => true,
        ],
        'pagador_nome' => [
            'db' => "(CASE 
                WHEN rec.tipo_pagador = 'manual' THEN rec.nome_pagador_manual
                WHEN rec.tipo_pessoa = 'pf' THEN pf.nome_completo 
                WHEN rec.tipo_pessoa = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                ELSE 'Não Informado'
            END)",
            'select' => true,
        ],
        'numero_contrato' => [
            'db' => 'ctr.numero_contrato',
            'select' => true
        ],
        'juros_mora_contrato' => [
            'db' => 'ctr.juros_mora',
            'select' => true
        ],
        'multa_moratoria_contrato' => [
            'db' => 'ctr.multa_moratoria',
            'select' => true
        ],
        // Campos vindos da simulação (tbl_contratos_parcelas)
        // valor_juros = valor mínimo aceito para carência de principal (BACEN: paga-se apenas juros)
        'valor_juros_parcela' => [
            'db' => 'ctp.valor_juros',
            'select' => true
        ],
        'valor_amortizacao_parcela' => [
            'db' => 'ctp.valor_amortizacao',
            'select' => true
        ],
        'saldo_devedor_parcela' => [
            'db' => 'ctp.saldo_devedor',
            'select' => true
        ]
    ];

    protected array $joins = [
        'LEFT JOIN tbl_bancos bnc ON bnc.id = rec.conta_bancaria_destino_id',
        'LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = rec.pagador_id AND rec.tipo_pessoa = "pf"',
        'LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = rec.pagador_id AND rec.tipo_pessoa = "pj"',
        'LEFT JOIN tbl_contratos ctr ON ctr.id = rec.contrato_id',
        'LEFT JOIN tbl_contratos_parcelas ctp ON ctp.contrato_id = rec.contrato_id AND ctp.numero_parcela = rec.parcela_numero AND ctp.status_sistema = "incluido"'
    ];
}
