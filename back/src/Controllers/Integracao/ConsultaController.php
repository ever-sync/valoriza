<?php

namespace App\Controllers\Integracao;

use App\Controllers\BaseController;
use App\Services\Integracao\ConsultaService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

/**
 * Controller de consultas externas.
 */
class ConsultaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(ConsultaService::class);
    }

    /**
     * Endpoint para consulta de CEP.
     */
    public function consultarCEP(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return $this->servico->consultarCEP($dados['cep'] ?? '');
        });
    }

    /**
     * Endpoint para consulta de CNPJ.
     */
    public function consultarCNPJ(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return $this->servico->consultarCNPJ($dados['cnpj'] ?? '');
        });
    }
}
