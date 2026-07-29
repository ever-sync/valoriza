<?php

namespace App\Models\PessoaJuridica;

class PessoaJuridicaModel extends \App\Models\BaseModel
{
    protected string $table = 'tbl_pessoas_juridicas';
    protected string $alias = 'pj';
    protected array $default = [
        'status_sistema' => 'incluido'
    ];


    protected array $columns = [

        'id' => [
            'db' => 'pj.id',
            'select' => true,
        ],

        'empresa_id' => [
            'db' => 'pj.empresa_id',
            'select' => true,
        ],

        'razao_social' => [
            'db' => 'pj.razao_social',
            'select' => true,
        ],

        'nome_fantasia' => [
            'db' => 'pj.nome_fantasia',
            'select' => true,
        ],

        'cnpj' => [
            'db' => 'pj.cnpj',
            'select' => true,
        ],

        'telefone' => [
            'db' => 'pj.telefone',
            'select' => true,
        ],

        'email' => [
            'db' => 'pj.email',
            'select' => true,
        ],

        'rede_social' => [
            'db' => 'pj.rede_social',
            'select' => true,
        ],

        'cep' => [
            'db' => 'pj.cep',
            'select' => true,
        ],

        'estado' => [
            'db' => 'pj.estado',
            'select' => true,
        ],

        'cidade' => [
            'db' => 'pj.cidade',
            'select' => true,
        ],

        'bairro' => [
            'db' => 'pj.bairro',
            'select' => true,
        ],

        'endereco' => [
            'db' => 'pj.endereco',
            'select' => true,
        ],

        'numero' => [
            'db' => 'pj.numero',
            'select' => true,
        ],

        'complemento' => [
            'db' => 'pj.complemento',
            'select' => true,
        ],

        'limite_credito' => [
            'db' => 'pj.limite_credito',
            'select' => true,
        ],

        'observacao' => [
            'db' => 'pj.observacao',
            'select' => true,
        ],

        'banco' => [
            'db' => 'pj.banco',
            'select' => true,
        ],

        'agencia' => [
            'db' => 'pj.agencia',
            'select' => true,
        ],

        'conta' => [
            'db' => 'pj.conta',
            'select' => true,
        ],

        'chave_pix' => [
            'db' => 'pj.chave_pix',
            'select' => true,
        ],

        'data_cadastro' => [
            'db' => 'pj.data_cadastro',
            'select' => true,
        ],

        'data_atualizacao' => [
            'db' => 'pj.data_atualizacao',
            'select' => true,
        ],

        'cadastrado_por' => [
            'db' => 'pj.cadastrado_por',
            'select' => true,
        ],

        'atualizado_por' => [
            'db' => 'pj.atualizado_por',
            'select' => true,
        ],

    ];

    // protected array $joins = [
    //     'LEFT JOIN tbl_usuarios u ON m.aluno_id = u.id'
    // ];
}
