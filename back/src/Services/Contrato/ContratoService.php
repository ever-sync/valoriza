<?php

namespace App\Services\Contrato;

use App\Interfaces\CrudServiceInterface;
use App\Models\Contrato\ContratoModel;
use App\Models\Contrato\ContratoGarantiaModel;
use App\Services\BaseService;
use App\Models\Receita\ReceitaModel;
use App\Models\ConfiguracoesContratos\ConfiguracoesContratosModel;
use App\Models\Contrato\ContratoParcelaModel;
use App\Models\PessoaFisica\PessoaFisicaModel;
use App\Models\PessoaJuridica\PessoaJuridicaModel;
use App\Services\Contrato\CRDCIntegrationService;
use App\Services\MotorCalculo\MotorCalculoService;
use App\Exceptions\SystemException;
use DateTime;
use stdClass;

/**
 * Serviço responsável pela gestão de contratos de crédito.
 * Gerencia simulações, cálculos de IOF, CET (Custo Efetivo Total) e garantias.
 *
 * Este serviço implementa as regras de negócio para amortização (Price/SAC),
 * conversão de taxas equivalentes e cálculos tributários (IOF).
 */
class ContratoService extends BaseService implements CrudServiceInterface
{
    /** @var string Classe do modelo principal vinculado a este serviço */
    protected string $model = ContratoModel::class;

    /** @var float Juros de mora padrão aplicado em caso de atraso (1% ao mês) */
    protected float $jurosMoraPadrao = 1.0;

    /** @var float Multa moratória padrão aplicada sobre o valor da parcela (2%) */
    protected float $multaMoratoriaPadrao = 2.0;

    /** @var array Definição dos campos globais para validação e sanitização automática */
    protected array $camposGlobais = [
        'tipo_operacao:required,string',
        'valor_solicitado:required,string',
        'periodo_amortizacao:required,string',
        'modelo_amortizacao:required,string',
        'taxa_juros:required,string',
        'tipo_taxa:required,string',
        'quantidade_parcelas:required,string',
        'data_assinatura:required,string',
        'data_primeira_parcela:required,string',
        'simples_nacional:nullable,int',
        'calcular_iof:nullable,int',
        'tipo_cliente:required,string',
        'cliente_id:nullable,string',
        'juros_mora:nullable,string',
        'multa_moratoria:nullable,string',
        'risco_inadimplencia:nullable,string',
        'permitir_carencia:nullable,int',
        'limite_carencia:nullable,string',
        'tipo_assinatura:nullable,string',
        'enviar_registradora:nullable,int',
        'contrato_iniciado:nullable,int',
        'status:nullable,string',
        'numero_contrato:nullable,string',
        'tipo_juros_pagamento_unico:nullable,string',
    ];

    /**
     * Prepara os dados para persistência, aplicando conversões monetárias e tipos.
     */
    protected function prepareWrite(array $input, string $contexto = 'inserir'): stdClass
    {
        $dados = [
            'tipo_operacao'         => $this->sanitizer()->string($input['tipo_operacao'] ?? null),
            'valor_solicitado'      => $this->limparValorMonetario($input['valor_solicitado'] ?? null),
            'periodo_amortizacao'   => $this->sanitizer()->string($input['periodo_amortizacao'] ?? null),
            'modelo_amortizacao'    => $this->sanitizer()->string($input['modelo_amortizacao'] ?? null),
            'taxa_juros'            => (float) str_replace(',', '.', $input['taxa_juros'] ?? '0'),
            'tipo_taxa'             => $this->sanitizer()->string($input['tipo_taxa'] ?? null),
            'quantidade_parcelas'   => (int) ($input['quantidade_parcelas'] ?? 0),
            'data_assinatura'       => $this->sanitizer()->string($input['data_assinatura'] ?? null),
            'data_primeira_parcela' => $this->sanitizer()->string($input['data_primeira_parcela'] ?? null),
            'simples_nacional'      => (int) ($input['simples_nacional'] ?? 0),
            'calcular_iof'          => (int) ($input['calcular_iof'] ?? 0),
            'tipo_cliente'          => $this->sanitizer()->string($input['tipo_cliente'] ?? null),
            'cliente_id'            => !empty($input['cliente_id']) ? (int) $input['cliente_id'] : null,
            'juros_mora'            => !empty($input['juros_mora']) ? (float) str_replace(',', '.', $input['juros_mora']) : $this->jurosMoraPadrao,
            'multa_moratoria'       => !empty($input['multa_moratoria']) ? (float) str_replace(',', '.', $input['multa_moratoria']) : $this->multaMoratoriaPadrao,
            'risco_inadimplencia'   => !empty($input['risco_inadimplencia']) ? $this->sanitizer()->string($input['risco_inadimplencia']) : 'Baixo',
            'permitir_carencia'     => (int) ($input['permitir_carencia'] ?? 0),
            'limite_carencia'       => !empty($input['limite_carencia']) ? (int) $input['limite_carencia'] : null,
            'tipo_assinatura'       => !empty($input['tipo_assinatura']) ? $this->sanitizer()->string($input['tipo_assinatura']) : 'sem_assinatura',
            'enviar_registradora'   => (int) ($input['enviar_registradora'] ?? 0),
            'contrato_iniciado'     => (int) ($input['contrato_iniciado'] ?? 0),
            'status'                => !empty($input['status']) ? $this->sanitizer()->string($input['status']) : 'Ativo',
            'numero_contrato'       => $input['numero_contrato'] ?? null,
            'tipo_juros_pagamento_unico' => !empty($input['tipo_juros_pagamento_unico'])
                ? $this->sanitizer()->string($input['tipo_juros_pagamento_unico'])
                : 'compostos',
        ];

        if ($contexto === 'inserir' && empty($dados['numero_contrato'])) {
            $dados['numero_contrato'] = $this->model()->gerarProximoNumero();
        }

        $dto = new stdClass();
        $dto->inserir   = [$dados];
        $dto->atualizar = [$dados];

        return $dto;
    }

    /** @return ContratoGarantiaModel */
    protected function getGarantiaModel()
    {
        return new ContratoGarantiaModel();
    }
    /** @return ReceitaModel */
    protected function getReceitaModel()
    {
        return new ReceitaModel();
    }
    /** @return ConfiguracoesContratosModel */
    protected function getConfiguracoesModel()
    {
        return new ConfiguracoesContratosModel();
    }
    /** @return ContratoParcelaModel */
    protected function getContratoParcelaModel()
    {
        return new ContratoParcelaModel();
    }

    private function limparValorMonetario($valor): float
    {
        if (empty($valor)) return 0.00;
        if (is_numeric($valor)) return (float) $valor;
        $val = preg_replace('/[R$\s]/', '', (string) $valor);
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
        return (float) $val;
    }

    protected function converterTaxa(float $taxa, int $origem, int $destino): float
    {
        if ($taxa <= 0 || $origem <= 0 || $destino <= 0) return $taxa;
        return round(pow(1 + $taxa, $destino / $origem) - 1, 10);
    }

    /**
     * Recupera as alíquotas de IOF conforme Decretos 12.466/2025 e 12.499/2025.
     *
     * Alíquotas vigentes (atualização 2025/2026 — isonomia tributária):
     * - PJ Geral (Regime Normal): Diário 0,0082% | Adicional 0,38%
     * - PF / MEI:                 Diário 0,0082% | Adicional 0,38%
     * - Simples Nacional (≤R$30k): Diário 0,00274% | Adicional 0,38%
     *
     * Nota: Decreto 12.499/2025 reduziu adicional PJ de 0,95% para 0,38%
     * para garantir isonomia entre PF e PJ.
     *
     * @param string $tipoCliente 'pf' ou 'pj'
     * @param bool $simplesNacional Se o cliente é optante do Simples Nacional
     * @param float $valorOperacao Valor da operação (para verificar limite Simples)
     * @return array [taxa_iof_diario, taxa_iof_adicional] em decimal
     */
    protected function getConfiguracoesIOF(string $tipoCliente = 'pf', bool $simplesNacional = false, float $valorOperacao = 0): array
    {
        // Alíquota diária (Decreto 12.466/2025)
        if ($simplesNacional && $valorOperacao <= 30000.00) {
            // Simples Nacional com operação até R$ 30.000,00: alíquota reduzida
            $taxaDiaria = 0.0000274;  // 0,00274% ao dia
        } else {
            // PJ Geral, PF e MEI: mesma alíquota diária
            $taxaDiaria = 0.000082;   // 0,0082% ao dia
        }

        // Alíquota adicional: 0,38% para TODOS os mutuários (Decreto 12.499/2025)
        // Antes era 0,95% para PJ Geral — agora unificado em 0,38%
        $taxaAdicional = 0.0038;  // 0,38%

        return [
            'taxa_iof_diario'    => (float) $taxaDiaria,
            'taxa_iof_adicional' => (float) $taxaAdicional
        ];
    }

    /**
     * Valida compatibilidade entre periodicidade e modelo de amortização.
     *
     * Matriz de compatibilidade (padrão FEBRABAN):
     * - Mensal:          Price ✅ | SAC ✅ | Americano ✅
     * - Semanal:         Price ✅ | SAC ❌ | Americano ✅
     * - Diário:          Price ✅ | SAC ❌ | Americano ✅
     * - Pagamento único: Ignora modelo → usa juros simples ou compostos
     *
     * @throws SystemException Se a combinação for inválida
     */
    protected function validarCompatibilidadeModelo(string $periodo, string $modelo): void
    {
        if ($periodo === 'Pagamento único') {
            return; // Pagamento único ignora modelo de amortização
        }

        $matrizValida = [
            'Mensal'  => ['Price', 'SAC', 'Sistema americano'],
            'Semanal' => ['Price', 'Sistema americano'],
            'Diário'  => ['Price', 'Sistema americano'],
        ];

        $modelosPermitidos = $matrizValida[$periodo] ?? [];

        if (!empty($modelosPermitidos) && !in_array($modelo, $modelosPermitidos)) {
            $listaPermitidos = implode(', ', $modelosPermitidos);
            throw new SystemException(
                "O modelo '{$modelo}' não é compatível com o período '{$periodo}'. Modelos permitidos: {$listaPermitidos}.",
                422
            );
        }
    }

    /**
     * Calcula IOF conforme regras BACEN (Decreto 6.306/2007).
     *
     * IOF Diário: incide sobre CADA PARCELA DE AMORTIZAÇÃO multiplicada
     * pelos dias corridos entre a data de assinatura e o vencimento daquela parcela.
     * Fórmula: Σ (amortização_i × taxa_diaria × min(365, dias_i))
     *
     * IOF Adicional: alíquota fixa (0,38%) sobre o valor principal.
     *
     * @param float $valor         Valor solicitado (principal)
     * @param float $taxaDiaria    Taxa IOF diária em decimal (ex: 0.000027 = 0,0027%/dia)
     * @param float $taxaAdicional Taxa IOF adicional em decimal (ex: 0.0038 = 0,38%)
     * @param array $amortizacoes  Array de valores de amortização por parcela
     * @param array $dias          Array de dias corridos até vencimento de cada parcela
     * @return array IOF detalhado
     */
    protected function calcularIOF(float $valor, float $taxaDiaria, float $taxaAdicional, array $amortizacoes, array $dias): array
    {
        $count = min(count($amortizacoes), count($dias));
        $iofDiario = 0;

        for ($i = 0; $i < $count; $i++) {
            $diasLimitado = min(365, $dias[$i]);
            // Arredondamento individual por parcela (Padrão de mercado para emissão de boletos)
            $iofDiario += round($amortizacoes[$i] * $diasLimitado * $taxaDiaria, 2);
        }

        $iofAdicional = round($valor * $taxaAdicional, 2);

        return [
            'diario'             => $iofDiario,
            'adicional'          => $iofAdicional,
            'total'              => round($iofDiario + $iofAdicional, 2),
            'taxa_diaria_pct'    => round($taxaDiaria * 100, 5),    // Ex: 0,00820%
            'taxa_adicional_pct' => round($taxaAdicional * 100, 2), // Ex: 0,95% ou 0,38%
        ];
    }

    /**
     * Calcula o Custo Efetivo Total (CET) — padrão ESC Solutions.
     *
     * Metodologia em 2 etapas:
     *   1) Newton-Raphson encontra a taxa DIÁRIA equivalente (CET/dia)
     *      usando dias corridos reais como expoentes.
     *      FC₀ = Σ FCⱼ / (1 + r_dia)^dⱼ
     *   2) Conversão por juros compostos:
     *      CET_mês = (1 + r_dia)^30 - 1
     *      CET_ano = (1 + r_dia)^365 - 1
     *
     * Isso garante convergência para qualquer periodicidade (diário, semanal, mensal),
     * mesmo com taxas extremas (ex: 15% ao dia).
     *
     * @param float  $valorLiquido   FC₀ — valor líquido liberado ao cliente
     * @param array  $parcelas       Cronograma [{parcela, vencimento_iso, ...}]
     * @param string $dataAssinatura Data de assinatura ISO (Y-m-d)
     * @return array ['mes' => float, 'ano' => float] em percentual
     */
    protected function calcularCET(float $valorLiquido, array $parcelas, string $dataAssinatura): array
    {
        if (empty($parcelas) || $valorLiquido <= 0) return ['mes' => 0, 'ano' => 0];

        $d0 = $this->ensureDateTime($dataAssinatura);

        // Pré-calcular dias corridos reais para cada parcela
        $fluxos = [];
        foreach ($parcelas as $p) {
            $dj = $this->ensureDateTime($p['vencimento_iso'] ?? $p['vencimento'] ?? null);
            $dias = max(1, (int) $d0->diff($dj)->days);
            $fluxos[] = [
                'valor' => (float) $p['parcela'],
                'dias'  => $dias,
            ];
        }

        // ─── Etapa 1: Newton-Raphson para encontrar taxa DIÁRIA ───
        // Chute inicial: estimar a taxa diária a partir do fluxo
        $totalPago = array_sum(array_column($fluxos, 'valor'));
        $diasMedio = $fluxos[0]['dias'] ?? 1;
        $rDia = pow($totalPago / $valorLiquido, 1 / $diasMedio) - 1;
        $rDia = max(1e-8, min($rDia, 2.0)); // Limitar chute entre 0 e 200%/dia

        for ($iter = 0; $iter < 300; $iter++) {
            $vpl = -$valorLiquido;
            $derivada = 0.0;

            foreach ($fluxos as $f) {
                $d = $f['dias'];
                $v = $f['valor'];
                $base = pow(1 + $rDia, $d);

                $vpl      += $v / $base;
                $derivada -= ($d * $v) / ($base * (1 + $rDia));
            }

            if (abs($derivada) < 1e-18) break;

            $novoR = $rDia - ($vpl / $derivada);

            // Convergência relativa (funciona tanto para taxas pequenas quanto enormes)
            if (abs($novoR - $rDia) < abs($rDia) * 1e-12 + 1e-14) {
                $rDia = $novoR;
                break;
            }
            $rDia = max(-0.99, $novoR);
        }

        // ─── Etapa 2: Conversão diária → mensal e anual ───
        $cetMes = pow(1 + $rDia, 30) - 1;
        $cetAno = pow(1 + $rDia, 365) - 1;

        return [
            'mes' => round($cetMes * 100, 2),
            'ano' => round($cetAno * 100, 2)
        ];
    }

    protected function calcularPMT(float $v, float $i, int $n): float
    {
        if ($i <= 0) return $v / $n;
        return $v * ($i * pow(1 + $i, $n)) / (pow(1 + $i, $n) - 1);
    }

    public function cadastrar(array $dados): int
    {
        $garantias = $dados['garantias'] ?? [];
        $this->validarSimulacao($dados);
        unset($dados['garantias']);
        $simulacao = $this->calcularSimulacao($dados);
        $regras = array_merge($this->camposGlobais, $this->camposInserir);
        $permitidos = array_map(fn($r) => explode(':', $r)[0], $regras);
        $filtrados = array_intersect_key($dados, array_flip($permitidos));
        $id = parent::cadastrar($filtrados);
        if ($id) {
            if (!empty($garantias)) $this->salvarGarantias($id, $garantias);
            $this->salvarSimulacao($id, $simulacao);

            // Lançar parcelas automaticamente no financeiro
            try {
                $this->lancarParcelas($id);
            } catch (\Throwable $e) {
                // Silencia erro se já estiverem lançadas ou houver erro no lançamento automático
            }
        }
        return $id;
    }

    public function atualizar(array $dados, int $id): bool
    {
        $garantias = $dados['garantias'] ?? [];
        $this->validarSimulacao($dados);
        unset($dados['garantias']);
        $simulacao = $this->calcularSimulacao($dados);
        $regras = array_merge($this->camposGlobais, $this->camposAtualizar);
        $permitidos = array_map(fn($r) => explode(':', $r)[0], $regras);
        $filtrados = array_intersect_key($dados, array_flip($permitidos));
        $sucesso = parent::atualizar($filtrados, $id);
        if ($sucesso) {
            if (!empty($garantias)) $this->salvarGarantias($id, $garantias, true);
            $this->salvarSimulacao($id, $simulacao);

            // Lançar parcelas automaticamente no financeiro (se ainda não foram lançadas)
            try {
                $this->lancarParcelas($id);
            } catch (\Throwable $e) {
                // Silencia erro se já estiverem lançadas ou houver erro no lançamento automático
            }
        }
        return $sucesso;
    }

    public function excluir(int $id): bool
    {
        $vinculos = $this->getReceitaModel()->consultar(['contrato_id' => $id, 'status_sistema' => 'incluido']);
        if (!empty($vinculos)) throw new SystemException('Contrato possui parcelas lançadas no financeiro.', 422);
        return parent::excluir($id);
    }

    public function consultarGarantias(int $contratoId): array
    {
        $garantias = $this->getGarantiaModel()->consultar(['contrato_id' => $contratoId, 'status_sistema' => 'incluido']);
        foreach ($garantias as &$g) {
            if (!empty($g['pessoa_id_garantia']) && !empty($g['tipo_pessoa_garantia'])) {
                try {
                    if ($g['tipo_pessoa_garantia'] === 'pf') {
                        $pessoa = $this->auxModel(PessoaFisicaModel::class)->consultar(['psf.id' => $g['pessoa_id_garantia']]);
                        if (!empty($pessoa[0])) {
                            $g['pessoa_nome_garantia'] = $pessoa[0]['nome_completo'] ?? null;
                            $g['cpf'] = $pessoa[0]['cpf'] ?? null;
                            $g['email'] = $pessoa[0]['email'] ?? null;
                            $g['telefone'] = $pessoa[0]['telefone'] ?? null;
                            $g['endereco'] = $pessoa[0]['endereco'] ?? null;
                            $g['numero'] = $pessoa[0]['numero'] ?? null;
                            $g['bairro'] = $pessoa[0]['bairro'] ?? null;
                            $g['cidade'] = $pessoa[0]['cidade'] ?? null;
                            $g['estado'] = $pessoa[0]['estado'] ?? null;
                        }
                    } else {
                        $pessoa = $this->auxModel(PessoaJuridicaModel::class)->consultar(['psj.id' => $g['pessoa_id_garantia']]);
                        if (!empty($pessoa[0])) {
                            $g['pessoa_nome_garantia'] = $pessoa[0]['razao_social'] ?? null;
                            $g['cnpj'] = $pessoa[0]['cnpj'] ?? null;
                            $g['email'] = $pessoa[0]['email'] ?? null;
                            $g['telefone'] = $pessoa[0]['telefone'] ?? null;
                            $g['endereco'] = $pessoa[0]['endereco'] ?? null;
                            $g['numero'] = $pessoa[0]['numero'] ?? null;
                            $g['bairro'] = $pessoa[0]['bairro'] ?? null;
                            $g['cidade'] = $pessoa[0]['cidade'] ?? null;
                            $g['estado'] = $pessoa[0]['estado'] ?? null;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        return $garantias;
    }

    private function salvarGarantias(int $contratoId, array $garantias, bool $substituir = false): void
    {
        $model = $this->getGarantiaModel();
        $empresaId = $this->model()->getEmpresaId();
        if ($substituir) {
            $anteriores = $model->consultar(['ctg.contrato_id' => $contratoId]);
            foreach ($anteriores as $g) $model->atualizar(['status_sistema' => 'excluido'], (int) $g['id']);
        }
        foreach ($garantias as $g) {
            $isPessoa = in_array(trim($g['tipo_garantia'] ?? ''), ['Avalista', 'Devedor solidário']);
            $pessoaId = !empty($g['pessoa_id_garantia']) ? (int) $g['pessoa_id_garantia'] : null;

            $model->cadastrar([
                'contrato_id'               => $contratoId,
                'empresa_id'                => $empresaId,
                'tipo_garantia'             => $g['tipo_garantia'] ?? '',
                'tipo_pessoa_garantia'      => $g['tipo_pessoa_garantia'] ?? null,
                'pessoa_id_garantia'        => $pessoaId,
                'nome_completo'             => ($isPessoa && $pessoaId) ? null : ($g['nome_completo'] ?? null),
                'cpf'                       => ($isPessoa && $pessoaId) ? null : ($g['cpf'] ?? null),
                'rg'                        => ($isPessoa && $pessoaId) ? null : ($g['rg'] ?? null),
                'email'                     => ($isPessoa && $pessoaId) ? null : ($g['email'] ?? null),
                'telefone'                  => ($isPessoa && $pessoaId) ? null : ($g['telefone'] ?? null),
                'renda_mensal'              => ($isPessoa && $pessoaId) ? null : (!empty($g['renda_mensal']) ? $this->limparValorMonetario($g['renda_mensal']) : null),
                'cep'                       => ($isPessoa && $pessoaId) ? null : ($g['cep'] ?? null),
                'estado'                    => ($isPessoa && $pessoaId) ? null : ($g['estado'] ?? null),
                'cidade'                    => ($isPessoa && $pessoaId) ? null : ($g['cidade'] ?? null),
                'bairro'                    => ($isPessoa && $pessoaId) ? null : ($g['bairro'] ?? null),
                'endereco'                  => ($isPessoa && $pessoaId) ? null : ($g['endereco'] ?? null),
                'numero'                    => ($isPessoa && $pessoaId) ? null : ($g['numero'] ?? null),
                'tabelionato'               => $g['tabelionato'] ?? null,
                'numero_matricula'          => $g['numero_matricula'] ?? null,
                'proprietario'              => $g['proprietario'] ?? null,
                'valor_avaliacao'           => !empty($g['valor_avaliacao']) ? $this->limparValorMonetario($g['valor_avaliacao']) : null,
                'descricao'                 => $g['descricao'] ?? null,
                'numero_serie'              => $g['numero_serie'] ?? null,
                'fabricante'                => $g['fabricante'] ?? null,
                'modelo'                    => $g['modelo'] ?? null,
                'ano_fabricacao'            => $g['ano_fabricacao'] ?? null,
                'chassi'                    => $g['chassi'] ?? null,
                'placa'                     => $g['placa'] ?? null,
                'tipo_recebivel'            => $g['tipo_recebivel'] ?? null,
                'numero_identificacao'      => $g['numero_identificacao'] ?? null,
                'sacado'                    => $g['sacado'] ?? null,
                'data_vencimento_recebivel' => !empty($g['data_vencimento_recebivel']) ? $g['data_vencimento_recebivel'] : null,
                'status_sistema'            => 'incluido'
            ]);
        }
    }

    private function salvarSimulacao(int $contratoId, array $simulacao): void
    {
        $model = $this->getContratoParcelaModel();
        $model->limparPorContrato($contratoId);
        foreach ($simulacao['parcelas'] as $p) {
            $model->cadastrar([
                'empresa_id'        => $model->getEmpresaId(),
                'contrato_id'       => $contratoId,
                'numero_parcela'    => $p['num'],
                'valor_parcela'     => $p['parcela'],
                'data_vencimento'   => $p['vencimento_iso'],
                'valor_juros'       => $p['juros'],
                'valor_amortizacao' => $p['amortizacao'],
                'saldo_devedor'     => $p['saldo'],
                'status_sistema'    => 'incluido',
            ]);
        }
    }


    /**
     * Calcula juros de mora, multa e atualização para quitação de uma parcela em atraso.
     * Segue regras do BACEN/Código Civil:
     * - Multa: máx 2% (CDC)
     * - Juros Mora: máx 1% a.m. (pro-rata)
     * - Juros Atualização: Taxa contratual (pro-rata)
     */
    public function calcularEncargosQuitacao(array $receita, array $contrato = []): array
    {
        $valorOriginal = (float) str_replace(',', '.', ($receita['valor_recebido'] ?? 0));

        $dataVencimento = $this->ensureDateTime($receita['data_vencimento'] ?? null);
        $dataCalculo = $this->ensureDateTime($receita['data_recebimento'] ?? null);

        $diasAtraso = 0;
        if ($dataCalculo > $dataVencimento) {
            $interval = $dataVencimento->diff($dataCalculo);
            $diasAtraso = (int) $interval->format('%a'); // Diferença total em dias
        }

        $taxaMulta = !empty($contrato['multa_moratoria']) ? (float) str_replace(',', '.', $contrato['multa_moratoria']) : $this->multaMoratoriaPadrao;
        $taxaJurosMora = !empty($contrato['juros_mora']) ? (float) str_replace(',', '.', $contrato['juros_mora']) : $this->jurosMoraPadrao;
        $taxaJurosContrato = !empty($contrato['taxa_juros']) ? (float) str_replace(',', '.', $contrato['taxa_juros']) : 0;

        // Multa moratória: 2% máximo legal (incide uma única vez)
        $taxaMulta = min((float) $taxaMulta, 2.0);
        $multa = $diasAtraso > 0 ? ($valorOriginal * ($taxaMulta / 100)) : 0;

        // Juros de mora: 1% ao mês máximo legal (pro-rata diário)
        $taxaJurosMora = min((float) $taxaJurosMora, 1.0);
        $jurosMora = $valorOriginal * ($taxaJurosMora / 100) * ($diasAtraso / 30);

        // Juros de atualização: taxa do contrato (pro-rata diário)
        $jurosAtualizacao = $valorOriginal * ($taxaJurosContrato / 100) * ($diasAtraso / 30);

        // IOF para o atraso (Limitado a 365 dias somando o período original, incidindo sobre o principal da parcela)
        $iof = 0;
        $taxaIof = 0;
        if (!empty($contrato['calcular_iof'])) {
            $parcelaNumero = $receita['parcela_numero'] ?? 1;
            $contratoId = $contrato['id'] ?? ($receita['contrato_id'] ?? 0);

            $valorAmortizacao = $valorOriginal;
            if ($contratoId) {
                $parcelaObj = $this->getContratoParcelaModel()->consultar([
                    'contrato_id' => $contratoId,
                    'numero_parcela' => $parcelaNumero,
                    'status_sistema' => 'incluido'
                ]);
                if (!empty($parcelaObj[0]['valor_amortizacao'])) {
                    $valorAmortizacao = (float) $parcelaObj[0]['valor_amortizacao'];
                }
            }

            $dataAssinatura = $this->ensureDateTime($contrato['data_assinatura'] ?? $dataVencimento);
            $diasContrato = max(0, (int) $dataAssinatura->diff($dataVencimento)->days);

            $diasIofDisponiveis = max(0, 365 - $diasContrato);
            $diasIofAtraso = min($diasAtraso, $diasIofDisponiveis);

            if ($diasIofAtraso > 0) {
                $tipoCliente = $contrato['tipo_cliente'] ?? 'pf';
                $simplesNacional = (bool) ($contrato['simples_nacional'] ?? false);
                $taxasIof = $this->getConfiguracoesIOF($tipoCliente, $simplesNacional, (float)($contrato['valor_solicitado'] ?? 0));
                $taxaIofDiaria = $taxasIof['taxa_iof_diario'];
                $taxaIof = $taxaIofDiaria * 100; // Para display

                $iof = $valorAmortizacao * $taxaIofDiaria * $diasIofAtraso;
            }
        }

        $totalEncargos = $multa + $jurosMora + $jurosAtualizacao + $iof;
        $valorTotal = $valorOriginal + $totalEncargos;

        return [
            'valor_original'    => round($valorOriginal, 2),
            'dias_atraso'       => $diasAtraso,
            'taxa_multa'        => $taxaMulta,
            'multa'             => round($multa, 2),
            'taxa_juros_mora'   => $taxaJurosMora,
            'juros_mora'        => round($jurosMora, 2),
            'taxa_juros'        => $taxaJurosContrato,
            'juros_atualizacao' => round($jurosAtualizacao, 2),
            'taxa_iof'          => $taxaIof,
            'iof'               => round($iof, 2),
            'total_encargos'    => round($totalEncargos, 2),
            'valor_total'       => round($valorTotal, 2)
        ];
    }

    /**
     * Helper para garantir que uma data seja convertida em DateTime corretamente,
     * lidando com formatos Y-m-d e d/m/Y.
     */
    private function ensureDateTime($date): \DateTime
    {
        if ($date instanceof \DateTime) return $date;
        if (empty($date)) return new \DateTime();

        $dateStr = substr($date, 0, 10);
        $dt = null;

        if (strpos($dateStr, '-') !== false) {
            $dt = \DateTime::createFromFormat('Y-m-d', $dateStr);
        } else if (strpos($dateStr, '/') !== false) {
            $dt = \DateTime::createFromFormat('d/m/Y', $dateStr);
        }

        if (!$dt) {
            try {
                $dt = new \DateTime($dateStr);
            } catch (\Exception $e) {
                $dt = new \DateTime();
            }
        }

        $dt->setTime(0, 0, 0);
        return $dt;
    }

    public function consultarParcelas(int $contratoId): array
    {
        $parcelas = $this->getContratoParcelaModel()->consultar(['contrato_id' => $contratoId, 'status_sistema' => 'incluido']);
        $receitas = $this->getReceitaModel()->consultar(['contrato_id' => $contratoId, 'status_sistema' => 'incluido']);
        $receitasMap = [];
        foreach ($receitas as $r) if (!empty($r['parcela_numero'])) $receitasMap[$r['parcela_numero']] = $r;
        foreach ($parcelas as &$p) {
            $num = $p['numero_parcela'];
            $p['lancada_financeiro'] = isset($receitasMap[$num]);
            $p['status_financeiro']  = $p['lancada_financeiro'] ? ($receitasMap[$num]['status'] ?? 'Lançada') : 'Simulação';
        }
        return $parcelas;
    }

    private function validarSimulacao(array $dados): void
    {
        $v = $this->limparValorMonetario($dados['valor_solicitado'] ?? 0);
        $i = (float) str_replace(',', '.', $dados['taxa_juros'] ?? 0);
        $n = (int) ($dados['quantidade_parcelas'] ?? 0);
        if ($v <= 0 || $i <= 0 || $n <= 0) throw new SystemException('Parâmetros obrigatórios ausentes.', 422);

        // Validação da taxa mínima baseada nas garantias
        $cfgs = $this->getConfiguracoesModel()->consultar([], 1);
        if (!empty($cfgs[0])) {
            $minimo = null;
            $garantias = $dados['garantias'] ?? [];
            $tipos = array_column($garantias, 'tipo_garantia');

            if (empty($tipos)) {
                $minimo = $cfgs[0]['taxa_juros_minima_sem_garantia'] ?? null;
            } elseif (in_array('Avalista', $tipos) || in_array('Devedor solidário', $tipos)) {
                $minimo = $cfgs[0]['taxa_juros_avalista'] ?? null;
            } elseif (in_array('Imóvel', $tipos)) {
                $minimo = $cfgs[0]['taxa_juros_imovel'] ?? null;
            } elseif (in_array('Veículo', $tipos)) {
                $minimo = $cfgs[0]['taxa_juros_veiculo'] ?? null;
            } else {
                $minimo = $cfgs[0]['taxa_juros_outras_garantias'] ?? null;
            }

            if ($minimo !== null && $i < (float)$minimo) {
                $minFormatado = number_format((float)$minimo, 2, ',', '.');
                throw new SystemException("A taxa de juros informada ({$i}%) é menor que a mínima permitida ({$minFormatado}%).", 422);
            }
        }
    }

    /**
     * Calcula a simulação completa de um contrato.
     *
     * Delega ao MotorCalculoService que implementa:
     * - BigDecimal (Brick\Math) para precisão arbitrária (§7)
     * - Strategy Pattern para sistemas de amortização (§3)
     * - IOF com Gross-Up convergente (§4.4)
     * - CET Newton-Raphson (§5)
     * - Arredondamento cirúrgico somente na emissão (§6)
     *
     * @param array $dados Dados do contrato
     * @return array Simulação completa com parcelas, IOF, CET e totais
     */
    public function calcularSimulacao(array $dados): array
    {
        $motor = new MotorCalculoService();

        try {
            return $motor->simular($dados);
        } catch (\DomainException $e) {
            throw new SystemException($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            throw new SystemException($e->getMessage(), 500);
        }
    }

    /**
     * [LEGADO] Calcula a simulação usando o motor original (float-based).
     * Mantido para referência e fallback. Usar calcularSimulacao() em produção.
     *
     * @deprecated Use calcularSimulacao() que delega ao MotorCalculoService.
     */
    public function calcularSimulacaoLegado(array $dados): array
    {
        // ═══════════════════════════════════════════════════════════
        // 1. PARSING DOS PARÂMETROS
        // ═══════════════════════════════════════════════════════════
        $valorSolicitado = $this->limparValorMonetario($dados['valor_solicitado'] ?? 0);
        $taxaNominal     = (float) str_replace(',', '.', $dados['taxa_juros'] ?? 0);
        $qtdParcelas     = max(1, (int) ($dados['quantidade_parcelas'] ?? 0));
        $modelo          = $dados['modelo_amortizacao'] ?? 'Price';
        $periodo         = $dados['periodo_amortizacao'] ?? 'Mensal';
        $tipoTaxa        = $dados['tipo_taxa'] ?? 'mensal';
        $tipoOperacao    = $dados['tipo_operacao'] ?? 'Empréstimo';
        $simplesNacional = (bool) ($dados['simples_nacional'] ?? false);
        $calcularIof     = (bool) ($dados['calcular_iof'] ?? false);
        $tipoCliente     = $dados['tipo_cliente'] ?? 'pf';

        if ($valorSolicitado <= 0 || $taxaNominal <= 0) {
            return $this->emptySimulacao();
        }

        // Pagamento Único → forçar n=1
        if ($periodo === 'Pagamento único') {
            $qtdParcelas = 1;
        }

        // Taxa: a informada já pertence ao período selecionado na interface.
        // Ex: se Diário e 0,5%, é 0,5% ao dia. Sem conversão com pow().
        $taxaDecimal = $taxaNominal / 100;
        $taxaPeriodo = $taxaDecimal;

        // Datas (dias corridos reais estritos)
        $dataAssinaturaStr   = $dados['data_assinatura'] ?? null;
        $dataAssinatura      = $this->ensureDateTime($dataAssinaturaStr)->setTime(0, 0, 0);
        $dataPrimeiraParcela = $this->ensureDateTime($dados['data_primeira_parcela'] ?? null)->setTime(0, 0, 0);

        // ═══════════════════════════════════════════════════════════
        // 2. DESCONTO DE RECEBÍVEIS (Motor Dedicado — Juros Simples)
        // ═══════════════════════════════════════════════════════════
        if ($tipoOperacao === 'Desconto de Recebíveis') {
            $diasAteVencimento = max(1, (int) $dataAssinatura->diff($dataPrimeiraParcela)->days);
            $taxaDiariaDesc = ($tipoTaxa === 'diario') ? $taxaDecimal : ($taxaDecimal / 30);

            $taxasIof = $this->getConfiguracoesIOF($tipoCliente, $simplesNacional, $valorSolicitado);
            $iof = ['diario' => 0, 'adicional' => 0, 'total' => 0];
            if ($calcularIof) {
                $iof = $this->calcularIOF($valorSolicitado, $taxasIof['taxa_iof_diario'], $taxasIof['taxa_iof_adicional'], [$valorSolicitado], [$diasAteVencimento]);
            }

            $desconto        = $valorSolicitado * $taxaDiariaDesc * $diasAteVencimento;
            $valorFinanciado = $valorSolicitado + $iof['total'];
            $valorLiquido    = $valorSolicitado - $desconto;

            $parcelas = [[
                'num'            => 1,
                'parcela'        => round($valorFinanciado, 2),
                'vencimento'     => $dataPrimeiraParcela->format('d/m/Y'),
                'vencimento_iso' => $dataPrimeiraParcela->format('Y-m-d'),
                'juros'          => round($desconto, 2),
                'amortizacao'    => round($valorSolicitado, 2),
                'saldo'          => 0,
            ]];

            return [
                'parcelas'         => $parcelas,
                'total_parcelas'   => round($valorFinanciado, 2),
                'total_juros'      => round($desconto, 2),
                'valor_financiado' => round($valorFinanciado, 2),
                'valor_liquido'    => round($valorLiquido, 2),
                'iof'              => $iof,
                'cet'              => $this->calcularCET($valorLiquido, $parcelas, $dataAssinaturaStr),
                'tipo_operacao'    => $tipoOperacao,
            ];
        }

        // ═══════════════════════════════════════════════════════════
        // 3. VALIDAÇÃO DE COMPATIBILIDADE PERIODICIDADE × MODELO
        // ═══════════════════════════════════════════════════════════
        $this->validarCompatibilidadeModelo($periodo, $modelo);

        // ═══════════════════════════════════════════════════════════
        // 4. PAGAMENTO ÚNICO — Motor Dedicado (Padrão ESC Solutions)
        //
        //    PV = Valor do Contrato (Solicitado + IOF), IOF é financiado.
        //
        //    Juros Simples: M = PV × (1 + i × n)
        //      → n = 1 se mês comercial exato, senão dias_reais / 30
        //      → Não há capitalização intermediária
        //
        //    Juros Compostos: M = PV × (1 + i/30)^n_dias
        //      → Capitalização DIÁRIA com taxa nominal ÷ 30
        //      → n_dias = 30 (mês comercial) se exato, senão dias_reais
        //      → Exemplo: 15% a.m. → 0,5%/dia → (1,005)^30 = 16,14% efetivo
        //
        // ═══════════════════════════════════════════════════════════
        if ($periodo === 'Pagamento único') {
            $tipoJuros = $dados['tipo_juros_pagamento_unico'] ?? 'compostos';
            $diasPrazo = max(1, (int) $dataAssinatura->diff($dataPrimeiraParcela)->days);

            // Detectar se o intervalo é exatamente 1 mês (mesmo dia do mês seguinte)
            $diffInterval = $dataAssinatura->diff($dataPrimeiraParcela);
            $mesExato = ((int)$dataPrimeiraParcela->format('d') === (int)$dataAssinatura->format('d'))
                     && ($diffInterval->m === 1 || ($diffInterval->m === 0 && $diffInterval->y === 0 && $diasPrazo >= 28 && $diasPrazo <= 31));

            // 1) Calcular IOF PRIMEIRO (será financiado junto ao principal)
            $taxasIof = $this->getConfiguracoesIOF($tipoCliente, $simplesNacional, $valorSolicitado);
            $iof = ['diario' => 0, 'adicional' => 0, 'total' => 0, 'taxa_diaria_pct' => 0, 'taxa_adicional_pct' => 0];
            if ($calcularIof) {
                $iof = $this->calcularIOF($valorSolicitado, $taxasIof['taxa_iof_diario'], $taxasIof['taxa_iof_adicional'], [$valorSolicitado], [$diasPrazo]);
            }

            // 2) PV = Valor do Contrato (solicitado + IOF financiado)
            $valorContrato = $valorSolicitado + $iof['total'];

            // 3) Calcular juros conforme regime de capitalização
            if ($tipoJuros === 'simples') {
                // Juros Simples: M = PV × (1 + i × n)
                // n = 1 para mês comercial exato, senão dias_reais / 30
                $n = $mesExato ? 1 : ($diasPrazo / 30);
                $montante = $valorContrato * (1 + $taxaPeriodo * $n);
            } else {
                // Juros Compostos: capitalização diária com mês comercial
                // taxa_diaria = taxa_mensal / 30
                // n_dias = 30 para mês comercial exato, senão dias_reais
                $taxaDiaria = $taxaPeriodo / 30;
                $nDias = $mesExato ? 30 : $diasPrazo;
                $montante = $valorContrato * pow(1 + $taxaDiaria, $nDias);
            }

            $jurosTotal = $montante - $valorContrato;

            $parcelas = [[
                'num'            => 1,
                'parcela'        => round($montante, 2),
                'vencimento'     => $dataPrimeiraParcela->format('d/m/Y'),
                'vencimento_iso' => $dataPrimeiraParcela->format('Y-m-d'),
                'juros'          => round($jurosTotal, 2),
                'amortizacao'    => round($valorContrato, 2),
                'saldo'          => 0,
            ]];

            return [
                'parcelas'         => $parcelas,
                'total_parcelas'   => round($montante, 2),
                'total_juros'      => round($jurosTotal, 2),
                'valor_financiado' => round($valorContrato, 2),
                'iof'              => $iof,
                'cet'              => $this->calcularCET($valorSolicitado - $iof['total'], $parcelas, $dataAssinaturaStr),
                'taxa_periodo'     => round($taxaPeriodo * 100, 6),
                'tipo_operacao'    => $tipoOperacao,
                'tipo_juros'       => $tipoJuros,
            ];
        }

        // ═══════════════════════════════════════════════════════════
        // 5. GERAÇÃO DE DATAS DE VENCIMENTO E DIAS CORRIDOS (IOF)
        // ═══════════════════════════════════════════════════════════
        $diasAteVencimento = [];
        $datasVencimento   = [];

        for ($i = 1; $i <= $qtdParcelas; $i++) {
            $venc = $this->calcularDataVencimento($dataPrimeiraParcela, $i, $periodo)->setTime(0, 0, 0);
            $datasVencimento[] = $venc;
            if ($periodo === 'Mensal') {
                $diasAteVencimento[] = $i * 30;
            } else {
                $diasAteVencimento[] = (int) $dataAssinatura->diff($venc)->days;
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 6. CÁLCULO DE IOF — GROSS-UP CIRCULAR (5 iterações)
        //    Alíquotas: Decreto 12.466/2025
        // ═══════════════════════════════════════════════════════════
        $taxasIof = $this->getConfiguracoesIOF($tipoCliente, $simplesNacional, $valorSolicitado);
        $iof = ['diario' => 0, 'adicional' => 0, 'total' => 0];

        if ($calcularIof) {
            $valorBase = $valorSolicitado;
            for ($ciclo = 0; $ciclo < 5; $ciclo++) {
                $amorts = $this->gerarAmortizacoesTeoricas($valorBase, $taxaPeriodo, $qtdParcelas, $modelo);
                $iof = $this->calcularIOF($valorSolicitado, $taxasIof['taxa_iof_diario'], $taxasIof['taxa_iof_adicional'], $amorts, $diasAteVencimento);
                $valorBase = $valorSolicitado + $iof['total'];
            }
            $valorFinanciado = round($valorBase, 2);
        } else {
            $valorFinanciado = round($valorSolicitado, 2);
        }

        // ═══════════════════════════════════════════════════════════
        // 7. GERAÇÃO DO CRONOGRAMA — Strategy (Price / SAC / Americano)
        //
        //    Arredondamento bancário (padrão ESC Solutions):
        //    - Price: PMT arredondado PARA CIMA (ceil 2 casas)
        //      → última parcela absorve resíduo (sempre ≤ PMT)
        //    - Americano: juros TRUNCADO (floor 2 casas)
        //      → evita superestimar encargos intermediários
        //    - SAC: amortização fixa com precisão total, juros arredondado normal
        //
        // ═══════════════════════════════════════════════════════════
        $pmtRaw = $this->calcularPMT($valorFinanciado, $taxaPeriodo, $qtdParcelas);

        // Price: ceil do PMT (convenção bancária — parcela fixa arredondada para cima)
        $pmtFinal = ($modelo === 'Price' || $modelo === 'default')
            ? ceil($pmtRaw * 100) / 100
            : $pmtRaw;

        $saldoInterno = $valorFinanciado;
        $amortFixa = ($modelo === 'SAC') ? $valorFinanciado / $qtdParcelas : 0;
        $totalJuros = 0;
        $parcelas = [];

        for ($i = 1; $i <= $qtdParcelas; $i++) {
            $jurosInterno = $saldoInterno * $taxaPeriodo;

            if ($i === $qtdParcelas) {
                // Última parcela absorve saldo residual matematicamente
                $amortInterna   = $saldoInterno;
                $parcelaInterna = $amortInterna + $jurosInterno;
            } else {
                [$parcelaInterna, $amortInterna] = match ($modelo) {
                    'Price' => [$pmtFinal, $pmtFinal - $jurosInterno],
                    'SAC'   => [$amortFixa + $jurosInterno, $amortFixa],
                    'Sistema americano' => [floor($jurosInterno * 100) / 100, 0.0],
                    default => [$pmtFinal, $pmtFinal - $jurosInterno],
                };
            }

            $saldoInterno -= $amortInterna;
            $totalJuros   += $jurosInterno;

            $parcelas[] = [
                'num'            => $i,
                'parcela'        => round($parcelaInterna, 2),
                'vencimento'     => $datasVencimento[$i - 1]->format('d/m/Y'),
                'vencimento_iso' => $datasVencimento[$i - 1]->format('Y-m-d'),
                'juros'          => round($jurosInterno, 2),
                'amortizacao'    => round($amortInterna, 2),
                'saldo'          => max(0, round($saldoInterno, 2)),
            ];
        }

        // ═══════════════════════════════════════════════════════════
        // 8. RESULTADO FINAL + CET (Res. 4881/2020 — Base 365 dias corridos)
        // ═══════════════════════════════════════════════════════════
        return [
            'parcelas'         => $parcelas,
            'total_parcelas'   => round(array_sum(array_column($parcelas, 'parcela')), 2),
            'total_juros'      => round($totalJuros, 2),
            'valor_financiado' => round($valorFinanciado, 2),
            'iof'              => $iof,
            'cet'              => $this->calcularCET($valorSolicitado - $iof['total'], $parcelas, $dataAssinaturaStr),
            'taxa_periodo'     => round($taxaPeriodo * 100, 6),
            'tipo_operacao'    => $tipoOperacao,
        ];
    }




    private function gerarAmortizacoesTeoricas(float $valorFinanciado, float $taxaPeriodo, int $qtdParcelas, string $modelo): array
    {
        $pmtFinal = $this->calcularPMT($valorFinanciado, $taxaPeriodo, $qtdParcelas);
        $saldoInterno = $valorFinanciado;

        $amortFixa = 0;
        if ($modelo === 'SAC') {
            $amortFixa = $valorFinanciado / $qtdParcelas;
        }

        $amortizacoes = [];

        for ($i = 1; $i <= $qtdParcelas; $i++) {
            $jurosInterno = $saldoInterno * $taxaPeriodo;

            if ($i === $qtdParcelas) {
                $amortInterna = $saldoInterno;
            } else {
                if ($modelo === 'Price') {
                    $amortInterna = $pmtFinal - $jurosInterno;
                } elseif ($modelo === 'SAC') {
                    $amortInterna = $amortFixa;
                } else {
                    $amortInterna = $this->calcularAmortizacao($modelo, $pmtFinal, $jurosInterno, $saldoInterno, $valorFinanciado, $qtdParcelas, $i);
                }
            }

            $amortizacoes[] = $amortInterna;
            $saldoInterno -= $amortInterna;
        }

        return $amortizacoes;
    }

    /**
     * Calcula a data de vencimento da parcela N.
     */
    private function calcularDataVencimento(DateTime $dataBase, int $numeroParcela, string $periodo): DateTime
    {
        $data = clone $dataBase;
        $offset = $numeroParcela - 1;

        switch ($periodo) {
            case 'Semanal':
                $data->modify("+{$offset} weeks");
                break;
            case 'Diário':
                $data->modify("+{$offset} days");
                break;
            default: // Mensal
                $data->modify("+{$offset} months");
                break;
        }

        return $data;
    }

    /**
     * Calcula a amortização de uma parcela conforme o modelo de amortização.
     *
     * @param string $modelo       Price | SAC | Sistema americano | Pagamento único
     * @param float  $pmt          Valor da parcela fixa (Price)
     * @param float  $juros        Juros do período
     * @param float  $saldoDevedor Saldo devedor atual
     * @param float  $valorBase    Valor financiado (base para SAC)
     * @param int    $totalParc    Quantidade total de parcelas
     * @param int    $parcAtual    Parcela atual (1-indexed)
     * @return float Valor da amortização
     */
    private function calcularAmortizacao(
        string $modelo,
        float $pmt,
        float $juros,
        float $saldoDevedor,
        float $valorBase,
        int $totalParc,
        int $parcAtual
    ): float {
        return match ($modelo) {
            'SAC'                                => $valorBase / $totalParc,
            'Sistema americano', 'Pagamento único' => ($parcAtual === $totalParc) ? $saldoDevedor : 0,
            default /* Price */                  => $pmt - $juros,
        };
    }

    public function lancarParcelas(int $id): array
    {
        $dados = $this->model()->consultar(['ctr.id' => $id]);
        if (empty($dados)) throw new SystemException('Contrato inválido.', 404);
        $contrato = $dados[0];
        $receitaModel = $this->getReceitaModel();
        if (!empty($receitaModel->consultar(['contrato_id' => $id, 'status_sistema' => 'incluido']))) throw new SystemException('Parcelas já lançadas.', 422);
        $simulacao = $this->calcularSimulacao($contrato);
        foreach ($simulacao['parcelas'] as $p) {
            $receitaModel->cadastrar(['empresa_id' => $contrato['empresa_id'], 'contrato_id' => $id, 'parcela_numero' => $p['num'], 'descricao' => "Parc. {$p['num']}/{$contrato['quantidade_parcelas']} - Contrato {$contrato['numero_contrato']}", 'valor_recebido' => $p['parcela'], 'data_vencimento' => $p['vencimento_iso'], 'status' => 'Pendente', 'tipo_pessoa' => $contrato['tipo_cliente'], 'pagador_id' => $contrato['cliente_id']]);
        }
        return ['success' => true, 'count' => count($simulacao['parcelas'])];
    }

    private function emptySimulacao(): array
    {
        return ['parcelas' => [], 'total_parcelas' => 0, 'valor_financiado' => 0];
    }
}
