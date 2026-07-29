<?php

namespace App\Services\Despesa;

use App\Models\Despesa\DespesaModel;
use App\Services\BaseService;
use DateTime;
use stdClass;

class DespesaService extends BaseService
{
    protected string $model = DespesaModel::class;

    protected array $camposGlobais = [
        'descricao:required,string',
        'valor_pago:required,string',
        'data_pagamento:nullable,string',
        'data_vencimento:required,string',
        'conta_bancaria_origem_id:required,string',
        'tipo_favorecido:required,string',
        'tipo_pessoa:nullable,string',
        'favorecido_id:nullable,string',
        'nome_favorecido_manual:nullable,string',
        'tipo_comprovante_fiscal:nullable,string',
        'numero_comprovante:nullable,string',
        'categoria_id:nullable,string',
        'forma_pagamento:nullable,string',
        'status:nullable,string',
        'irrf_retido:nullable,string',
        'inss_retido:nullable,string',
        'iss_retido:nullable,string',
        'outros_impostos_retidos:nullable,string',
        'observacoes:nullable,string',
        'despesa_recorrente:nullable,int',
        'quantidade_despesas_recorrentes:nullable,int',
    ];

    private function realizar(array $dados, $contexto = 'inserir'): array
    {
        $this->validarCampos($this->camposGlobais, $dados);
        return $this->normalizarCampos($dados);
    }

    protected function prepareWrite(array $dados, $contexto = 'inserir'): stdClass
    {
        $dadosTratados = $this->realizar($dados, $contexto);

        $map = function (array $v): array {
            $dadosMapeados = [
                'descricao'                       => $this->sanitizer()->string($v['descricao'] ?? null),
                'valor_pago'                      => $this->limparValorMonetario($v['valor_pago'] ?? 0),
                'data_pagamento'                  => $this->sanitizer()->string($v['data_pagamento'] ?? null),
                'data_vencimento'                 => $this->sanitizer()->string($v['data_vencimento'] ?? null),
                'conta_bancaria_origem_id'        => $this->sanitizer()->int($v['conta_bancaria_origem_id'] ?? null),
                'tipo_favorecido'                 => $this->sanitizer()->string($v['tipo_favorecido'] ?? null),
                'tipo_pessoa'                     => $this->sanitizer()->string($v['tipo_pessoa'] ?? null),
                'favorecido_id'                   => $this->sanitizer()->int($v['favorecido_id'] ?? null),
                'nome_favorecido_manual'          => $this->sanitizer()->string($v['nome_favorecido_manual'] ?? null),
                'tipo_comprovante_fiscal'         => $this->sanitizer()->string($v['tipo_comprovante_fiscal'] ?? null),
                'numero_comprovante'              => $this->sanitizer()->string($v['numero_comprovante'] ?? null),
                'categoria_id'                    => $this->sanitizer()->int($v['categoria_id'] ?? null),
                'forma_pagamento'                 => $this->sanitizer()->string($v['forma_pagamento'] ?? null),
                'status'                          => $this->sanitizer()->string($v['status'] ?? 'Pendente'),
                'irrf_retido'                     => $this->limparValorMonetario($v['irrf_retido'] ?? 0),
                'inss_retido'                     => $this->limparValorMonetario($v['inss_retido'] ?? 0),
                'iss_retido'                      => $this->limparValorMonetario($v['iss_retido'] ?? 0),
                'outros_impostos_retidos'         => $this->limparValorMonetario($v['outros_impostos_retidos'] ?? 0),
                'observacoes'                     => $this->sanitizer()->string($v['observacoes'] ?? null),
                'despesa_recorrente'              => $this->sanitizer()->int($v['despesa_recorrente'] ?? 0),
                'quantidade_despesas_recorrentes' => $this->sanitizer()->int($v['quantidade_despesas_recorrentes'] ?? 1),
            ];

            if (($dadosMapeados['tipo_favorecido'] ?? null) === 'manual') {
                $dadosMapeados['favorecido_id'] = null;
                $dadosMapeados['tipo_pessoa'] = null;
            } else {
                $dadosMapeados['nome_favorecido_manual'] = null;
            }

            return $dadosMapeados;
        };

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }

    private function limparValorMonetario($valor): float
    {
        if (empty($valor)) {
            return 0.00;
        }

        $val = preg_replace('/[R$\s]/', '', (string) $valor);
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);

        return (float) $val;
    }

    public function cadastrar(array $dados): int
    {
        $dto = $this->prepareWrite($dados, 'inserir');
        $dadosTratados = $dto->inserir[0];

        $recorrente = (int) ($dadosTratados['despesa_recorrente'] ?? 0);
        $quantidade = (int) ($dadosTratados['quantidade_despesas_recorrentes'] ?? 1);

        $primeiroId = $this->model()->cadastrar($dadosTratados);

        if (!$recorrente || $quantidade <= 1) {
            return $primeiroId;
        }

        $dataVencimentoBase = new DateTime($dadosTratados['data_vencimento']);
        $dataPagamentoBase = !empty($dadosTratados['data_pagamento'])
            ? new DateTime($dadosTratados['data_pagamento'])
            : null;

        for ($i = 1; $i < $quantidade; $i++) {
            $dadosCopia = $dadosTratados;

            $novaDataVenc = clone $dataVencimentoBase;
            $novaDataVenc->modify("+{$i} months");
            $dadosCopia['data_vencimento'] = $novaDataVenc->format('Y-m-d');

            if ($dataPagamentoBase) {
                $novaDataPag = clone $dataPagamentoBase;
                $novaDataPag->modify("+{$i} months");
                $dadosCopia['data_pagamento'] = $novaDataPag->format('Y-m-d');
            }

            $dadosCopia['despesa_recorrente'] = 0;
            $dadosCopia['quantidade_despesas_recorrentes'] = 1;
            $dadosCopia['status'] = 'Pendente';
            $dadosCopia['cadastrado_por'] = $this->getUsuarioId();

            $this->model()->cadastrar($dadosCopia);
        }

        return $primeiroId;
    }
}
