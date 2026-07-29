<?php

namespace App\Services\PessoaFisica;

use App\Models\PessoaFisica\PessoaFisicaModel;
use App\Services\BaseService;
use stdClass;

class PessoaFisicaService extends BaseService
{
    protected string $model = PessoaFisicaModel::class;

    protected array $camposGlobais = [
        'nome_completo:required,string,max255',
        'cpf:required,string,max255',
        'rg:required,string,max255',
        'orgao_emissor_rg:required,string,max255',
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
        'renda_mensal:required,string,max255',
        'limite_credito:required,string,max255',
        'estado_civil:required,string,max255',
        'regime_partilha:required,string,max255',
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
            'nome_completo'    => $this->sanitizer()->string($v['nome_completo'] ?? null),
            'cpf'              => $this->sanitizer()->string($v['cpf'] ?? null),
            'rg'               => $this->sanitizer()->string($v['rg'] ?? null),
            'orgao_emissor_rg' => $this->sanitizer()->string($v['orgao_emissor_rg'] ?? null),
            'telefone'         => $this->sanitizer()->string($v['telefone'] ?? null),
            'email'            => $this->sanitizer()->string($v['email'] ?? null),
            'rede_social'      => $this->sanitizer()->string($v['rede_social'] ?? null),
            'cep'              => $this->sanitizer()->string($v['cep'] ?? null),
            'estado'           => $this->sanitizer()->string($v['estado'] ?? null),
            'cidade'           => $this->sanitizer()->string($v['cidade'] ?? null),
            'bairro'           => $this->sanitizer()->string($v['bairro'] ?? null),
            'endereco'         => $this->sanitizer()->string($v['endereco'] ?? null),
            'numero'           => $this->sanitizer()->string($v['numero'] ?? null),
            'complemento'      => $this->sanitizer()->string($v['complemento'] ?? null),
            'renda_mensal'     => $this->sanitizer()->string($v['renda_mensal'] ?? null),
            'limite_credito'   => $this->sanitizer()->string($v['limite_credito'] ?? null),
            'estado_civil'     => $this->sanitizer()->string($v['estado_civil'] ?? null),
            'regime_partilha'  => $this->sanitizer()->string($v['regime_partilha'] ?? null),
            'observacao'       => $this->sanitizer()->string($v['observacao'] ?? null),
            'banco'            => $this->sanitizer()->string($v['banco'] ?? null),
            'agencia'          => $this->sanitizer()->string($v['agencia'] ?? null),
            'conta'            => $this->sanitizer()->string($v['conta'] ?? null),
            'chave_pix'        => $this->sanitizer()->string($v['chave_pix'] ?? null),
        ];

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }
}
