<?php

namespace App\Services\MotorCalculo;

use App\Services\MotorCalculo\Contratos\AmortizacaoStrategyInterface;
use App\Services\MotorCalculo\Enums\SistemaAmortizacao;
use App\Services\MotorCalculo\Enums\TipoPessoa;
use App\Services\MotorCalculo\Enums\PoliticaResiduo;
use App\Services\MotorCalculo\Exceptions\ParametroInvalidoException;
use App\Services\MotorCalculo\Strategies\PriceStrategy;
use App\Services\MotorCalculo\Strategies\SacStrategy;
use App\Services\MotorCalculo\Strategies\SamStrategy;
use App\Services\MotorCalculo\Strategies\AmericanoStrategy;
use App\Services\MotorCalculo\ValueObjects\LinhaFluxo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Motor de Cálculo de Crédito e Financiamento — Rotina Mestra.
 *
 * Responsabilidades:
 *   1. Validação de parâmetros base (§9 — Travas de Bloqueio)
 *   2. Resolução de Strategy de amortização (§3 — Strategy Pattern)
 *   3. Cálculo iterativo de IOF com Gross-Up (§4)
 *   4. Geração do cronograma de parcelas com BigDecimal (§7)
 *   5. Cálculo do CET via Newton-Raphson (§5)
 *   6. Arredondamento cirúrgico somente na emissão final (§6)
 *
 * PADRÃO OURO: Toda manipulação monetária usa Brick\Math\BigDecimal
 * com scale=14 mínimo. Float é utilizado pontualmente apenas em:
 * - Solver CET (Newton-Raphson iterativo, §5.2)
 * - Exponenciação fracionária (pow() nativo, sem suporte BigDecimal)
 *
 * @see Documentação Normativa: Motor de Cálculo de Crédito e Financiamento (SFN)
 */
final class MotorCalculoService
{
    private const SCALE = 14;

    /**
     * Executa a simulação completa de um contrato de crédito.
     *
     * Etapas:
     *   1. Parsing seguro dos parâmetros (string → BigDecimal)
     *   2. Validação com exceções de domínio (§9)
     *   3. Instanciação da Strategy de amortização (§3)
     *   4. Cálculo de IOF com Gross-Up circular (§4)
     *   5. Geração do cronograma de parcelas
     *   6. Cálculo do CET (§5)
     *   7. Serialização com arredondamento monetário (§6)
     *
     * @param array $params Parâmetros da simulação
     * @return array Simulação completa serializada
     */
    public function simular(array $params): array
    {
        // ═══════════════════════════════════════════════════════════
        // 1. PARSING SEGURO — String → BigDecimal (§7)
        // ═══════════════════════════════════════════════════════════
        $valorSolicitadoStr = $this->limparValorMonetario($params['valor_solicitado'] ?? '0');
        $taxaJurosStr       = str_replace(',', '.', (string) ($params['taxa_juros'] ?? '0'));
        $qtdParcelas        = max(1, (int) ($params['quantidade_parcelas'] ?? 0));
        $modeloStr          = $params['modelo_amortizacao'] ?? 'Price';
        $periodoStr         = $params['periodo_amortizacao'] ?? 'Mensal';
        $tipoOperacao       = $params['tipo_operacao'] ?? 'Empréstimo';
        $tipoClienteStr     = $params['tipo_cliente'] ?? 'pf';
        $simplesNacional    = (bool) ($params['simples_nacional'] ?? false);
        $calcularIof        = (bool) ($params['calcular_iof'] ?? false);
        $tipoJurosPU        = $params['tipo_juros_pagamento_unico'] ?? 'compostos';

        // Instanciação OBRIGATÓRIA a partir de string (§7 — proibição float)
        $valorSolicitado = BigDecimal::of($valorSolicitadoStr);
        $taxaPctBd       = BigDecimal::of($taxaJurosStr);
        $taxaPeriodo     = ConversorTaxas::percentualParaDecimal($taxaJurosStr);

        // ═══════════════════════════════════════════════════════════
        // 2. VALIDAÇÃO DE PARÂMETROS BASE (§9)
        // ═══════════════════════════════════════════════════════════
        $this->validarParametrosBase($valorSolicitado, $taxaPeriodo, $qtdParcelas, $periodoStr);

        // Tipo de pessoa
        $tipoPessoa = match ($tipoClienteStr) {
            'pj'  => TipoPessoa::JURIDICA,
            'mei' => TipoPessoa::MEI,
            default => TipoPessoa::FISICA,
        };

        // Datas
        $dataAbertura = $this->parseData($params['data_assinatura'] ?? null, true, 'data_assinatura');
        $dataPrimeiraParcela = $this->parseData($params['data_primeira_parcela'] ?? null);

        // Pagamento Único → forçar n=1
        if ($periodoStr === 'Pagamento único') {
            $qtdParcelas = 1;
        }

        // ═══════════════════════════════════════════════════════════
        // 3. GERAR DATAS DE VENCIMENTO E DIAS CORRIDOS
        // ═══════════════════════════════════════════════════════════
        $datasVencimento = [];
        $diasCorridos = [];

        for ($i = 1; $i <= $qtdParcelas; $i++) {
            $venc = $this->calcularDataVencimento($dataPrimeiraParcela, $i, $periodoStr);
            $datasVencimento[] = $venc;
            // Dias corridos reais para IOF e CET (ACT/365)
            $diasCorridos[] = max(1, (int) $dataAbertura->diff($venc)->days);
        }

        // ═══════════════════════════════════════════════════════════
        // 4. PAGAMENTO ÚNICO — Motor Dedicado (§3.4)
        // ═══════════════════════════════════════════════════════════
        if ($periodoStr === 'Pagamento único') {
            return $this->calcularPagamentoUnico(
                $valorSolicitado, $taxaPeriodo, $tipoJurosPU,
                $dataAbertura, $dataPrimeiraParcela, $datasVencimento,
                $diasCorridos, $calcularIof, $tipoPessoa, $simplesNacional,
                $tipoOperacao
            );
        }

        // ═══════════════════════════════════════════════════════════
        // 5. INSTANCIAR STRATEGY DE AMORTIZAÇÃO (§3)
        // ═══════════════════════════════════════════════════════════
        $strategy = $this->resolverStrategy($modeloStr);

        // ═══════════════════════════════════════════════════════════
        // 6. CÁLCULO DE IOF COM GROSS-UP CIRCULAR (§4.4)
        // ═══════════════════════════════════════════════════════════
        $iofResultado = ['diario' => '0', 'adicional' => '0', 'total' => '0',
                         'taxa_diaria_pct' => '0', 'taxa_adicional_pct' => '0'];
        $valorFinanciado = $valorSolicitado;

        if ($calcularIof) {
            $aliquotas = CalculadoraIOF::getAliquotas($tipoPessoa, $simplesNacional, $valorSolicitado);

            // Gerador de amortizações teóricas para o Gross-Up
            $geradorAmorts = function (BigDecimal $vf) use ($strategy, $taxaPeriodo, $qtdParcelas, $diasCorridos): array {
                $amorts = $strategy->gerarAmortizacoesTeoricas($vf, $taxaPeriodo, $qtdParcelas);
                return [$amorts, $diasCorridos];
            };

            $iofResultado = CalculadoraIOF::resolverGrossUp(
                $valorSolicitado,
                BigDecimal::of('0'), // outrosEncargos
                $aliquotas['diario'],
                $aliquotas['adicional'],
                $geradorAmorts,
            );

            $valorFinanciado = $valorSolicitado->plus(BigDecimal::of($iofResultado['total']));
        }

        // ═══════════════════════════════════════════════════════════
        // 7. GERAÇÃO DO CRONOGRAMA DE PARCELAS (§3.1 — §3.4)
        // ═══════════════════════════════════════════════════════════
        $fluxos = $strategy->gerarFluxo(
            $valorFinanciado, $taxaPeriodo, $qtdParcelas, $datasVencimento, $dataAbertura
        );

        // ═══════════════════════════════════════════════════════════
        // 8. CÁLCULO DO CET (§5 — Res. 4.881/2020)
        // ═══════════════════════════════════════════════════════════
        $fc0 = $valorSolicitado; // Fluxo líquido de entrada (V_s sem IOF)
        $fluxosCET = [];
        foreach ($fluxos as $linha) {
            if ($linha->parcela->isGreaterThan(BigDecimal::of('0'))) {
                $fluxosCET[] = [
                    'valor' => $linha->parcela,
                    'dias'  => $linha->diasCorridosDesdeAbertura,
                ];
            }
        }

        $cet = ['mes' => 0.0, 'ano' => 0.0];
        try {
            // Chute inicial: taxa anual equivalente composta a partir da taxa do período
            $taxaAnualChute = $this->estimarTaxaAnualChute($taxaPeriodo, $periodoStr);
            $cet = SolverCET::calcular($fc0, $fluxosCET, $taxaAnualChute);
        } catch (\Throwable $e) {
            // CET é obrigatório (Res. CMN 4.881/2020) — registrar falha
            error_log('[MotorCalculo] Falha no cálculo do CET: ' . $e->getMessage());
        }

        // ═══════════════════════════════════════════════════════════
        // 9. SERIALIZAÇÃO COM ARREDONDAMENTO MONETÁRIO (§6)
        // ═══════════════════════════════════════════════════════════
        return $this->serializarResultado(
            $fluxos, $valorFinanciado, $valorSolicitado,
            $iofResultado, $cet, $taxaPeriodo, $tipoOperacao
        );
    }

    /**
     * Calcula pagamento único (Bullet / Sistema Americano de parcela única).
     */
    private function calcularPagamentoUnico(
        BigDecimal $valorSolicitado,
        BigDecimal $taxaPeriodo,
        string     $tipoJuros,
        \DateTime  $dataAbertura,
        \DateTime  $dataPrimeiraParcela,
        array      $datasVencimento,
        array      $diasCorridos,
        bool       $calcularIof,
        TipoPessoa $tipoPessoa,
        bool       $simplesNacional,
        string     $tipoOperacao,
    ): array {
        $diasPrazo = max(1, $diasCorridos[0]);
        $um = BigDecimal::of('1');

        // 1) IOF (financiado)
        $iofResultado = ['diario' => '0', 'adicional' => '0', 'total' => '0',
                         'taxa_diaria_pct' => '0', 'taxa_adicional_pct' => '0'];

        if ($calcularIof) {
            $aliquotas = CalculadoraIOF::getAliquotas($tipoPessoa, $simplesNacional, $valorSolicitado);
            $geradorAmorts = function (BigDecimal $vf) use ($diasCorridos): array {
                return [[$vf], $diasCorridos];
            };

            $iofResultado = CalculadoraIOF::resolverGrossUp(
                $valorSolicitado,
                BigDecimal::of('0'),
                $aliquotas['diario'],
                $aliquotas['adicional'],
                $geradorAmorts,
            );
        }

        // 2) V_f = V_s + IOF
        $valorContrato = $valorSolicitado->plus(BigDecimal::of($iofResultado['total']));

        // 3) Juros
        if ($tipoJuros === 'simples') {
            // M = PV × (1 + i × n)  — n em fração de mês
            $n = BigDecimal::of((string) $diasPrazo)->dividedBy('30', self::SCALE, RoundingMode::HalfUp);
            $montante = $valorContrato->multipliedBy(
                $um->plus($taxaPeriodo->multipliedBy($n))
            );
        } else {
            // M = PV × (1 + i_d)^dias — capitalização diária com taxa equivalente composta
            // Taxa equivalente diária: i_d = (1 + i_mensal)^(1/30) - 1
            $taxaDiariaFloat = pow((float) $taxaPeriodo->plus($um)->__toString(), 1.0 / 30.0) - 1.0;
            // Montante composto: PV × (1 + i_d)^dias
            $montanteFloat = (float) $valorContrato->__toString() * pow(1.0 + $taxaDiariaFloat, $diasPrazo);
            $montante = BigDecimal::of(number_format($montanteFloat, self::SCALE, '.', ''));
        }

        $jurosTotal = $montante->minus($valorContrato);
        $venc = $datasVencimento[0];

        $fluxo = new LinhaFluxo(
            numero: 1,
            parcela: $montante->toScale(2, RoundingMode::HalfUp),
            juros: $jurosTotal->toScale(2, RoundingMode::HalfUp),
            amortizacao: $valorContrato->toScale(2, RoundingMode::HalfUp),
            saldoDevedor: BigDecimal::of('0'),
            vencimentoIso: $venc->format('Y-m-d'),
            vencimentoFmt: $venc->format('d/m/Y'),
            diasCorridosDesdeAbertura: $diasCorridos[0],
        );

        // CET
        $cet = ['mes' => 0.0, 'ano' => 0.0];
        try {
            $fluxosCET = [['valor' => $fluxo->parcela, 'dias' => $fluxo->diasCorridosDesdeAbertura]];
            $cet = SolverCET::calcular($valorSolicitado, $fluxosCET, 0.10);
        } catch (\Throwable $e) {
            error_log('[MotorCalculo] Falha no cálculo do CET (pagamento único): ' . $e->getMessage());
        }

        $parcelas = [$fluxo->toArray()];
        return [
            'parcelas'         => $parcelas,
            'total_parcelas'   => (float) $montante->toScale(2, RoundingMode::HalfUp)->__toString(),
            'total_juros'      => (float) $jurosTotal->toScale(2, RoundingMode::HalfUp)->__toString(),
            'valor_financiado' => (float) $valorContrato->toScale(2, RoundingMode::HalfUp)->__toString(),
            'iof'              => $this->serializarIOF($iofResultado),
            'cet'              => $cet,
            'taxa_periodo'     => (float) ConversorTaxas::decimalParaPercentual($taxaPeriodo)->__toString(),
            'tipo_operacao'    => $tipoOperacao,
            'tipo_juros'       => $tipoJuros,
        ];
    }

    /**
     * Valida parâmetros base conforme §9 — Travas de Bloqueio.
     */
    private function validarParametrosBase(
        BigDecimal $valorSolicitado,
        BigDecimal $taxaPeriodo,
        int        $qtdParcelas,
        string     $periodo,
    ): void {
        // V_f ≤ 0 → Bloqueio
        if ($valorSolicitado->isNegativeOrZero()) {
            throw new ParametroInvalidoException('valor_solicitado', 'Principal zerado ou negativo.');
        }

        // n ≤ 0 → Bloqueio (exceto pagamento único que é forçado a 1)
        if ($qtdParcelas <= 0 && $periodo !== 'Pagamento único') {
            throw new ParametroInvalidoException('quantidade_parcelas', 'Prazo nulo ou negativo.');
        }

        // i < 0 → Bloqueio (taxa negativa = remuneração predatória absurda)
        if ($taxaPeriodo->isNegative()) {
            throw new ParametroInvalidoException('taxa_juros', 'Taxa de remuneração negativa.');
        }
    }

    /**
     * Resolve a Strategy concreta de amortização pelo nome do modelo.
     */
    private function resolverStrategy(string $modelo): AmortizacaoStrategyInterface
    {
        return match ($modelo) {
            'Price', 'default' => new PriceStrategy(PoliticaResiduo::AJUSTE_ULTIMA_PARCELA),
            'SAC'              => new SacStrategy(),
            'SAM'              => new SamStrategy(),
            'Sistema americano' => new AmericanoStrategy(bulletPuro: false),
            'Bullet'           => new AmericanoStrategy(bulletPuro: true),
            default            => new PriceStrategy(PoliticaResiduo::AJUSTE_ULTIMA_PARCELA),
        };
    }

    /**
     * Serializa o resultado completo para o formato de resposta.
     *
     * Arredondamento monetário (2 casas, HALF_UP) incide SOMENTE aqui (§6).
     */
    private function serializarResultado(
        array      $fluxos,
        BigDecimal $valorFinanciado,
        BigDecimal $valorSolicitado,
        array      $iofResultado,
        array      $cet,
        BigDecimal $taxaPeriodo,
        string     $tipoOperacao,
    ): array {
        $parcelas = [];
        $totalParcelas = BigDecimal::of('0');
        $totalJuros = BigDecimal::of('0');
        $totalAmortizacao = BigDecimal::of('0');

        foreach ($fluxos as $linha) {
            /** @var LinhaFluxo $linha */
            $parcelas[] = $linha->toArray();
            $totalParcelas = $totalParcelas->plus($linha->parcela);
            $totalJuros = $totalJuros->plus($linha->juros);
            $totalAmortizacao = $totalAmortizacao->plus($linha->amortizacao);
        }

        return [
            'parcelas'           => $parcelas,
            'total_parcelas'     => (float) $totalParcelas->toScale(2, RoundingMode::HalfUp)->__toString(),
            'total_juros'        => (float) $totalJuros->toScale(2, RoundingMode::HalfUp)->__toString(),
            'total_amortizacao'  => (float) $totalAmortizacao->toScale(2, RoundingMode::HalfUp)->__toString(),
            'valor_financiado'   => (float) $valorFinanciado->toScale(2, RoundingMode::HalfUp)->__toString(),
            'valor_solicitado'   => (float) $valorSolicitado->toScale(2, RoundingMode::HalfUp)->__toString(),
            'iof'                => $this->serializarIOF($iofResultado),
            'cet'                => $cet,
            'taxa_periodo'       => (float) ConversorTaxas::decimalParaPercentual($taxaPeriodo)->__toString(),
            'tipo_operacao'      => $tipoOperacao,
        ];
    }

    /**
     * Converte IOF de BigDecimal/string para o formato de saída numérico.
     */
    private function serializarIOF(array $iof): array
    {
        return [
            'diario'             => (float) ($iof['diario'] ?? 0),
            'adicional'          => (float) ($iof['adicional'] ?? 0),
            'total'              => (float) ($iof['total'] ?? 0),
            'taxa_diaria_pct'    => (float) ($iof['taxa_diaria_pct'] ?? 0),
            'taxa_adicional_pct' => (float) ($iof['taxa_adicional_pct'] ?? 0),
        ];
    }

    /**
     * Estima o chute inicial da taxa anual para o Newton-Raphson do CET.
     */
    private function estimarTaxaAnualChute(BigDecimal $taxaPeriodo, string $periodo): float
    {
        $taxaFloat = (float) $taxaPeriodo->__toString();
        $periodosAno = match ($periodo) {
            'Diário'  => 360,
            'Semanal' => 52,
            default   => 12,
        };
        // Equivalência composta: (1 + i_periodo)^n - 1
        return pow(1.0 + $taxaFloat, $periodosAno) - 1.0;
    }

    /**
     * Calcula data de vencimento da parcela N.
     */
    private function calcularDataVencimento(\DateTime $dataBase, int $numeroParcela, string $periodo): \DateTime
    {
        $data = clone $dataBase;
        $offset = $numeroParcela - 1;

        switch ($periodo) {
            case 'Semanal':
                $data->modify("+{$offset} weeks");
                break;
            case 'Diário':
                $data->modify("+{$offset} days");
                break;
            case 'Pagamento único':
                // Data base é a data da parcela única
                break;
            default: // Mensal
                $data->modify("+{$offset} months");
                break;
        }

        return $data;
    }

    /**
     * Limpa e normaliza valor monetário brasileiro para string numérica.
     *
     * Retorna string pura (nunca float) para alimentar BigDecimal::of().
     */
    private function limparValorMonetario(mixed $valor): string
    {
        if (empty($valor)) return '0';
        if (is_numeric($valor)) return (string) $valor;

        $val = preg_replace('/[R$\s]/', '', (string) $valor);
        $val = str_replace('.', '', $val);   // Remove separador de milhar
        $val = str_replace(',', '.', $val);  // Troca vírgula por ponto decimal

        return is_numeric($val) ? $val : '0';
    }

    /**
     * Parse de data com suporte a Y-m-d e d/m/Y.
     *
     * @param mixed $date     Valor da data
     * @param bool  $obrigatorio Se true, lança exceção para data vazia/inválida
     * @param string $campo   Nome do campo (para mensagem de erro)
     */
    private function parseData(mixed $date, bool $obrigatorio = false, string $campo = 'data'): \DateTime
    {
        if ($date instanceof \DateTime) return $date;
        if (empty($date)) {
            if ($obrigatorio) {
                throw new ParametroInvalidoException($campo, 'Data obrigatória não informada.');
            }
            return new \DateTime();
        }

        $dateStr = substr((string) $date, 0, 10);

        if (str_contains($dateStr, '-')) {
            $dt = \DateTime::createFromFormat('Y-m-d', $dateStr);
        } elseif (str_contains($dateStr, '/')) {
            $dt = \DateTime::createFromFormat('d/m/Y', $dateStr);
        } else {
            $dt = null;
        }

        if (!$dt) {
            try {
                $dt = new \DateTime($dateStr);
            } catch (\Exception $e) {
                if ($obrigatorio) {
                    throw new ParametroInvalidoException($campo, "Data inválida: '{$dateStr}'.");
                }
                $dt = new \DateTime();
            }
        }

        $dt->setTime(0, 0, 0);
        return $dt;
    }
}
