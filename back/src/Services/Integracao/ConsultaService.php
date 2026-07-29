<?php

namespace App\Services\Integracao;

use App\Services\BaseService;
use App\Exceptions\SystemException;

/**
 * Serviço de consultas externas (CEP, CNPJ).
 */
class ConsultaService extends BaseService
{
    /**
     * Consulta dados de um CEP via ViaCEP.
     * 
     * @param string $cep
     * @return array
     */
    public function consultarCEP(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            throw new SystemException('CEP inválido. Deve conter 8 dígitos.', 422);
        }

        $url = "https://viacep.com.br/ws/{$cep}/json/";
        $response = @file_get_contents($url);

        if (!$response) {
            throw new SystemException('Não foi possível conectar ao serviço de CEP.', 502);
        }

        $data = json_decode($response, true);

        if (isset($data['erro'])) {
            throw new SystemException('CEP não encontrado.', 404);
        }

        return [
            'cep'        => $data['cep'] ?? '',
            'endereco'   => $data['logradouro'] ?? '',
            'bairro'     => $data['bairro'] ?? '',
            'cidade'     => $data['localidade'] ?? '',
            'estado'     => $data['uf'] ?? '',
            'ibge'       => $data['ibge'] ?? '',
            'complemento'=> $data['complemento'] ?? '',
        ];
    }

    /**
     * Consulta dados de um CNPJ via CNPJ.ws (API Pública).
     * 
     * @param string $cnpj
     * @return array
     */
    public function consultarCNPJ(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            throw new SystemException('CNPJ inválido. Deve conter 14 dígitos.', 422);
        }

        $url = "https://publica.cnpj.ws/cnpj/{$cnpj}";
        
        // CNPJ.ws recomenda usar User-Agent
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: LumaApp/1.0\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if (!$response) {
            // Verifica se é erro 429 (Too Many Requests) ou 404
            $status = $http_response_header[0] ?? '';
            if (str_contains($status, '429')) {
                throw new SystemException('Limite de consultas atingido. Tente novamente em instantes.', 429);
            }
            if (str_contains($status, '404')) {
                throw new SystemException('CNPJ não encontrado.', 404);
            }
            throw new SystemException('Não foi possível conectar ao serviço de CNPJ.', 502);
        }

        $data = json_decode($response, true);

        $resultado = [
            'cnpj'          => $data['cnpj'] ?? '',
            'razao_social'  => $data['razao_social'] ?? '',
            'nome_fantasia' => $data['nome_fantasia'] ?? '',
            'email'         => $data['estabelecimento']['email'] ?? '',
            'telefone'      => ($data['estabelecimento']['ddd1'] ?? '') . ($data['estabelecimento']['telefone1'] ?? ''),
            'cep'           => $data['estabelecimento']['cep'] ?? '',
            'estado'        => $data['estabelecimento']['estado']['sigla'] ?? '',
            'cidade'        => $data['estabelecimento']['cidade']['nome'] ?? '',
            'bairro'        => $data['estabelecimento']['bairro'] ?? '',
            'endereco'      => $data['estabelecimento']['logradouro'] ?? '',
            'numero'        => $data['estabelecimento']['numero'] ?? '',
            'complemento'   => $data['estabelecimento']['complemento'] ?? '',
        ];

        return $resultado;
    }
}
