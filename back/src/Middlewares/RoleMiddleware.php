<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Enums\Auth;
use Lumynus\Framework\AbstractMiddleware;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class RoleMiddleware extends AbstractMiddleware
{
    public function handle(Request $req, Response $res)
    {
        $rota = $req->getUri();
        $user = $this->sessions()->get(Auth::token->value);
        $perfil = $user['perfil_acesso'] ?? 'administrador';

        // Administrador ou rotas de autenticação liberadas
        if ($perfil === 'administrador' || str_contains($rota, '/auth/')) {
            return true;
        }

        if ($perfil === 'contador') {
            // Permitir visualização de receitas e despesas
            if ($req->getMethod() === 'GET' && (str_contains($rota, '/receita/') || str_contains($rota, '/despesa/'))) {
                return true;
            }

            // Permitir ver e editar o próprio perfil
            $meuId = $user['usuario_id'];
            if (str_contains($rota, "/usuario/buscar") && str_contains($rota, "id=$meuId")) {
                return true;
            }
            if (str_contains($rota, "/usuario/editar/$meuId")) {
                return true;
            }
        }

        return $res->status(403)->json([
            'success' => false,
            'data' => 'Acesso negado. Perfil sem permissão para esta área.'
        ]);
    }
}
