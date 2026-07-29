<?php

/**
 * Testes Homologatórios — §11 da Documentação Normativa.
 *
 * Cenário A: Happy Path IOF Limítrofe (PJ, 500 dias, Gross-Up, barreira 365)
 * Cenário B: Happy Path Fluxo Tabela PRICE (PF, R$300k, 5 meses, 4% a.m.)
 *
 * Execução: php tests/MotorCalculoTest.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MotorCalculo\MotorCalculoService;
use App\Services\MotorCalculo\CalculadoraIOF;
use App\Services\MotorCalculo\ConversorTaxas;
use App\Services\MotorCalculo\SolverCET;
use App\Services\MotorCalculo\Strategies\PriceStrategy;
use App\Services\MotorCalculo\Enums\TipoPessoa;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

$passados = 0;
$falhas = 0;
$total = 0;

function assertTest(string $nome, bool $condicao, string $mensagem = ''): void
{
    global $passados, $falhas, $total;
    $total++;
    if ($condicao) {
        $passados++;
        echo "  ✅ PASS: {$nome}\n";
    } else {
        $falhas++;
        echo "  ❌ FAIL: {$nome}" . ($mensagem ? " — {$mensagem}" : "") . "\n";
    }
}

function assertEqualsFloat(string $nome, float $esperado, float $atual, float $tolerancia = 0.01): void
{
    $diff = abs($esperado - $atual);
    assertTest(
        $nome,
        $diff <= $tolerancia,
        "Esperado: {$esperado}, Obtido: {$atual}, Diff: {$diff}"
    );
}

// ═══════════════════════════════════════════════════════════
// CENÁRIO A: IOF LIMÍTROFE (PJ, 500 dias, Gross-Up)
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "CENÁRIO A: IOF LIMÍTROFE — PJ 500 dias Gross-Up\n";
echo "══════════════════════════════════════════════════\n";

// Contexto: PJ Normal, R$10.000, Pagamento Único 500 dias, IOF Financiado
echo "\nA.1. Alíquotas IOF para PJ Normal\n";
$aliquotasPJ = CalculadoraIOF::getAliquotas(TipoPessoa::JURIDICA, false, BigDecimal::of('10000'));
assertTest(
    'Alíquota Adicional PJ = 0.0095 (0,95%)',
    $aliquotasPJ['adicional']->isEqualTo(BigDecimal::of('0.0095')),
    "Obtido: " . $aliquotasPJ['adicional']->__toString()
);
assertTest(
    'Alíquota Diária PJ = 0.000082 (0,0082%)',
    $aliquotasPJ['diario']->isEqualTo(BigDecimal::of('0.000082')),
    "Obtido: " . $aliquotasPJ['diario']->__toString()
);

echo "\nA.2. Barreira 365 dias\n";
// IOF Diário com 500 dias → deve usar min(500, 365) = 365
$iofDireto = CalculadoraIOF::calcular(
    BigDecimal::of('10000'),
    BigDecimal::of('0.000082'),
    BigDecimal::of('0.0095'),
    [BigDecimal::of('10000')],
    [500],  // 500 dias → barreira 365
);

// IOF Diário deveria ser: 10000 × 365 × 0.000082 = 299.30
$iofDiarioEsperado = 10000 * 365 * 0.000082;
assertEqualsFloat(
    "IOF Diário com barreira 365 (500→365)",
    round($iofDiarioEsperado, 2),
    (float) $iofDireto['diario'],
    0.01
);

// IOF Adicional: 10000 × 0.0095 = 95.00
assertEqualsFloat(
    "IOF Adicional PJ = R$95.00",
    95.00,
    (float) $iofDireto['adicional'],
    0.01
);

echo "\nA.3. Gross-Up IOF (Convergência)\n";
$iofGrossUp = CalculadoraIOF::resolverGrossUp(
    BigDecimal::of('10000'),
    BigDecimal::of('0'),
    BigDecimal::of('0.000082'),
    BigDecimal::of('0.0095'),
    function (BigDecimal $vf) {
        return [[$vf], [500]];
    },
);
assertTest(
    "Gross-Up converge (IOF total > 0)",
    (float) $iofGrossUp['total'] > 0,
    "IOF total = " . $iofGrossUp['total']
);
// IOF após Gross-Up deve ser maior que IOF sem Gross-Up
assertTest(
    "Gross-Up IOF > IOF direto (referência circular eleva a base)",
    (float) $iofGrossUp['total'] > (float) $iofDireto['total'],
    "Gross-Up: " . $iofGrossUp['total'] . " vs Direto: " . $iofDireto['total']
);

// ═══════════════════════════════════════════════════════════
// CENÁRIO B: TABELA PRICE — PF R$300.000, 5 meses, 4% a.m.
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "CENÁRIO B: TABELA PRICE — R\$300.000 / 5 meses / 4% a.m.\n";
echo "══════════════════════════════════════════════════\n";

echo "\nB.1. Fator K (Coeficiente PRICE)\n";
// K = i·(1+i)^n / ((1+i)^n - 1)
// K = 0.04 × 1.04^5 / (1.04^5 - 1)
$um = BigDecimal::of('1');
$i = BigDecimal::of('0.04');
$n = 5;
$fatorExp = $i->plus($um)->power($n); // (1.04)^5
$kNum = $i->multipliedBy($fatorExp);
$kDen = $fatorExp->minus($um);
$k = $kNum->dividedBy($kDen, 14, RoundingMode::HalfUp);

echo "  K calculado (14 casas): " . $k->__toString() . "\n";
// Assert K contém 0.2246271 nos primeiros 7 dígitos significativos
$kStr = $k->__toString();
assertTest(
    'K irredutível contém 0.2246271',
    str_contains($kStr, '0.2246271'),
    "K = {$kStr}"
);

echo "\nB.2. Parcela Fixa\n";
$vf = BigDecimal::of('300000');
$parcelaFixa = $vf->multipliedBy($k)->toScale(2, RoundingMode::HalfUp);
echo "  Parcela calculada: R\$ " . $parcelaFixa->__toString() . "\n";
assertTest(
    'Parcela = R$67.388,13',
    $parcelaFixa->isEqualTo(BigDecimal::of('67388.13')),
    "Parcela = " . $parcelaFixa->__toString()
);

echo "\nB.3. Fluxo PRICE completo\n";
$strategy = new PriceStrategy();
$dataBase = new DateTime('2026-01-15');
$datas = [];
for ($m = 1; $m <= 5; $m++) {
    $d = clone $dataBase;
    $d->modify("+{$m} months");
    $datas[] = $d;
}

$fluxos = $strategy->gerarFluxo($vf, $i, 5, $datas, $dataBase);

// Verificar saldo final = 0.00
$ultimoFluxo = end($fluxos);
assertTest(
    'Saldo Devedor final = 0.00',
    $ultimoFluxo->saldoDevedor->isEqualTo(BigDecimal::of('0')),
    "Saldo final = " . $ultimoFluxo->saldoDevedor->__toString()
);

// Verificar Σ amortizações = V_f = 300.000,00
$totalAmort = BigDecimal::of('0');
foreach ($fluxos as $f) {
    $totalAmort = $totalAmort->plus($f->amortizacao);
}
$totalAmortArredondado = $totalAmort->toScale(2, RoundingMode::HalfUp);
echo "  Total Amortização: R\$ " . $totalAmortArredondado->__toString() . "\n";
assertTest(
    'Σ Amortização = 300.000,00',
    $totalAmortArredondado->isEqualTo(BigDecimal::of('300000.00')),
    "Σ Amort = " . $totalAmortArredondado->__toString()
);

// Imprimir cronograma
echo "\n  Cronograma detalhado:\n";
echo "  ┌──────┬─────────────┬─────────────┬─────────────┬─────────────┐\n";
echo "  │ Mês  │   Parcela   │    Juros    │ Amortização │    Saldo    │\n";
echo "  ├──────┼─────────────┼─────────────┼─────────────┼─────────────┤\n";
foreach ($fluxos as $f) {
    $arr = $f->toArray();
    printf(
        "  │  %d   │ %11s │ %11s │ %11s │ %11s │\n",
        $arr['num'],
        number_format($arr['parcela'], 2, ',', '.'),
        number_format($arr['juros'], 2, ',', '.'),
        number_format($arr['amortizacao'], 2, ',', '.'),
        number_format($arr['saldo'], 2, ',', '.')
    );
}
echo "  └──────┴─────────────┴─────────────┴─────────────┴─────────────┘\n";

// ═══════════════════════════════════════════════════════════
// CENÁRIO C: INTEGRAÇÃO COMPLETA (Motor de Simulação)
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "CENÁRIO C: INTEGRAÇÃO — Motor Completo Price + IOF + CET\n";
echo "══════════════════════════════════════════════════\n";

$motor = new MotorCalculoService();
$resultado = $motor->simular([
    'valor_solicitado'       => '300000',
    'taxa_juros'             => '4',
    'quantidade_parcelas'    => 5,
    'modelo_amortizacao'     => 'Price',
    'periodo_amortizacao'    => 'Mensal',
    'tipo_operacao'          => 'Empréstimo',
    'tipo_cliente'           => 'pf',
    'simples_nacional'       => 0,
    'calcular_iof'           => 0,
    'data_assinatura'        => '2026-01-15',
    'data_primeira_parcela'  => '2026-02-15',
]);

echo "\n  Resultado do Motor:\n";
echo "  Valor Financiado: R\$ " . number_format($resultado['valor_financiado'], 2, ',', '.') . "\n";
echo "  Total Parcelas:   R\$ " . number_format($resultado['total_parcelas'], 2, ',', '.') . "\n";
echo "  Total Juros:      R\$ " . number_format($resultado['total_juros'], 2, ',', '.') . "\n";
echo "  CET Mensal:       " . $resultado['cet']['mes'] . "% a.m.\n";
echo "  CET Anual:        " . $resultado['cet']['ano'] . "% a.a.\n";

assertEqualsFloat(
    'Motor: Valor Financiado = 300.000,00 (sem IOF)',
    300000.00,
    $resultado['valor_financiado']
);

// Total Amortização = V_f
assertEqualsFloat(
    'Motor: Σ Amortização = 300.000,00',
    300000.00,
    $resultado['total_amortizacao']
);

// ═══════════════════════════════════════════════════════════
// CENÁRIO D: EDGE CASES — Validação de Exceções (§9)
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "CENÁRIO D: EDGE CASES — Exceções de Domínio\n";
echo "══════════════════════════════════════════════════\n";

// Teste: Principal negativo
try {
    $motor->simular(['valor_solicitado' => '-1000', 'taxa_juros' => '2', 'quantidade_parcelas' => 12,
        'modelo_amortizacao' => 'Price', 'periodo_amortizacao' => 'Mensal',
        'data_assinatura' => '2026-01-01', 'data_primeira_parcela' => '2026-02-01']);
    assertTest('Principal negativo → Exceção', false, 'Deveria lançar exceção');
} catch (\DomainException $e) {
    assertTest('Principal negativo → ParametroInvalidoException', true);
}

// Teste: Taxa negativa
try {
    $motor->simular(['valor_solicitado' => '10000', 'taxa_juros' => '-5', 'quantidade_parcelas' => 12,
        'modelo_amortizacao' => 'Price', 'periodo_amortizacao' => 'Mensal',
        'data_assinatura' => '2026-01-01', 'data_primeira_parcela' => '2026-02-01']);
    assertTest('Taxa negativa → Exceção', false, 'Deveria lançar exceção');
} catch (\DomainException $e) {
    assertTest('Taxa negativa → ParametroInvalidoException', true);
}

// Teste: Taxa zero (fallback P = V/n)
$resultadoZero = $motor->simular([
    'valor_solicitado' => '12000', 'taxa_juros' => '0', 'quantidade_parcelas' => 12,
    'modelo_amortizacao' => 'Price', 'periodo_amortizacao' => 'Mensal',
    'data_assinatura' => '2026-01-01', 'data_primeira_parcela' => '2026-02-01']);
assertEqualsFloat(
    'Taxa 0%: Parcela = V/n = R$1.000,00',
    1000.00,
    $resultadoZero['parcelas'][0]['parcela']
);

// ═══════════════════════════════════════════════════════════
// CENÁRIO E: SAC
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "CENÁRIO E: SAC — R\$100.000 / 4 meses / 2% a.m.\n";
echo "══════════════════════════════════════════════════\n";

$resultadoSac = $motor->simular([
    'valor_solicitado'       => '100000',
    'taxa_juros'             => '2',
    'quantidade_parcelas'    => 4,
    'modelo_amortizacao'     => 'SAC',
    'periodo_amortizacao'    => 'Mensal',
    'tipo_operacao'          => 'Empréstimo',
    'tipo_cliente'           => 'pf',
    'calcular_iof'           => 0,
    'data_assinatura'        => '2026-01-15',
    'data_primeira_parcela'  => '2026-02-15',
]);

// SAC: A = 100.000/4 = 25.000
assertEqualsFloat(
    'SAC: Amortização fixa ≈ R$25.000,00',
    25000.00,
    $resultadoSac['parcelas'][0]['amortizacao']
);

// SAC: P1 = 25.000 + (100.000 × 0.02) = 25.000 + 2.000 = 27.000
assertEqualsFloat(
    'SAC: Parcela 1 = R$27.000,00',
    27000.00,
    $resultadoSac['parcelas'][0]['parcela']
);

// SAC: Σ Amortização = V_f
assertEqualsFloat(
    'SAC: Σ Amortização = 100.000,00',
    100000.00,
    $resultadoSac['total_amortizacao']
);

// ═══════════════════════════════════════════════════════════
// CENÁRIO F: CONVERSOR DE TAXAS
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "CENÁRIO F: Conversor de Taxas (Equivalência Composta)\n";
echo "══════════════════════════════════════════════════\n";

// 1% a.m. → anual = (1.01)^12 - 1 = 12.6825...%
$taxaMensal = BigDecimal::of('0.01');
$taxaAnual = ConversorTaxas::equivalenteComposta($taxaMensal, 1, 12);
$taxaAnualPct = (float) $taxaAnual->multipliedBy('100')->__toString();
assertEqualsFloat(
    'Equivalência: 1% a.m. → 12.68% a.a.',
    12.6825,
    $taxaAnualPct,
    0.01
);

// Proibição: NÃO dividir efetiva anual por 12
// 12% a.a. efetiva → mensal ≠ 1%
$taxaAnual12 = BigDecimal::of('0.12');
$taxaMensalEq = ConversorTaxas::equivalenteComposta($taxaAnual12, 12, 1);
$taxaMensalPct = (float) $taxaMensalEq->multipliedBy('100')->__toString();
assertTest(
    'Equivalência: 12% a.a. → mensal ≠ 1% (proibição §2)',
    abs($taxaMensalPct - 1.0) > 0.01,
    "Obtido: {$taxaMensalPct}% (deveria ser ~0.949%)"
);

// ═══════════════════════════════════════════════════════════
// RESULTADO FINAL
// ═══════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════════════\n";
echo "RESULTADO: {$passados}/{$total} testes passaram";
if ($falhas > 0) {
    echo " ({$falhas} falhas)";
}
echo "\n══════════════════════════════════════════════════\n\n";

exit($falhas > 0 ? 1 : 0);
