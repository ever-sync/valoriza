<?php

namespace App\Models\Despesa;

use App\Models\BaseModel;

class DespesaModel extends BaseModel
{
    protected string $table = 'tbl_despesas';
    protected string $alias = 'dsp';

    protected bool $multiTenant = true;
    protected bool $registrarAutoria = true;

    protected array $default = [
        'dsp.status_sistema' => 'incluido',
    ];

    protected array $joins = [
        'LEFT JOIN tbl_bancos bnc ON bnc.id = dsp.conta_bancaria_origem_id',
        'LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = dsp.favorecido_id AND dsp.tipo_pessoa = "pf"',
        'LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = dsp.favorecido_id AND dsp.tipo_pessoa = "pj"'
    ];

    protected array $columns = [
        'id' => [
            'db' => 'dsp.id',
            'select' => true,
        ],
        'empresa_id' => [
            'db' => 'dsp.empresa_id',
            'select' => true,
        ],
        'data_pagamento' => [
            'db' => 'dsp.data_pagamento',
            'select' => true,
        ],
        'data_vencimento' => [
            'db' => 'dsp.data_vencimento',
            'select' => true,
        ],
        'descricao' => [
            'db' => 'dsp.descricao',
            'select' => true,
        ],
        'valor_pago' => [
            'db' => 'dsp.valor_pago',
            'select' => true,
        ],
        'conta_bancaria_origem_id' => [
            'db' => 'dsp.conta_bancaria_origem_id',
            'select' => true,
        ],
        'tipo_favorecido' => [
            'db' => 'dsp.tipo_favorecido',
            'select' => true,
        ],
        'tipo_pessoa' => [
            'db' => 'dsp.tipo_pessoa',
            'select' => true,
        ],
        'favorecido_id' => [
            'db' => 'dsp.favorecido_id',
            'select' => true,
        ],
        'nome_favorecido_manual' => [
            'db' => 'dsp.nome_favorecido_manual',
            'select' => true,
        ],
        'tipo_comprovante_fiscal' => [
            'db' => 'dsp.tipo_comprovante_fiscal',
            'select' => true,
        ],
        'numero_comprovante' => [
            'db' => 'dsp.numero_comprovante',
            'select' => true,
        ],
        'categoria_id' => [
            'db' => 'dsp.categoria_id',
            'select' => true,
        ],
        'forma_pagamento' => [
            'db' => 'dsp.forma_pagamento',
            'select' => true,
        ],
        'status' => [
            'db' => 'dsp.status',
            'select' => true,
        ],
        'irrf_retido' => [
            'db' => 'dsp.irrf_retido',
            'select' => true,
        ],
        'inss_retido' => [
            'db' => 'dsp.inss_retido',
            'select' => true,
        ],
        'iss_retido' => [
            'db' => 'dsp.iss_retido',
            'select' => true,
        ],
        'outros_impostos_retidos' => [
            'db' => 'dsp.outros_impostos_retidos',
            'select' => true,
        ],
        'observacoes' => [
            'db' => 'dsp.observacoes',
            'select' => true,
        ],
        'despesa_recorrente' => [
            'db' => 'dsp.despesa_recorrente',
            'select' => true,
        ],
        'quantidade_despesas_recorrentes' => [
            'db' => 'dsp.quantidade_despesas_recorrentes',
            'select' => true,
        ],
        'data_cadastro' => [
            'db' => 'dsp.data_cadastro',
            'select' => true,
        ],
        'data_atualizacao' => [
            'db' => 'dsp.data_atualizacao',
            'select' => true,
        ],
        'status_sistema' => [
            'db' => 'dsp.status_sistema',
            'select' => true,
        ],
        'cadastrado_por' => [
            'db' => 'dsp.cadastrado_por',
            'select' => true,
        ],
        'atualizado_por' => [
            'db' => 'dsp.atualizado_por',
            'select' => true,
        ],

        // Relacionamentos para o SELECT
        'conta_nome' => [
            'db' => 'bnc.banco',
            'select' => true,
        ],
        // Favorecido PF ou PJ dependendo do tipo
        'favorecido_nome' => [
            'db' => "(CASE 
                WHEN dsp.tipo_favorecido = 'manual' THEN dsp.nome_favorecido_manual
                WHEN dsp.tipo_pessoa = 'pf' THEN pf.nome_completo 
                WHEN dsp.tipo_pessoa = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                ELSE 'Não Informado'
            END)",
            'select' => true,
        ]
    ];
}
