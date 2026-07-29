<?php

namespace App\Controllers\Receita;

use App\Controllers\BaseController;
use App\Services\Receita\ReceitaService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

/**
 * Controlador de Receitas.
 *
 * Gerencia as ações HTTP para o módulo de receitas financeiras.
 * Herda CRUD padrão (inserir, editar, excluir, buscar) do BaseController.
 */
class ReceitaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(ReceitaService::class);
    }

    /**
     * Simular uma prorrogação.
     * Retorna os valores calculados (juros, multa, etc) para a tela.
     */
    public function simularProrrogacao(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->simularProrrogacao($id, ['data_vencimento_nova' => $dados['data_vencimento_nova'] ?? null]);
        });
    }

    /**
     * Prorrogar uma parcela.
     * Recebe: data_vencimento_nova, justificativa
     * O valor é calculado automaticamente pelo serviço.
     */
    public function prorrogar(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->prorrogar($dados, $id);
        });
    }

    /**
     * Consultar histórico de prorrogações de uma receita.
     */
    public function consultarProrrogacoes(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->consultarProrrogacoes($id);
        });
    }

    /**
     * Registrar pagamento parcial (carência de principal).
     * Recebe: valor_pago, data_pagamento
     */
    public function pagarParcial(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->pagarParcial($id, $dados);
        });
    }

    /**
     * Aplica carência de principal.
     */
    public function pagarCarencia(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->pagarCarencia($id, $dados);
        });
    }

    /**
     * Registrar pagamento integral (quitação).
     * Recebe: data_recebimento, conta_bancaria_destino_id
     */
    public function quitarIntegral(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->quitarIntegral($id, $dados);
        });
    }

    /**
     * Consultar contrato vinculado a uma receita.
     */
    public function consultarContrato(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->consultarContrato($id);
        });
    }

    /**
     * Calcular encargos de quitação de uma receita.
     *
     * Retorna juros de mora, multa moratória, juros de atualização
     * calculados com base nas taxas do contrato vinculado e nos dias de atraso.
     *
     * @param Request  $req Requisição com data_recebimento opcional
     * @param Response $res Resposta HTTP
     * @return Response Encargos calculados
     */
    public function calcularEncargos(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            return $this->servico->calcularEncargosRecebimento(
                (int) $id,
                $dados['data_recebimento'] ?? date('Y-m-d'),
                $dados['valor_original'] ?? null
            );
        });
    }
}
