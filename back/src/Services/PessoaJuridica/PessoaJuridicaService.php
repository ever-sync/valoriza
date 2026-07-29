<?php

namespace App\Services\PessoaJuridica;

use App\Models\PessoaJuridica\PessoaJuridicaModel;
use App\Services\BaseService;
use stdClass;

class PessoaJuridicaService extends BaseService
{
    protected string $model = PessoaJuridicaModel::class;

    protected array $camposGlobais = [
        'razao_social:required,string,max255',
        'nome_fantasia:required,string,max255',
        'cnpj:required,string,max255',
        'telefone:required,string,max255',
        'email:required,string,max255',
        'rede_social:nullable,string,max255',
        'cep:required,string,max255',
        'estado:required,string,max255',
        'cidade:required,string,max255',
        'bairro:required,string,max255',
        'endereco:required,string,max255',
        'numero:required,string,max255',
        'complemento:required,string,max255',
        'limite_credito:required,string,max255',
        'observacao:required,string,max255',
        'banco:required,string,max255',
        'agencia:required,string,max255',
        'conta:required,string,max255',
        'chave_pix:required,string,max255',
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
            'razao_social'  => $this->sanitizer()->string($v['razao_social'] ?? null),
            'nome_fantasia' => $this->sanitizer()->string($v['nome_fantasia'] ?? null),
            'cnpj'          => $this->sanitizer()->string($v['cnpj'] ?? null),
            'telefone'      => $this->sanitizer()->string($v['telefone'] ?? null),
            'email'         => $this->sanitizer()->string($v['email'] ?? null),
            'rede_social'   => $this->sanitizer()->string($v['rede_social'] ?? null),
            'cep'           => $this->sanitizer()->string($v['cep'] ?? null),
            'estado'        => $this->sanitizer()->string($v['estado'] ?? null),
            'cidade'        => $this->sanitizer()->string($v['cidade'] ?? null),
            'bairro'        => $this->sanitizer()->string($v['bairro'] ?? null),
            'endereco'      => $this->sanitizer()->string($v['endereco'] ?? null),
            'numero'        => $this->sanitizer()->string($v['numero'] ?? null),
            'complemento'   => $this->sanitizer()->string($v['complemento'] ?? null),
            'limite_credito' => $this->sanitizer()->string($v['limite_credito'] ?? null),
            'observacao'    => $this->sanitizer()->string($v['observacao'] ?? null),
            'banco'         => $this->sanitizer()->string($v['banco'] ?? null),
            'agencia'       => $this->sanitizer()->string($v['agencia'] ?? null),
            'conta'         => $this->sanitizer()->string($v['conta'] ?? null),
            'chave_pix'     => $this->sanitizer()->string($v['chave_pix'] ?? null),
        ];

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }
}
