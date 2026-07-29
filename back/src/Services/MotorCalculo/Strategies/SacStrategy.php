<?php

namespace App\Services\MotorCalculo\Strategies;

use App\Services\MotorCalculo\Contratos\AmortizacaoStrategyInterface;
use App\Services\MotorCalculo\ValueObjects\LinhaFluxo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * SAC (Sistema de Amortização Constante) — §3.2 da documentação normativa.
 *
 * Regra: devolução fixa do capital.
 * Amortização: A = V_f / n (fixa no ato da emissão)
 * Juros: J_t = SD_{t-1} × i (sobre saldo decrescente)
 * Parcela: P_t = A + J_t (variável, decrescente linearmente)
 *
 * Garantia inquebrável: Σ A_t = V_f
 */
final class SacStrategy implements AmortizacaoStrategyInterface
{
    private const SCALE = 14;

    public function gerarFluxo(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        // A = V_f / n — fixada no ato da emissão
        $amortizacaoFixa = $valorFinanciado->dividedBy($prazo, self::SCALE, RoundingMode::HalfUp)
                                            ->toScale(2, RoundingMode::HalfUp);

        $saldoDevedor = $valorFinanciado;
        $fluxos = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            $venc = $datasVencimento[$mes - 1];
            $diasCorridos = max(1, (int) $dataAbertura->diff($venc)->days);

            // J_t = SD_{t-1} × i
            $juros = $saldoDevedor->multipliedBy($taxaPeriodo)->toScale(2, RoundingMode::HalfUp);

            if ($mes === $prazo) {
                // Última parcela: absorve resíduo — A_n = SD_{n-1}
                $amortizacao = $saldoDevedor;
                $saldoDevedor = BigDecimal::of('0');
            } else {
                $amortizacao = $amortizacaoFixa;
                $saldoDevedor = $saldoDevedor->minus($amortizacao);
            }

            // P_t = A + J_t
            $parcela = $amortizacao->plus($juros);

            $fluxos[] = new LinhaFluxo(
                numero: $mes,
                parcela: $parcela,
                juros: $juros,
                amortizacao: $amortizacao,
                saldoDevedor: $saldoDevedor,
                vencimentoIso: $venc->format('Y-m-d'),
                vencimentoFmt: $venc->format('d/m/Y'),
                diasCorridosDesdeAbertura: $diasCorridos,
            );
        }

        return $fluxos;
    }

    public function gerarAmortizacoesTeoricas(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
    ): array {
        $amortizacaoFixa = $valorFinanciado->dividedBy($prazo, self::SCALE, RoundingMode::HalfUp)
                                            ->toScale(2, RoundingMode::HalfUp);

        $saldoDevedor = $valorFinanciado;
        $amortizacoes = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            if ($mes === $prazo) {
                $amortizacoes[] = $saldoDevedor;
            } else {
                $amortizacoes[] = $amortizacaoFixa;
                $saldoDevedor = $saldoDevedor->minus($amortizacaoFixa);
            }
        }

        return $amortizacoes;
    }

    public function getNome(): string
    {
        return 'SAC';
    }
}
