<?php

namespace App\Services\MotorCalculo\Exceptions;

/**
 * Exceção determinística para falha de convergência do CET.
 *
 * Disparada quando o solver Newton-Raphson excede o limite máximo
 * de iterações sem atingir a tolerância de convergência |x_{k+1} - x_k| ≤ 10^-8.
 */
class UnconvergedCetException extends \RuntimeException
{
    public function __construct(int $iteracoes, string $tolerancia)
    {
        parent::__construct(
            "Exaustão matemática do Solver CET: não convergiu após {$iteracoes} iterações. "
            . "Tolerância exigida: {$tolerancia}. Verifique os fluxos de caixa injetados."
        );
    }
}
