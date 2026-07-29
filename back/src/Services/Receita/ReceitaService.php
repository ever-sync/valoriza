<?php

namespace App\Services\Receita;

use App\Models\Receita\ReceitaModel;
use App\Models\Receita\ProrrogacaoModel;
use App\Models\Contrato\ContratoParcelaModel;
use App\Services\BaseService;
use App\Interfaces\CrudServiceInterface;
use stdClass;

/**
 * Serviço de Receitas.
 *
 * Gerencia receitas financeiras, incluindo:
 * - CRUD padrão com validação e sanitização
 * - Prorrogação: atualiza a MESMA parcela com multa + juros mora (não cria nova)
 * - Pagamento parcial (carência de principal): este SIM gera nova parcela
 * - Quitação com encargos (juros mora + multa moratória)
 * - Sincronização automática de status do contrato vinculado
 *
 * Regras financeiras aplicadas:
 * - Multa moratória: incide UMA VEZ sobre o valor original (máx 2% — CDC art. 52 §1°)
 * - Juros de mora: pro-rata diário simples = valor × (taxa/100) × (dias/30) (máx 1% a.m. — Código Civil art. 406)
 * - Receitas vinculadas a contrato NÃO permitem edição direta de data_vencimento e valor_recebido
 */
class ReceitaService extends BaseService implements CrudServiceInterface
{
    protected string $model = ReceitaModel::class;

    /** @var array Campos de validação do formulário */
    protected array $camposGlobais = [
        'descricao:required,string',
        'valor_recebido:required,string',
        'data_recebimento:nullable,string',
        'data_vencimento:required,string',
        'conta_bancaria_destino_id:nullable,string',
        'tipo_pagador:required,string',
        'tipo_pessoa:nullable,string',
        'pagador_id:nullable,string',
        'nome_pagador_manual:nullable,string',
        'tipo_comprovante_fiscal:nullable,string',
        'numero_comprovante:nullable,string',
        'categoria_id:nullable,string',
        'forma_recebimento:nullable,string',
        'status:nullable,string',
        'observacoes:nullable,string',
        'contrato_id:nullable,string',
        'parcela_numero:nullable,int',
    ];

    /**
     * Valida e normaliza os dados de entrada.
     *
     * @param array  $dados    Dados brutos do formulário
     * @param string $contexto 'inserir' ou 'atualizar'
     * @return array Dados normalizados
     */
    private function realizar(array $dados, $contexto = 'inserir'): array
    {
        $this->validarCampos($this->camposGlobais, $dados);
        return $this->normalizarCampos($dados);
    }

    /**
     * Prepara os dados para escrita no banco.
     *
     * @param array  $dados    Dados validados
     * @param string $contexto 'inserir' ou 'atualizar'
     * @return stdClass DTO com arrays 'inserir' e 'atualizar'
     */
    protected function prepareWrite(array $dados, $contexto = 'inserir'): stdClass
    {
        $dadosTratados = $this->realizar($dados, $contexto);

        $map = function (array $v): array {
            $dadosMapeados = [
                'descricao'                 => $this->sanitizer()->string($v['descricao'] ?? null),
                'valor_recebido'            => $this->limparValorMonetario($v['valor_recebido'] ?? 0),
                'data_recebimento'          => $this->sanitizer()->string($v['data_recebimento'] ?? null),
                'data_vencimento'           => $this->sanitizer()->string($v['data_vencimento'] ?? null),
                'conta_bancaria_destino_id' => $this->sanitizer()->int($v['conta_bancaria_destino_id'] ?? null),
                'tipo_pagador'              => $this->sanitizer()->string($v['tipo_pagador'] ?? null),
                'tipo_pessoa'               => $this->sanitizer()->string($v['tipo_pessoa'] ?? null),
                'pagador_id'                => $this->sanitizer()->int($v['pagador_id'] ?? null),
                'nome_pagador_manual'       => $this->sanitizer()->string($v['nome_pagador_manual'] ?? null),
                'tipo_comprovante_fiscal'   => $this->sanitizer()->string($v['tipo_comprovante_fiscal'] ?? null),
                'numero_comprovante'        => $this->sanitizer()->string($v['numero_comprovante'] ?? null),
                'categoria_id'              => $this->sanitizer()->int($v['categoria_id'] ?? null),
                'forma_recebimento'         => $this->sanitizer()->string($v['forma_recebimento'] ?? null),
                'status'                    => $this->sanitizer()->string($v['status'] ?? 'Pendente'),
                'observacoes'               => $this->sanitizer()->string($v['observacoes'] ?? null),
                'contrato_id'               => $this->sanitizer()->int($v['contrato_id'] ?? null),
                'parcela_numero'            => $this->sanitizer()->int($v['parcela_numero'] ?? null),
            ];

            if (($dadosMapeados['tipo_pagador'] ?? null) === 'manual') {
                $dadosMapeados['pagador_id'] = null;
                $dadosMapeados['tipo_pessoa'] = null;
            } else {
                $dadosMapeados['nome_pagador_manual'] = null;
            }

            return $dadosMapeados;
        };

        $dto = new stdClass();
        $dto->inserir   = array_map(function ($v) use ($map) {
            return array_merge(['cadastrado_por' => $this->getUsuarioId()], $map($v));
        }, $dadosTratados);

        $dto->atualizar = array_map(function ($v) use ($map) {
            return array_merge(['atualizado_por' => $this->getUsuarioId()], $map($v));
        }, $dadosTratados);

        return $dto;
    }

    /**
     * Converte valor monetário formatado para float.
     *
     * @param mixed $valor Valor em formato brasileiro ou numérico
     * @return float Valor limpo
     */
    private function limparValorMonetario($valor): float
    {
        if (empty($valor)) return 0.00;
        if (is_numeric($valor)) return (float) $valor;
        $val = preg_replace('/[R$\s]/', '', (string) $valor);
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
        return (float) $val;
    }

    // =========================================================================
    // SOBRESCRITA DO CRUD — BLOQUEIO DE EDIÇÃO PARA RECEITAS DE CONTRATO
    // =========================================================================

    /**
     * Atualiza uma receita.
     *
     * Se a receita estiver vinculada a um contrato, bloqueia alteração
     * direta de data_vencimento e valor_recebido.
     *
     * @param array $dados Dados atualizados
     * @param int   $id    ID da receita
     * @return bool
     */
    public function atualizar(array $dados, int $id): bool
    {
        // Verificar se receita é vinculada a contrato
        $receitaAtual = $this->model()->consultar(['id' => $id]);
        if (!empty($receitaAtual)) {
            $receita = $receitaAtual[0];

            $statusFinalizados = ['recebido', 'recebido parcial', 'repactuada', 'cancelado'];
            if (in_array(strtolower($receita['status'] ?? ''), $statusFinalizados)) {
                throw new \App\Exceptions\SystemException("Parcelas com status '{$receita['status']}' são consideradas finalizadas e não podem ser editadas manualmente.");
            }

            if (!empty($receita['contrato_id'])) {
                // Remover campos protegidos — só podem ser alterados via prorrogação/pagamento parcial
                unset($dados['data_vencimento']);
                unset($dados['valor_recebido']);
            }
        }

        $resultado = parent::atualizar($dados, $id);

        if ($resultado) {
            $this->sincronizarContrato($id);
        }

        return $resultado;
    }

    // =========================================================================
    // SINCRONIZAÇÃO COM CONTRATO
    // =========================================================================

    /**
     * Verifica se todas as parcelas de um contrato foram quitadas
     * e atualiza o status do contrato automaticamente.
     *
     * @param int $receitaId ID da receita alterada
     */
    private function sincronizarContrato(int $receitaId): void
    {
        $receita = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receita) || empty($receita[0]['contrato_id'])) return;

        $contratoId = (int) $receita[0]['contrato_id'];
        $contratoService = $this->getContratoService();
        $contratos = $contratoService->model()->consultar(['id' => $contratoId]);
        if (empty($contratos)) return;

        $contrato = $contratos[0];

        $receitas = $this->model()->consultar([
            'contrato_id' => $contratoId,
            'status_sistema' => 'incluido',
        ]);

        if (empty($receitas)) return;

        $esperado = (int) $contrato['quantidade_parcelas'];
        // Considerar receitas "Repactuada" como já tratadas
        $receitasAtivas = array_filter($receitas, fn($r) => $r['status'] !== 'Repactuada');

        $todasPagas = true;
        foreach ($receitasAtivas as $r) {
            if ($r['status'] !== 'Recebido') {
                $todasPagas = false;
                break;
            }
        }

        if ($todasPagas && count($receitasAtivas) > 0) {
            $contratoService->model()->atualizar(['status' => 'Quitado'], $contratoId);
        } elseif (($contrato['status'] ?? null) === 'Quitado' && !$todasPagas) {
            $contratoService->model()->atualizar(['status' => 'Ativo'], $contratoId);
        }
    }

    // =========================================================================
    // INSTÂNCIAS DE SERVIÇOS/MODELS AUXILIARES
    // =========================================================================

    /** @return ProrrogacaoModel Modelo de prorrogações */
    protected function getProrrogacaoModel(): ProrrogacaoModel
    {
        return new ProrrogacaoModel();
    }

    /** @return \App\Services\Contrato\ContratoService Serviço de contratos */
    protected function getContratoService(): \App\Services\Contrato\ContratoService
    {
        return new \App\Services\Contrato\ContratoService();
    }

    /** @return ContratoParcelaModel Modelo de parcelas do contrato */
    protected function getContratoParcelaModel(): ContratoParcelaModel
    {
        return new ContratoParcelaModel();
    }

    /**
     * Sincroniza alterações de status da receita com a parcela correspondente
     * na tabela tbl_contratos_parcelas, mantendo coerência.
     *
     * @param array $receita Dados da receita (com contrato_id e parcela_numero)
     * @param array $dadosSincronizar Dados a atualizar na parcela do contrato
     */
    private function sincronizarParcelaContrato(array $receita, array $dadosSincronizar = []): void
    {
        if (empty($receita['contrato_id']) || empty($receita['parcela_numero'])) return;

        $parcelaModel = $this->getContratoParcelaModel();
        $parcelas = $parcelaModel->consultar([
            'contrato_id' => (int) $receita['contrato_id'],
            'numero_parcela' => (int) $receita['parcela_numero'],
            'status_sistema' => 'incluido',
        ]);

        if (!empty($parcelas) && !empty($dadosSincronizar)) {
            $parcelaModel->atualizar($dadosSincronizar, (int) $parcelas[0]['id']);
        }
    }

    /**
     * Cria uma nova parcela na tabela de simulação do contrato
     * quando há pagamento parcial ou prorrogação (mantém coerência).
     *
     * @param array  $receita       Dados da receita original
     * @param float  $valorParcela  Valor da nova parcela
     * @param float  $valorJuros    Valor dos juros embutidos
     * @param string $dataVencimento Nova data de vencimento
     * @param float  $saldoDevedor  Saldo devedor após esta parcela
     */
    private function criarParcelaContrato(
        array $receita,
        float $valorParcela,
        float $valorJuros,
        string $dataVencimento,
        float $saldoDevedor = 0
    ): void {
        if (empty($receita['contrato_id'])) return;

        $parcelaModel = $this->getContratoParcelaModel();
        $empresaId = $this->getEmpresaId() ?? ($receita['empresa_id'] ?? null);

        $parcelaModel->cadastrar([
            'empresa_id'        => $empresaId,
            'contrato_id'       => (int) $receita['contrato_id'],
            'numero_parcela'    => (int) ($receita['parcela_numero'] ?? 0),
            'valor_parcela'     => round($valorParcela, 2),
            'data_vencimento'   => $dataVencimento,
            'valor_juros'       => round($valorJuros, 2),
            'valor_amortizacao' => round(max(0, $valorParcela - $valorJuros), 2),
            'saldo_devedor'     => round($saldoDevedor, 2),
            'status_sistema'    => 'incluido',
        ]);
    }

    // =========================================================================
    // CÁLCULOS DE ENCARGOS
    // =========================================================================

    /**
     * Calcula encargos (juros mora + multa moratória) de uma receita.
     *
     * @param int $receitaId ID da receita
     * @return array Encargos calculados
     */
    public function calcularEncargosQuitacao(int $receitaId): array
    {
        $receita = $this->model()->consultar(['rec.id' => $receitaId]);
        if (empty($receita)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }
        $receita = $receita[0];

        $contratoService = $this->getContratoService();
        $contrato = null;

        if (!empty($receita['contrato_id'])) {
            $contratos = $contratoService->model()->consultar(['ctr.id' => (int) $receita['contrato_id']]);
            if (!empty($contratos)) $contrato = $contratos[0];
        }

        return $contratoService->calcularEncargosQuitacao($receita, $contrato ?? []);
    }

    /**
     * Calcula encargos para o modal de recebimento unificado.
     *
     * Usa as taxas de juros_mora e multa_moratoria do contrato vinculado.
     * Regras legais (CDC / Código Civil):
     * - Multa moratória: até 2% — incide UMA VEZ sobre valor original (CDC art. 52 §1°)
     * - Juros de mora: até 1% a.m. — pro-rata diário simples (Código Civil art. 406)
     * - Juros de atualização: taxa do contrato — pro-rata diário (compensação monetária)
     *
     * @param int    $receitaId      ID da receita
     * @param string $dataRecebimento Data prevista do recebimento (Y-m-d)
     * @return array Encargos detalhados com contrato e parcela info
     */
    public function calcularEncargosRecebimento(int $receitaId, string $dataRecebimento, ?float $valorOriginalFrontend = null): array
    {
        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }
        $receita = $receitas[0];

        // Se o frontend informou qual é o valor base, usamos ele
        if ($valorOriginalFrontend !== null) {
            $receita['valor_recebido'] = $valorOriginalFrontend;
        }

        // Injetar data de recebimento para cálculo de dias de atraso
        $receita['data_recebimento'] = $dataRecebimento;

        $contratoService = $this->getContratoService();
        $contrato = null;

        if (!empty($receita['contrato_id'])) {
            $contratos = $contratoService->model()->consultar(['id' => (int) $receita['contrato_id']]);
            if (!empty($contratos)) $contrato = $contratos[0];
        }

        $encargos = $contratoService->calcularEncargosQuitacao($receita, $contrato ?? []);

        // Enriquecer com dados do contrato e da parcela para o frontend
        return [
            'receita_id'        => $receitaId,
            'contrato_id'       => $receita['contrato_id'] ?? null,
            'numero_contrato'   => $contrato['numero_contrato'] ?? null,
            'parcela_numero'    => $receita['parcela_numero'] ?? null,
            'total_parcelas'    => $contrato['quantidade_parcelas'] ?? null,
            'valor_original'    => $encargos['valor_original'],
            'dias_atraso'       => $encargos['dias_atraso'],
            'juros_atualizacao' => $encargos['juros_atualizacao'],
            'juros_mora'        => $encargos['juros_mora'],
            'multa'             => $encargos['multa'],
            'iof'               => $encargos['iof'] ?? 0,
            'total_encargos'    => $encargos['total_encargos'],
            'valor_devido'      => $encargos['valor_total'],
            'taxas' => [
                'juros_mora'       => $encargos['taxa_juros_mora'],
                'multa_moratoria'  => $encargos['taxa_multa'],
                'juros_contrato'   => $encargos['taxa_juros'],
                'iof'              => $encargos['taxa_iof'] ?? 0,
            ],
            'debug' => [
                'receita_id_db' => $receita['id'] ?? 'null',
                'raw_vencimento' => $receita['data_vencimento'] ?? 'null',
                'raw_recebimento' => $dataRecebimento,
                'dias_atraso' => $encargos['dias_atraso'],
            ]
        ];
    }

    // =========================================================================
    // QUITAÇÃO COM ENCARGOS
    // =========================================================================

    /**
     * Quita uma receita com cálculo automático de encargos por atraso.
     *
     * @param int   $receitaId ID da receita
     * @param array $dados     ['data_recebimento', 'valor_recebido']
     * @return bool
     */
    public function quitar(int $receitaId, array $dados = []): bool
    {
        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }

        $receita = $receitas[0];

        if ($receita['status'] === 'Recebido') {
            throw new \App\Exceptions\SystemException('Esta receita já foi recebida.');
        }

        $dataRecebimento = $dados['data_recebimento'] ?? date('Y-m-d');
        $valorRecebido = $this->limparValorMonetario($dados['valor_recebido'] ?? $receita['valor_recebido']);

        $contratoService = $this->getContratoService();
        $contrato = null;

        if (!empty($receita['contrato_id'])) {
            $contratos = $contratoService->model()->consultar(['ctr.id' => (int) $receita['contrato_id']]);
            if (!empty($contratos)) $contrato = $contratos[0];
        }

        $receitaParaCalculo = $receita;
        $receitaParaCalculo['data_recebimento'] = $dataRecebimento;

        $encargos = $contratoService->calcularEncargosQuitacao($receitaParaCalculo, $contrato ?? []);

        $valorFinal = min($valorRecebido, $encargos['valor_total']);

        // Observação com detalhamento dos encargos
        $obsEncargos = '';
        if ($encargos['dias_atraso'] > 0) {
            $obsEncargos = sprintf(
                "\n[Quitação com Encargos] Dias em atraso: %d | Multa (%.2f%%): R$ %s | Juros Mora (%.2f%%): R$ %s | Total Encargos: R$ %s",
                $encargos['dias_atraso'],
                $encargos['taxa_multa'],
                number_format($encargos['multa'], 2, ',', '.'),
                $encargos['taxa_juros_mora'],
                number_format($encargos['juros_mora'], 2, ',', '.'),
                number_format($encargos['total_encargos'], 2, ',', '.')
            );
        }

        $novaObs = ($receita['observacoes'] ?? '') . $obsEncargos;
        $novaObs .= sprintf(
            "\n[Quitação] Recebido em %s: R$ %s (Original: R$ %s)",
            $dataRecebimento,
            number_format($valorFinal, 2, ',', '.'),
            number_format($encargos['valor_original'], 2, ',', '.')
        );

        $this->model()->atualizar([
            'status' => 'Recebido',
            'data_recebimento' => $dataRecebimento,
            'valor_recebido' => $valorFinal,
            'observacoes' => $novaObs,
        ], $receitaId);

        $this->sincronizarContrato($receitaId);

        return true;
    }

    // =========================================================================
    // PRORROGAÇÃO DE PARCELA
    // =========================================================================

    /**
     * Prorrogação de parcela — atualiza a MESMA receita existente.
     *
     * O usuário informa apenas a nova data de vencimento e a justificativa.
     * O sistema calcula automaticamente os encargos por atraso:
     *
     * - Multa moratória: incide UMA VEZ sobre o valor original (máx 2% — CDC art. 52 §1°)
     * - Juros de mora: pro-rata diário simples (máx 1% a.m. — Código Civil art. 406)
     *
     * Fluxo:
     * 1. Calcular dias de atraso (vencimento original → hoje)
     * 2. Aplicar multa (1x) + juros mora (simples, pro-rata)
     * 3. Atualizar a MESMA receita com novo valor e nova data
     * 4. Registrar histórico na tabela tbl_receitas_prorrogacoes
     *
     * Efetiva a prorrogação de uma receita.
     * NÃO cria nova receita. NÃO marca como "Repactuada".
     * Nova receita só é gerada no pagamento parcial (carência de principal).
     *
     * @param array $dados     ['data_vencimento_nova', 'justificativa']
     * @param int   $receitaId ID da receita a prorrogar
     * @return array Resultado da operação
     */
    public function prorrogar(array $dados, int $receitaId): array
    {
        $novaData = $dados['data_vencimento_nova'] ?? null;
        if (!$novaData) {
            throw new \App\Exceptions\SystemException('Nova data de vencimento é obrigatória.');
        }

        $justificativa = trim($dados['justificativa'] ?? '');
        if (empty($justificativa)) {
            throw new \App\Exceptions\SystemException('A justificativa é obrigatória.');
        }

        $this->validarLimiteProrrogacoes($receitaId);

        // Chama a simulação para obter os valores corretos e validados
        $simulacao = $this->simularProrrogacao($receitaId, ['data_vencimento_nova' => $novaData]);

        $receitas = $this->model()->consultar(['id' => $receitaId]);
        $receita = $receitas[0];

        $empresaId = $this->getEmpresaId() ?? ($receita['empresa_id'] ?? null);

        // Fallback para o contrato caso a receita não tenha empresa_id
        if (!$empresaId && !empty($receita['contrato_id'])) {
            $contratoSync = $this->obterContratoVinculado($receita);
            $empresaId = $contratoSync['empresa_id'] ?? null;
        }

        if (!$empresaId) {
            throw new \App\Exceptions\SystemException('Não foi possível identificar a empresa para esta operação.');
        }

        $usuario = $this->getUsuario();
        $usuarioId = $usuario['id_usuario'] ?? $usuario['usuario_id'] ?? $usuario['id'] ?? null;

        $valorOriginal = $simulacao['valor_original'];
        $diasExtensao = $simulacao['encargos']['dias_extensao'];
        $multa = $simulacao['encargos']['multa'];
        $jurosMora = $simulacao['encargos']['juros_mora'];
        $valorAtualizado = $simulacao['valor_atualizado'];
        $multaContrato = $simulacao['encargos']['taxa_multa'];
        $jurosMoraContrato = $simulacao['encargos']['taxa_juros_mora'];

        $totalParcelas = (int) ($contrato['quantidade_parcelas'] ?? 0);

        // ── TRANSAÇÃO ─────────────────────────────────────────────
        $this->model()->beginTransaction();

        try {
            // 1. Registrar prorrogação no histórico
            $this->getProrrogacaoModel()->cadastrar([
                'receita_id'               => $receitaId,
                'contrato_id'              => (int) ($receita['contrato_id'] ?? 0),
                'empresa_id'               => $empresaId,
                'numero_contrato'          => $contrato['numero_contrato'] ?? null,
                'parcela_numero'           => (int) ($receita['parcela_numero'] ?? 0),
                'total_parcelas'           => $totalParcelas,
                'cliente_nome'             => $receita['pagador_nome'] ?? null,
                'cliente_documento'        => null,
                'data_vencimento_anterior' => $receita['data_vencimento'],
                'data_vencimento_nova'     => $novaData,
                'valor_anterior'           => $valorOriginal,
                'valor_recebido'           => 0,
                'juros_atualizacao'        => 0, // Prorrogação não aplica juros de atualização
                'juros_mora'               => $jurosMora,
                'multa_atraso'             => $multa,
                'valor_devido'             => $valorAtualizado,
                'valor_novo'               => $valorAtualizado,
                'justificativa'            => $justificativa,
                'cadastrado_por'           => $usuarioId,
            ]);

            // 2. Atualizar a MESMA receita (não cria nova)
            $obsProrrogacao = sprintf(
                "\n[Prorrogação] Vencimento: %s → %s | Multa (%s%%): R$ %s | Juros mora (%s%% a.m.): R$ %s | Valor: R$ %s → R$ %s | Motivo: %s",
                $receita['data_vencimento'],
                $novaData,
                number_format($multaContrato, 2, ',', '.'),
                number_format($multa, 2, ',', '.'),
                number_format($jurosMoraContrato, 2, ',', '.'),
                number_format($jurosMora, 2, ',', '.'),
                number_format($valorOriginal, 2, ',', '.'),
                number_format($valorAtualizado, 2, ',', '.'),
                $justificativa
            );

            $this->model()->atualizar([
                'valor_recebido'  => $valorAtualizado,
                'data_vencimento' => $novaData,
                'status'          => 'Pendente',
                'observacoes'     => ($receita['observacoes'] ?? '') . $obsProrrogacao,
            ], $receitaId);

            // 3. Sincronizar com tbl_contratos_parcelas (atualizar, não recriar)
            if (!empty($receita['contrato_id']) && !empty($receita['parcela_numero'])) {
                $this->sincronizarParcelaContrato($receita, [
                    'valor_parcela'   => $valorAtualizado,
                    'data_vencimento' => $novaData,
                ]);
            }

            $this->model()->commit();

            return [
                'success' => true,
                'message' => 'Parcela prorrogada com sucesso!',
                'dados'   => [
                    'valor_original'    => $valorOriginal,
                    'valor_atualizado'  => $valorAtualizado,
                    'novo_vencimento'   => $novaData,
                    'encargos'          => [
                        'juros_mora'    => $jurosMora,
                        'multa'         => $multa,
                        'dias_extensao' => $diasExtensao,
                        'taxa_multa'    => $multaContrato,
                        'taxa_juros_mora' => $jurosMoraContrato,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            $this->model()->rollBack();
            throw new \App\Exceptions\SystemException('Erro ao prorrogar parcela: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // PAGAMENTO INTEGRAL
    // =========================================================================

    /**
     * Registra o pagamento integral de uma receita.
     *
     * @param int $receitaId
     * @param array $dados (data_recebimento, conta_bancaria_destino_id)
     * @return array
     */
    public function quitarIntegral(int $receitaId, array $dados): array
    {
        if (empty($dados['conta_bancaria_destino_id'])) {
            throw new \App\Exceptions\SystemException('A conta bancária de destino é obrigatória para quitação.');
        }

        if (empty($dados['data_recebimento'])) {
            throw new \App\Exceptions\SystemException('A data do recebimento é obrigatória.');
        }

        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }
        $receita = $receitas[0];

        $statusFinalizados = ['recebido', 'recebido parcial', 'repactuada', 'cancelado'];
        if (in_array(strtolower($receita['status'] ?? ''), $statusFinalizados)) {
            throw new \App\Exceptions\SystemException("Parcelas com status '{$receita['status']}' são consideradas finalizadas e não podem ser quitadas.");
        }

        // Usamos model()->atualizar para bypassar a validação de $this->camposGlobais
        $this->model()->atualizar([
            'status' => 'Recebido',
            'data_recebimento' => $dados['data_recebimento'],
            'conta_bancaria_destino_id' => $dados['conta_bancaria_destino_id'],
        ], $receitaId);

        $this->sincronizarContrato($receitaId);

        return [
            'success' => true,
            'message' => 'Pagamento integral registrado com sucesso!'
        ];
    }

    // =========================================================================
    // PAGAMENTO PARCIAL (CARÊNCIA DE PRINCIPAL)
    // =========================================================================

    /**
     * Registra um abatimento (pagamento parcial).
     * O valor pago é debitado da parcela atual, que permanece aberta com o saldo devedor.
     *
     * @param int   $receitaId ID da receita original
     * @param array $dados     ['valor_pago', 'data_pagamento', 'data_vencimento_nova']
     * @return array Resultado da operação
     */
    public function pagarParcial(int $receitaId, array $dados): array
    {
        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }

        $receita = $receitas[0];

        if (!in_array(strtolower($receita['status'] ?? ''), ['pendente', 'atrasado'])) {
            throw new \App\Exceptions\SystemException('Apenas receitas Pendentes ou Atrasadas aceitam abatimento parcial.');
        }

        $valorOriginal = (float) ($receita['valor_recebido'] ?? 0);
        $valorPago = $this->limparValorMonetario($dados['valor_pago'] ?? 0);
        $dataPagamento = $dados['data_pagamento'] ?? date('Y-m-d');
        
        // Pode manter a data de vencimento atual ou prorrogar o saldo restante
        $novaData = $dados['data_vencimento_nova'] ?? $receita['data_vencimento'];

        if ($valorPago <= 0) {
            throw new \App\Exceptions\SystemException('O valor pago deve ser maior que zero.');
        }
        if ($valorPago >= $valorOriginal) {
            throw new \App\Exceptions\SystemException('O valor pago quita ou ultrapassa a parcela. Utilize a quitação integral.');
        }

        $saldoResidual = round($valorOriginal - $valorPago, 2);

        $empresaId = $this->getEmpresaId() ?? ($receita['empresa_id'] ?? null);
        
        if (!$empresaId && !empty($receita['contrato_id'])) {
            $contratoSync = $this->obterContratoVinculado($receita);
            $empresaId = $contratoSync['empresa_id'] ?? null;
        }

        $usuario = $this->getUsuario();
        $usuarioId = $usuario['id_usuario'] ?? $usuario['usuario_id'] ?? $usuario['id'] ?? null;

        $this->model()->beginTransaction();

        try {
            // 1. Atualizar a receita original para abater o valor (mantém pendente/atrasado)
            $obsOriginal = sprintf(
                "\n[Abatimento Parcial] Pago R$ %s de R$ %s em %s. Saldo restante: R$ %s.",
                number_format($valorPago, 2, ',', '.'),
                number_format($valorOriginal, 2, ',', '.'),
                $dataPagamento,
                number_format($saldoResidual, 2, ',', '.')
            );

            // Se prorrogou, e o status era atrasado, e a nova data > hoje, volta pra Pendente
            $novoStatus = $receita['status'];
            if ($novaData > date('Y-m-d')) {
                $novoStatus = 'Pendente';
            }

            $this->model()->atualizar([
                'valor_recebido' => $saldoResidual,
                'data_vencimento' => $novaData,
                'status' => $novoStatus,
                'observacoes' => ($receita['observacoes'] ?? '') . $obsOriginal,
            ], $receitaId);

            // 2. Criar uma NOVA receita para representar o valor PAGO no fluxo de caixa
            $this->model()->cadastrar([
                'empresa_id'      => $empresaId,
                'contrato_id'     => $receita['contrato_id'] ?? null,
                'parcela_numero'  => $receita['parcela_numero'] ?? null,
                'descricao'       => ($receita['descricao'] ?? 'Parcela') . " (Abatimento)",
                'valor_recebido'  => $valorPago,
                'data_vencimento' => $receita['data_vencimento'],
                'data_recebimento'=> $dataPagamento,
                'conta_bancaria_destino_id' => $dados['conta_bancaria_destino_id'] ?? ($receita['conta_bancaria_destino_id'] ?? null),
                'status'          => 'Recebido',
                'tipo_pagador'    => $receita['tipo_pagador'] ?? 'cadastrado',
                'tipo_pessoa'     => $receita['tipo_pessoa'] ?? null,
                'pagador_id'      => $receita['pagador_id'] ?? null,
                'cadastrado_por'  => $usuarioId,
            ]);

            // 3. Sincronizar com tbl_contratos_parcelas (atualiza a parcela para o novo saldo)
            if (!empty($receita['contrato_id']) && !empty($receita['parcela_numero'])) {
                $this->sincronizarParcelaContrato($receita, [
                    'valor_parcela' => $saldoResidual,
                    'data_vencimento' => $novaData
                ]);
            }

            $this->model()->commit();

            return [
                'success' => true,
                'message' => 'Abatimento parcial registrado com sucesso!',
                'dados'   => [
                    'valor_original' => $valorOriginal,
                    'valor_pago'     => $valorPago,
                    'saldo_residual' => $saldoResidual,
                    'nova_data'      => $novaData
                ],
            ];
        } catch (\Throwable $e) {
            $this->model()->rollBack();
            throw new \App\Exceptions\SystemException('Erro ao registrar abatimento parcial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Aplica Carência de Principal.
     * O cliente paga os juros do mês. A parcela atual é liquidada com esse valor.
     * Uma nova parcela com o valor original integral é criada no final do contrato.
     *
     * @param int   $receitaId ID da receita
     * @param array $dados     ['valor_pago', 'data_pagamento']
     * @return array Resultado da operação
     */
    public function pagarCarencia(int $receitaId, array $dados): array
    {
        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }

        $receita = $receitas[0];

        if (!in_array(strtolower($receita['status'] ?? ''), ['pendente', 'atrasado'])) {
            throw new \App\Exceptions\SystemException('Apenas receitas pendentes aceitam carência.');
        }

        $contrato = $this->obterContratoVinculado($receita);
        if (empty($contrato) || !((int) ($contrato['permitir_carencia'] ?? 0))) {
            throw new \App\Exceptions\SystemException('O contrato não permite carência de principal.');
        }

        $limiteCarencia = (int) ($contrato['limite_carencia'] ?? 0);
        $usosAtuais = (int) ($contrato['usos_carencia'] ?? 0);
        if ($limiteCarencia > 0 && $usosAtuais >= $limiteCarencia) {
            throw new \App\Exceptions\SystemException("Limite de carência atingido ({$usosAtuais}/{$limiteCarencia}).");
        }

        $valorOriginal = (float) ($receita['valor_recebido'] ?? 0);
        $valorPago = $this->limparValorMonetario($dados['valor_pago'] ?? 0);
        $dataPagamento = $dados['data_pagamento'] ?? date('Y-m-d');

        if ($valorPago <= 0) {
            throw new \App\Exceptions\SystemException('O valor pago de carência deve ser maior que zero.');
        }

        // 1. Descobrir o final do contrato para empurrar a nova parcela
        $contratoId = (int) $receita['contrato_id'];
        $receitasContrato = $this->model()->consultar(['contrato_id' => $contratoId, 'status_sistema' => 'incluido']);
        $maxParcelaNumero = 0;
        $ultimaDataVencimento = $receita['data_vencimento'];

        foreach ($receitasContrato as $r) {
            if ((int)$r['parcela_numero'] > $maxParcelaNumero) {
                $maxParcelaNumero = (int)$r['parcela_numero'];
            }
            if ($r['data_vencimento'] > $ultimaDataVencimento) {
                $ultimaDataVencimento = $r['data_vencimento'];
            }
        }

        $novaParcelaNumero = $maxParcelaNumero + 1;

        // Calcular data de vencimento da nova parcela no final do contrato
        $periodo = $contrato['periodo_amortizacao'] ?? 'Mensal';
        $novaDataObj = new \DateTime($ultimaDataVencimento);
        if ($periodo === 'Semanal')       $novaDataObj->modify('+7 days');
        elseif ($periodo === 'Diário')    $novaDataObj->modify('+1 day');
        else                              $novaDataObj->modify('+1 month');
        $novaData = $novaDataObj->format('Y-m-d');

        $empresaId = $this->getEmpresaId() ?? ($receita['empresa_id'] ?? null);
        if (!$empresaId) $empresaId = $contrato['empresa_id'] ?? null;
        
        $usuario = $this->getUsuario();
        $usuarioId = $usuario['id_usuario'] ?? $usuario['usuario_id'] ?? $usuario['id'] ?? null;

        $this->model()->beginTransaction();

        try {
            // 1. Atualizar receita original para RECEBIDO (pagou a carência)
            $obsOriginal = sprintf(
                "\n[Carência Aplicada] Pago R$ %s referente a juros/carência em %s. O principal de R$ %s foi jogado para o final do contrato (Parcela %d).",
                number_format($valorPago, 2, ',', '.'),
                $dataPagamento,
                number_format($valorOriginal, 2, ',', '.'),
                $novaParcelaNumero
            );

            $this->model()->atualizar([
                'status'           => 'Recebido',
                'valor_recebido'   => $valorPago,
                'data_recebimento' => $dataPagamento,
                'conta_bancaria_destino_id' => $dados['conta_bancaria_destino_id'] ?? ($receita['conta_bancaria_destino_id'] ?? null),
                'observacoes'      => ($receita['observacoes'] ?? '') . $obsOriginal,
            ], $receitaId);

            // 2. Incrementar usos de carência e qtd_parcelas do contrato
            $contratoService = $this->getContratoService();
            $novaQtdParcelas = (int)($contrato['quantidade_parcelas'] ?? 0) + 1;
            $contratoService->model()->atualizar([
                'usos_carencia' => $usosAtuais + 1,
                'quantidade_parcelas' => $novaQtdParcelas
            ], $contratoId);

            // 3. Criar NOVA receita no FINAL do contrato com o valor INTEGRAL original
            $this->model()->cadastrar([
                'empresa_id'      => $empresaId,
                'contrato_id'     => $contratoId,
                'parcela_numero'  => $novaParcelaNumero,
                'descricao'       => "Parcela {$novaParcelaNumero}/{$novaQtdParcelas} (Transferência por Carência)",
                'valor_recebido'  => $valorOriginal,
                'data_vencimento' => $novaData,
                'status'          => 'Pendente',
                'tipo_pagador'    => $receita['tipo_pagador'] ?? 'cadastrado',
                'tipo_pessoa'     => $receita['tipo_pessoa'] ?? null,
                'pagador_id'      => $receita['pagador_id'] ?? null,
                'cadastrado_por'  => $usuarioId,
            ]);

            // 4. Sincronizar com tbl_contratos_parcelas
            $this->sincronizarParcelaContrato($receita, [
                'valor_parcela' => $valorPago,
                'valor_amortizacao' => 0,
                'valor_juros' => $valorPago
            ]);

            // Inserir a nova parcela de principal no final
            $receitaParaSinc = $receita;
            $receitaParaSinc['parcela_numero'] = $novaParcelaNumero;
            $juros = !empty($receita['valor_juros_parcela']) ? $receita['valor_juros_parcela'] : 0;
            $saldoAnterior = $receita['saldo_devedor_parcela'] ?? $valorOriginal;

            $this->criarParcelaContrato(
                $receitaParaSinc,
                $valorOriginal,
                $juros,
                $novaData,
                max(0, (float)$saldoAnterior)
            );

            $this->model()->commit();

            return [
                'success' => true,
                'message' => 'Carência aplicada com sucesso! Nova parcela gerada no final.',
                'dados'   => [
                    'valor_original'    => $valorOriginal,
                    'valor_pago'        => $valorPago,
                    'nova_parcela_num'  => $novaParcelaNumero,
                    'nova_data'         => $novaData,
                    'usos_carencia'     => $usosAtuais + 1,
                ],
            ];
        } catch (\Throwable $e) {
            $this->model()->rollBack();
            throw new \App\Exceptions\SystemException('Erro ao aplicar carência: ' . $e->getMessage(), 500);
        }
    }


    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    /**
     * Obtém o contrato vinculado a uma receita.
     *
     * @param array $receita Dados da receita
     * @return array Dados do contrato ou array vazio
     */
    private function obterContratoVinculado(array $receita): array
    {
        if (empty($receita['contrato_id'])) return [];

        $contratoService = $this->getContratoService();
        $contratos = $contratoService->model()->consultar(['ctr.id' => (int) $receita['contrato_id']]);
        return !empty($contratos) ? $contratos[0] : [];
    }

    /**
     * Consulta o contrato vinculado a uma receita específica.
     * Útil para o frontend obter regras de carência, juros e multa.
     *
     * @param int $receitaId ID da receita
     * @return array Dados do contrato
     * @throws \App\Exceptions\SystemException Se a receita ou contrato não forem encontrados
     */
    public function consultarContrato(int $receitaId): array
    {
        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }

        $receita = $receitas[0];
        $contrato = $this->obterContratoVinculado($receita);

        if (empty($contrato)) {
            throw new \App\Exceptions\SystemException('Esta receita não possui um contrato vinculado.');
        }

        return $contrato;
    }

    /**
     * Valida se o limite de prorrogações foi atingido.
     *
     * @param int $receitaId ID da receita
     * @throws \App\Exceptions\SystemException Se limite atingido
     */
    private function validarLimiteProrrogacoes(int $receitaId): void
    {
        try {
            $configModel = new \App\Models\ConfiguracoesContratos\ConfiguracoesContratosModel();
            $configs = $configModel->consultar([], 1);

            $limite = 12;
            if (!empty($configs[0]['limite_prorrogacoes'])) {
                $limite = (int) $configs[0]['limite_prorrogacoes'];
            }

            $prorrogacoes = $this->getProrrogacaoModel()->consultar([
                'prr.receita_id' => $receitaId,
                'prr.status_sistema' => 'incluido',
            ]);

            if (count($prorrogacoes) >= $limite) {
                throw new \App\Exceptions\SystemException(
                    "Limite de {$limite} prorrogações atingido para esta parcela.",
                    422
                );
            }
        } catch (\App\Exceptions\SystemException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Se falhar ao consultar config, permite prosseguir
        }
    }

    /**
     * Retorna o histórico de prorrogações de uma receita.
     *
     * @param int $receitaId ID da receita
     * @return array Lista de prorrogações ordenadas por ID desc
     */
    public function consultarProrrogacoes(int $receitaId): array
    {
        return $this->getProrrogacaoModel()->consultar(
            ['prr.receita_id' => $receitaId, 'prr.status_sistema' => 'incluido'],
            'id_desc'
        );
    }

    /**
     * Simula uma prorrogação sem salvar (para visualização no frontend).
     *
     * Aplica as mesmas regras do método prorrogar():
     * - Multa moratória: 1x sobre valor original (máx 2% CDC)
     * - Juros de mora: pro-rata diário simples sobre dias de extensão (máx 1% a.m.)
     * - Dias = vencimento original → nova data
     *
     * @param int   $receitaId ID da receita
     * @param array $dados     ['data_vencimento_nova']
     * @return array Valores calculados
     */
    public function simularProrrogacao(int $receitaId, array $dados): array
    {
        $receitas = $this->model()->consultar(['id' => $receitaId]);
        if (empty($receitas)) {
            throw new \App\Exceptions\SystemException('Receita não encontrada.');
        }

        $receita = $receitas[0];
        $contrato = $this->obterContratoVinculado($receita);

        $jurosMoraContrato = (float) ($contrato['juros_mora'] ?? 1.0);
        $multaContrato = (float) ($contrato['multa_moratoria'] ?? 2.0);

        $valorOriginal = (float) ($receita['valor_recebido'] ?? 0);
        $dataVencimentoOriginal = new \DateTime($receita['data_vencimento']);
        $novaData = $dados['data_vencimento_nova'] ?? null;

        if (!$novaData) {
            return [
                'valor_original'    => $valorOriginal,
                'valor_atualizado'  => $valorOriginal,
                'encargos'          => [
                    'juros_mora'      => 0,
                    'multa'           => 0,
                    'dias_extensao'   => 0,
                    'taxa_juros_mora' => $jurosMoraContrato,
                    'taxa_multa'      => $multaContrato,
                ],
            ];
        }

        $novaDataObj = new \DateTime($novaData);

        // Dias de extensão = do vencimento original até a nova data
        $diasExtensao = max(0, (int) $dataVencimentoOriginal->diff($novaDataObj)->days);

        // Multa moratória: incide UMA VEZ
        $multa = round($valorOriginal * ($multaContrato / 100), 2);

        // Juros de mora: pro-rata diário SIMPLES sobre o período de extensão
        $jurosMora = round($valorOriginal * ($jurosMoraContrato / 100) * ($diasExtensao / 30), 2);

        // Valor atualizado = original + multa + juros mora
        $valorAtualizado = round($valorOriginal + $multa + $jurosMora, 2);

        return [
            'valor_original'    => $valorOriginal,
            'valor_atualizado'  => $valorAtualizado,
            'novo_vencimento'   => $novaData,
            'encargos'          => [
                'juros_mora'      => $jurosMora,
                'multa'           => $multa,
                'dias_extensao'   => $diasExtensao,
                'taxa_juros_mora' => $jurosMoraContrato,
                'taxa_multa'      => $multaContrato,
            ],
        ];
    }
}
