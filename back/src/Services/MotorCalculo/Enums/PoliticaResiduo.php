<?php

namespace App\Services\MotorCalculo\Enums;

/**
 * Política de tratamento de resíduo na Tabela PRICE.
 *
 * O Price gera infinitas casas decimais. A política define como
 * absorver frações de centavos acumuladas.
 */
enum PoliticaResiduo: string
{
    /** Ajuste na Última Parcela (Prática de mercado consolidada): A_n = SD_{n-1} */
    case AJUSTE_ULTIMA_PARCELA = 'ajuste_ultima_parcela';

    /** Largest Remainder Method: distribui frações iterativamente */
    case LARGEST_REMAINDER = 'largest_remainder';
}
