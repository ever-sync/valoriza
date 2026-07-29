<?php

namespace App\Services\MotorCalculo\Strategies;

use App\Services\MotorCalculo\Contratos\AmortizacaoStrategyInterface;
use App\Services\MotorCalculo\ValueObjects\LinhaFluxo;
use App\Services\MotorCalculo\Enums\PoliticaResiduo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Tabela PRICE (Sistema Francês) — §3.1 da documentação normativa.
 *
 * Regra: parcelas financeiras periódicas constantes.
 * Fórmula: P = V_f × [i·(1+i)^n] / [(1+i)^n - 1]
 *
 * Juros calculado primeiro: J_t = SD_{t-1} × i
 * Amortização por subtração: A_t = P - J_t
 *
 * Tratamento de Resíduos:
 *   - Ajuste na Última Parcela (Prática de mercado consolidada): A_n = SD_{n-1}
 *   - Largest Remainder Method (configurável)
 *
 * Fallback: Se i = 0, P = V_f / n (divisão linear).
 */
final class PriceStrategy implements AmortizacaoStrategyInterface
{
    private const SCALE = 14;

    public function __construct(
        private readonly PoliticaResiduo $politicaResiduo = PoliticaResiduo::AJUSTE_ULTIMA_PARCELA,
    ) {}

    public function gerarFluxo(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        // Fallback: taxa zero → divisão linear (§9 — Divisão por Zero)
        if ($taxaPeriodo->isZero()) {
            return $this->gerarFluxoSemJuros($valorFinanciado, $prazo, $datasVencimento, $dataAbertura);
        }

        // K = i·(1+i)^n / ((1+i)^n - 1)
        $um = BigDecimal::of('1');
        $fatorExponencial = $taxaPeriodo->plus($um)->power($prazo);
        $kNumerador = $taxaPeriodo->multipliedBy($fatorExponencial);
        $kDenominador = $fatorExponencial->minus($um);
        $k = $kNumerador->dividedBy($kDenominador, self::SCALE, RoundingMode::HalfUp);

        // Parcela fixa arredondada para 2 casas (HALF_UP — §6)
        $parcelaFixa = $valorFinanciado->multipliedBy($k)->toScale(2, RoundingMode::HalfUp);

        $saldoDevedor = $valorFinanciado;
        $fluxos = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            $venc = $datasVencimento[$mes - 1];
            $diasCorridos = max(1, (int) $dataAbertura->diff($venc)->days);

            if ($mes === $prazo && $this->politicaResiduo === PoliticaResiduo::AJUSTE_ULTIMA_PARCELA) {
                // §3.1 — Ajuste na Última Parcela (Prática de mercado):
                // A_n = SD_{n-1}, P_n = SD_{n-1} + (SD_{n-1} × i)
                $juros = $saldoDevedor->multipliedBy($taxaPeriodo)->toScale(2, RoundingMode::HalfUp);
                $amortizacao = $saldoDevedor;
                $parcelaAjustada = $amortizacao->plus($juros);
                $saldoDevedor = BigDecimal::of('0');

                $fluxos[] = new LinhaFluxo(
                    numero: $mes,
                    parcela: $parcelaAjustada,
                    juros: $juros,
                    amortizacao: $amortizacao,
                    saldoDevedor: $saldoDevedor,
                    vencimentoIso: $venc->format('Y-m-d'),
                    vencimentoFmt: $venc->format('d/m/Y'),
                    diasCorridosDesdeAbertura: $diasCorridos,
                );
            } else {
                $juros = $saldoDevedor->multipliedBy($taxaPeriodo)->toScale(2, RoundingMode::HalfUp);
                $amortizacao = $parcelaFixa->minus($juros);
                $saldoDevedor = $saldoDevedor->minus($amortizacao);

                $fluxos[] = new LinhaFluxo(
                    numero: $mes,
                    parcela: $parcelaFixa,
                    juros: $juros,
                    amortizacao: $amortizacao,
                    saldoDevedor: $saldoDevedor,
                    vencimentoIso: $venc->format('Y-m-d'),
                    vencimentoFmt: $venc->format('d/m/Y'),
                    diasCorridosDesdeAbertura: $diasCorridos,
                );
            }
        }

        return $fluxos;
    }

    public function gerarAmortizacoesTeoricas(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
    ): array {
        if ($taxaPeriodo->isZero()) {
            $amort = $valorFinanciado->dividedBy($prazo, self::SCALE, RoundingMode::HalfUp);
            return array_fill(0, $prazo, $amort);
        }

        $um = BigDecimal::of('1');
        $fatorExponencial = $taxaPeriodo->plus($um)->power($prazo);
        $kNumerador = $taxaPeriodo->multipliedBy($fatorExponencial);
        $kDenominador = $fatorExponencial->minus($um);
        $k = $kNumerador->dividedBy($kDenominador, self::SCALE, RoundingMode::HalfUp);
        $parcelaFixa = $valorFinanciado->multipliedBy($k)->toScale(2, RoundingMode::HalfUp);

        $saldoDevedor = $valorFinanciado;
        $amortizacoes = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            if ($mes === $prazo) {
                $amortizacoes[] = $saldoDevedor;
            } else {
                $juros = $saldoDevedor->multipliedBy($taxaPeriodo)->toScale(2, RoundingMode::HalfUp);
                $amort = $parcelaFixa->minus($juros);
                $amortizacoes[] = $amort;
                $saldoDevedor = $saldoDevedor->minus($amort);
            }
        }

        return $amortizacoes;
    }

    public function getNome(): string
    {
        return 'Price';
    }

    /**
     * Fallback para taxa zero: parcela = V_f / n.
     */
    private function gerarFluxoSemJuros(
        BigDecimal $valorFinanciado,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        $amort = $valorFinanciado->dividedBy($prazo, self::SCALE, RoundingMode::HalfUp)
                                  ->toScale(2, RoundingMode::HalfUp);
        $saldoDevedor = $valorFinanciado;
        $fluxos = [];
        $zero = BigDecimal::of('0');

        for ($mes = 1; $mes <= $prazo; $mes++) {
            $venc = $datasVencimento[$mes - 1];
            $diasCorridos = max(1, (int) $dataAbertura->diff($venc)->days);

            if ($mes === $prazo) {
                $amortFinal = $saldoDevedor;
                $saldoDevedor = BigDecimal::of('0');

                $fluxos[] = new LinhaFluxo(
                    numero: $mes,
                    parcela: $amortFinal,
                    juros: $zero,
                    amortizacao: $amortFinal,
                    saldoDevedor: $saldoDevedor,
                    vencimentoIso: $venc->format('Y-m-d'),
                    vencimentoFmt: $venc->format('d/m/Y'),
                    diasCorridosDesdeAbertura: $diasCorridos,
                );
            } else {
                $saldoDevedor = $saldoDevedor->minus($amort);

                $fluxos[] = new LinhaFluxo(
                    numero: $mes,
                    parcela: $amort,
                    juros: $zero,
                    amortizacao: $amort,
                    saldoDevedor: $saldoDevedor,
                    vencimentoIso: $venc->format('Y-m-d'),
                    vencimentoFmt: $venc->format('d/m/Y'),
                    diasCorridosDesdeAbertura: $diasCorridos,
                );
            }
        }

        return $fluxos;
    }
}
