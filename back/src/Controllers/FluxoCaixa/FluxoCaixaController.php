<?php

namespace App\Controllers\FluxoCaixa;

use App\Controllers\BaseController;
use App\Services\FluxoCaixa\FluxoCaixaService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class FluxoCaixaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(FluxoCaixaService::class);
    }

    public function getFluxoCaixa(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function () {
            return $this->servico->getFluxoCaixa();
        });
    }

    public function getFluxoPeriodo(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function () use ($req) {
            $inicio = $req->get('inicio');
            $fim = $req->get('fim');
            return $this->servico->getFluxoPeriodo($inicio, $fim);
        });
    }
}
