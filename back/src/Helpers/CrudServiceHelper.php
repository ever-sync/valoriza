<?php

namespace App\Helpers;

use App\Exceptions\SystemException;
use App\Enums\ValidacaoEnum;
use Lumynus\Framework\Validate;

trait CRUDServiceHelper
{

    /**
     * Valida a existência e obrigatoriedade dos campos informados.
     *
     * O método normaliza os dados recebidos para garantir que sempre
     * sejam tratados como uma lista de registros, permitindo validação
     * uniforme tanto para um único conjunto de dados quanto para múltiplos.
     *
     * Para cada registro, valida se os campos definidos em `$regras`
     * existem e possuem valores válidos. Caso algum campo seja inválido
     * ou esteja ausente, um log é registrado e uma exceção é lançada.
     *
     * @param array $regras Regras de validação contendo os campos obrigatórios
     * @param array $dados  Dados a serem validados (array associativo ou lista de arrays)
     *
     * @throws EmsoftException Quando existirem campos obrigatórios vazios ou inválidos
     *
     * @return void
     */
    public function validarCampos(array $regras, array $dados): void
    {
        if (empty($regras) || !is_array($regras)) {
            return;
        }

        $nomeClasse = get_class($this);
        $partes = explode('\\', $nomeClasse);
        $nomeClasse = end($partes);

        $dadosTratados = $this->normalizarCampos($dados);

        foreach ($dadosTratados as $key => $valor) {
            if (!is_array($valor) || empty($valor)) {
                continue;
            }

            $validaCampos = (array)(new Validate())->exists($regras, $valor);

            if ($validaCampos['success'] === false) {
                $campos = implode(', ', array_keys($validaCampos['errors']));
                throw new SystemException($nomeClasse . ': ' . ValidacaoEnum::CAMPOS_INVALIDOS->value . ' São eles: ' . $campos, autoAnalisar: false);
            }
        }
    }

    /**
     * Remove do array de dados todos os campos que não estejam definidos
     * nas regras de validação informadas.
     *
     * Este método altera o array de dados por referência, mantendo apenas
     * os campos permitidos conforme as regras fornecidas.
     *
     * Exemplo de regra:
     *  - 'item_id: required, min1, string'
     *
     * Neste caso, apenas o campo "item_id" será preservado nos dados.
     *
     * @param array<int, string> $regras
     *     Lista de regras de validação, onde cada item define o campo e
     *     suas restrições no formato "campo: regra1, regra2".
     *
     * @param array<string, mixed> &$dados
     *     Dados de entrada que serão filtrados por referência, removendo
     *     campos não autorizados.
     *
     * @return void
     */
    public function removerCamposNaoPermitidos(array $regras, array &$dados): void
    {
        if (empty($regras) || !is_array($regras)) {
            return;
        }

        $camposPermitidos = [];

        foreach ($regras as $regra) {
            if (!is_string($regra) || strpos($regra, ':') === false) {
                continue;
            }
            $campo = trim(strtok($regra, ':'));
            if (!empty($campo)) {
                $camposPermitidos[$campo] = true;
            }
        }

        foreach ($dados as $campo => $_) {
            if (!isset($camposPermitidos[$campo])) {
                unset($dados[$campo]);
            }
        }
    }

    /**
     * Normaliza os dados recebidos para um formato consistente de validação.
     *
     * Este método garante que os dados retornados estejam sempre em um formato
     * previsível, tratando três cenários distintos:
     *
     * 1. Quando o array contém apenas um único elemento e esse elemento é um array
     *    (ex: [ ['id' => 1, 'nome' => 'Carlos'] ]), retorna apenas o array interno.
     *
     * 2. Quando o array já é uma lista de arrays
     *    (ex: [ ['id' => 1], ['id' => 2] ]), retorna o próprio array sem alterações.
     *
     * 3. Quando os dados são um array associativo simples
     *    (ex: ['id' => 1, 'nome' => 'Carlos']),
     *    encapsula os dados em uma lista:
     *    (ex: [ ['id' => 1, 'nome' => 'Carlos'] ]).
     *
     * @param array $dados Array associativo ou lista de arrays a ser normalizado
     *
     * @return array Dados normalizados para processamento uniforme
     */
    public function normalizarCampos(array $dados): array
    {
        if (empty($dados) || !is_array($dados)) {
            return [[]];
        }

        if (count($dados) === 1 && is_array($primeiro = reset($dados))) {
            return $primeiro;
        }

        if (isset($dados[0]) && is_array($dados[0])) {
            return $dados;
        }

        return [$dados];
    }

    /**
     * Verifica se um valor deve ser considerado vazio
     *
     * Considera vazio: null, string vazia ou composta apenas por espaços
     * Não considera vazio: 0 ou '0'
     *
     * @param mixed $valor
     * @param bool $considereZeroComoVazio Se true, considera 0 como vazio.
     * @return boolean Retorna true se o valor for considerado vazio
     */
    public function eVazio(mixed $valor, bool $considereZeroComoVazio = false): bool
    {
        if (is_null($valor)) {
            return true;
        }

        if (is_string($valor)) {
            return trim($valor) === '';
        }

        if ($considereZeroComoVazio && is_numeric($valor)) {
            return $valor === 0;
        }

        return false;
    }

    /**
     * Remove do array apenas os campos vazios, se desejado.
     * @param array $campos
     * @param bool $removerVazios Se true, remove campos vazios; se false, mantém todos.
     * @param bool $considereZeroComoVazio Se true, considera 0 como vazio.
     * @return array
     */
    public function removerCamposVazios(array $campos, bool $removerVazios = true, bool $considereZeroComoVazio = false): array
    {
        if (!$removerVazios) {
            return $campos;
        }

        foreach ($campos as $campo => $valor) {
            if ($this->eVazio($valor, $considereZeroComoVazio)) {
                unset($campos[$campo]);
            }
        }
        return $campos;
    }
}
