<?php

namespace App\Services\MotorCalculo\Strategies;

use App\Services\MotorCalculo\Contratos\AmortizacaoStrategyInterface;
use App\Services\MotorCalculo\ValueObjects\LinhaFluxo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * SAM (Sistema de Amortização Misto) — §3.3 da documentação técnica.
 *
 * Regra: atenuar a carga inicial do SAC.
 * P_SAM_t = (P_PRICE_t + P_SAC_t) / 2 — média aritmética exata.
 *
 * Implementação: computa os fluxos completos de Price e SAC nos mesmos
 * parâmetros e calcula a média aritmética de cada componente.
 */
final class SamStrategy implements AmortizacaoStrategyInterface
{
    private const SCALE = 14;

    private PriceStrategy $priceStrategy;
    private SacStrategy $sacStrategy;

    public function __construct()
    {
        $this->priceStrategy = new PriceStrategy();
        $this->sacStrategy = new SacStrategy();
    }

    public function gerarFluxo(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        $fluxosPrice = $this->priceStrategy->gerarFluxo(
            $valorFinanciado, $taxaPeriodo, $prazo, $datasVencimento, $dataAbertura
        );
        $fluxosSac = $this->sacStrategy->gerarFluxo(
            $valorFinanciado, $taxaPeriodo, $prazo, $datasVencimento, $dataAbertura
        );

        $dois = BigDecimal::of('2');
        $saldoDevedor = $valorFinanciado;
        $fluxos = [];

        for ($mes = 0; $mes < $prazo; $mes++) {
            $price = $fluxosPrice[$mes];
            $sac = $fluxosSac[$mes];

            // P_SAM_t = (P_PRICE_t + P_SAC_t) / 2
            $parcela = $price->parcela->plus($sac->parcela)
                             ->dividedBy($dois, self::SCALE, RoundingMode::HalfUp);

            $juros = $price->juros->plus($sac->juros)
                           ->dividedBy($dois, self::SCALE, RoundingMode::HalfUp);

            $amortizacao = $parcela->minus($juros);

            if ($mes === $prazo - 1) {
                // Última parcela: absorve resíduo
                $amortizacao = $saldoDevedor;
                $juros = $saldoDevedor->multipliedBy($taxaPeriodo)->toScale(2, RoundingMode::HalfUp);
                $parcela = $amortizacao->plus($juros);
                $saldoDevedor = BigDecimal::of('0');
            } else {
                $saldoDevedor = $saldoDevedor->minus($amortizacao);
            }

            $venc = $datasVencimento[$mes];
            $diasCorridos = max(1, (int) $dataAbertura->diff($venc)->days);

            $fluxos[] = new LinhaFluxo(
                numero: $mes + 1,
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
        $amortsPrice = $this->priceStrategy->gerarAmortizacoesTeoricas($valorFinanciado, $taxaPeriodo, $prazo);
        $amortsSac = $this->sacStrategy->gerarAmortizacoesTeoricas($valorFinanciado, $taxaPeriodo, $prazo);

        $dois = BigDecimal::of('2');
        $amortizacoes = [];

        for ($i = 0; $i < $prazo; $i++) {
            $amortizacoes[] = $amortsPrice[$i]->plus($amortsSac[$i])
                                              ->dividedBy($dois, self::SCALE, RoundingMode::HalfUp);
        }

        return $amortizacoes;
    }

    public function getNome(): string
    {
        return 'SAM';
    }
}
