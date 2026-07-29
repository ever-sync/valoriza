<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PessoaFisica\PessoaFisicaService;
use Lumynus\Framework\AbstractController;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class ControllerExample extends AbstractController
{
    public function index(Response $res, Request $req)
    {
        $this->container()->make((int)PessoaFisicaService::class);
        $res->html($this->container()->getTrace());
    }
}
