<?php

namespace App\Services\Auth;

use App\Exceptions\SystemException;
use App\Models\Usuario\UsuarioModel;
use App\Services\BaseService;

class AuthService extends BaseService
{
    private $usuarioModel = null;

    public function model(): UsuarioModel
    {
        return $this->usuarioModel ??= new UsuarioModel();
    }

    /**
     * Autentica o usuário e gera a sessão
     * @param string $email
     * @param string $senha
     * @return array Dados do usuário logado
     * @throws SystemException
     */
    public function login(string $email, string $senha): array
    {
        $email = $this->sanitizer()->email($email);

        if (empty($email) || empty($senha)) {
            throw new SystemException('E-mail e senha são obrigatórios.', 400, true);
        }

        // Buscar usuário pelo email (ignorando o filtro multi-tenant durante o login)
        $this->model()->setMultiTenant(false);
        $usuarios = $this->model()->consultar(['email' => $email, 'ativo' => 1]);

        if (empty($usuarios)) {
            throw new SystemException('Credenciais inválidas ou usuário inativo.', 401, true);
        }

        $usuario = $usuarios[0];

        if (!password_verify($senha, $usuario['senha'])) {
            throw new SystemException('Credenciais inválidas.', 401, true);
        }

        unset($usuario['senha']);

        $dadosSessao = [
            'usuario_id' => $usuario['id'],
            'empresa_id' => $usuario['empresa_id'],
            'nome_completo' => $usuario['nome_completo'],
            'email' => $usuario['email'],
            'perfil_acesso' => $usuario['perfil_acesso']
        ];

        $this->sessions()->set(\App\Enums\Auth::token->value, $dadosSessao);

        return $usuario;
    }

    /**
     * Retorna os dados do usuário logado na sessão
     * @return array|null
     */
    public function me(): ?array
    {
        if (!$this->sessions()->has(\App\Enums\Auth::token->value)) {
            throw new SystemException('Nenhum usuário logado.');
        }
        return $this->sessions()->get(\App\Enums\Auth::token->value);
    }

    /**
     * Destrói a sessão do usuário
     * @return string
     */
    public function logout(): string
    {
        $this->sessions()->remove(\App\Enums\Auth::token->value);
        $this->sessions()->clear();
        return 'Logout realizado com sucesso.';
    }
}
