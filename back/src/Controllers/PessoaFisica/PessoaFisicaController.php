<?php

namespace App\Controllers\PessoaFisica;

use App\Controllers\BaseController;
use App\Services\PessoaFisica\PessoaFisicaService;

class PessoaFisicaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(PessoaFisicaService::class);
    }
}
