<?php

namespace App\Services\MotorCalculo;

use App\Services\MotorCalculo\Exceptions\ParametroInvalidoException;
use App\Services\MotorCalculo\Exceptions\UnconvergedCetException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Solver do Custo Efetivo Total (CET) — §5 da documentação normativa.
 *
 * Base regulatória: Resolução CMN 4.881/2020.
 *
 * Fórmula oficial:
 *   Σ[j=1..N] FC_j / (1 + CET)^(d_j/365) = FC_0
 *
 * Onde:
 *   - FC_0: valor líquido liberado (fluxo 0)
 *   - FC_j: obrigações futuras (parcelas + IOF + tarifas + seguros vinculados)
 *   - d_j:  dias corridos inteiros entre liberação e vencimento (base 365, ignorando bissextos)
 *   - CET:  taxa anual procurada
 *
 * Método: Newton-Raphson (XIRR) iterando sobre VPL e sua primeira derivada.
 * O PHP NÃO emprega aproximações lineares.
 *
 * Condições:
 *   - Chute inicial: Taxa Nominal Anual do contrato
 *   - Convergência: |x_{k+1} - x_k| ≤ 10^-8
 *   - Limite: 100 iterações → UnconvergedCetException
 */
final class SolverCET
{
    /** @var int Máximo de iterações do Newton-Raphson */
    private const MAX_ITERACOES = 100;

    /** @var float Tolerância de convergência absoluta */
    private const TOLERANCIA = 1e-8;

    /**
     * Encontra o CET anual via Newton-Raphson (XIRR).
     *
     * @param BigDecimal $fc0            Valor líquido liberado (FC_0)
     * @param array      $fluxosFuturos  Array de ['valor' => BigDecimal|float, 'dias' => int]
     * @param float      $chuteInicial   Estimativa inicial da taxa anual (padrão: 0.10 = 10%)
     * @return array{mes: float, ano: float} CET em percentual (mensal e anual)
     *
     * @throws UnconvergedCetException Se não convergir em MAX_ITERACOES
     */
    public static function calcular(
        BigDecimal $fc0,
        array      $fluxosFuturos,
        float      $chuteInicial = 0.10,
    ): array {
        if ($fc0->isNegative()) {
            throw new ParametroInvalidoException(
                'fc0',
                'Valor líquido liberado (FC_0) é negativo — impossível calcular CET. Verifique os dados de entrada.'
            );
        }

        if (empty($fluxosFuturos) || $fc0->isZero()) {
            return ['mes' => 0.0, 'ano' => 0.0];
        }

        // Converte FC_0 para float para o solver (§5.2 permite float no solver iterativo)
        $fc0Float = (float) $fc0->__toString();

        // Prepara os fluxos
        $fluxos = [];
        foreach ($fluxosFuturos as $f) {
            $valor = $f['valor'] instanceof BigDecimal
                ? (float) $f['valor']->__toString()
                : (float) $f['valor'];
            $dias = (int) $f['dias'];
            if ($dias > 0 && $valor > 0) {
                $fluxos[] = ['valor' => $valor, 'dias' => $dias];
            }
        }

        if (empty($fluxos)) {
            return ['mes' => 0.0, 'ano' => 0.0];
        }

        // Newton-Raphson: encontrar taxa anual que zera f(x) = Σ FC_j·(1+x)^(-d_j/365) - FC_0
        $taxaX = max(1e-6, $chuteInicial);

        for ($iter = 0; $iter < self::MAX_ITERACOES; $iter++) {
            $funcaoVPL = -$fc0Float;
            $funcaoDerivada = 0.0;

            foreach ($fluxos as $f) {
                $d = $f['dias'];
                $v = $f['valor'];
                $prazoAnos = $d / 365.0;

                // f(x) = Σ FC_j · (1+x)^(-d_j/365) - FC_0
                $fator = pow(1.0 + $taxaX, -$prazoAnos);
                $funcaoVPL += $v * $fator;

                // f'(x) = Σ [-d_j/365 · FC_j · (1+x)^(-(d_j/365 + 1))]
                $funcaoDerivada += -$prazoAnos * $v * pow(1.0 + $taxaX, -($prazoAnos + 1.0));
            }

            // Proteção contra derivada nula
            if (abs($funcaoDerivada) < self::TOLERANCIA) {
                throw new UnconvergedCetException(
                    $iter,
                    'Derivada nula — divisão impossível no cômputo do CET.'
                );
            }

            // x_{k+1} = x_k - f(x_k) / f'(x_k)
            $novaTaxa = $taxaX - ($funcaoVPL / $funcaoDerivada);

            // Teste de convergência: |x_{k+1} - x_k| ≤ 10^-8
            if (abs($novaTaxa - $taxaX) <= self::TOLERANCIA) {
                $taxaX = $novaTaxa;
                return self::formatarResultado($taxaX);
            }

            // Proteção contra divergência catastrófica
            $taxaX = max(-0.99, $novaTaxa);
        }

        throw new UnconvergedCetException(self::MAX_ITERACOES, (string) self::TOLERANCIA);
    }

    /**
     * Converte a taxa anual encontrada para CET mensal e anual em percentual.
     *
     * CET_mensal = (1 + CET_anual)^(1/12) - 1  [equivalência composta]
     * Apresentação em percentual com 2 casas.
     */
    private static function formatarResultado(float $taxaAnual): array
    {
        $cetAno = $taxaAnual * 100.0;
        $cetMes = (pow(1.0 + $taxaAnual, 1.0 / 12.0) - 1.0) * 100.0;

        return [
            'mes' => round($cetMes, 2),
            'ano' => round($cetAno, 2),
        ];
    }
}
