<?php

namespace App\Models\Contrato;

use App\Models\BaseModel;

class ContratoParcelaModel extends BaseModel
{
    protected string $table = 'tbl_contratos_parcelas';
    protected string $alias = 'ctp';

    protected bool $multiTenant = true;
    protected bool $registrarAutoria = false;

    protected array $default = [
        'ctp.status_sistema' => 'incluido',
    ];

    protected array $joins = [];

    protected array $columns = [
        'id'                => [
            'db' => 'ctp.id',
            'select' => true
        ],
        'empresa_id'        => [
            'db' => 'ctp.empresa_id',
            'select' => true
        ],
        'contrato_id'       => [
            'db' => 'ctp.contrato_id',
            'select' => true
        ],
        'numero_parcela'    => [
            'db' => 'ctp.numero_parcela',
            'select' => true
        ],
        'valor_parcela'     => [
            'db' => 'ctp.valor_parcela',
            'select' => true
        ],
        'data_vencimento'   => [
            'db' => 'ctp.data_vencimento',
            'select' => true
        ],
        'valor_juros'       => [
            'db' => 'ctp.valor_juros',
            'select' => true
        ],
        'valor_amortizacao' => [
            'db' => 'ctp.valor_amortizacao',
            'select' => true
        ],
        'saldo_devedor'     => [
            'db' => 'ctp.saldo_devedor',
            'select' => true
        ],
        'status_sistema'    => [
            'db' => 'ctp.status_sistema',
            'select' => true
        ],
        'data_cadastro'     => [
            'db' => 'ctp.data_cadastro',
            'select' => true
        ],
        'data_atualizacao'  => [
            'db' => 'ctp.data_atualizacao',
            'select' => true
        ],
    ];

    public function limparPorContrato(int $contratoId): void
    {
        $sql = "DELETE FROM {$this->table} WHERE contrato_id = ?";
        $this->query($sql, [$contratoId]);
    }
}
