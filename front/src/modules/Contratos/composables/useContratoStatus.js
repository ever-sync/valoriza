/**
 * Composable para a tela de Status de Execução de um contrato.
 * 
 * Responsável por:
 * - Carregar dados do contrato, garantias, parcelas e configurações
 * - Exibir a simulação usando o composable centralizado (API backend)
 * - Gerenciar ações do usuário (lançamento financeiro, exclusão)
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getContrato,
  getContratoGarantias,
  deleteContrato,
  getContratoParcelas,
  lancarParcelasContrato,
  enviarParaCRDC
} from '../services/contratos.service'
import { getConfiguracoes } from '@/modules/Configuracoes/services/configuracoesContratos.service'
import { applyMask } from '@/helpers/masks'
import { useSimulacaoContrato } from './useSimulacao'

export function useContratoStatus() {
  const rota = useRoute()
  const roteador = useRouter()
  const idContrato = computed(() => rota.params.id)

  // ===================================================
  // Estado
  // ===================================================

  const carregando = ref(true)
  const contrato = ref(null)
  const garantias = ref([])
  const parcelasLancadas = ref([])
  const configuracoes = ref(null)

  /** Controle de modais */
  const modalExclusao = ref(false)
  const modalLancamento = ref(false)
  const modalFeedback = ref(false)
  const mensagemFeedback = ref('')
  const tipoFeedback = ref('success')
  const processandoLancamento = ref(false)
  const processandoCRDC = ref(false)

  // ===================================================
  // Carregamento inicial
  // ===================================================

  const carregar = async () => {
    try {
      const [resContrato, resGarantias, resParcelas, resConfig] = await Promise.all([
        getContrato(idContrato.value, { showLoading: false }),
        getContratoGarantias(idContrato.value, { showLoading: false }),
        getContratoParcelas(idContrato.value, { showLoading: false }),
        getConfiguracoes({ por_pagina: 1 }, { showLoading: false })
      ])

      if (resContrato?.success) {
        const dados = resContrato.data?.data ? resContrato.data.data : resContrato.data
        contrato.value = Array.isArray(dados) ? dados[0] : dados
      }
      if (resGarantias?.success) {
        garantias.value = resGarantias.data?.data ? resGarantias.data.data : resGarantias.data
      }
      if (resParcelas?.success) {
        parcelasLancadas.value = resParcelas.data?.data ? resParcelas.data.data : resParcelas.data
      }
      if (resConfig?.success) {
        const cfg = resConfig.data?.data || resConfig.data || []
        if (cfg.length) configuracoes.value = cfg[0]
      }
    } catch (erro) {
      console.error('Erro ao carregar contrato:', erro)
    } finally {
      carregando.value = false
    }
  }

  onMounted(carregar)

  // Recarregar quando navegar para outro contrato (mesmo componente reutilizado)
  watch(idContrato, (novoId, antigoId) => {
    if (novoId && novoId !== antigoId) {
      carregando.value = true
      contrato.value = null
      garantias.value = []
      parcelasLancadas.value = []
      carregar()
    }
  })

  // ===================================================
  // Helpers de formatação
  // ===================================================

  const formatarMoeda = (v) => {
    return (parseFloat(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
  }

  const formatarData = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '-'

  const valorSolicitadoFormatado = computed(() => contrato.value ? formatarMoeda(contrato.value.valor_solicitado) : '')
  const taxaJurosFormatada = computed(() => {
    if (!contrato.value) return ''
    const val = parseFloat(contrato.value.taxa_juros) || 0
    return val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %'
  })

  // ===================================================
  // Simulação — usa o MESMO motor do backend via API
  // ===================================================
  // IMPORTANTE: Não duplicar lógica de simulação aqui.
  // Usar o composable centralizado garante 100% de equivalência
  // entre os valores exibidos na criação e na tela de status.

  const {
    cronograma: cronogramaSimulacao,
    iofCalculado: iofSimulacao,
    cet: cetSimulacao,
    valorTotalParcelas,
    valorFinanciado: valorFinanciadoSimulacao,
    simulacaoCarregando
  } = useSimulacaoContrato({ formulario: contrato, configuracoes })

  /** Objeto de simulação no formato esperado pelo template */
  const simulacao = computed(() => {
    if (!contrato.value || !cronogramaSimulacao.value?.length) return null

    return {
      parcelas: cronogramaSimulacao.value,
      iof: {
        diario: iofSimulacao.value.diario,
        adicional: iofSimulacao.value.adicional,
        total: iofSimulacao.value.total,
        ativo: iofSimulacao.value.ativo
      },
      totalAReceber: valorTotalParcelas.value,
      cet: cetSimulacao.value
    }
  })

  // ===================================================
  // Ações do usuário
  // ===================================================

  const excluirContrato = async () => {
    try {
      const res = await deleteContrato(idContrato.value)
      if (res.success) roteador.push('/contratos')
    } catch (erro) { console.error('Erro ao excluir:', erro) }
  }

  const lancarFinanceiro = async () => {
    if (processandoLancamento.value) return
    processandoLancamento.value = true
    try {
      const res = await lancarParcelasContrato(idContrato.value)
      modalLancamento.value = false
      mensagemFeedback.value = res.message || 'Operação realizada!'
      tipoFeedback.value = res.success ? 'success' : 'error'
      modalFeedback.value = true

      if (res.success) {
        const resP = await getContratoParcelas(idContrato.value)
        if (resP.success) parcelasLancadas.value = resP.data
      }
    } catch (erro) {
      mensagemFeedback.value = 'Erro de conexão.'
      tipoFeedback.value = 'error'
      modalFeedback.value = true
    } finally {
      processandoLancamento.value = false
    }
  }

  /** Verifica se as parcelas já foram lançadas como receitas */
  const jaLancado = computed(() => {
    return Array.isArray(parcelasLancadas.value) && parcelasLancadas.value.length > 0
  })

  const enviarCRDC = async () => {
    if (processandoCRDC.value) return
    processandoCRDC.value = true
    try {
      const res = await enviarParaCRDC(idContrato.value)
      mensagemFeedback.value = res.message || 'Contrato enviado para CRDC com sucesso!'
      tipoFeedback.value = res.success ? 'success' : 'error'
      modalFeedback.value = true
    } catch (erro) {
      mensagemFeedback.value = 'Erro ao conectar com CRDC.'
      tipoFeedback.value = 'error'
      modalFeedback.value = true
    } finally {
      processandoCRDC.value = false
    }
  }

  return {
    // Estado
    carregando: computed(() => carregando.value || simulacaoCarregando.value), 
    contrato, garantias, parcelasLancadas, configuracoes,

    // Modais e Loadings
    modalExclusao, modalLancamento, modalFeedback,
    mensagemFeedback, tipoFeedback, processandoLancamento, processandoCRDC,

    // Formatação
    formatarMoeda, formatarData, valorSolicitadoFormatado, taxaJurosFormatada, applyMask,

    // Simulação
    simulacao,

    // Ações
    excluirContrato, lancarFinanceiro, enviarCRDC, jaLancado,

    // Navegação
    roteador
  }
}
