<?php

namespace App\Controllers\Empresa;

use App\Controllers\BaseController;
use App\Services\Empresa\EmpresaService;

class EmpresaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(EmpresaService::class);
    }
}
