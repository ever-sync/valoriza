<?php

namespace App\Controllers\Despesa;

use App\Controllers\BaseController;
use App\Services\Despesa\DespesaService;

class DespesaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(DespesaService::class);
    }
}
