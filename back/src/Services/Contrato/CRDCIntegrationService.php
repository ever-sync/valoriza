<?php

namespace App\Services\Contrato;

use App\Infrastructure\CRDC\CRDCClient;
use App\Models\Contrato\ContratoModel;
use App\Models\PessoaFisica\PessoaFisicaModel;
use App\Models\PessoaJuridica\PessoaJuridicaModel;
use App\Services\BaseService;
use Exception;

/**
 * Serviço responsável por orquestrar a integração de contratos com a CRDC.
 */
class CRDCIntegrationService extends BaseService
{
    private CRDCClient $client;
    private ContratoModel $contratoModel;

    public function __construct()
    {
        parent::__construct();
        $this->client = new CRDCClient();
        $this->contratoModel = new ContratoModel();
    }

    /**
     * Envia um contrato para registro na CRDC.
     */
    public function enviarContrato(int $contratoId): array
    {
        $contrato = $this->obterDadosCompletosContrato($contratoId);

        $payload = [
            'codigo_seguradora' => '00000000000191', // CNPJ da Instituição (Exemplo)
            'apolice_codigo'    => $contrato['numero_contrato'],
            'data_emissao'      => $contrato['data_assinatura'],
            'data_inicio'       => $contrato['data_assinatura'],
            'data_termino'      => $this->calcularDataTermino($contrato),
            'entidades'         => $this->mapearEntidades($contrato),
            'objetos_segurados' => [
                [
                    'codigo'           => $contrato['numero_contrato'],
                    'tipo'             => '1',
                    'descricao_objeto' => 'Contrato de empréstimo/crédito',
                    'valor'            => (float) $contrato['valor_solicitado'],
                    'valor_real'       => (float) $contrato['valor_solicitado']
                ]
            ]
        ];

        return $this->client->sendRegistry($payload);
    }

    /**
     * Busca dados do contrato e do cliente vinculado.
     */
    private function obterDadosCompletosContrato(int $contratoId): array
    {
        $dados = $this->contratoModel->consultar(['ctr.id' => $contratoId]);
        if (empty($dados)) {
            throw new Exception("Contrato $contratoId não encontrado.");
        }

        $contrato = $dados[0];

        // Busca detalhes do cliente
        if ($contrato['tipo_cliente'] === 'pf') {
            $cliente = (new PessoaFisicaModel())->consultar(['pf.id' => $contrato['cliente_id']]);
            $contrato['cliente_detalhes'] = $cliente[0] ?? [];
        } else {
            $cliente = (new PessoaJuridicaModel())->consultar(['pj.id' => $contrato['cliente_id']]);
            $contrato['cliente_detalhes'] = $cliente[0] ?? [];
        }

        return $contrato;
    }

    /**
     * Mapeia os envolvidos no contrato para o formato da CRDC.
     */
    private function mapearEntidades(array $contrato): array
    {
        $cliente = $contrato['cliente_detalhes'];
        $entidades = [];

        // Devedor (Tomador)
        $entidades[] = [
            'documento'      => preg_replace('/\D/', '', $cliente['cpf'] ?? $cliente['cnpj'] ?? ''),
            'tipo_documento' => $contrato['tipo_cliente'] === 'pf' ? 'PF' : 'PJ',
            'identPessoa'    => '03', // 03 = Tomador/Devedor no SRO
            'nome'           => $cliente['nome_completo'] ?? $cliente['razao_social'] ?? '',
            'email'          => $cliente['email'] ?? '',
            'endereco'       => $cliente['endereco'] ?? '',
            'cidade'         => $cliente['cidade'] ?? '',
            'estado'         => $cliente['estado'] ?? '',
            'pais'           => 'BRA',
            'codigo_postal'  => preg_replace('/\D/', '', $cliente['cep'] ?? '')
        ];

        // Garantidores (Avalistas e Devedores Solidários)
        $idContrato = (int) $contrato['id'];
        $garantias = (new \App\Services\Contrato\ContratoService())->consultarGarantias($idContrato);

        foreach ($garantias as $g) {
            if (in_array($g['tipo_garantia'], ['Avalista', 'Devedor solidário'])) {
                $entidades[] = [
                    'documento'      => preg_replace('/\D/', '', $g['cpf'] ?? $g['cnpj'] ?? ''),
                    'tipo_documento' => !empty($g['cpf']) ? 'PF' : 'PJ',
                    'identPessoa'    => '04', // 04 = Garantidor/Interveniente no SRO
                    'nome'           => $g['pessoa_nome_garantia'] ?? $g['nome_completo'] ?? '',
                    'email'          => $g['email'] ?? '',
                    'endereco'       => $g['endereco'] ?? '',
                    'cidade'         => $g['cidade'] ?? '',
                    'estado'         => $g['estado'] ?? '',
                    'pais'           => 'BRA',
                    'codigo_postal'  => preg_replace('/\D/', '', $g['cep'] ?? '')
                ];
            }
        }

        return $entidades;
    }

    private function calcularDataTermino(array $contrato): string
    {
        $data = new \DateTime($contrato['data_primeira_parcela']);
        $parcelas = (int) $contrato['quantidade_parcelas'];
        
        if ($contrato['periodo_amortizacao'] === 'Mensal') {
            $data->modify("+" . ($parcelas - 1) . " months");
        } else {
            $data->modify("+" . ($parcelas - 1) . " days");
        }

        return $data->format('Y-m-d');
    }
}
