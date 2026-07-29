<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\Auth\AuthService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class AuthController extends BaseController
{

    public function __construct()
    {
        parent::__construct(AuthService::class);
    }

    /**
     * Endpoint para login
     */
    public function login(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            $email = $dados['email'] ?? '';
            $senha = $dados['senha'] ?? '';
            return $this->servico->login($email, $senha);
        });
    }

    /**
     * Endpoint para retornar os dados do usuário logado
     */
    public function me(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return $this->servico->me();
        });
    }

    /**
     * Endpoint para logout
     */
    public function logout(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return   $this->servico->logout();
        });
    }
}
