<?php

namespace App\Controllers\Usuario;

use App\Controllers\BaseController;
use App\Services\Usuario\UsuarioService;

class UsuarioController extends BaseController
{
    public function __construct()
    {
        parent::__construct(UsuarioService::class);
    }
}
