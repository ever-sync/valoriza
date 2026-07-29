<?php

namespace App\Services\MotorCalculo\ValueObjects;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Valor Object que encapsula uma linha do fluxo de amortização.
 *
 * Todas as propriedades monetárias são BigDecimal com precisão interna scale=14.
 * O arredondamento monetário para 2 casas ocorre SOMENTE na emissão final.
 */
final class LinhaFluxo
{
    public function __construct(
        public readonly int        $numero,
        public readonly BigDecimal $parcela,
        public readonly BigDecimal $juros,
        public readonly BigDecimal $amortizacao,
        public readonly BigDecimal $saldoDevedor,
        public readonly string     $vencimentoIso,
        public readonly string     $vencimentoFmt,
        public readonly int        $diasCorridosDesdeAbertura,
    ) {}

    /**
     * Serializa para array com arredondamento monetário (2 casas — HALF_UP).
     * Esse é o ÚNICO ponto de arredondamento conforme §6 da normativa.
     */
    public function toArray(): array
    {
        return [
            'num'            => $this->numero,
            'parcela'        => (float) $this->parcela->toScale(2, RoundingMode::HalfUp)->__toString(),
            'juros'          => (float) $this->juros->toScale(2, RoundingMode::HalfUp)->__toString(),
            'amortizacao'    => (float) $this->amortizacao->toScale(2, RoundingMode::HalfUp)->__toString(),
            'saldo'          => (float) $this->saldoDevedor->toScale(2, RoundingMode::HalfUp)->__toString(),
            'vencimento'     => $this->vencimentoFmt,
            'vencimento_iso' => $this->vencimentoIso,
        ];
    }
}
