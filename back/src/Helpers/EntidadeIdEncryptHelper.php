<?php

namespace App\Helpers;

use Lumynus\Framework\LumynusUtilities;

/**
 * EntityIdEncryptorMiddHelper - Centralizador de lógica de encriptação/descriptografia de IDs
 * 
 * Esta classe encapsula toda a lógica de tratamento de IDs criptografados,
 * eliminando a necessidade de espalhar essa lógica por middleware e services.
 * 
 * Padrão: Interceptor/Processor
 */
trait EntidadeIdEncryptHelper
{
    use LumynusUtilities;

    const ENCRYPTION_KEY = 'esc';
    const ID_FIELD = 'id';
    const ID_SUFFIX = '_id';

    /**
     * Encripta IDs em um array de dados
     * - Encripta o campo 'id' principal
     * - Encripta qualquer campo que contenha "_id"
     *
     * @param array $data
     * @return array
     */
    public function encryptIds(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->encryptIds($value);
            } else if ($this->isIdField($key) || $key === self::ID_FIELD) {
                $data[$key] = $this->encryptValue($value);
            }
        }

        return $data;
    }

    /**
     * Descriptografa IDs em um array de dados de forma recursiva.
     * - Descriptografa o campo 'id' principal
     * - Descriptografa qualquer campo que contenha "_id" (ou que passe no isIdField)
     *
     * @param array $data
     * @return array
     */
    public function decryptIds(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            // Se o valor for um array, chamamos a função recursivamente
            // Isso resolve o problema de arrays dentro de arrays (ex: "contatos")
            if (is_array($value)) {
                $data[$key] = $this->decryptIds($value);
            }
            // Se não for um array, verificamos se a chave é um campo de ID
            else {
                // Checa se é o ID principal OU se passa na sua regra de sufixo/prefixo
                if ($key === self::ID_FIELD || $this->isIdField($key)) {
                    // Substitui o valor criptografado pelo descriptografado mantendo a chave intacta
                    $data[$key] = $this->decryptValue($value);
                }
            }
        }

        return $data;
    }

    /**
     * Encripta um valor de ID individual
     *
     * @param mixed $value
     * @return string
     */
    public function encryptValue($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return $this->base64url_encode(
                $this->encryption()->encrypt((string)$value, self::ENCRYPTION_KEY)
            );
        } catch (\Throwable $th) {
            throw new \Exception("Erro ao encriptar ID: {$th->getMessage()}");
        }
    }

    /**
     * Descriptografa um valor de ID individual
     *
     * @param mixed $value
     * @return string
     */
    public function decryptValue($value): string
    {
        if (empty($value)) {
            return '';
        }

        // Se for um ID temporário do front-end, retornamos como está
        if (str_starts_with((string)$value, 'temp-')) {
            return (string)$value;
        }

        try {
            $decoded = $this->base64url_decode((string)$value);
            if (empty($decoded)) {
                return (string)$value;
            }

            return $this->encryption()->decrypt(
                $decoded,
                self::ENCRYPTION_KEY
            );
        } catch (\Throwable $th) {
            throw new \Exception("Erro ao descriptografar ID: {$th->getMessage()}");
        }
    }

    /**
     * Encripta uma coleção de registros (array multi-dimensional)
     * ou um registro único (array associativo).
     *
     * Detecta automaticamente se o array é uma lista de registros
     * (array indexado) ou um registro único (array associativo)
     * e aplica a encriptação adequada.
     *
     * @param array $records
     * @return array
     */
    public function encryptRecords(array $records): array
    {
        if (empty($records)) {
            return $records;
        }

        // Detecta se é array associativo (registro único) ou lista indexada
        if (!array_is_list($records)) {
            return $this->encryptIds($records);
        }

        return array_map(function ($record) {
            if (!is_array($record)) {
                return $record;
            }

            return $this->encryptIds($record);
        }, $records);
    }

    /**
     * Descriptografa uma coleção de registros (array multi-dimensional)
     *
     * @param array $records
     * @return array
     */
    public function decryptRecords(array $records): array
    {
        return array_map(fn($record) => $this->decryptIds($record), $records);
    }

    /**
     * verificar se campo contem id do fipo conforme sufixo
     *
     * @param string $fieldName
     * @return bool
     */
    private function isIdField(string $fieldName): bool
    {
        return str_contains($fieldName, self::ID_SUFFIX);
    }

    public function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
