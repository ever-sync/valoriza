<?php

namespace App\Services\FluxoCaixa;

use App\Models\FluxoCaixa\FluxoCaixaModel;
use App\Services\BaseService;

class FluxoCaixaService extends BaseService
{
    protected string $model = FluxoCaixaModel::class;

    public function getFluxoCaixa(): array
    {
        return $this->model()->getFluxoProjetado($this->getEmpresaId());
    }

    public function getFluxoPeriodo(string $inicio, string $fim): array
    {
        return $this->model()->getFluxoPeriodo($this->getEmpresaId(), $inicio, $fim);
    }
}
