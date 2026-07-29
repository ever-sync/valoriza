<?php

namespace App\Services\Usuario;

use App\Models\Usuario\UsuarioModel;
use App\Services\BaseService;
use stdClass;

class UsuarioService extends BaseService
{
    protected string $model = UsuarioModel::class;

    protected array $camposGlobais = [
        'nome_completo:required,string',
        'email:required,email',
        'telefone:string',
        'perfil_acesso:required,string',
        'ativo:int',
        'notificar_contratos:int',
    ];

    protected array $camposInserir = [
        'senha:required,string',
    ];

    protected array $camposAtualizar = [
        'senha:string',
    ];

    private function realizar(array $dados, $contexto = 'inserir'): array
    {
        $campos = [
            ...$this->camposGlobais,
            ...($contexto === 'inserir' ? $this->camposInserir : $this->camposAtualizar),
        ];

        $this->validarCampos($campos, $dados);

        return $this->normalizarCampos($dados);
    }

    protected function prepareWrite(array $dados, $contexto = 'inserir'): stdClass
    {
        $dadosTratados = $this->realizar($dados, $contexto);

        $map = function (array $v): array {
            $dadosMapeados = [
                'nome_completo'        => $this->sanitizer()->string($v['nome_completo'] ?? null),
                'email'                => $this->sanitizer()->string($v['email'] ?? null),
                'telefone'             => $this->sanitizer()->string($v['telefone'] ?? ''),
                'perfil_acesso'        => $this->sanitizer()->string($v['perfil_acesso'] ?? null),
                'ativo'                => $this->sanitizer()->int($v['ativo'] ?? 1),
                'notificar_contratos'  => $this->sanitizer()->int($v['notificar_contratos'] ?? 0),
                'avatar'               => $this->sanitizer()->string($v['avatar'] ?? ''),
            ];

            if (!empty($v['senha'])) {
                $dadosMapeados['senha'] = password_hash((string) $v['senha'], PASSWORD_DEFAULT);
            }

            return $dadosMapeados;
        };

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }
}
