<?php

namespace App\Models\Contrato;

use App\Models\BaseModel;

class ContratoGarantiaModel extends BaseModel
{
    protected string $table = 'tbl_contratos_garantias';
    protected string $alias = 'ctg';

    protected bool $multiTenant = true;
    protected bool $registrarAutoria = false;

    protected array $default = [
        'ctg.status_sistema' => 'incluido',
    ];

    protected array $columns = [
        'id' => [
            'db' => 'ctg.id',
            'select' => true
        ],
        'contrato_id' => [
            'db' => 'ctg.contrato_id',
            'select' => true
        ],
        'empresa_id' => [
            'db' => 'ctg.empresa_id',
            'select' => true
        ],
        'tipo_garantia' => [
            'db' => 'ctg.tipo_garantia',
            'select' => true
        ],
        'tipo_pessoa_garantia' => [
            'db' => 'ctg.tipo_pessoa_garantia',
            'select' => true
        ],
        'pessoa_id_garantia' => [
            'db' => 'ctg.pessoa_id_garantia',
            'select' => true
        ],

        // Pessoa (Avalista / Devedor solidário)
        'nome_completo' => [
            'db' => 'ctg.nome_completo',
            'select' => true
        ],
        'cpf' => [
            'db' => 'ctg.cpf',
            'select' => true
        ],
        'rg' => [
            'db' => 'ctg.rg',
            'select' => true
        ],
        'orgao_emissor_rg' => [
            'db' => 'ctg.orgao_emissor_rg',
            'select' => true
        ],
        'email' => [
            'db' => 'ctg.email',
            'select' => true
        ],
        'telefone' => [
            'db' => 'ctg.telefone',
            'select' => true
        ],
        'renda_mensal' => [
            'db' => 'ctg.renda_mensal',
            'select' => true
        ],
        'estado_civil' => [
            'db' => 'ctg.estado_civil',
            'select' => true
        ],
        'regime_bens' => [
            'db' => 'ctg.regime_bens',
            'select' => true
        ],
        'cep' => [
            'db' => 'ctg.cep',
            'select' => true
        ],
        'estado' => [
            'db' => 'ctg.estado',
            'select' => true
        ],
        'cidade' => [
            'db' => 'ctg.cidade',
            'select' => true
        ],
        'bairro' => [
            'db' => 'ctg.bairro',
            'select' => true
        ],
        'endereco' => [
            'db' => 'ctg.endereco',
            'select' => true
        ],
        'numero' => [
            'db' => 'ctg.numero',
            'select' => true
        ],
        'complemento' => [
            'db' => 'ctg.complemento',
            'select' => true
        ],
        // Imóvel
        'tabelionato' => [
            'db' => 'ctg.tabelionato',
            'select' => true
        ],
        'numero_matricula' => [
            'db' => 'ctg.numero_matricula',
            'select' => true
        ],
        'proprietario' => [
            'db' => 'ctg.proprietario',
            'select' => true
        ],
        // Genéricos
        'descricao' => [
            'db' => 'ctg.descricao',
            'select' => true
        ],
        'numero_serie' => [
            'db' => 'ctg.numero_serie',
            'select' => true
        ],
        'estado_conservacao' => [
            'db' => 'ctg.estado_conservacao',
            'select' => true
        ],
        'localizacao_fisica' => [
            'db' => 'ctg.localizacao_fisica',
            'select' => true
        ],
        'valor_avaliacao' => [
            'db' => 'ctg.valor_avaliacao',
            'select' => true
        ],
        // Veículo
        'fabricante' => [
            'db' => 'ctg.fabricante',
            'select' => true
        ],
        'modelo' => [
            'db' => 'ctg.modelo',
            'select' => true
        ],
        'ano_fabricacao' => [
            'db' => 'ctg.ano_fabricacao',
            'select' => true
        ],
        'ano_modelo' => [
            'db' => 'ctg.ano_modelo',
            'select' => true
        ],
        'chassi' => [
            'db' => 'ctg.chassi',
            'select' => true
        ],
        'renavam' => [
            'db' => 'ctg.renavam',
            'select' => true
        ],
        'placa' => [
            'db' => 'ctg.placa',
            'select' => true
        ],
        'cor' => [
            'db' => 'ctg.cor',
            'select' => true
        ],
        'outros_dados' => [
            'db' => 'ctg.outros_dados',
            'select' => true
        ],
        // Recebível
        'tipo_recebivel' => [
            'db' => 'ctg.tipo_recebivel',
            'select' => true
        ],
        'numero_identificacao' => [
            'db' => 'ctg.numero_identificacao',
            'select' => true
        ],
        'sacado' => [
            'db' => 'ctg.sacado',
            'select' => true
        ],
        'data_vencimento_recebivel' => [
            'db' => 'ctg.data_vencimento_recebivel',
            'select' => true
        ],
        // Sistema
        'status_sistema' => [
            'db' => 'ctg.status_sistema',
            'select' => true
        ],
        'data_cadastro' => [
            'db' => 'ctg.data_cadastro',
            'select' => true
        ],
    ];
}
