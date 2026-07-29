<?php

namespace App\Models\Contrato;

use App\Models\BaseModel;

class ContratoModel extends BaseModel
{
    protected string $table = 'tbl_contratos';
    protected string $alias = 'ctr';

    protected bool $multiTenant = true;
    protected bool $registrarAutoria = true;

    protected array $default = [
        'ctr.status_sistema' => 'incluido',
    ];

    protected array $joins = [
        "LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = ctr.cliente_id AND ctr.tipo_cliente = 'pf'",
        "LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = ctr.cliente_id AND ctr.tipo_cliente = 'pj'",
    ];

    protected array $columns = [
        'id' => [
            'db' => 'ctr.id',
            'select' => true
        ],
        'empresa_id' => [
            'db' => 'ctr.empresa_id',
            'select' => true
        ],
        'numero_contrato' => [
            'db' => 'ctr.numero_contrato',
            'select' => true
        ],
        'tipo_operacao' => [
            'db' => 'ctr.tipo_operacao',
            'select' => true
        ],
        'valor_solicitado' => [
            'db' => 'ctr.valor_solicitado',
            'select' => true
        ],
        'periodo_amortizacao' => [
            'db' => 'ctr.periodo_amortizacao',
            'select' => true
        ],
        'modelo_amortizacao' => [
            'db' => 'ctr.modelo_amortizacao',
            'select' => true
        ],
        'taxa_juros' => [
            'db' => 'ctr.taxa_juros',
            'select' => true
        ],
        'tipo_taxa' => [
            'db' => 'ctr.tipo_taxa',
            'select' => true
        ],
        'quantidade_parcelas' => [
            'db' => 'ctr.quantidade_parcelas',
            'select' => true
        ],
        'data_assinatura' => [
            'db' => 'ctr.data_assinatura',
            'select' => true
        ],
        'data_primeira_parcela' => [
            'db' => 'ctr.data_primeira_parcela',
            'select' => true
        ],
        'simples_nacional' => [
            'db' => 'ctr.simples_nacional',
            'select' => true
        ],
        'calcular_iof' => [
            'db' => 'ctr.calcular_iof',
            'select' => true
        ],
        'tipo_cliente' => [
            'db' => 'ctr.tipo_cliente',
            'select' => true
        ],
        'cliente_id' => [
            'db' => 'ctr.cliente_id',
            'select' => true
        ],
        'juros_mora' => [
            'db' => 'ctr.juros_mora',
            'select' => true
        ],
        'multa_moratoria' => [
            'db' => 'ctr.multa_moratoria',
            'select' => true
        ],
        'risco_inadimplencia' => [
            'db' => 'ctr.risco_inadimplencia',
            'select' => true
        ],
        'permitir_carencia' => [
            'db' => 'ctr.permitir_carencia',
            'select' => true
        ],
        'limite_carencia' => [
            'db' => 'ctr.limite_carencia',
            'select' => true
        ],
        'usos_carencia' => [
            'db' => 'ctr.usos_carencia',
            'select' => true
        ],
        'tipo_assinatura' => [
            'db' => 'ctr.tipo_assinatura',
            'select' => true
        ],
        'enviar_registradora' => [
            'db' => 'ctr.enviar_registradora',
            'select' => true
        ],
        'contrato_iniciado' => [
            'db' => 'ctr.contrato_iniciado',
            'select' => true
        ],
        'status' => [
            'db' => 'ctr.status',
            'select' => true
        ],
        'data_cadastro' => [
            'db' => 'ctr.data_cadastro',
            'select' => true
        ],
        'data_atualizacao' => [
            'db' => 'ctr.data_atualizacao',
            'select' => true
        ],
        'status_sistema' => [
            'db' => 'ctr.status_sistema',
            'select' => true
        ],
        'cadastrado_por' => [
            'db' => 'ctr.cadastrado_por',
            'select' => true
        ],
        'atualizado_por' => [
            'db' => 'ctr.atualizado_por',
            'select' => true
        ],
        // Relacionamentos
        'cliente_nome' => [
            'db' => "(CASE
                WHEN ctr.tipo_cliente = 'pf' THEN pf.nome_completo
                WHEN ctr.tipo_cliente = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                ELSE 'Não Informado'
            END)",
            'select' => true,
        ],
    ];

    public function gerarProximoNumero(): string
    {
        $ano = date('Y');

        // Conta contratos do ano atual para gerar a sequência
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE numero_contrato LIKE ? 
                    AND empresa_id = ? 
                    AND status_sistema != 'excluido'";

        $params = ["{$ano}/%", $this->getEmpresaId()];
        $res = $this->query($sql, $params);

        $proximo = (int)($res[0]['total'] ?? 0) + 1;
        return $ano . '/' . str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }
}
