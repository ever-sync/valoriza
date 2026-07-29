<?php

namespace App\Services\Dashboard;

use App\Models\Dashboard\DashboardModel;
use App\Services\BaseService;

class DashboardService extends BaseService
{
    protected string $model = DashboardModel::class;

    public function getStats(): array
    {
        $dashboardModel = $this->model();
        $empresaId = $this->getEmpresaId();
        
        $mesAtual = date('Y-m');
        $hoje = date('Y-m-d');

        return [
            'receita_mes' => $dashboardModel->getReceitaMes($empresaId, $mesAtual),
            'receitas_pendentes' => $dashboardModel->getReceitasPendentes($empresaId, $hoje),
            'atrasos' => $dashboardModel->getAtrasos($empresaId, $hoje),
            'novos_clientes' => $dashboardModel->getNovosClientes($empresaId, $mesAtual),
            'transacoes_recentes' => $dashboardModel->getTransacoesRecentes($empresaId),
            'grafico' => $dashboardModel->getHistoricoMensal($empresaId)
        ];
    }
}
