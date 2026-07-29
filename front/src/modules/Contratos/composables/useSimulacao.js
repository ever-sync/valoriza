/**
 * Composable para simulação de contratos.
 * 
 * TODA a lógica de cálculo financeiro reside no BACKEND (ContratoService).
 * Este composable apenas faz chamadas à API e gerencia o estado reativo.
 * 
 * Isso garante 100% de equivalência entre:
 * - Simulação exibida ao criar contrato
 * - Simulação exibida na tela de status
 * - Parcelas efetivamente lançadas no financeiro
 */
import { ref, computed, watch } from 'vue'
import { simularContrato } from '../services/contratos.service'
import { parsearValor, formatarReal } from '@/helpers/formatacao/valores.js'

/**
 * Hook para simulação de contratos via API backend.
 * 
 * @param {Object} options
 * @param {Ref} options.formulario - Ref com dados do contrato
 * @param {Ref} options.configuracoes - Ref das configurações (não usado no cálculo, apenas IOF labels)
 * @returns {Object} Dados da simulação e helpers
 */
export function useSimulacaoContrato(options = {}) {
  const { formulario = ref({}), configuracoes = ref(null) } = options

  // Estado da simulação retornada pelo backend
  const simulacaoData = ref(null)
  const simulacaoCarregando = ref(false)
  const simulacaoErro = ref(null)

  // Debounce timer
  let debounceTimer = null

  /**
   * Solicita a simulação ao backend.
   * Chamada com debounce para evitar excesso de requests.
   */
  const solicitarSimulacao = async () => {
    const f = formulario.value
    if (!f) return

    const valor = parsearValor(f.valor_solicitado)
    const taxa = parseFloat(String(f.taxa_juros || '').replace(',', '.').replace('%', '')) || 0
    const parcelas = parseInt(f.quantidade_parcelas) || 0
    const dataAssinatura = f.data_assinatura
    const dataPrimeira = f.data_primeira_parcela || f.data_assinatura

    // Não simular se os campos obrigatórios estão vazios
    if (!valor || !taxa || !parcelas || !dataAssinatura || !dataPrimeira) {
      simulacaoData.value = null
      simulacaoCarregando.value = false
      return
    }

    simulacaoErro.value = null

    try {
      const res = await simularContrato({
        tipo_operacao: f.tipo_operacao || 'Empréstimo',
        valor_solicitado: String(valor),
        taxa_juros: String(taxa),
        tipo_taxa: f.tipo_taxa || 'mensal',
        quantidade_parcelas: String(parcelas),
        modelo_amortizacao: f.modelo_amortizacao || 'Price',
        periodo_amortizacao: f.periodo_amortizacao || 'Mensal',
        data_assinatura: dataAssinatura,
        data_primeira_parcela: dataPrimeira,
        calcular_iof: f.calcular_iof ? 1 : 0,
        simples_nacional: f.simples_nacional ? 1 : 0,
        tipo_juros_pagamento_unico: f.tipo_juros_pagamento_unico || 'compostos',
      }, { showLoading: false, showToast: false })

      if (res?.success && res.data) {
        simulacaoData.value = res.data
      } else if (res?.data) {
        // Fallback: resposta direta
        simulacaoData.value = res.data
      }
    } catch (e) {
      simulacaoErro.value = e?.message || 'Erro na simulação'
      console.error('Erro ao simular contrato:', e)
    } finally {
      simulacaoCarregando.value = false
    }
  }

  /**
   * Solicita simulação com debounce (500ms).
   * Evita chamadas excessivas durante digitação.
   */
  const simularComDebounce = () => {
    if (debounceTimer) clearTimeout(debounceTimer)
    simulacaoCarregando.value = true // Set loading immediately
    debounceTimer = setTimeout(solicitarSimulacao, 500)
  }

  // Watcher: re-simular quando parâmetros mudam
  watch(
    () => {
      const f = formulario.value
      if (!f) return null
      return [
        f.valor_solicitado,
        f.taxa_juros,
        f.tipo_taxa,
        f.quantidade_parcelas,
        f.modelo_amortizacao,
        f.periodo_amortizacao,
        f.tipo_operacao,
        f.data_assinatura,
        f.data_primeira_parcela,
        f.calcular_iof,
        f.simples_nacional,
        f.tipo_juros_pagamento_unico
      ].join('|')
    },
    (novo, antigo) => {
      if (novo && novo !== antigo) {
        simularComDebounce()
      }
    },
    { immediate: true }
  )

  // ─── COMPUTEDS (derivados da resposta do backend) ───────────

  const cronograma = computed(() => {
    if (!simulacaoData.value?.parcelas) return []
    return simulacaoData.value.parcelas.map(p => ({
      num: p.num,
      parcela: String(p.parcela),
      rawParcela: parseFloat(p.parcela),
      vencimento: p.vencimento,
      vencimento_iso: p.vencimento_iso,
      juros: String(p.juros),
      rawJuros: parseFloat(p.juros),
      amortizacao: String(p.amortizacao),
      saldo: String(p.saldo),
    }))
  })

  const iofCalculado = computed(() => {
    const iof = simulacaoData.value?.iof
    if (!iof) return { diario: 0, adicional: 0, total: 0, ativo: false, taxa_diaria_pct: 0, taxa_adicional_pct: 0 }
    return {
      diario: parseFloat(iof.diario) || 0,
      adicional: parseFloat(iof.adicional) || 0,
      total: parseFloat(iof.total) || 0,
      ativo: (parseFloat(iof.total) || 0) > 0,
      taxa_diaria_pct: parseFloat(iof.taxa_diaria_pct) || 0,
      taxa_adicional_pct: parseFloat(iof.taxa_adicional_pct) || 0,
    }
  })

  const cet = computed(() => {
    const c = simulacaoData.value?.cet
    if (!c) return { mes: '0,00', ano: '0,00' }
    return {
      mes: typeof c.mes === 'number'
        ? c.mes.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : String(c.mes ?? '0,00'),
      ano: typeof c.ano === 'number'
        ? c.ano.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : String(c.ano ?? '0,00'),
    }
  })

  const valorTotalParcelas = computed(() => {
    return parseFloat(simulacaoData.value?.total_parcelas) || 0
  })

  const valorTotalJuros = computed(() => {
    return parseFloat(simulacaoData.value?.total_juros) || 0
  })

  const valorFinanciado = computed(() => {
    return parseFloat(simulacaoData.value?.valor_financiado) || 0
  })

  return {
    cronograma,
    iofCalculado,
    cet,
    valorTotalParcelas,
    valorTotalJuros,
    valorFinanciado,
    simulacaoCarregando,
    simulacaoErro,
    solicitarSimulacao,
    helpers: {
      formatarReal,
      parsearValor,
    }
  }
}

export default useSimulacaoContrato
