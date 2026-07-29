<?php

namespace App\Services\MotorCalculo\Enums;

/**
 * Tipo de pessoa (natureza jurídica do mutuário).
 * Determina a matriz de alíquotas IOF conforme Decretos 12.466/2025 e 12.467/2025.
 */
enum TipoPessoa: string
{
    case FISICA = 'pf';
    case JURIDICA = 'pj';
    case MEI = 'mei';
}
