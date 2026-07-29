/**
 * Composable para gerenciar formulários de Contratos.
 * Contém toda a lógica de negócio relacionada a contratos,
 * incluindo gerenciamento de steps, cliente, garantias e simulação.
 * 
 * @example
 * import { useContrato } from '@/composables/useContrato'
 * import { createContrato, updateContrato, getContrato } from '../services/contratos.service'
 * import { getConfiguracoes } from '../services/configuracoes.service'
 * 
 * const props = defineProps({ initialData: { type: Object, default: null } })
 * const emit = defineEmits(['saved', 'cancel'])
 * 
 * const {
 *   etapaAtual, formulario, clienteSelecionado, garantias,
 *   podeAvançar, podeVoltar, buscarCliente, adicionarGarantia,
 *   proximoStep, anteriorStep, salvar
 * } = useContrato({ initialData: props.initialData })
 */
import { ref, computed, reactive, watch } from 'vue'
import { parsearValor, formatarDecimal } from '@/helpers/formatacao/valores.js'
import { calcularIof, calcularCet, gerarCronograma, parsearTaxa } from '@/helpers/financeiro'

/**
 * Cria um composable para gerenciamento de contratos.
 * 
 * @param {Object} opcoes - Opções de configuração
 * @param {Object} opcoes.initialData - Dados iniciais do formulário
 * @returns {Object} Estado e métodos do composable
 */
export function useContrato(opcoes = {}) {
  const { initialData = ref(null) } = opcoes

  // ============================================
  // CONSTANTES
  // ============================================

  const TOTAL_STEPS = 5

  const DADOS_PADRAO = {
    tipo_operacao: 'Empréstimo',
    valor_solicitado: '',
    quantidade_parcelas: 12,
    taxa_juros: '',
    modelo_amortizacao: 'Price',
    periodo_amortizacao: 'Mensal',
    data_assinatura: '',
    data_primeira_parcela: '',
    calcular_iof: true,
    simples_nacional: false
  }

  const GARANTIA_PADRAO = {
    tipo_garantia: 'avalista',
    nome_completo: '',
    cpf: '',
    rg: '',
    orgao_emissor_rg: '',
    email: '',
    telefone: '',
    renda_mensal: '',
    estado_civil: '',
    regime_bens: '',
    cep: '',
    estado: '',
    cidade: '',
    bairro: '',
    endereco: '',
    numero: '',
    complemento: ''
  }

  const CONFIG_PADRAO = {
    taxa_iof_diario: 0.000082,
    taxa_iof_adicional: 0.0038,
    taxa_juros_minima_sem_garantia: 0.03,
    taxa_juros_avalista: 0.025,
    taxa_juros_imovel: 0.02,
    taxa_juros_veiculo: 0.025,
    taxa_juros_outras_garantias: 0.03,
    quantidade_minima_parcelas: 1,
    quantidade_maxima_parcelas: 60
  }

  // ============================================
  // ESTADO - FORMULÁRIO PRINCIPAL
  // ============================================

  /** Etapa atual do formulário (1-5) */
  const etapaAtual = ref(1)

  /** Formulário de dados básicos do contrato */
  const formulario = ref({ ...DADOS_PADRAO })

  /** Configurações do sistema de contratos */
  const configuracoes = ref({ ...CONFIG_PADRAO })

  /** Estado de carregamento */
  const carregando = ref(false)

  // ============================================
  // ESTADO - CLIENTE
  // ============================================

  /** Cliente selecionado no step 2 */
  const clienteSelecionado = ref(null)

  /** Tipo do cliente (pf ou pj) */
  const tipoCliente = ref('')

  /** Label do cliente para display no DynamicSelect */
  const clienteLabel = ref('')

  // ============================================
  // ESTADO - GARANTIAS
  // ============================================

  /** Lista de garantias do contrato */
  const garantias = ref([])

  /** Garantias disponíveis */
  const tipoGarantiaSelecionada = reactive({
    sem_garantia: true,
    avalistas: false,
    imovel: false,
    veiculo: false,
    devedor_solidario: false,
    recebiveis: false,
    outras: false
  })

  // ============================================
  // ESTADO - SIMULAÇÃO
  // ============================================

  /** Dados da simulação */
  const simulacao = ref({
    cronograma: [],
    iof: { diario: 0, adicional: 0, total: 0 },
    cet: { mes: '0,00', ano: '0,00' },
    valorTotalParcelas: 0,
    valorTotalJuros: 0,
    valorFinanciado: 0
  })

  // ============================================
  // COMPUTED
  // ============================================

  /** Verifica se está em modo de edição */
  const modoEdicao = computed(() => !!formulario.value.id)

  /** Verifica se pode avançar para o próximo step */
  const podeAvançar = computed(() => etapaAtual.value < TOTAL_STEPS)

  /** Verifica se pode voltar para o step anterior */
  const podeVoltar = computed(() => etapaAtual.value > 1)

  /** Título do step atual */
  const tituloStep = computed(() => {
    const titulos = {
      1: 'Operação',
      2: 'Cliente',
      3: 'Garantias',
      4: 'Configurações',
      5: 'Revisão'
    }
    return titulos[etapaAtual.value] || ''
  })

  /** Taxa mínima de juros baseada no tipo de garantia */
  const taxaMinimaPorGarantia = computed(() => {
    if (!configuracoes.value) return null
    
    const parseTaxa = (valor) => parsearTaxa(valor)
    
    if (tipoGarantiaSelecionada.sem_garantia) return parseTaxa(configuracoes.value.taxa_juros_minima_sem_garantia)
    if (tipoGarantiaSelecionada.avalistas) return parseTaxa(configuracoes.value.taxa_juros_avalista)
    if (tipoGarantiaSelecionada.imovel) return parseTaxa(configuracoes.value.taxa_juros_imovel)
    if (tipoGarantiaSelecionada.veiculo) return parseTaxa(configuracoes.value.taxa_juros_veiculo)
    
    return parseTaxa(configuracoes.value.taxa_juros_outras_garantias)
  })

  /** Mapa de taxas mínimas por tipo de garantia */
  const taxasPorTipo = computed(() => {
    if (!configuracoes.value) return {}
    
    const parseTaxa = (valor) => parsearTaxa(valor)
    
    return {
      sem_garantia: parseTaxa(configuracoes.value.taxa_juros_minima_sem_garantia),
      avalistas: parseTaxa(configuracoes.value.taxa_juros_avalista),
      imovel: parseTaxa(configuracoes.value.taxa_juros_imovel),
      veiculo: parseTaxa(configuracoes.value.taxa_juros_veiculo),
      devedor_solidario: parseTaxa(configuracoes.value.taxa_juros_outras_garantias),
      recebiveis: parseTaxa(configuracoes.value.taxa_juros_outras_garantias),
      outras: parseTaxa(configuracoes.value.taxa_juros_outras_garantias)
    }
  })

  /** Label da taxa mínima para o tipo de garantia */
  const labelTaxaMinima = computed(() => {
    const taxa = taxaMinimaPorGarantia.value
    if (!taxa) return null
    return `Valor mínimo: ${(taxa * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}%`
  })

  // ============================================
  // MÉTODOS - NAVEGAÇÃO DE STEPS
  // ============================================

  /**
   * Avança para o próximo step.
   * 
   * @returns {void}
   */
  function próximoStep() {
    if (podeAvançar.value) {
      etapaAtual.value++
    }
  }

  /**
   * Volta para o step anterior.
   * 
   * @returns {void}
   */
  function anteriorStep() {
    if (podeVoltar.value) {
      etapaAtual.value--
    }
  }

  /**
   * Vai para um step específico.
   * 
   * @param {number} step - Número do step (1-5)
   * @returns {void}
   */
  function irParaStep(step) {
    if (step >= 1 && step <= TOTAL_STEPS) {
      etapaAtual.value = step
    }
  }

  // ============================================
  // MÉTODOS - CLIENTE
  // ============================================

  /**
   * Define o cliente selecionado.
   * 
   * @param {Object} cliente - Dados do cliente
   * @param {string} tipo - Tipo de cliente (pf ou pj)
   * @param {string} label - Label para display
   * @returns {void}
   */
  function definirCliente(cliente, tipo, label) {
    clienteSelecionado.value = cliente
    tipoCliente.value = tipo
    clienteLabel.value = label
    
    if (cliente?.id) {
      formulario.value.cliente_id = cliente.id
      formulario.value.tipo_cliente = tipo
    }
  }

  /**
   * Limpa o cliente selecionado.
   * 
   * @returns {void}
   */
  function limparCliente() {
    clienteSelecionado.value = null
    tipoCliente.value = ''
    clienteLabel.value = ''
    formulario.value.cliente_id = ''
    formulario.value.tipo_cliente = ''
  }

  // ============================================
  // MÉTODOS - GARANTIAS
  // ============================================

  /**
   * Alterna o tipo de garantia selecionado.
   * 
   * @param {string} tipo - Tipo de garantia
   * @returns {void}
   */
  function alternarTipoGarantia(tipo) {
    if (tipo === 'sem_garantia') {
      Object.keys(tipoGarantiaSelecionada).forEach(chave => {
        tipoGarantiaSelecionada[chave] = false
      })
      tipoGarantiaSelecionada.sem_garantia = true
    } else {
      tipoGarantiaSelecionada.sem_garantia = false
      tipoGarantiaSelecionada[tipo] = !tipoGarantiaSelecionada[tipo]
      
      const algumaAtiva = Object.entries(tipoGarantiaSelecionada)
        .some(([chave, valor]) => chave !== 'sem_garantia' && valor)
      
      if (!algumaAtiva) {
        tipoGarantiaSelecionada.sem_garantia = true
      }
    }
    
    // Atualiza taxa de juros para o mínimo do tipo de garantia
    const taxaMinima = taxaMinimaPorGarantia.value
    if (taxaMinima !== null) {
      const valorParaMascara = (taxaMinima * 100).toFixed(0)
      formulario.value.taxa_juros = (taxaMinima * 100).toFixed(2).replace('.', ',') + ' %'
    }
  }

  /**
   * Adiciona uma nova garantia à lista.
   * 
   * @param {Object} dados - Dados da garantia
   * @returns {void}
   */
  function adicionarGarantia(dados) {
    garantias.value.push({ ...GARANTIA_PADRAO, ...dados })
  }

  /**
   * Remove uma garantia da lista.
   * 
   * @param {number} indice - Índice da garantia a remover
   * @returns {void}
   */
  function removerGarantia(indice) {
    garantias.value.splice(indice, 1)
  }

  /**
   * Atualiza uma garantia existente.
   * 
   * @param {number} indice - Índice da garantia
   * @param {Object} dados - Novos dados
   * @returns {void}
   */
  function atualizarGarantia(indice, dados) {
    if (indice >= 0 && indice < garantias.value.length) {
      garantias.value[indice] = { ...garantias.value[indice], ...dados }
    }
  }

  // ============================================
  // MÉTODOS - SIMULAÇÃO
  // ============================================

  /**
   * Calcula a simulação do contrato (IOF, CET, cronograma).
   * 
   * @returns {void}
   */
  function executarSimulação() {
    const valor = parsearValor(formulario.value.valor_solicitado)
    const quantidadeParcelas = parseInt(formulario.value.quantidade_parcelas) || 0
    
    if (!valor || !quantidadeParcelas) {
      simulacao.value = {
        cronograma: [],
        iof: { diario: 0, adicional: 0, total: 0 },
        cet: { mes: '0,00', ano: '0,00' },
        valorTotalParcelas: 0,
        valorTotalJuros: 0,
        valorFinanciado: 0
      }
      return
    }

    // Parse da taxa de juros
    const taxaStr = String(formulario.value.taxa_juros || '').replace('%', '').trim()
    const taxaJuros = (/^-?\d+(\.\d+)?$/.test(taxaStr))
      ? parseFloat(taxaStr) / 100
      : (parseFloat(taxaStr.replace(',', '.')) || 0) / 100

    const modelo = formulario.value.modelo_amortizacao || 'Price'
    const periodo = formulario.value.periodo_amortizacao || 'Mensal'
    const dataBase = formulario.value.data_primeira_parcela || formulario.value.data_assinatura

    if (!dataBase) {
      simulacao.value = {
        cronograma: [],
        iof: { diario: 0, adicional: 0, total: 0 },
        cet: { mes: '0,00', ano: '0,00' },
        valorTotalParcelas: 0,
        valorTotalJuros: 0,
        valorFinanciado: 0
      }
      return
    }

    // Calcula IOF
    const iof = formulario.value.calcular_iof
      ? calcularIof({
          valor,
          quantidadeParcelas,
          taxaJuros,
          modelo,
          periodo,
          dataAssinatura: formulario.value.data_assinatura,
          dataPrimeiraParcela: dataBase,
          taxaIofDiario: configuracoes.value?.taxa_iof_diario,
          taxaIofAdicional: configuracoes.value?.taxa_iof_adicional
        })
      : { diario: 0, adicional: 0, total: 0 }

    // Valor financiado (valor + IOF)
    const valorFinanciado = valor + (formulario.value.calcular_iof ? iof.total : 0)

    // Gera cronograma
    const nEfetivo = periodo === 'Pagamento único' ? 1 : quantidadeParcelas
    const cronograma = gerarCronograma({
      valorFinanciado,
      quantidadeParcelas: nEfetivo,
      taxaJuros,
      modelo,
      periodo,
      dataBase
    })

    // Calcula CET
    const fluxos = cronograma.map(p => parseFloat(p.parcela))
    const cet = calcularCet(valorFinanciado, fluxos, periodo)

    // Calcula totais
    const valorTotalParcelas = cronograma.reduce((acc, p) => acc + parseFloat(p.parcela), 0)
    const valorTotalJuros = cronograma.reduce((acc, p) => acc + parseFloat(p.juros), 0)

    simulacao.value = {
      cronograma,
      iof,
      cet,
      valorTotalParcelas,
      valorTotalJuros,
      valorFinanciado
    }
  }

  // ============================================
  // MÉTODOS - FORMULÁRIO
  // ============================================

  /**
   * Preenche o formulário com dados do banco.
   * 
   * @param {Object} dados - Dados carregados
   * @returns {void}
   */
  function preencherComDados(dados) {
    if (!dados) {
      formulario.value = { ...DADOS_PADRAO }
      clienteSelecionado.value = null
      garantias.value = []
      return
    }

    // Preenche formulário básico
    formulario.value = {
      ...DADOS_PADRAO,
      ...dados,
      valor_solicitado: dados.valor_solicitado ? formatarDecimal(parsearValor(dados.valor_solicitado)) : '',
      taxa_juros: dados.taxa_juros ? String(dados.taxa_juros).replace('.', ',') + ' %' : ''
    }

    // Preenche cliente se existir
    if (dados.cliente_id) {
      clienteSelecionado.value = { id: dados.cliente_id }
      tipoCliente.value = dados.tipo_cliente
      clienteLabel.value = dados.cliente_nome || ''
    }

    // Preenche garantias se existirem
    if (dados.garantias && Array.isArray(dados.garantias)) {
      garantias.value = dados.garantias
    }

    // Atualiza tipos de garantia
    if (dados.tipos_garantia) {
      Object.keys(tipoGarantiaSelecionada).forEach(chave => {
        tipoGarantiaSelecionada[chave] = false
      })
      dados.tipos_garantia.forEach(tipo => {
        if (Object.prototype.hasOwnProperty.call(tipoGarantiaSelecionada, tipo)) {
          tipoGarantiaSelecionada[tipo] = true
        }
      })
    }

    // Executa simulação
    executarSimulação()
  }

  /**
   * Reseta o formulário para valores padrão.
   * 
   * @returns {void}
   */
  function resetarFormulario() {
    formulario.value = { ...DADOS_PADRAO }
    clienteSelecionado.value = null
    garantias.value = []
    etapaAtual.value = 1
    simulacao.value = {
      cronograma: [],
      iof: { diario: 0, adicional: 0, total: 0 },
      cet: { mes: '0,00', ano: '0,00' },
      valorTotalParcelas: 0,
      valorTotalJuros: 0,
      valorFinanciado: 0
    }
  }

  /**
   * Carrega as configurações do sistema.
   * 
   * @param {Object} config - Configurações carregadas
   * @returns {void}
   */
  function definirConfiguracoes(config) {
    if (config) {
      configuracoes.value = { ...CONFIG_PADRAO, ...config }
    }
  }

  /**
   * Extrai o payload para envio ao backend.
   * 
   * @returns {Object} Payload do contrato
   */
  function extrairPayload() {
    const payload = { ...formulario.value }
    
    // Converte valores monetários para número
    payload.valor_solicitado = parsearValor(formulario.value.valor_solicitado)
    
    // Parse da taxa de juros
    const taxaStr = String(formulario.value.taxa_juros || '').replace('%', '').trim()
    if (/^-?\d+(\.\d+)?$/.test(taxaStr)) {
      payload.taxa_juros = parseFloat(taxaStr) / 100
    } else {
      payload.taxa_juros = parseFloat(taxaStr.replace(',', '.')) / 100
    }
    
    payload.quantidade_parcelas = parseInt(formulario.value.quantidade_parcelas)
    payload.calcular_iof = formulario.value.calcular_iof ? 1 : 0
    payload.simples_nacional = formulario.value.simples_nacional ? 1 : 0

    // Adiciona dados do cliente
    if (clienteSelecionado.value?.id) {
      payload.cliente_id = clienteSelecionado.value.id
      payload.tipo_cliente = tipoCliente.value
    }

    // Adiciona garantias
    payload.garantias = garantias.value

    // Adiciona tipos de garantia ativos
    payload.tipos_garantia = Object.keys(tipoGarantiaSelecionada)
      .filter(chave => tipoGarantiaSelecionada[chave])

    return payload
  }

  // ============================================
  // WATCHERS
  // ============================================

  // Sincroniza dados iniciais com o formulário
  watch(initialData, (novoValor) => {
    if (novoValor) {
      preencherComDados(novoValor)
    } else {
      resetarFormulario()
    }
  }, { immediate: true })

  // Recalcula simulação quando campos relevantes mudam
  watch(
    () => [
      formulario.value.valor_solicitado,
      formulario.value.quantidade_parcelas,
      formulario.value.taxa_juros,
      formulario.value.modelo_amortizacao,
      formulario.value.periodo_amortizacao,
      formulario.value.data_primeira_parcela,
      formulario.value.data_assinatura,
      formulario.value.calcular_iof
    ],
    () => {
      executarSimulação()
    },
    { deep: true }
  )

  // ============================================
  // RETORNO PÚBLICO
  // ============================================

  return {
    // Estado - Navegação
    etapaAtual,
    totalSteps: TOTAL_STEPS,
    tituloStep,
    podeAvançar,
    podeVoltar,
    próximoStep,
    anteriorStep,
    irParaStep,

    // Estado - Formulário
    formulario,
    carregando,
    modoEdicao,
    dadosPadrao: DADOS_PADRAO,

    // Estado - Cliente
    clienteSelecionado,
    tipoCliente,
    clienteLabel,
    definirCliente,
    limparCliente,

    // Estado - Garantias
    garantias,
    tipoGarantiaSelecionada,
    adicionarGarantia,
    removerGarantia,
    atualizarGarantia,
    alternarTipoGarantia,
    taxaMinimaPorGarantia,
    taxasPorTipo,
    labelTaxaMinima,

    // Estado - Simulação
    simulacao,
    executarSimulação,

    // Configurações
    configuracoes,
    definirConfiguracoes,

    // Métodos
    preencherComDados,
    resetarFormulario,
    extrairPayload
  }
}

export default useContrato