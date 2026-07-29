/**
 * Composable para gerenciar formulários de Receitas.
 *
 * Responsável por:
 * - Estado do formulário principal (CRUD)
 * - Prorrogação de parcelas (atualiza a MESMA parcela com encargos)
 * - Pagamento parcial / carência de principal (este gera nova parcela)
 * - Cálculos de encargos (multa moratória, juros de mora)
 * - Bloqueio de edição de campos protegidos em receitas vinculadas a contrato
 *
 * Regras financeiras (CDC / Código Civil):
 * - Multa moratória: incide UMA VEZ sobre o valor original (máx 2% — CDC art. 52 §1°)
 * - Juros de mora: pro-rata diário simples = valor × (taxa/100) × (dias/30) (máx 1% a.m. — Código Civil art. 406)
 * - Prorrogação NÃO aplica juros de atualização ou juros compostos
 */
import { ref, computed, watch } from 'vue'
import { parsearValor, formatarDecimal, formatarData, diferencaDias, desformatarParaBanco } from '@/helpers/formatacao'

/** Estado compartilhado para serviços injetados (Singleton pattern para o módulo) */
let servicoProrrogacoesRef = null
let servicoSimularProrrogacaoRef = null

/**
 * Cria o composable de gerenciamento de receitas.
 *
 * @param {Object} opcoes - Opções de configuração
 * @param {import('vue').Ref} opcoes.initialData - Dados iniciais do formulário
 * @returns {Object} Estado reativo e métodos do composable
 */
export function useReceita(opcoes = {}) {
  const { initialData = ref(null) } = opcoes

  // ============================================
  // ESTADO — FORMULÁRIO PRINCIPAL
  // ============================================

  /** Estrutura padrão do formulário de receita */
  const dadosPadrao = {
    data_recebimento: '',
    data_vencimento: '',
    descricao: '',
    valor_recebido: '',
    conta_bancaria_destino_id: '',
    tipo_pagador: 'cadastrado',
    tipo_pessoa: '',
    pagador_id: '',
    nome_pagador_manual: '',
    tipo_comprovante_fiscal: '',
    numero_comprovante: '',
    categoria_id: '',
    forma_recebimento: '',
    status: 'Pendente',
    observacoes: '',
    parcela_numero: null,
    contrato_id: null
  }

  /** Formulário principal */
  const formulario = ref({ ...dadosPadrao })

  /** Estado de carregamento geral */
  const carregando = ref(false)

  /** Labels para selects dinâmicos */
  const pagadorLabel = ref('')
  const bancoLabel = ref('')

  // ============================================
  // ESTADO — PRORROGAÇÃO
  // ============================================

  /** Controle de visibilidade do modal de prorrogação */
  const mostrarProrrogacao = ref(false)

  /** Estado de carregamento da prorrogação */
  const prorrogacaoCarregando = ref(false)

  /** Lista de prorrogações da receita */
  const prorrogacoes = ref([])

  /** Controle de visibilidade do histórico */
  const mostrarHistorico = ref(false)

  /** Dados do contrato vinculado */
  const contratoVinculado = ref(null)

  /** Formulário de prorrogação (apenas data e justificativa) */
  const formularioProrrogacao = ref({
    data_vencimento_nova: '',
    justificativa: ''
  })

  // ============================================
  // ESTADO — PAGAMENTO PARCIAL
  // ============================================

  /** Controle de visibilidade do modal de pagamento parcial */
  const mostrarPagamentoParcial = ref(false)

  /** Estado de carregamento do pagamento parcial */
  const pagamentoParcialCarregando = ref(false)

  /** Formulário de pagamento parcial */
  const formularioPagamentoParcial = ref({
    valor_pago: '',
    data_pagamento: new Date().toISOString().split('T')[0],
    conta_bancaria_destino_id: ''
  })

  const bancoLabelParcial = ref('')

  // ============================================
  // COMPUTED — CONTROLES DE ESTADO
  // ============================================

  /** Modo de edição (receita já existente) */
  const modoEdicao = computed(() => !!formulario.value?.id)

  /** Receita vinculada a contrato (campos protegidos) */
  const ehVinculadoContrato = computed(() => !!formulario.value?.contrato_id)

  /** Pode prorrogar: edição + status pendente/atrasado */
  const podeProrogar = computed(() => {
    return modoEdicao.value && ['Pendente', 'Atrasado'].includes(formulario.value?.status)
  })

  /**
   * Pode pagar parcialmente:
   * - Receita em modo edição com status Pendente/Atrasado
   * - Contrato vinculado com carência habilitada
   * - Limite de carência não atingido
   */
  const podePagarParcial = computed(() => {
    if (!podeProrogar.value || !ehVinculadoContrato.value) return false
    if (!contratoVinculado.value) return false
    const permitido = parseInt(contratoVinculado.value.permitir_carencia) === 1
    if (!permitido) return false
    const limite = parseInt(contratoVinculado.value.limite_carencia) || 0
    const usos = parseInt(contratoVinculado.value.usos_carencia) || 0
    return limite === 0 || usos < limite
  })

  /** Usos restantes de carência */
  const usosCarenciaRestantes = computed(() => {
    if (!contratoVinculado.value) return 0
    const limite = parseInt(contratoVinculado.value.limite_carencia) || 0
    const usos = parseInt(contratoVinculado.value.usos_carencia) || 0
    return Math.max(0, limite - usos)
  })

  // ============================================
  // COMPUTED — CÁLCULOS DE ENCARGOS (PRORROGAÇÃO)
  // ============================================

  /**
   * Dias de extensão = do vencimento original até a NOVA data escolhida pelo usuário.
   * Reage dinamicamente ao campo data_vencimento_nova do formulário de prorrogação.
   * Ex: vencimento 01/05 → nova data 01/06 = 31 dias de extensão.
   */
  const diasAtraso = ref(0)
  const valorOriginal = computed(() => parsearValor(formulario.value?.valor_recebido))
  const jurosMora = ref(0)
  const multaAtraso = ref(0)
  const taxaJurosMora = ref(1) // Default label
  const taxaMulta = ref(2)     // Default label
  const valorSimulado = ref(null)
  
  /** Valor final devido: valor simulado ou valor original como fallback */
  const valorDevido = computed(() => {
    if (valorSimulado.value !== null) return valorSimulado.value
    return valorOriginal.value
  })

  const carregandoSimulacaoProrrogacao = ref(false)

  let simulacaoTimer = null
  watch(() => [formularioProrrogacao.value.data_vencimento_nova, formulario.value?.id], ([novaData, id]) => {
    // Resetar valores se não houver data ou ID
    if (!novaData || !id) {
      diasAtraso.value = 0
      jurosMora.value = 0
      multaAtraso.value = 0
      valorSimulado.value = null
      return
    }

    if (simulacaoTimer) clearTimeout(simulacaoTimer)
    
    simulacaoTimer = setTimeout(async () => {
      const servico = servicoSimularProrrogacaoRef
      if (!servico) {
        console.warn('[useReceita] Serviço de simulação não configurado.')
        return
      }

      carregandoSimulacaoProrrogacao.value = true
      
      try {
        const resposta = await servico(id, { data_vencimento_nova: novaData }, { showLoading: false, showToast: false })
        
        if (resposta?.success && resposta.data) {
          const dadosSimulacao = Array.isArray(resposta.data) ? resposta.data[0] : resposta.data
          
          diasAtraso.value = dadosSimulacao.encargos?.dias_extensao || 0
          jurosMora.value = dadosSimulacao.encargos?.juros_mora || 0
          multaAtraso.value = dadosSimulacao.encargos?.multa || 0
          taxaJurosMora.value = dadosSimulacao.encargos?.taxa_juros_mora || 1
          taxaMulta.value = dadosSimulacao.encargos?.taxa_multa || 2
          valorSimulado.value = dadosSimulacao.valor_atualizado || null
        } else {
          valorSimulado.value = null
        }
      } catch (error) {
        console.error('[useReceita] Erro ao simular prorrogação:', error)
        valorSimulado.value = null
      } finally {
        carregandoSimulacaoProrrogacao.value = false
      }
    }, 500)
  }, { deep: true })

  // ============================================
  // COMPUTED — CÁLCULOS PAGAMENTO PARCIAL
  // ============================================

  /**
   * Valor mínimo aceito = juros da parcela (carência de principal).
   * Prioriza o valor_juros da simulação (tbl_contratos_parcelas via JOIN).
   * Se não houver, calcula em tempo real com a taxa do contrato.
   */
  const valorMinimoCarencia = computed(() => {
    // Priorizar valor vindo do banco (JOIN com tbl_contratos_parcelas)
    if (formulario.value?.valor_juros_parcela) {
      return Math.round(parseFloat(formulario.value.valor_juros_parcela) * 100) / 100
    }
    // Fallback: calcular com taxa do contrato
    if (!contratoVinculado.value) return 0
    const taxa = parsearValor(contratoVinculado.value.taxa_juros) / 100
    return Math.round(valorOriginal.value * taxa * 100) / 100
  })

  /** Saldo residual = valor original - valor pago */
  const saldoResidualParcial = computed(() => {
    const pago = parsearValor(formularioPagamentoParcial.value.valor_pago)
    return Math.max(0, valorOriginal.value - pago)
  })

  // ============================================
  // COMPUTED — CAMPO "VALOR PAGO" NO FORMULÁRIO PRINCIPAL
  // ============================================

  /** Campo reativo para o valor pago (formulário principal) */
  const valorPago = ref('')

  /** Valor do pagamento parseado */
  const valorPagoParsed = computed(() => parsearValor(valorPago.value))

  /** Verifica se é um pagamento parcial (menor que o original) */
  const ehPagamentoParcial = computed(() => {
    if (!ehVinculadoContrato.value || valorPagoParsed.value <= 0) return false
    return valorPagoParsed.value < valorOriginal.value
  })

  /** Verifica se o valor pago está abaixo do mínimo (juros da parcela) */
  const valorPagoAbaixoMinimo = computed(() => {
    if (!ehVinculadoContrato.value || valorPagoParsed.value <= 0) return false
    return valorPagoParsed.value < valorMinimoCarencia.value
  })

  /** Saldo residual do pagamento no formulário principal */
  const saldoResidualFormulario = computed(() => {
    if (valorPagoParsed.value <= 0) return 0
    return Math.max(0, valorOriginal.value - valorPagoParsed.value)
  })

  /** Mensagem de alerta dinâmica para o campo de pagamento */
  const alertaPagamento = computed(() => {
    if (!ehVinculadoContrato.value || valorPagoParsed.value <= 0) return null

    if (valorPagoAbaixoMinimo.value) {
      return {
        tipo: 'erro',
        mensagem: `Valor abaixo do mínimo. O pagamento mínimo aceito é ${formatarMoedaLocal(valorMinimoCarencia.value)} (juros da parcela).`
      }
    }

    if (ehPagamentoParcial.value) {
      const saldo = saldoResidualFormulario.value
      return {
        tipo: 'aviso',
        mensagem: `Pagamento parcial detectado. Será criada uma nova parcela com saldo residual de ${formatarMoedaLocal(saldo)}.`,
        detalhe: contratoVinculado.value?.permitir_carencia
          ? `Carência disponível (${usosCarenciaRestantes.value} uso(s) restante(s)).`
          : 'Atenção: O contrato não permite carência de principal.'
      }
    }

    if (valorPagoParsed.value >= valorOriginal.value) {
      return {
        tipo: 'sucesso',
        mensagem: 'Pagamento integral. A parcela será quitada normalmente.'
      }
    }

    return null
  })

  /** Formata valor como moeda brasileira */
  const formatarMoedaLocal = (valor) => {
    return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
  }

  // ============================================
  // MÉTODOS — FORMULÁRIO
  // ============================================

  /**
   * Preenche o formulário com dados carregados do backend.
   * @param {Object} dados Dados da receita
   */
  function preencherComDados(dados) {
    if (!dados) {
      formulario.value = { ...dadosPadrao }
      pagadorLabel.value = ''
      bancoLabel.value = ''
      prorrogacoes.value = []
      return
    }

    const formatarValorMonetario = (valor) => valor ? formatarDecimal(parsearValor(valor)) : ''

    formulario.value = {
      ...dadosPadrao,
      ...dados,
      valor_recebido: formatarValorMonetario(dados.valor_recebido),
      tipo_pagador: (dados.tipo_pagador === 'sistema' || !dados.tipo_pagador) ? 'cadastrado' : dados.tipo_pagador
    }

    pagadorLabel.value = dados.pagador_nome || ''
    bancoLabel.value = dados.conta_nome || ''


  }

  /** Reseta o formulário para valores padrão */
  function resetarFormulario() {
    formulario.value = { ...dadosPadrao }
    pagadorLabel.value = ''
    bancoLabel.value = ''
    prorrogacoes.value = []
    contratoVinculado.value = null
  }

  /** Limpa a seleção de pagador ao mudar tipo */
  function limparPagador() {
    formulario.value.pagador_id = ''
    pagadorLabel.value = ''
  }

  /**
   * Converte o formulário para payload de envio ao backend.
   * @returns {Object} Payload limpo com campos válidos
   */
  function extrairPayload() {
    const chavesValidas = Object.keys(dadosPadrao)
    const payload = {}

    Object.entries(formulario.value).forEach(([chave, valor]) => {
      if (chavesValidas.includes(chave)) {
        const camposMonetarios = ['valor_recebido']
        payload[chave] = camposMonetarios.includes(chave) ? desformatarParaBanco(valor) : valor
      }
    })

    return payload
  }

  // ============================================
  // MÉTODOS — PRORROGAÇÃO
  // ============================================

  /**
   * Carrega o histórico de prorrogações de uma receita.
   * @param {number} receitaId ID da receita
   */
  async function carregarProrrogações(receitaId) {
    if (!receitaId) return
    const servico = servicoProrrogacoesRef
    if (!servico) return

    try {
      const resposta = await servico(receitaId)
      if (resposta?.success) prorrogacoes.value = resposta.data || []
    } catch (erro) {
      console.error('Erro ao carregar prorrogações:', erro)
      prorrogacoes.value = []
    }
  }

  /**
   * Abre o modal de prorrogação e carrega o contrato vinculado.
   * @param {Function} servicoContrato Função para buscar contrato por ID
   */
  async function abrirProrrogacao(servicoContrato) {
    formularioProrrogacao.value = { data_vencimento_nova: '', justificativa: '' }
    mostrarProrrogacao.value = true

    if (formulario.value?.id && !contratoVinculado.value) {
      try {
        const resposta = await servicoContrato(formulario.value.id)
        if (resposta?.success) {
          const dados = Array.isArray(resposta.data) ? resposta.data[0] : resposta.data?.data || resposta.data
          contratoVinculado.value = dados
        }
      } catch (erro) {
        console.warn('Contrato não carregado:', erro)
      }
    }
  }

  /** Fecha o modal de prorrogação */
  function fecharProrrogacao() {
    mostrarProrrogacao.value = false
    formularioProrrogacao.value = { data_vencimento_nova: '', justificativa: '' }
  }

  /**
   * Salva uma prorrogação.
   * O payload envia apenas data_vencimento_nova e justificativa.
   * O backend calcula o novo valor automaticamente.
   *
   * @param {Function} servicoSalvar Função de API para prorrogar
   * @returns {Promise<boolean>} Sucesso da operação
   */
  async function salvarProrrogacao(servicoSalvar) {
    if (!formularioProrrogacao.value.data_vencimento_nova) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Informe a nova data de vencimento.' } }))
      return false
    }
    if (!formularioProrrogacao.value.justificativa?.trim()) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'A justificativa é obrigatória.' } }))
      return false
    }

    prorrogacaoCarregando.value = true

    try {
      const payload = {
        data_vencimento_nova: formularioProrrogacao.value.data_vencimento_nova,
        justificativa: formularioProrrogacao.value.justificativa
      }

      const resposta = await servicoSalvar(formulario.value.id, payload)

      if (resposta?.success) {
        fecharProrrogacao()
        return true
      }
      return false
    } catch (erro) {
      return false
    } finally {
      prorrogacaoCarregando.value = false
    }
  }

  // ============================================
  // MÉTODOS — PAGAMENTO PARCIAL
  // ============================================

  /**
   * Abre o modal de pagamento parcial e carrega o contrato vinculado.
   * @param {Function} servicoContrato Função para buscar contrato por ID
   */
  async function abrirPagamentoParcial(servicoContrato) {
    formularioPagamentoParcial.value = {
      valor_pago: '',
      data_pagamento: new Date().toISOString().split('T')[0]
    }
    mostrarPagamentoParcial.value = true

    if (formulario.value?.id && !contratoVinculado.value) {
      try {
        const resposta = await servicoContrato(formulario.value.id)
        if (resposta?.success) {
          const dados = Array.isArray(resposta.data) ? resposta.data[0] : resposta.data?.data || resposta.data
          contratoVinculado.value = dados
        }
      } catch (erro) {
        console.warn('Contrato não carregado:', erro)
      }
    }
  }

  /** Fecha o modal de pagamento parcial */
  function fecharPagamentoParcial() {
    mostrarPagamentoParcial.value = false
    formularioPagamentoParcial.value = { valor_pago: '', data_pagamento: '' }
  }

  /**
   * Envia o pagamento parcial ao backend.
   *
   * @param {Function} servicoSalvar Função de API para pagar parcial
   * @returns {Promise<boolean>} Sucesso da operação
   */
  async function salvarPagamentoParcial(servicoSalvar) {
    const valorPago = parsearValor(formularioPagamentoParcial.value.valor_pago)

    if (!valorPago || valorPago <= 0) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Informe o valor pago.' } }))
      return false
    }

    if (valorPago < valorMinimoCarencia.value) {
      window.dispatchEvent(new CustomEvent('toast', {
        detail: {
          type: 'error',
          message: `O valor mínimo aceito é R$ ${formatarDecimal(valorMinimoCarencia.value)} (juros da parcela).`
        }
      }))
      return false
    }

    if (valorPago >= valorOriginal.value) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'O valor é igual ou superior à parcela. Use a quitação normal.' } }))
      return false
    }

    pagamentoParcialCarregando.value = true

    try {
      const payload = {
        valor_pago: desformatarParaBanco(formularioPagamentoParcial.value.valor_pago),
        data_pagamento: formularioPagamentoParcial.value.data_pagamento,
        conta_bancaria_destino_id: formularioPagamentoParcial.value.conta_bancaria_destino_id
      }

      const resposta = await servicoSalvar(formulario.value.id, payload)

      if (resposta?.success) {
        fecharPagamentoParcial()
        return true
      }
      return false
    } catch (erro) {
      return false
    } finally {
      pagamentoParcialCarregando.value = false
    }
  }

  // ============================================
  // WATCHERS
  // ============================================

  /** Sincroniza dados iniciais com o formulário */
  watch(initialData, (novoValor) => {
    if (novoValor) preencherComDados(novoValor)
    else resetarFormulario()
  }, { immediate: true })

  // ============================================
  // RETORNO PÚBLICO
  // ============================================

  return {
    // Estado — Formulário
    formulario, carregando, modoEdicao, pagadorLabel, bancoLabel, dadosPadrao,

    // Estado — Controles
    ehVinculadoContrato,

    // Estado — Prorrogação
    formularioProrrogacao, mostrarProrrogacao, prorrogacaoCarregando,
    prorrogacoes, mostrarHistorico, contratoVinculado, podeProrogar,

    // Estado — Pagamento Parcial
    formularioPagamentoParcial, mostrarPagamentoParcial, pagamentoParcialCarregando,
    podePagarParcial, usosCarenciaRestantes, valorMinimoCarencia, saldoResidualParcial,
    bancoLabelParcial,

    // Estado — Campo Valor Pago (formulário principal)
    valorPago, ehPagamentoParcial, valorPagoAbaixoMinimo,
    saldoResidualFormulario, alertaPagamento,

    // Estado — Encargos (prorrogação calculada via backend)
    diasAtraso, valorOriginal, jurosMora, multaAtraso, taxaJurosMora, taxaMulta, valorDevido, carregandoSimulacaoProrrogacao,

    // Métodos — Formulário
    preencherComDados, resetarFormulario, limparPagador, extrairPayload,

    // Métodos — Prorrogação
    carregarProrrogações, abrirProrrogacao, fecharProrrogacao, salvarProrrogacao,

    // Métodos — Pagamento Parcial
    abrirPagamentoParcial, fecharPagamentoParcial, salvarPagamentoParcial
  }
}

/**
 * Configura serviços externos para o composable.
 * @param {Object} servicos Objeto com funções de API
 */
useReceita.configurarServicos = function(servicos) {
  if (servicos?.getProrrogacoes) servicoProrrogacoesRef = servicos.getProrrogacoes
  if (servicos?.simularProrrogacao) servicoSimularProrrogacaoRef = servicos.simularProrrogacao
  if (servicos?.simularProrrogacaoReceita) servicoSimularProrrogacaoRef = servicos.simularProrrogacaoReceita
}

export default useReceita