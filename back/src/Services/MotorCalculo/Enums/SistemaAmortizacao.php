<?php

namespace App\Services\MotorCalculo\Enums;

/**
 * Sistemas de amortização suportados pelo motor de cálculo.
 *
 * Cada valor representa uma Strategy exclusiva de mutação do Saldo Devedor,
 * conforme §3 da documentação normativa.
 */
enum SistemaAmortizacao: string
{
    /** Tabela PRICE (Sistema Francês) — parcelas constantes */
    case PRICE = 'Price';

    /** SAC (Sistema de Amortização Constante) — amortização fixa */
    case SAC = 'SAC';

    /** SAM (Sistema de Amortização Misto) — média aritmética Price + SAC */
    case SAM = 'SAM';

    /** Sistema Americano — juros periódicos, principal no vencimento */
    case AMERICANO = 'Sistema americano';

    /** Bullet Puro — capitalização composta sem pagamentos intermediários */
    case BULLET = 'Bullet';
}
