<?php

namespace App\Services\MotorCalculo\Enums;

/**
 * Periodicidade de amortização do contrato de crédito.
 */
enum PeriodoAmortizacao: string
{
    case DIARIO = 'Diário';
    case SEMANAL = 'Semanal';
    case MENSAL = 'Mensal';
    case PAGAMENTO_UNICO = 'Pagamento único';

    /**
     * Retorna o número de períodos em 1 ano (base 360 comercial).
     */
    public function periodosAno(): int
    {
        return match ($this) {
            self::DIARIO => 360,
            self::SEMANAL => 52,
            self::MENSAL => 12,
            self::PAGAMENTO_UNICO => 1,
        };
    }

    /**
     * Retorna o número de dias comerciais por período.
     */
    public function diasPorPeriodo(): int
    {
        return match ($this) {
            self::DIARIO => 1,
            self::SEMANAL => 7,
            self::MENSAL => 30,
            self::PAGAMENTO_UNICO => 360,
        };
    }
}
