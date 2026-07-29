<?php

namespace App\Exceptions;

use Exception;
use App\Exceptions\MensagemException;

/**
 * SystemException
 * 
 * Classe personalizada para tratamento de exceções com análise automática
 * de mensagens SQL via trait MensagemException.
 */
class SystemException extends Exception
{
    use MensagemException;

    private string $mensagemDetalhada = '';
    private bool $autoAnalisar = true;
    public int $statusCode = 400;

    /**
     * @param string $message Mensagem bruta da exceção
     * @param int $code Código interno da exceção (SQL error code ou 0)
     * @param bool $autoAnalisar Define se deve traduzir a mensagem automaticamente
     * @param int $statusCode Código de status HTTP (padrão 400)
     */
    public function __construct(string $message = "", int $code = 0, bool $autoAnalisar = true, int $statusCode = 400)
    {
        $this->statusCode = $statusCode;
        $this->autoAnalisar = $autoAnalisar;
        $this->mensagemDetalhada = $message;

        // Se for erro de duplicidade SQL, ajustamos o status para 409
        if ($autoAnalisar && str_contains(strtolower($message), 'duplicate')) {
            $this->statusCode = 409;
        }

        parent::__construct($message, $code);
    }

    /**
     * Retorna a mensagem analisada e traduzida
     * 
     * @param string $humanName Nome amigável do registro (ex: "um curso", "o usuário")
     * @return string
     */
    public function all(string $humanName = 'um registro'): string
    {
        if ($this->autoAnalisar === false) {
            return $this->mensagemDetalhada;
        }

        return $this->mensagemDetalhada = $this->getMensagem($humanName, $this->mensagemDetalhada);
    }

    /**
     * Atalho para obter o status code (compatibilidade com controllers)
     */
    public function getHttpStatus(): int
    {
        return $this->statusCode;
    }
}
