<?php

namespace App\Controllers\Relatorio;

use App\Controllers\BaseController;
use App\Services\Relatorio\RelatorioService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class RelatorioController extends BaseController
{
    public function __construct()
    {
        parent::__construct(RelatorioService::class);
    }

    public function getSumarioContratos(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function () {
            return $this->servico->getSumarioContratos();
        });
    }

    public function getSumarioClientes(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function () {
            return $this->servico->getSumarioClientes();
        });
    }

    public function getContabilRecebimentos(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($query) {
            $inicio = $query['inicio'] ?? date('Y-m-01');
            $fim = $query['fim'] ?? date('Y-m-t');
            $status = $query['status'] ?? 'Todos';
            $tipoData = $query['tipoData'] ?? 'vencimento';
            $clienteId = $query['clienteId'] ?? null;

            return $this->servico->getContabilRecebimentos($inicio, $fim, $status, $tipoData, $clienteId);
        });
    }

    public function getContabilPagamentos(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($query) {
            $inicio = $query['inicio'] ?? date('Y-m-01');
            $fim = $query['fim'] ?? date('Y-m-t');
            $status = $query['status'] ?? 'Todos';
            $tipoData = $query['tipoData'] ?? 'vencimento';
            $favorecidoId = $query['favorecidoId'] ?? null;

            return $this->servico->getContabilPagamentos($inicio, $fim, $status, $tipoData, $favorecidoId);
        });
    }
}
