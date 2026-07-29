<?php

namespace App\Services\MotorCalculo\Enums;

/**
 * Convenção de base temporal para contagem de dias.
 * Configurável no módulo atuarial conforme §2 da documentação normativa.
 */
enum ConvencaoTemporal: string
{
    /** ACT/365: Dias corridos reais sobre base fixa de 365. Obrigatório para CET (BACEN). */
    case ACT_365 = 'ACT/365';

    /** 30/360: Mês comercial — todos os meses com 30 dias. */
    case TRINTA_360 = '30/360';
}
