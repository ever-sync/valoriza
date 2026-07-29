<?php

namespace App\Models\Usuario;

class UsuarioModel extends \App\Models\BaseModel
{
    protected string $table = 'tbl_usuarios';
    protected string $alias = 'usr';

    // Usuarios belong to a company, so multiTenant should be true
    protected bool $multiTenant = true;

    protected array $default = [
        'status_sistema' => 'incluido'
    ];

    protected array $columns = [
        'id' => [
            'db' => 'usr.id',
            'select' => true,
        ],
        'empresa_id' => [
            'db' => 'usr.empresa_id',
            'select' => true,
        ],
        'nome_completo' => [
            'db' => 'usr.nome_completo',
            'select' => true,
        ],
        'email' => [
            'db' => 'usr.email',
            'select' => true,
        ],
        'telefone' => [
            'db' => 'usr.telefone',
            'select' => true,
        ],
        'senha' => [
            'db' => 'usr.senha',
            'select' => true,
        ],
        'perfil_acesso' => [
            'db' => 'usr.perfil_acesso',
            'select' => true,
        ],
        'ativo' => [
            'db' => 'usr.ativo',
            'select' => true,
        ],
        'notificar_contratos' => [
            'db' => 'usr.notificar_contratos',
            'select' => true,
        ],
        'avatar' => [
            'db' => 'usr.avatar',
            'select' => true,
        ],
        'data_cadastro' => [
            'db' => 'usr.data_cadastro',
            'select' => true,
        ],
        'data_atualizacao' => [
            'db' => 'usr.data_atualizacao',
            'select' => true,
        ],
        'status_sistema' => [
            'db' => 'usr.status_sistema',
            'select' => true,
        ],
    ];
}
