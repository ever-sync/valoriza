<?php

namespace App\Controllers\Banco;

use App\Controllers\BaseController;
use App\Services\Banco\BancoService;

class BancoController extends BaseController
{
    public function __construct()
    {
        parent::__construct(BancoService::class);
    }
}
