<?php

namespace App\Services\Relatorio;

use App\Models\Relatorio\RelatorioModel;
use App\Services\BaseService;

class RelatorioService extends BaseService
{
    protected string $model = RelatorioModel::class;

    public function getSumarioContratos(): array
    {
        return $this->model()->getSumarioContratos($this->getEmpresaId());
    }

    public function getSumarioClientes(): array
    {
        return $this->model()->getSumarioClientes($this->getEmpresaId());
    }

    public function getContabilRecebimentos(string $inicio, string $fim, string $status, string $tipoData, ?int $clienteId = null): array
    {
        return $this->model()->getContabilRecebimentos($this->getEmpresaId(), $inicio, $fim, $status, $tipoData, $clienteId);
    }

    public function getContabilPagamentos(string $inicio, string $fim, string $status, string $tipoData, ?int $favorecidoId = null): array
    {
        return $this->model()->getContabilPagamentos($this->getEmpresaId(), $inicio, $fim, $status, $tipoData, $favorecidoId);
    }
}
