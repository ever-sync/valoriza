<?php

namespace App\Services\MotorCalculo\Strategies;

use App\Services\MotorCalculo\Contratos\AmortizacaoStrategyInterface;
use App\Services\MotorCalculo\ValueObjects\LinhaFluxo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Sistema Americano (Juros Periódicos) — §3.4 da documentação normativa.
 *
 * Regra: O cliente NÃO amortiza capital mensalmente (A_t = 0 para t < n).
 * Paga apenas juros periódicos: P_t = V_f × i
 * Principal integralmente na última: P_n = V_f + (V_f × i)
 *
 * Variante Bullet Puro: sem pagamentos intermediários, juros compostos
 * capitalizados. SD_t = SD_{t-1} × (1+i). Pagamento único P_n = V_f × (1+i)^n.
 */
final class AmericanoStrategy implements AmortizacaoStrategyInterface
{
    private const SCALE = 14;

    public function __construct(
        private readonly bool $bulletPuro = false,
    ) {}

    public function gerarFluxo(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        if ($this->bulletPuro) {
            return $this->gerarFluxoBullet($valorFinanciado, $taxaPeriodo, $prazo, $datasVencimento, $dataAbertura);
        }

        return $this->gerarFluxoJurosPeriodicos($valorFinanciado, $taxaPeriodo, $prazo, $datasVencimento, $dataAbertura);
    }

    /**
     * Juros Periódicos: P_t = V_f × i (A_t = 0 para t < n)
     */
    private function gerarFluxoJurosPeriodicos(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        $saldoDevedor = $valorFinanciado;
        $zero = BigDecimal::of('0');
        $fluxos = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            $venc = $datasVencimento[$mes - 1];
            $diasCorridos = max(1, (int) $dataAbertura->diff($venc)->days);

            // J_t = V_f × i (sobre saldo integral — não há amortização intermediária)
            $juros = $saldoDevedor->multipliedBy($taxaPeriodo)->toScale(2, RoundingMode::HalfUp);

            if ($mes === $prazo) {
                // P_n = V_f + (V_f × i)
                $amortizacao = $saldoDevedor;
                $parcela = $amortizacao->plus($juros);
                $saldoDevedor = BigDecimal::of('0');
            } else {
                // P_t = V_f × i (apenas juros)
                $amortizacao = $zero;
                $parcela = $juros;
            }

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

    /**
     * Bullet Puro: SD_t = SD_{t-1} × (1+i), pagamento único P_n = V_f × (1+i)^n
     */
    private function gerarFluxoBullet(
        BigDecimal $valorFinanciado,
        BigDecimal $taxaPeriodo,
        int        $prazo,
        array      $datasVencimento,
        \DateTime  $dataAbertura,
    ): array {
        $um = BigDecimal::of('1');
        $zero = BigDecimal::of('0');
        $fatorComposto = $taxaPeriodo->plus($um);
        $saldoDevedor = $valorFinanciado;
        $fluxos = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            $venc = $datasVencimento[$mes - 1];
            $diasCorridos = max(1, (int) $dataAbertura->diff($venc)->days);

            // Capitalização composta: SD_t = SD_{t-1} × (1+i)
            $juros = $saldoDevedor->multipliedBy($taxaPeriodo);

            if ($mes === $prazo) {
                // Pagamento único: P_n = SD_n (que é V_f × (1+i)^n)
                $saldoFinal = $saldoDevedor->plus($juros);
                $parcela = $saldoFinal;
                $amortizacao = $valorFinanciado;
                $jurosTotais = $saldoFinal->minus($valorFinanciado);
                $saldoDevedor = BigDecimal::of('0');

                $fluxos[] = new LinhaFluxo(
                    numero: $mes,
                    parcela: $parcela,
                    juros: $jurosTotais,
                    amortizacao: $amortizacao,
                    saldoDevedor: $saldoDevedor,
                    vencimentoIso: $venc->format('Y-m-d'),
                    vencimentoFmt: $venc->format('d/m/Y'),
                    diasCorridosDesdeAbertura: $diasCorridos,
                );
            } else {
                // Sem pagamento intermediário — capitaliza juros
                $saldoDevedor = $saldoDevedor->plus($juros);

                $fluxos[] = new LinhaFluxo(
                    numero: $mes,
                    parcela: $zero,
                    juros: $zero,
                    amortizacao: $zero,
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
        $zero = BigDecimal::of('0');
        $amortizacoes = [];

        for ($mes = 1; $mes <= $prazo; $mes++) {
            if ($mes === $prazo) {
                $amortizacoes[] = $valorFinanciado;
            } else {
                $amortizacoes[] = $zero;
            }
        }

        return $amortizacoes;
    }

    public function getNome(): string
    {
        return $this->bulletPuro ? 'Bullet' : 'Sistema americano';
    }
}
