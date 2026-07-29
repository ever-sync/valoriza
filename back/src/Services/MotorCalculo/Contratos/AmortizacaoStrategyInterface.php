<?php

namespace App\Services\MotorCalculo\Contratos;

use Brick\Math\BigDecimal;
use App\Services\MotorCalculo\ValueObjects\LinhaFluxo;

/**
 * Interface Strategy para Sistemas de Amortização.
 *
 * Cada implementação encapsula a regra de mutação mutuamente exclusiva
 * do Saldo Devedor conforme §3 da documentação normativa.
 *
 * O motor instancia a Strategy concreta via padrão Strategy,
 * garantindo que as regras de amortização sejam injetáveis e testáveis.
 */
interface AmortizacaoStrategyInterface
{
    /**
     * Gera o fluxo completo de amortização.
     *
     * @param BigDecimal $valorFinanciado  V_f — Principal (base de cálculo de juros)
     * @param BigDecimal $taxaPeriodo      Taxa efetiva do período em decimal (ex: 0.04 para 4%)
     * @param int        $prazo            Número de parcelas (N)
     * @param array      $datasVencimento  Array de DateTime para cada parcela
     * @param \DateTime  $dataAbertura     Data de liberação do crédito
     * @return LinhaFluxo[]                Array de objetos LinhaFluxo
     */
    public function gerarFluxo(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array;

    /**
     * Gera array simples de amortizações teóricas (para cálculo de IOF).
     *
     * @return BigDecimal[] Array de valores de amortização por parcela
     */
    public function gerarAmortizacoesTeoricas(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
    ): array;

    /**
     * Retorna o nome identificador do sistema de amortização.
     */
    public function getNome(): string;
}
