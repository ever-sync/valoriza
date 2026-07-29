<?php

namespace App\Services\MotorCalculo\Exceptions;

/**
 * Exceção para falha de convergência no Gross-Up iterativo do IOF.
 *
 * Disparada quando a iteração circular IOF → Vf → IOF não converge
 * dentro do limite configurável de iterações (padrão: 100).
 */
class GrossUpDivergenciaException extends \RuntimeException
{
    public function __construct(int $iteracoes)
    {
        parent::__construct(
            "Gross-Up do IOF não convergiu após {$iteracoes} iterações. "
            . "Possível referência circular irrecuperável nos parâmetros tributários."
        );
    }
}
