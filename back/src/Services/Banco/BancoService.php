<?php

namespace App\Services\Banco;

use App\Models\Banco\BancoModel;
use App\Services\BaseService;
use stdClass;

class BancoService extends BaseService
{

    protected string $model = BancoModel::class;

    protected array $camposGlobais = [
        'banco:required,string',
        'agencia:required,string',
        'conta:required,string',
        'chave_pix:string',
        'padrao:int',
    ];

    private function realizar(array $dados, $contexto = 'inserir'): array
    {
        $this->validarCampos($this->camposGlobais, $dados);
        $dadosTratados = $this->normalizarCampos($dados);
        return $dadosTratados;
    }

    protected function prepareWrite(array $dados, $contexto = 'inserir'): stdClass
    {
        $dadosTratados = $this->realizar($dados, $contexto);

        $map = fn($v) => [
            'banco' => $this->sanitizer()->string($v['banco'] ?? null),
            'agencia'  => $this->sanitizer()->string($v['agencia'] ?? null),
            'conta'  => $this->sanitizer()->string($v['conta'] ?? null),
            'chave_pix'  => $this->sanitizer()->string($v['chave_pix'] ?? null),
            'padrao'  => $this->sanitizer()->int($v['padrao'] ?? null),
        ];

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }
}
