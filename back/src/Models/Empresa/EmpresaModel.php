<?php

namespace App\Models\Empresa;

class EmpresaModel extends \App\Models\BaseModel
{
    protected string $table = 'tbl_empresas';
    protected string $alias = 'emp';
    
    protected bool $multiTenant = false; // Empresa itself shouldn't be filtered by empresa_id (infinite recursion)

    protected array $default = [
        'status_sistema' => 'incluido'
    ];

    protected array $columns = [
        'id' => [
            'db' => 'emp.id',
            'select' => true,
        ],
        'cnpj' => [
            'db' => 'emp.cnpj',
            'select' => true,
        ],
        'razao_social' => [
            'db' => 'emp.razao_social',
            'select' => true,
        ],
        'nome_fantasia' => [
            'db' => 'emp.nome_fantasia',
            'select' => true,
        ],
        'email' => [
            'db' => 'emp.email',
            'select' => true,
        ],
        'telefone' => [
            'db' => 'emp.telefone',
            'select' => true,
        ],
        'data_abertura' => [
            'db' => 'emp.data_abertura',
            'select' => true,
        ],
        'capital_social' => [
            'db' => 'emp.capital_social',
            'select' => true,
        ],
        'cep' => [
            'db' => 'emp.cep',
            'select' => true,
        ],
        'estado' => [
            'db' => 'emp.estado',
            'select' => true,
        ],
        'cidade' => [
            'db' => 'emp.cidade',
            'select' => true,
        ],
        'bairro' => [
            'db' => 'emp.bairro',
            'select' => true,
        ],
        'endereco' => [
            'db' => 'emp.endereco',
            'select' => true,
        ],
        'numero' => [
            'db' => 'emp.numero',
            'select' => true,
        ],
        'complemento' => [
            'db' => 'emp.complemento',
            'select' => true,
        ],
        'logo' => [
            'db' => 'emp.logo',
            'select' => true,
        ],
        'data_cadastro' => [
            'db' => 'emp.data_cadastro',
            'select' => true,
        ],
        'data_atualizacao' => [
            'db' => 'emp.data_atualizacao',
            'select' => true,
        ],
        'status_sistema' => [
            'db' => 'emp.status_sistema',
            'select' => true,
        ],
    ];
}
