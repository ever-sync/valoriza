<?php

namespace App\Middlewares;

use App\Enums\Auth;
use Lumynus\Framework\AbstractMiddleware;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class AuthMiddleware extends AbstractMiddleware
{

    public function handle(Request $req, Response $res)
    {
        $uri = $req->getUri();

        // Exceção para login
        if (str_contains($uri, '/auth/login')) {
            return;
        }

        if (!$this->sessions()->has(Auth::token->value)) {
            return $res->status(401)->json(['success' => false, 'data' => 'Usuário não logado.']);
        }
    }
}
