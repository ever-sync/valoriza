<?php

namespace App\Controllers\Contrato;

use App\Controllers\BaseController;
use App\Services\Contrato\ContratoService;
use App\Services\Contrato\CRDCIntegrationService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class ContratoController extends BaseController
{
    public function __construct()
    {
        parent::__construct(ContratoService::class);
    }

    /**
     * Retorna as garantias de um contrato específico
     */
    public function consultarGarantias(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->consultarGarantias($id);
        });
    }

    /**
     * Retorna as parcelas lançadas (receitas) de um contrato
     */
    public function consultarParcelas(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->consultarParcelas($id);
        });
    }

    /**
     * Lança as parcelas do contrato no contas a receber (tbl_receitas)
     */
    public function lancarParcelas(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->lancarParcelas($id);
        });
    }

    /**
     * Simula um contrato sem salvar.
     * Recebe os parâmetros e retorna parcelas, IOF, CET, etc.
     */
    public function simular(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return $this->servico->calcularSimulacao($dados);
        });
    }

    /**
     * Envia o contrato para o portal CRDC
     */
    public function enviarCRDC(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            $crdcService = $this->auxService(CRDCIntegrationService::class);
            return $crdcService->enviarContrato($id);
        });
    }
}
