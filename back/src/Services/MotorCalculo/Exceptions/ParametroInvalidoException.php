<?php

namespace App\Services\MotorCalculo\Exceptions;

/**
 * Exceção de domínio para parâmetros base inválidos.
 *
 * Disparada quando:
 * - Principal ≤ 0
 * - Prazo ≤ 0
 * - Taxa < 0
 */
class ParametroInvalidoException extends \DomainException
{
    public function __construct(string $parametro, string $motivo)
    {
        parent::__construct("Parâmetro inválido [{$parametro}]: {$motivo}");
    }
}
