<?php

namespace App\Infrastructure\CRDC;

use App\Exceptions\SystemException;
use Exception;
use Lumynus\Framework\LumynusUtilities;

/**
 * Cliente de Infraestrutura para integração com a API CRDC (SRO).
 * Responsável por autenticação OAuth2 e comunicação HTTP.
 */
class CRDCClient
{

    use LumynusUtilities;

    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;

    public function __construct()
    {
        // TODO: Mover para arquivo de configuração ou env
        $this->baseUrl      = 'https://api-customer.crdcseguros.com.br/stg';
        $this->clientId     = 'crdc_integration'; // Deve ser preenchido com dados reais
        $this->clientSecret = 'aguia123';
    }

    /**
     * Obtém o token de acesso via OAuth2.
     */
    private function authenticate(): void
    {
        $url = $this->baseUrl . '/oauth2/token';

        $headers = [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'scope'      => 'crdc/read crdc/write'
        ]);

        $response = $this->request('POST', $url, $headers, $body, false);

        if (empty($response['access_token'])) {
            throw new SystemException('Falha na autenticação com CRDC: ' . ($response['error_description'] ?? 'Erro desconhecido'), 401);
        }

        $this->accessToken = $response['access_token'];
    }

    /**
     * Realiza uma requisição para a API da CRDC.
     */
    public function sendRegistry(array $payload): array
    {


        $this->logs()->register('Teste', $payload);

        if (!$this->accessToken) {
            $this->authenticate();
        }

        $url = $this->baseUrl . '/registry';

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];


        return $this->request('POST', $url, $headers, json_encode($payload));
    }

    /**
     * Wrapper para cURL.
     */
    private function request(string $method, string $url, array $headers, $body = null, bool $retryOnAuth = true): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        unset($ch);

        if ($error) {
            throw new SystemException("Erro de conexão com CRDC: $error", 500);
        }

        $data = json_decode($response, true) ?? [];

        // Se o token expirou, tenta renovar uma vez
        if ($httpCode === 401 && $retryOnAuth && $this->accessToken) {
            $this->accessToken = null;
            return $this->sendRegistry(json_decode($body, true));
        }

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? $data['error_description'] ?? "Erro HTTP $httpCode";
            throw new SystemException("CRDC API Error: $msg", $httpCode);
        }

        return $data;
    }
}
