<?php

namespace App\Controllers\ConfiguracoesContratos;

use App\Controllers\BaseController;
use App\Services\ConfiguracoesContratos\ConfiguracoesContratosService;

class ConfiguracoesContratosController extends BaseController
{
    public function __construct()
    {
        parent::__construct(ConfiguracoesContratosService::class);
    }
}
