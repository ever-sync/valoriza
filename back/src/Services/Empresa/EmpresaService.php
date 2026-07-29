<?php

namespace App\Services\Empresa;

use App\Models\Empresa\EmpresaModel;
use App\Services\BaseService;
use stdClass;

class EmpresaService extends BaseService
{
    protected string $model = EmpresaModel::class;

    protected array $camposGlobais = [
        'cnpj:required,string',
        'razao_social:required,string',
        'nome_fantasia:string',
        'email:email',
        'telefone:string',
        'data_abertura:string',
        'capital_social:string',
        'cep:string',
        'estado:string',
        'cidade:string',
        'bairro:string',
        'endereco:string',
        'numero:string',
        'complemento:string',
        'logo:string',
    ];

    private function realizar(array $dados, $contexto = 'inserir'): array
    {
        $this->validarCampos($this->camposGlobais, $dados);
        return $this->normalizarCampos($dados);
    }

    protected function prepareWrite(array $dados, $contexto = 'inserir'): stdClass
    {
        $dadosTratados = $this->realizar($dados, $contexto);

        $map = fn($v) => [
            'cnpj'          => $this->sanitizer()->string($v['cnpj'] ?? null),
            'razao_social'  => $this->sanitizer()->string($v['razao_social'] ?? null),
            'nome_fantasia' => $this->sanitizer()->string($v['nome_fantasia'] ?? ''),
            'email'         => $this->sanitizer()->string($v['email'] ?? ''),
            'telefone'      => $this->sanitizer()->string($v['telefone'] ?? ''),
            'data_abertura' => $this->sanitizer()->string($v['data_abertura'] ?? ''),
            'capital_social' => $this->sanitizer()->string($v['capital_social'] ?? '0'),
            'cep'           => $this->sanitizer()->string($v['cep'] ?? ''),
            'estado'        => $this->sanitizer()->string($v['estado'] ?? ''),
            'cidade'        => $this->sanitizer()->string($v['cidade'] ?? ''),
            'bairro'        => $this->sanitizer()->string($v['bairro'] ?? ''),
            'endereco'      => $this->sanitizer()->string($v['endereco'] ?? ''),
            'numero'        => $this->sanitizer()->string($v['numero'] ?? ''),
            'complemento'   => $this->sanitizer()->string($v['complemento'] ?? ''),
            'logo'          => $this->sanitizer()->string($v['logo'] ?? ''),
        ];

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }
}
