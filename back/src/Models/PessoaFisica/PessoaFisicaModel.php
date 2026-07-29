<?php

namespace App\Models\PessoaFisica;

class PessoaFisicaModel extends \App\Models\BaseModel
{
    protected string $table = 'tbl_pessoas_fisicas';
    protected string $alias = 'pf';

    protected array $default = [
        'status_sistema' => 'incluido'
    ];

    protected array $columns = [

        'id' => [
            'db' => 'pf.id',
            'select' => true,
        ],

        'empresa_id' => [
            'db' => 'pf.empresa_id',
            'select' => true,
        ],

        'nome_completo' => [
            'db' => 'pf.nome_completo',
            'select' => true,
        ],

        'cpf' => [
            'db' => 'pf.cpf',
            'select' => true,
        ],

        'rg' => [
            'db' => 'pf.rg',
            'select' => true,
        ],

        'orgao_emissor_rg' => [
            'db' => 'pf.orgao_emissor_rg',
            'select' => true,
        ],

        'telefone' => [
            'db' => 'pf.telefone',
            'select' => true,
        ],

        'email' => [
            'db' => 'pf.email',
            'select' => true,
        ],

        'rede_social' => [
            'db' => 'pf.rede_social',
            'select' => true,
        ],

        'cep' => [
            'db' => 'pf.cep',
            'select' => true,
        ],

        'estado' => [
            'db' => 'pf.estado',
            'select' => true,
        ],

        'cidade' => [
            'db' => 'pf.cidade',
            'select' => true,
        ],

        'bairro' => [
            'db' => 'pf.bairro',
            'select' => true,
        ],

        'endereco' => [
            'db' => 'pf.endereco',
            'select' => true,
        ],

        'numero' => [
            'db' => 'pf.numero',
            'select' => true,
        ],

        'complemento' => [
            'db' => 'pf.complemento',
            'select' => true,
        ],

        'renda_mensal' => [
            'db' => 'pf.renda_mensal',
            'select' => true,
        ],

        'limite_credito' => [
            'db' => 'pf.limite_credito',
            'select' => true,
        ],

        'estado_civil' => [
            'db' => 'pf.estado_civil',
            'select' => true,
        ],

        'regime_partilha' => [
            'db' => 'pf.regime_partilha',
            'select' => true,
        ],

        'observacao' => [
            'db' => 'pf.observacao',
            'select' => true,
        ],

        'banco' => [
            'db' => 'pf.banco',
            'select' => true,
        ],

        'agencia' => [
            'db' => 'pf.agencia',
            'select' => true,
        ],

        'conta' => [
            'db' => 'pf.conta',
            'select' => true,
        ],

        'chave_pix' => [
            'db' => 'pf.chave_pix',
            'select' => true,
        ],

        'data_cadastro' => [
            'db' => 'pf.data_cadastro',
            'select' => true,
        ],

        'data_atualizacao' => [
            'db' => 'pf.data_atualizacao',
            'select' => true,
        ],

        'cadastrado_por' => [
            'db' => 'pf.cadastrado_por',
            'select' => true,
        ],

        'atualizado_por' => [
            'db' => 'pf.atualizado_por',
            'select' => true,
        ],

        'status_sistema' => [
            'db' => 'pf.status_sistema',
            'select' => true,
        ],

    ];

    // protected array $joins = [
    //     'LEFT JOIN tbl_usuarios u ON pf.cadastrado_por = u.id'
    // ];
}
