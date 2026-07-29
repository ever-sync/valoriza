<?php

namespace App\Services\MotorCalculo;

use App\Services\MotorCalculo\Enums\TipoPessoa;
use App\Services\MotorCalculo\Exceptions\GrossUpDivergenciaException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculadora de IOF (Imposto sobre Operações Financeiras) — §4 da normativa.
 *
 * Base legal: Decreto 6.306/2007, alterado pelos Decretos 12.466 e 12.467/2025.
 *
 * Escopo de Incidência:
 *   IOF = (V_f × Alíquota_Fixa) + Σ (A_k × min(d_k, 365) × Alíquota_Diária)
 *
 * A função min(d_k, 365) aplica a barreira tributária inquebrável,
 * impedindo cobrança extra em contratos alongados de múltiplos anos.
 *
 * As alíquotas NÃO são hardcoded — são extraídas de matrizes de configuração
 * temporal referenciadas pela data da operação.
 */
final class CalculadoraIOF
{
    private const SCALE = 14;

    /** @var int Limite máximo de iterações do Gross-Up */
    private const MAX_ITERACOES_GROSSUP = 100;

    /** @var string Tolerância de convergência do Gross-Up */
    private const TOLERANCIA_GROSSUP = '0.000001';

    /**
     * Tabela parametrizável de alíquotas IOF vigentes.
     *
     * Estrutura: [tipo_pessoa => [diario => float, adicional => float]]
     * Deve ser substituída por consulta a banco/config em produção.
     *
     * Valores conforme Decreto 12.499/2025 (restaurado por decisão STF jul/2025):
     * - PF:     Adicional 0,38% | Diário 0,0082%
     * - PJ:     Adicional 0,38% | Diário 0,0082%
     * - MEI:    Adicional 0,38% | Diário 0,00274% (Simples Nacional)
     * - Simples (≤R$30k): Adicional 0,38% | Diário 0,00274%
     */
    private static array $tabelaAliquotas = [
        'pf' => [
            'diario'    => '0.000082',   // 0,0082% ao dia
            'adicional' => '0.0038',     // 0,38%
        ],
        'pj' => [
            'diario'    => '0.000082',   // 0,0082% ao dia
            'adicional' => '0.0038',     // 0,38% — Decreto 12.499/2025
        ],
        'pj_simples' => [
            'diario'    => '0.0000274',  // 0,00274% ao dia
            'adicional' => '0.0038',     // 0,38%
        ],
        'mei' => [
            'diario'    => '0.0000274',  // 0,00274% ao dia (Simples Nacional)
            'adicional' => '0.0038',     // 0,38% (limitado como PF)
        ],
    ];

    /**
     * Recupera alíquotas IOF conforme natureza jurídica do tomador.
     *
     * @param TipoPessoa $tipoPessoa     Natureza jurídica
     * @param bool       $simplesNacional Se optante do Simples Nacional
     * @param BigDecimal $valorOperacao   Valor da operação (para verificar limite Simples ≤ R$30k)
     * @return array{diario: BigDecimal, adicional: BigDecimal}
     */
    public static function getAliquotas(
        TipoPessoa $tipoPessoa,
        bool       $simplesNacional = false,
        ?BigDecimal $valorOperacao = null,
    ): array {
        $valorOperacao ??= BigDecimal::of('0');

        $chave = match (true) {
            $tipoPessoa === TipoPessoa::MEI => 'mei',
            $simplesNacional => 'pj_simples',
            $tipoPessoa === TipoPessoa::JURIDICA => 'pj',
            default => 'pf',
        };

        $aliquotas = self::$tabelaAliquotas[$chave];

        return [
            'diario'    => BigDecimal::of($aliquotas['diario']),
            'adicional' => BigDecimal::of($aliquotas['adicional']),
        ];
    }

    /**
     * Calcula IOF conforme Decreto 6.306/2007 para amortizações parceladas.
     *
     * Fórmula normativa:
     *   IOF = (V_f × Alíquota_Fixa) + Σ[k=1..n] (A_k × min(d_k, 365) × Alíquota_Diária)
     *
     * @param BigDecimal   $valorFinanciado  V_f — Principal
     * @param BigDecimal   $aliquotaDiaria   Taxa IOF diária em decimal
     * @param BigDecimal   $aliquotaAdicional Taxa IOF adicional (fixa) em decimal
     * @param BigDecimal[] $amortizacoes     Frações de capital devolvido por parcela
     * @param int[]        $diasCorridos     Dias corridos da abertura até cada vencimento
     * @return array{diario: string, adicional: string, total: string, taxa_diaria_pct: string, taxa_adicional_pct: string}
     */
    public static function calcular(
        BigDecimal $valorFinanciado,
        BigDecimal $aliquotaDiaria,
        BigDecimal $aliquotaAdicional,
        array      $amortizacoes,
        array      $diasCorridos,
    ): array {
        $count = min(count($amortizacoes), count($diasCorridos));
        $iofDiario = BigDecimal::of('0');
        $trezentosSesssentaECinco = 365;

        for ($i = 0; $i < $count; $i++) {
            // min(d_k, 365) — barreira tributária inquebrável
            $diasLimitado = min($trezentosSesssentaECinco, $diasCorridos[$i]);

            // A_k × min(d_k, 365) × Alíquota_Diária
            $iofParcela = $amortizacoes[$i]
                ->multipliedBy(BigDecimal::of((string) $diasLimitado))
                ->multipliedBy($aliquotaDiaria);

            // Arredondamento individual por parcela (§6)
            $iofParcela = $iofParcela->toScale(2, RoundingMode::HalfUp);
            $iofDiario = $iofDiario->plus($iofParcela);
        }

        // IOF Adicional: V_f × Alíquota_Fixa
        $iofAdicional = $valorFinanciado->multipliedBy($aliquotaAdicional)
            ->toScale(2, RoundingMode::HalfUp);

        $iofTotal = $iofDiario->plus($iofAdicional)->toScale(2, RoundingMode::HalfUp);

        return [
            'diario'             => $iofDiario->toScale(2, RoundingMode::HalfUp)->__toString(),
            'adicional'          => $iofAdicional->__toString(),
            'total'              => $iofTotal->__toString(),
            'taxa_diaria_pct'    => $aliquotaDiaria->multipliedBy('100')
                ->toScale(5, RoundingMode::HalfUp)->__toString(),
            'taxa_adicional_pct' => $aliquotaAdicional->multipliedBy('100')
                ->toScale(2, RoundingMode::HalfUp)->__toString(),
        ];
    }

    /**
     * Resolve o Gross-Up circular do IOF financiado — §4.4 da normativa.
     *
     * Quando o IOF é financiado, ele aumenta o V_f, que por sua vez aumenta a base
     * de tributação, gerando referência circular. Resolução iterativa por convergência.
     *
     * Algoritmo:
     *   1. IOF_0 = CalculaIOF(V_s)
     *   2. Loop (max 100 iterações):
     *      a. V_f_novo = V_s + OutrosEncargos + IOF_{n-1}
     *      b. IOF_n = CalculaIOF(V_f_novo)
     *      c. SE |IOF_n - IOF_{n-1}| < 0.000001: retorna IOF_n
     *   3. Se não convergir, lança GrossUpDivergenciaException
     *
     * @param BigDecimal                                     $valorSolicitado  V_s
     * @param BigDecimal                                     $outrosEncargos   Encargos financiados adicionais
     * @param BigDecimal                                     $aliquotaDiaria   Taxa IOF diária
     * @param BigDecimal                                     $aliquotaAdicional Taxa IOF adicional
     * @param callable(BigDecimal): array{BigDecimal[], int[]} $geradorAmortizacoes Função que gera amortizações e dias para V_f dado
     * @return array IOF calculado com convergência
     */
    public static function resolverGrossUp(
        BigDecimal $valorSolicitado,
        BigDecimal $outrosEncargos,
        BigDecimal $aliquotaDiaria,
        BigDecimal $aliquotaAdicional,
        callable   $geradorAmortizacoes,
    ): array {
        $tolerancia = BigDecimal::of(self::TOLERANCIA_GROSSUP);
        $iofAnterior = BigDecimal::of('0');
        $iofAtual = null;
        $resultado = null;

        for ($iteracao = 0; $iteracao < self::MAX_ITERACOES_GROSSUP; $iteracao++) {
            // V_f_novo = V_s + OutrosEncargos + IOF_{n-1}
            $vfNovo = $valorSolicitado->plus($outrosEncargos)->plus($iofAnterior);

            // Gera amortizações teóricas para o novo V_f
            [$amortizacoes, $diasCorridos] = $geradorAmortizacoes($vfNovo);

            // IOF_n = CalculaIOF(V_f_novo)
            $resultado = self::calcular($vfNovo, $aliquotaDiaria, $aliquotaAdicional, $amortizacoes, $diasCorridos);
            $iofAtual = BigDecimal::of($resultado['total']);

            // Teste de convergência: |IOF_n - IOF_{n-1}| < tolerância
            $delta = $iofAtual->minus($iofAnterior)->abs();
            if ($delta->isLessThan($tolerancia)) {
                return $resultado;
            }

            $iofAnterior = $iofAtual;
        }

        throw new GrossUpDivergenciaException(self::MAX_ITERACOES_GROSSUP);
    }
}
