<?php

namespace App\Helpers;

use Lumynus\Http\Contracts\Response;

trait RespostaHelper
{

    /**
     * Valida o resultado de uma operação e retorna uma resposta JSON de erro
     * caso a validação falhe.
     *
     * Usado normalmente para validação de campos obrigatórios antes
     * de executar regras de negócio.
     *
     * @param Response $res   Instância de resposta HTTP do Lumynus.
     * @param bool     $resultado Resultado da validação (true para válido).
     * @param array    $campos Lista de campos obrigatórios ausentes ou inválidos.
     *
     * @return bool|Response Retorna true se a validação passar ou
     *                       uma Response JSON com erro (400) caso falhe.
     */
    public function validaRetorno(
        Response $res,
        bool $resultado,
        array $campos = []
    ): bool|Response {
        if ($resultado !== true) {
            return $res
                ->status(400)
                ->json([
                    'success' => false,
                    'data' => 'Campos obrigatórios não enviados.',
                    'fields' => $campos
                ]);
        }

        return true;
    }

    /**
     * Retorna uma resposta JSON padronizada contendo apenas mensagem informativa,
     * baseada no código HTTP informado.
     *
     * Pode utilizar mensagens padrão por status HTTP ou uma mensagem personalizada.
     *
     * @param Response            $res Instância de resposta HTTP do Lumynus.
     * @param int                 $statusCode Código HTTP da resposta.
     * @param null|string|array   $mensagem Mensagem personalizada opcional.
     * @param bool                $useMensagemPersonalizada Define se a mensagem
     *                                                       personalizada deve sobrescrever
     *                                                       a mensagem padrão do status.
     *
     * @return Response Retorna uma Response JSON com sucesso, parcial ou erro.
     */

    public function jsonRetornoMensagem(
        Response $res,
        int $statusCode,
        null|string|array $mensagem = null,
        bool $useMensagemPersonalizada = false
    ): Response {
        $mensagemInterna = match ($statusCode) {
            200 => 'Operação realizada com sucesso.',
            201 => 'Recurso criado com sucesso.',
            400 => 'Requisição inválida.',
            404 => 'Recurso não encontrado.',
            500 => 'Erro interno do servidor.',
            207 => $mensagem,
            default => $mensagem,
        };

        return $res->status($statusCode)->json([
            'success' => in_array($statusCode, [200, 201, 202, 207], true),
            'partial' => in_array($statusCode, [207], true),
            'message' => $useMensagemPersonalizada ? $mensagem : $mensagemInterna
        ]);
    }

    /**
     * Retorna uma resposta JSON contendo dados ou conteúdo,
     * permitindo o uso de chave padrão ("data") ou chave personalizada.
     *
     * Ideal para respostas de listagem, busca ou retorno de payloads.
     *
     * @param Response            $res Instância de resposta HTTP do Lumynus.
     * @param int                 $statusCode Código HTTP da resposta.
     * @param null|string|array   $conteudo Conteúdo a ser retornado na resposta.
     * @param bool                $useChavePersonalizadaRetorno Define se o conteúdo
     *                                                           já possui estrutura de chave
     *                                                           personalizada para o retorno.
     *
     * @return Response Retorna uma Response JSON contendo o conteúdo informado.
     */

    public function jsonRetornoConteudo(
        Response $res,
        int $statusCode,
        null|string|array $conteudo = null,
        bool $useChavePersonalizadaRetorno = false
    ): Response {

        return $res->status($statusCode)->json([
            'success' => in_array($statusCode, [200, 204], true),
            ...(
                $useChavePersonalizadaRetorno
                ? $conteudo
                : ['data' => $conteudo]
            )
        ]);
    }
}
