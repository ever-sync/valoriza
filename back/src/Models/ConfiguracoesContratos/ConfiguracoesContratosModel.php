<?php

namespace App\Models\ConfiguracoesContratos;

class ConfiguracoesContratosModel extends \App\Models\BaseModel
{
    protected string $table = 'tbl_configuracoes_contratos';
    protected string $alias = 'cfgc';

    // Configurações belong to a company
    protected bool $multiTenant = true;

    protected array $default = [
        'status_sistema' => 'incluido'
    ];

    protected array $columns = [
        'id' => [
            'db' => 'cfgc.id',
            'select' => true,
        ],
        'empresa_id' => [
            'db' => 'cfgc.empresa_id',
            'select' => true,
        ],
        'taxa_juros_minima_sem_garantia' => [
            'db' => 'cfgc.taxa_juros_minima_sem_garantia',
            'select' => true,
        ],
        'taxa_juros_avalista' => [
            'db' => 'cfgc.taxa_juros_avalista',
            'select' => true,
        ],
        'taxa_juros_imovel' => [
            'db' => 'cfgc.taxa_juros_imovel',
            'select' => true,
        ],
        'taxa_juros_veiculo' => [
            'db' => 'cfgc.taxa_juros_veiculo',
            'select' => true,
        ],
        'taxa_juros_outras_garantias' => [
            'db' => 'cfgc.taxa_juros_outras_garantias',
            'select' => true,
        ],
        'qtd_minima_parcelas' => [
            'db' => 'cfgc.qtd_minima_parcelas',
            'select' => true,
        ],
        'qtd_maxima_parcelas' => [
            'db' => 'cfgc.qtd_maxima_parcelas',
            'select' => true,
        ],
        'tipo_registro_crdc' => [
            'db' => 'cfgc.tipo_registro_crdc',
            'select' => true,
        ],
        'crdc_usuario' => [
            'db' => 'cfgc.crdc_usuario',
            'select' => true,
        ],
        'crdc_senha' => [
            'db' => 'cfgc.crdc_senha',
            'select' => false, // Sensitive
        ],
        'crdc_cnpj' => [
            'db' => 'cfgc.crdc_cnpj',
            'select' => true,
        ],
        'dias_notificacao_vencimento' => [
            'db' => 'cfgc.dias_notificacao_vencimento',
            'select' => true,
        ],
        'notificar_vencimento_email' => [
            'db' => 'cfgc.notificar_vencimento_email',
            'select' => true,
        ],
        'notificar_vencimento_whatsapp' => [
            'db' => 'cfgc.notificar_vencimento_whatsapp',
            'select' => true,
        ],
        'exibir_notificacoes_vencimento' => [
            'db' => 'cfgc.exibir_notificacoes_vencimento',
            'select' => true,
        ],
        'frequencia_notificacao_atraso' => [
            'db' => 'cfgc.frequencia_notificacao_atraso',
            'select' => true,
        ],
        'notificar_atraso_email' => [
            'db' => 'cfgc.notificar_atraso_email',
            'select' => true,
        ],
        'notificar_atraso_whatsapp' => [
            'db' => 'cfgc.notificar_atraso_whatsapp',
            'select' => true,
        ],
        'exibir_notificacoes_atraso' => [
            'db' => 'cfgc.exibir_notificacoes_atraso',
            'select' => true,
        ],
        'copiar_avalistas_atraso' => [
            'db' => 'cfgc.copiar_avalistas_atraso',
            'select' => true,
        ],
        'data_cadastro' => [
            'db' => 'cfgc.data_cadastro',
            'select' => true,
        ],
        'data_atualizacao' => [
            'db' => 'cfgc.data_atualizacao',
            'select' => true,
        ],
        'status_sistema' => [
            'db' => 'cfgc.status_sistema',
            'select' => true,
        ],
    ];
}
