<?php

namespace App\Services\MotorCalculo;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Conversor de Taxas de Juros — §2 da documentação normativa.
 *
 * Implementa rigorosamente as regras de equivalência e conversão:
 * - Taxa Efetiva → Equivalência por regime composto
 * - Taxa Nominal → Divisão proporcional antes da exponenciação
 * - Proporcionalidade simples somente para juros de mora legais
 *
 * ATENÇÃO: Não dividir taxa efetiva anual por 12 para obter mensal.
 * A conversão de taxas efetivas deve utilizar equivalência composta,
 * nunca proporcionalidade linear (que é reservada a taxas nominais).
 */
final class ConversorTaxas
{
    private const SCALE = 14;

    /**
     * Converte taxa efetiva entre periodicidades (regime composto).
     *
     * Fórmula: i_eq = (1 + i_origem)^(n_origem/n_destino) - 1
     *
     * Onde n_origem e n_destino são períodos/ano (ex: 12=mensal, 1=anual).
     * Ex.: 4% a.m. → anual: (1.04)^(12/1) - 1 ≈ 60,10%
     *
     * @param BigDecimal $taxa     Taxa efetiva de origem em decimal (ex: 0.04 para 4%)
     * @param int        $origem   Períodos/ano da taxa informada (ex: 12 para mensal)
     * @param int        $destino  Períodos/ano da taxa desejada (ex: 1 para anual)
     * @return BigDecimal Taxa equivalente no período destino
     */
    public static function equivalenteComposta(BigDecimal $taxa, int $origem, int $destino): BigDecimal
    {
        if ($taxa->isZero() || $origem <= 0 || $destino <= 0) {
            return $taxa;
        }

        // Expoente = n_origem / n_destino (períodos/ano)
        // Ex.: mensal→anual: origem=12, destino=1 → expoente=12 → (1+i)^12
        $expoente = (float) $origem / (float) $destino;

        // (1 + i)^expoente - 1
        // Usando float para pow() pois BigDecimal não suporta expoentes fracionários nativamente
        $base = (float) $taxa->plus(BigDecimal::one())->__toString();
        $resultado = pow($base, $expoente) - 1.0;

        return BigDecimal::of((string) $resultado);
    }

    /**
     * Converte taxa nominal para efetiva do período.
     *
     * Exemplo: "12% a.a. com capitalização mensal" → 12/12 = 1% a.m. (efetiva mensal)
     *
     * @param BigDecimal $taxaNominal  Taxa nominal informada em decimal
     * @param int        $periodos     Número de períodos de capitalização no ano
     * @return BigDecimal Taxa efetiva do período
     */
    public static function nominalParaEfetiva(BigDecimal $taxaNominal, int $periodos): BigDecimal
    {
        if ($periodos <= 0) {
            return $taxaNominal;
        }

        return $taxaNominal->dividedBy($periodos, self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * Proporcionalidade linear de taxas (regime simples).
     *
     * DEVE ser aplicada SOMENTE quando a lei exigir juros simples:
     * - Juros de mora legais limitados a 1% a.m., prorratizados ao dia (/30)
     *
     * Fórmula: i_prop = i × (n / m)
     *
     * @param BigDecimal $taxa  Taxa no período base
     * @param int        $n     Numerador (período destino)
     * @param int        $m     Denominador (período origem)
     * @return BigDecimal Taxa proporcional
     */
    public static function proporcionalSimples(BigDecimal $taxa, int $n, int $m): BigDecimal
    {
        if ($m <= 0) {
            return $taxa;
        }

        return $taxa->multipliedBy(BigDecimal::of((string) $n))
                    ->dividedBy($m, self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * Converte taxa percentual (ex: "4.0") para decimal (ex: "0.04").
     * Instanciação SEMPRE a partir de string (§7 — proibição de passagem de float).
     *
     * @param string $taxaPct Taxa em percentual como string
     * @return BigDecimal Taxa em decimal
     */
    public static function percentualParaDecimal(string $taxaPct): BigDecimal
    {
        return BigDecimal::of($taxaPct)->dividedBy('100', self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * Converte taxa decimal para percentual.
     *
     * @param BigDecimal $taxaDecimal Taxa em decimal
     * @param int $casasDecimais Casas para arredondamento visual
     * @return BigDecimal Taxa em percentual
     */
    public static function decimalParaPercentual(BigDecimal $taxaDecimal, int $casasDecimais = 6): BigDecimal
    {
        return $taxaDecimal->multipliedBy('100')->toScale($casasDecimais, RoundingMode::HalfUp);
    }
}
