<?php

namespace App\Models\Banco;

class BancoModel extends \App\Models\BaseModel
{
    protected string $table = 'tbl_bancos';
    protected string $alias = 'bnc';

    // Bancos belong to a company, so multiTenant should be true
    protected bool $multiTenant = true;

    protected array $default = [
        'status_sistema' => 'incluido'
    ];

    protected array $columns = [
        'id' => [
            'db' => 'bnc.id',
            'select' => true,
        ],
        'empresa_id' => [
            'db' => 'bnc.empresa_id',
            'select' => true,
        ],
        'banco' => [
            'db' => 'bnc.banco',
            'select' => true,
        ],
        'agencia' => [
            'db' => 'bnc.agencia',
            'select' => true,
        ],
        'conta' => [
            'db' => 'bnc.conta',
            'select' => true,
        ],
        'chave_pix' => [
            'db' => 'bnc.chave_pix',
            'select' => true,
        ],
        'padrao' => [
            'db' => 'bnc.padrao',
            'select' => true,
        ],
        'data_cadastro' => [
            'db' => 'bnc.data_cadastro',
            'select' => true,
        ],
        'data_atualizacao' => [
            'db' => 'bnc.data_atualizacao',
            'select' => true,
        ],
        'status_sistema' => [
            'db' => 'bnc.status_sistema',
            'select' => true,
        ],
    ];
}
