<?php

namespace App\Controllers\PessoaJuridica;

use App\Controllers\BaseController;
use App\Services\PessoaJuridica\PessoaJuridicaService;

class PessoaJuridicaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(PessoaJuridicaService::class);
    }
}
