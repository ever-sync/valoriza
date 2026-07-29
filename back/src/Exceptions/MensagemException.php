<?php

namespace App\Exceptions;

trait MensagemException
{

    /**
     * Array de mensagens de erro mapeadas
     */
    private array $mensagens = [
        'sql::duplicate' => 'Já existe $1 $2 $3 cadastrado no sistema.',
        'sql::cannot delete or update a parent row' => 'Não é possível excluir $1 pois existem registros relacionados a ele.',
        'sql::cannot add or update a child row' => 'Não foi possível salvar $1 pois o registro relacionado $2 não existe.',
        'sql::a foreign key constraint fails' => 'Não foi possível salvar $1 pois o registro relacionado $2 não existe.',
        'sql::data truncated for column' => 'O valor fornecido para o campo $2 do(a) $1 é inválido.',
        'sql::unknown column' => 'Não existe o campo $2'
    ];

    /**
     * Mapeamento das funções de formatação de mensagens
     */
    private array $chamadasFuncoes = [
        'sql' => 'getSqlMensagem',
        'default' => 'getDefault'
    ];

    /**
     * Motor de busca e formatação de mensagens de erro
     */
    public function getMensagem(string $nomeClasse = 'registro', string $mensagem = '')
    {
        $funcao = 'default';
        $valorParaSubstituir = '';
        $mensagemLower = strtolower($mensagem);


        foreach ($this->mensagens as $key => $msg) {
            $desmontar = explode('::', $key);
            if (str_contains($mensagemLower, strtolower($desmontar[1]))) {
                $funcao = $desmontar[0] ?? 'default';
                $valorParaSubstituir = $msg;
                break;
            }
        }

        if (!isset($this->chamadasFuncoes[$funcao])) {
            return 'Ocorreu um erro inesperado.';
        }

        return $this->{$this->chamadasFuncoes[$funcao]}($nomeClasse, $mensagem, $valorParaSubstituir);
    }

    /**
     * /////////////////////////////////////
     * 
     *   METODOS DE FORMATAÇÃO DE MENSAGENS
     * 
     * ////////////////////////////////////
     */

    private function getSqlMensagem(
        string $nameClass = 'registro',
        string $sqlMessage = '',
        string $template = ''
    ): string {

        $campo = '';
        $valor = '';

        // DUPLICATE ENTRY
        if (preg_match("/duplicate entry '(.+?)' for key '(.+?)'/i", $sqlMessage, $m)) {
            $valor = $m[1];
            $campo = str_contains($m[2], '.')
                ? explode('.', $m[2])[1]
                : $m[2];
        }

        // DATA TRUNCATED
        if (preg_match("/data truncated for column '(.+?)'/i", $sqlMessage, $m)) {
            $campo = $m[1];
        }

        // UNKNOWN COLUMN
        if (preg_match("/unknown column '(.+?)'/i", $sqlMessage, $m)) {
            $campo = $m[1];
        }

        // FOREIGN KEY CONSTRAINT FAILS
        if (preg_match("/FOREIGN KEY \(`(.+?)`\)/i", $sqlMessage, $m)) {
            $campo = $m[1];
        }

        return str_replace(
            ['$1', '$2', '$3'],
            [$nameClass, $campo, $valor],
            $template
        );
    }

    private function getDefault(
        string $nameClass = 'registro',
        string $message = '',
        string $template = ''
    ): string {

        return $message;
    }
}
