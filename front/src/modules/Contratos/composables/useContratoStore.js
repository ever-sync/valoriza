/**
 * Store centralizado do formulário de contratos.
 * 
 * Mantém todo o estado reativo compartilhado entre as etapas,
 * evitando perda de dados ao navegar entre os steps do wizard.
 * 
 * Uso: importar este composable em cada etapa e no ContratoForm.
 * O estado é singleton por instância de composable chamada no mesmo escopo reativo.
 */
import { ref, reactive, computed, watch } from 'vue'
import { parsearValor, desformatarParaBanco } from '@/helpers/formatacao/valores.js'
import { parsearTaxa } from '@/helpers/financeiro'
import { getPessoasFisicas, createPessoaFisica } from '@/modules/PessoasFisicas/services/pessoasFisicas.service'
import { getPessoasJuridicas, createPessoaJuridica } from '@/modules/PessoasJuridicas/services/pessoasJuridicas.service'
import { useSimulacaoContrato } from './useSimulacao'
import { consultarCEP, consultarCNPJ } from '@/helpers/Integracoes/consulta'

/**
 * Cria e retorna o store centralizado do contrato.
 * Deve ser chamado UMA VEZ no componente pai (ContratoForm)
 * e então passado via provide/inject ou props para os filhos.
 */
export function criarContratoStore(opcoesIniciais = {}) {

  // ===================================================
  // ETAPA 1 — Operação
  // ===================================================

  const operacao = ref({
    tipo_operacao: 'Empréstimo',
    valor_solicitado: '',
    periodo_amortizacao: '',
    modelo_amortizacao: 'Price',
    taxa_juros: '',
    tipo_taxa: 'mensal',
    quantidade_parcelas: '',
    data_assinatura: new Date().toISOString().split('T')[0],
    data_primeira_parcela: '',
    simples_nacional: 0,
    calcular_iof: 0,
    tipo_juros_pagamento_unico: 'compostos',
  })

  /** Seleção de tipos de garantia (toggles visuais na etapa 1) */
  const garantiaSelecionada = reactive({
    sem_garantia: true,
    avalistas: false,
    imovel: false,
    veiculo: false,
    devedor_solidario: false,
    recebiveis: false,
    outras: false
  })

  /** Constantes de operação */
  const TIPO_OPERACAO = ['Empréstimo', 'Financiamento', 'Desconto de Recebíveis']
  const PERIODO_AMORTIZACAO = ['Diário', 'Semanal', 'Mensal', 'Pagamento único']
  const MODELO_AMORTIZACAO = ['Price', 'Sistema americano', 'SAC']

  /**
   * Modelos de amortização disponíveis conforme periodicidade selecionada.
   * Matriz de compatibilidade FEBRABAN:
   *   Mensal  → Price, SAC, Americano
   *   Semanal → Price, Americano
   *   Diário  → Price, Americano
   *   Pagamento único → nenhum (usa juros simples/compostos)
   */
  const modelosDisponiveis = computed(() => {
    const periodo = operacao.value.periodo_amortizacao
    const matriz = {
      'Mensal':  ['Price', 'SAC', 'Sistema americano'],
      'Semanal': ['Price', 'Sistema americano'],
      'Diário':  ['Price', 'Sistema americano'],
    }
    return matriz[periodo] || MODELO_AMORTIZACAO
  })

  const tiposGarantiaNomes = {
    avalistas: 'Avalista',
    imovel: 'Imóvel',
    veiculo: 'Veículo',
    devedor_solidario: 'Devedor solidário',
    recebiveis: 'Recebível',
    outras: 'Outro'
  }

  /** Mapeamento inverso para recuperar a chave a partir do nome */
  const mapaToggleTipo = {
    avalistas: 'Avalista', imovel: 'Imóvel', veiculo: 'Veículo',
    devedor_solidario: 'Devedor solidário', recebiveis: 'Recebível', outras: 'Outro'
  }
  const mapaInversoTipo = Object.fromEntries(Object.entries(mapaToggleTipo).map(([k, v]) => [v, k]))

  // ===================================================
  // ETAPA 2 — Cliente
  // ===================================================

  const cliente = ref({
    tipo_cliente: 'pj',
    cliente_id: '',
    cliente_nome: ''
  })

  const clienteLabel = ref('')
  const mostrarModalCliente = ref(false)
  const modalClienteCarregando = ref(false)
  const modalClienteFormulario = ref({})
  const carregandoBuscaModal = ref(false)

  const chaveNomeCliente = computed(() => cliente.value.tipo_cliente === 'pf' ? 'nome_completo' : 'razao_social')

  // ===================================================
  // ETAPA 3 — Garantias
  // ===================================================

  const garantias = ref([])
  const recuperandoGarantias = ref(false)

  const estadosCivis = ['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Viúvo(a)', 'União Estável']
  const regimesBens = ['Comunhão Parcial', 'Comunhão Universal', 'Separação Total', 'Participação Final nos Aquestos']
  const tiposGarantiaOpcoes = ['Avalista', 'Devedor solidário', 'Imóvel', 'Veículo', 'Recebível', 'Outro']
  const tiposRecebivel = ['Duplicata', 'Cheque', 'Nota Promissória', 'Boleto', 'Contrato', 'Outro']

  // ===================================================
  // ETAPA 4 — Inadimplência
  // ===================================================

  const inadimplencia = ref({
    juros_mora: '1',
    multa_moratoria: '',
    risco_inadimplencia: 'Baixo',
    permitir_carencia: 0,
    limite_carencia: ''
  })

  const riscos = ['Baixo', 'Médio', 'Alto', 'Muito Alto']

  // ===================================================
  // ETAPA 5 — Conclusão
  // ===================================================

  const conclusao = ref({
    tipo_assinatura: 'sem_assinatura',
    enviar_registradora: 0,
    contrato_iniciado: 0,
    status: 'Ativo'
  })

  const declaracaoAssinada = ref(false)

  // ===================================================
  // Configurações do sistema
  // ===================================================

  const configuracoes = ref(null)

  // ===================================================
  // MÉTODOS — Operação (Etapa 1)
  // ===================================================

  /** Alterna o tipo de garantia selecionado */
  const alternarTipoGarantia = (tipo) => {
    if (tipo === 'sem_garantia') {
      Object.keys(garantiaSelecionada).forEach(k => garantiaSelecionada[k] = false)
      garantiaSelecionada.sem_garantia = true
    } else {
      garantiaSelecionada.sem_garantia = false
      garantiaSelecionada[tipo] = !garantiaSelecionada[tipo]
      const algumaAtiva = Object.entries(garantiaSelecionada).some(([k, v]) => k !== 'sem_garantia' && v)
      if (!algumaAtiva) garantiaSelecionada.sem_garantia = true
    }
    // Atualiza taxa mínima se necessário
    const min = taxaMinimaPorGarantia.value
    if (min !== null) {
      operacao.value.taxa_juros = (min * 100).toFixed(0)
    }
  }

  /** Alterna campo booleano (switches) */
  const alternarSwitch = (objeto, campo) => {
    objeto.value[campo] = objeto.value[campo] ? 0 : 1
  }

  /** Próxima data de referência para primeira parcela */
  const proximaDataRef = (dataStr, periodo = 'Mensal') => {
    if (!dataStr) return ''
    const d = new Date(dataStr + 'T00:00:00')
    if (periodo === 'Semanal') d.setDate(d.getDate() + 7)
    else if (periodo === 'Diário') d.setDate(d.getDate() + 1)
    else d.setMonth(d.getMonth() + 1)
    return d.toISOString().split('T')[0]
  }

  /** Watcher: auto-preencher data da primeira parcela */
  watch([() => operacao.value.data_assinatura, () => operacao.value.periodo_amortizacao], ([data, periodo]) => {
    if (data && !opcoesIniciais.dadosIniciais?.id) {
      operacao.value.data_primeira_parcela = proximaDataRef(data, periodo)
    }
  })

  /**
   * Computed: Verifica se é pagamento único.
   * Pagamento único ou Desconto de Recebíveis forçam n=1.
   */
  const ehPagamentoUnico = computed(() => 
    operacao.value.periodo_amortizacao === 'Pagamento único' || 
    operacao.value.tipo_operacao === 'Desconto de Recebíveis'
  )

  /** Computed: Verifica se é operação de desconto de recebíveis */
  const ehDescontoRecebiveis = computed(() => operacao.value.tipo_operacao === 'Desconto de Recebíveis')

  /** Watcher: Pagamento único → forçar n=1 */
  watch(() => operacao.value.periodo_amortizacao, (novoPeriodo) => {
    if (novoPeriodo === 'Pagamento único') {
      operacao.value.quantidade_parcelas = 1
    }
  })

  /** Watcher: Desconto de Recebíveis → forçar Pagamento único e ocultar modelos */
  watch(() => operacao.value.tipo_operacao, (novoTipo) => {
    if (novoTipo === 'Desconto de Recebíveis') {
      operacao.value.periodo_amortizacao = 'Pagamento único'
      operacao.value.modelo_amortizacao = 'Price'
      operacao.value.quantidade_parcelas = 1
    }
  })

  /** Watcher: Corrige modelo quando periodicidade muda (matriz de compatibilidade) */
  watch(() => operacao.value.periodo_amortizacao, (novoPeriodo) => {
    if (novoPeriodo === 'Pagamento único') return
    const permitidos = modelosDisponiveis.value
    if (!permitidos.includes(operacao.value.modelo_amortizacao)) {
      operacao.value.modelo_amortizacao = permitidos[0] || 'Price'
    }
  })

  /** Taxa mínima calculada com base na garantia selecionada */
  const taxaMinimaPorGarantia = computed(() => {
    if (!configuracoes.value) return null
    const p = (v) => parsearTaxa(v)
    if (garantiaSelecionada.sem_garantia) return p(configuracoes.value.taxa_juros_minima_sem_garantia)
    if (garantiaSelecionada.avalistas) return p(configuracoes.value.taxa_juros_avalista)
    if (garantiaSelecionada.imovel) return p(configuracoes.value.taxa_juros_imovel)
    if (garantiaSelecionada.veiculo) return p(configuracoes.value.taxa_juros_veiculo)
    return p(configuracoes.value.taxa_juros_outras_garantias)
  })

  const taxasPorTipo = computed(() => {
    if (!configuracoes.value) return {}
    const p = (v) => parsearTaxa(v)
    return {
      sem_garantia: p(configuracoes.value.taxa_juros_minima_sem_garantia),
      avalistas: p(configuracoes.value.taxa_juros_avalista),
      imovel: p(configuracoes.value.taxa_juros_imovel),
      veiculo: p(configuracoes.value.taxa_juros_veiculo),
      devedor_solidario: p(configuracoes.value.taxa_juros_outras_garantias),
      recebiveis: p(configuracoes.value.taxa_juros_outras_garantias),
      outras: p(configuracoes.value.taxa_juros_outras_garantias)
    }
  })

  const rotulaTaxaMinima = computed(() => {
    const m = taxaMinimaPorGarantia.value
    if (!m) return null
    return `Valor mínimo: ${m.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}%`
  })

  const rotulaTaxaPorTipo = (chave) => {
    const taxa = taxasPorTipo.value[chave]
    if (!taxa) return null
    return `${taxa.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}% (valor mínimo)`
  }

  // ===================================================
  // SIMULAÇÃO FINANCEIRA — Via API Backend
  // ===================================================
  // TODA lógica de cálculo reside no backend (ContratoService).
  // O frontend apenas consome os resultados via API.


  const {
    cronograma: cronogramaGerado,
    iofCalculado,
    cet,
    valorTotalParcelas,
    valorTotalJuros,
    valorFinanciado: valorFinanciadoCalc,
    simulacaoCarregando,
    solicitarSimulacao
  } = useSimulacaoContrato({ formulario: operacao, configuracoes })


  const formatarMoeda = (v) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

  // ===================================================
  // MÉTODOS — Cliente (Etapa 2)
  // ===================================================

  const buscarClientes = async (params) => {
    const chaveBusca = cliente.value.tipo_cliente === 'pf' ? 'nome_completo_like' : 'razao_social_like'
    const servicoBusca = cliente.value.tipo_cliente === 'pf' ? getPessoasFisicas : getPessoasJuridicas
    const q = { limit: params.limit || 10 }
    if (params.search) q[chaveBusca] = params.search
    return await servicoBusca(q, { showLoading: false })
  }

  const aoAlterarTipoCliente = () => {
    cliente.value.cliente_id = ''
    cliente.value.cliente_nome = ''
    clienteLabel.value = ''
  }

  const aoSelecionarCliente = (pessoa) => {
    cliente.value.cliente_id = pessoa.id
    cliente.value.cliente_nome = pessoa[chaveNomeCliente.value]
    clienteLabel.value = pessoa[chaveNomeCliente.value]
  }

  const contextoModalCliente = ref('cliente') // 'cliente' ou { tipo: 'garantia', indice: 0 }

  const configurarFormularioModal = (tipoPessoa) => {
    if (tipoPessoa === 'pf') {
      modalClienteFormulario.value = {
        nome_completo: '', cpf: '', rg: '', orgao_emissor_rg: '',
        email: '', rede_social: '', telefone: '', estado_civil: '', regime_partilha: '',
        cep: '', estado: '', cidade: '', bairro: '', endereco: '', numero: '', complemento: '',
        renda_mensal: '', limite_credito: '', banco: '', agencia: '', conta: '', chave_pix: '', observacao: ''
      }
    } else {
      modalClienteFormulario.value = {
        razao_social: '', nome_fantasia: '', cnpj: '', email: '', rede_social: '', telefone: '',
        cep: '', estado: '', cidade: '', bairro: '', endereco: '', numero: '', complemento: '',
        limite_credito: '', banco: '', agencia: '', conta: '', chave_pix: '', observacao: ''
      }
    }
  }

  const handleSearchCEPModal = async (cep) => {
    const cepLimpo = String(cep || '').replace(/\D/g, '')
    if (cepLimpo.length !== 8) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'CEP incompleto. Digite os 8 dígitos.' } }))
      return
    }
    carregandoBuscaModal.value = true
    try {
      const res = await consultarCEP(cepLimpo)
      if (res?.success && res.data) {
        modalClienteFormulario.value.endereco = res.data.endereco
        modalClienteFormulario.value.bairro = res.data.bairro
        modalClienteFormulario.value.cidade = res.data.cidade
        modalClienteFormulario.value.estado = res.data.estado
      }
    } catch (e) { console.error(e) } finally { carregandoBuscaModal.value = false }
  }

  const handleSearchCNPJModal = async (cnpj) => {
    const cnpjLimpo = String(cnpj || '').replace(/\D/g, '')
    if (cnpjLimpo.length !== 14) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'CNPJ incompleto. Digite os 14 dígitos.' } }))
      return
    }
    carregandoBuscaModal.value = true
    try {
      const res = await consultarCNPJ(cnpjLimpo)
      if (res?.success && res.data) {
        const d = res.data
        modalClienteFormulario.value.razao_social = d.razao_social
        modalClienteFormulario.value.nome_fantasia = d.nome_fantasia
        modalClienteFormulario.value.email = d.email
        modalClienteFormulario.value.rede_social = ''
        modalClienteFormulario.value.telefone = d.telefone
        modalClienteFormulario.value.cep = d.cep
        modalClienteFormulario.value.estado = d.estado
        modalClienteFormulario.value.cidade = d.cidade
        modalClienteFormulario.value.bairro = d.bairro
        modalClienteFormulario.value.endereco = d.endereco
        modalClienteFormulario.value.numero = d.numero
        modalClienteFormulario.value.complemento = d.complemento
      }
    } catch (e) { console.error(e) } finally { carregandoBuscaModal.value = false }
  }

  const abrirModalCliente = () => {
    contextoModalCliente.value = 'cliente'
    configurarFormularioModal(cliente.value.tipo_cliente)
    mostrarModalCliente.value = true
  }

  const abrirModalPessoaGarantia = (indice) => {
    contextoModalCliente.value = { tipo: 'garantia', indice }
    const g = garantias.value[indice]
    configurarFormularioModal(g.tipo_pessoa_garantia)
    // Opcionalmente podemos injetar no store que o modal agora considera o tipo de pessoa da garantia
    // Para simplificar, ContratoCliente.vue já usa cliente.value.tipo_cliente para ver PF/PJ. Mas espera,
    // se reusarmos o modal que tá no ContratoCliente.vue, ele olha pra `cliente.value.tipo_cliente`.
    // Melhor mudar a variável `tipoFluxoModal` no state se formos reutilizar a view dele?
    // ContratoCliente.vue tá checando `store.cliente.value.tipo_cliente`. Então vamos temporariamente mudar
    // isso ou criar uma variável tipoFluxoModal ?
    // Uma abordagem mais fácil: adicionar tipo_pessoa no modalClienteFormulario e no ContratoCliente.vue ler dessa ref.
  }

  // Refatorado abaixo
  const tipoPessoaModal = computed(() => {
    if (contextoModalCliente.value === 'cliente') return cliente.value.tipo_cliente
    return garantias.value[contextoModalCliente.value.indice]?.tipo_pessoa_garantia || 'pf'
  })

  // (Substituindo toda a seção para ser robusta)
  const salvarModalCliente = async () => {
    modalClienteCarregando.value = true
    try {
      const isPf = tipoPessoaModal.value === 'pf'
      const criarFn = isPf ? createPessoaFisica : createPessoaJuridica

      const payload = { ...modalClienteFormulario.value }
      if (payload.renda_mensal) payload.renda_mensal = desformatarParaBanco(payload.renda_mensal)
      if (payload.limite_credito) payload.limite_credito = desformatarParaBanco(payload.limite_credito)

      const res = await criarFn(payload)
      if (res?.success) {
        const novoId = res.id || res.data?.id
        const pessoaSalva = { ...payload, id: novoId }
        
        if (contextoModalCliente.value === 'cliente') {
          if (novoId) {
            cliente.value.cliente_id = novoId
            clienteLabel.value = isPf ? pessoaSalva.nome_completo : pessoaSalva.razao_social
          }
        } else if (contextoModalCliente.value?.tipo === 'garantia') {
          if (novoId) {
            aoSelecionarPessoaGarantia(contextoModalCliente.value.indice, pessoaSalva)
          }
        }

        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Cadastro efetuado com sucesso!' } }))
        mostrarModalCliente.value = false
      }
    } catch (e) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: e?.message || 'Erro ao cadastrar' } }))
    } finally {
      modalClienteCarregando.value = false
    }
  }

  // ===================================================
  // MÉTODOS — Garantias (Etapa 3)
  // ===================================================

  const novaGarantia = (tipo) => ({
    id: Math.random().toString(36).substr(2, 9),
    tipo_garantia: tipo || 'Avalista',
    tipo_pessoa_garantia: 'pf',
    pessoa_id_garantia: '',
    pessoa_nome_garantia: '',
    nome_completo: '', cpf: '', rg: '', orgao_emissor_rg: '',
    email: '', telefone: '', renda_mensal: '', estado_civil: '', regime_bens: '',
    cep: '', estado: '', cidade: '', bairro: '', endereco: '', numero: '', complemento: '',
    tabelionato: '', numero_matricula: '', proprietario: '',
    descricao: '', numero_serie: '', estado_conservacao: '', localizacao_fisica: '', valor_avaliacao: '',
    fabricante: '', modelo: '', ano_fabricacao: '', ano_modelo: '',
    chassi: '', renavam: '', placa: '', cor: '', outros_dados: '',
    tipo_recebivel: '', numero_identificacao: '', sacado: '', data_vencimento_recebivel: '',
    _aberta: true
  })

  const ehPessoa = (g) => ['Avalista', 'Devedor solidário'].includes(g.tipo_garantia)
  const ehImovel = (g) => g.tipo_garantia === 'Imóvel'
  const ehVeiculo = (g) => g.tipo_garantia === 'Veículo'
  const ehRecebivel = (g) => g.tipo_garantia === 'Recebível'
  const ehOutro = (g) => g.tipo_garantia === 'Outro'

  const adicionarGarantia = () => {
    garantiaSelecionada.sem_garantia = false
    garantias.value.push(novaGarantia('Avalista'))
  }

  const removerGarantia = (i) => {
    const g = garantias.value[i]
    const chave = mapaInversoTipo[g.tipo_garantia]
    if (chave) {
      const contagem = garantias.value.filter(item => item.tipo_garantia === g.tipo_garantia).length
      if (contagem <= 1) {
        garantiaSelecionada[chave] = false
        const algumaAtiva = Object.entries(garantiaSelecionada).some(([k, v]) => k !== 'sem_garantia' && v)
        if (!algumaAtiva) garantiaSelecionada.sem_garantia = true
      }
    }
    garantias.value.splice(i, 1)
  }

  const aoSelecionarPessoaGarantia = (indice, pessoa) => {
    const g = garantias.value[indice]
    if (!g) return
    g.pessoa_id_garantia = pessoa.id
    const nome = g.tipo_pessoa_garantia === 'pf' ? pessoa.nome_completo : pessoa.razao_social
    g.pessoa_nome_garantia = nome
    g.nome_completo = nome
    g.cpf = pessoa.cpf || pessoa.cnpj
    g.email = pessoa.email
    g.telefone = pessoa.telefone
    g.cep = pessoa.cep
    g.endereco = pessoa.endereco
    g.numero = pessoa.numero
    g.bairro = pessoa.bairro
    g.cidade = pessoa.cidade
    g.estado = pessoa.estado
  }

  const buscarPessoaGarantia = async (indice, params) => {
    const g = garantias.value[indice]
    if (!g) return { data: [] }
    const servico = g.tipo_pessoa_garantia === 'pf' ? getPessoasFisicas : getPessoasJuridicas
    const chaveBusca = g.tipo_pessoa_garantia === 'pf' ? 'nome_completo_like' : 'razao_social_like'
    const q = { limit: params.limit || 10 }
    if (params.search) q[chaveBusca] = params.search
    return await servico(q, { showLoading: false })
  }

  const alternarGarantiaAberta = (i) => {
    if (garantias.value[i]) garantias.value[i]._aberta = !garantias.value[i]._aberta
  }

  /** Sincroniza toggles de garantia → lista de garantias */
  watch(garantiaSelecionada, (sel) => {
    if (recuperandoGarantias.value || !sel) return
    Object.entries(mapaToggleTipo).forEach(([chave, nome]) => {
      const existe = garantias.value.find(g => g.tipo_garantia === nome)
      if (sel[chave] && !existe) garantias.value.push(novaGarantia(nome))
      if (!sel[chave] && existe) {
        const idx = garantias.value.indexOf(existe)
        if (idx > -1) garantias.value.splice(idx, 1)
      }
    })
  }, { deep: true })

  // ===================================================
  // MÉTODOS — Inadimplência (Etapa 4)
  // ===================================================

  const alternarSwitchInadimplencia = (campo) => {
    inadimplencia.value[campo] = inadimplencia.value[campo] ? 0 : 1
    if (campo === 'permitir_carencia' && !inadimplencia.value[campo]) inadimplencia.value.limite_carencia = ''
  }

  // ===================================================
  // MÉTODOS — Conclusão (Etapa 5)
  // ===================================================

  const alternarSwitchConclusao = (campo) => {
    conclusao.value[campo] = conclusao.value[campo] ? 0 : 1
  }

  const alternarDeclaracao = () => {
    declaracaoAssinada.value = !declaracaoAssinada.value
  }

  // ===================================================
  // VALIDAÇÃO — Etapa 1
  // ===================================================

  const validarOperacao = () => {
    const f = operacao.value
    if (!f.valor_solicitado) { aviso('Informe o valor solicitado.'); return false }
    if (!f.taxa_juros) { aviso('Informe a taxa de juros.'); return false }
    if (!f.data_assinatura) { aviso('Informe a data de assinatura.'); return false }
    if (!f.data_primeira_parcela) { aviso('Informe a data da primeira parcela.'); return false }

    // Validação de parcelas — pula para Pagamento único / Desconto de Recebíveis
    if (!ehPagamentoUnico.value) {
      if (!f.quantidade_parcelas) { aviso('Informe a quantidade de parcelas.'); return false }
      const qtd = parseInt(f.quantidade_parcelas) || 0
      const minQtd = configuracoes.value?.qtd_minima_parcelas || 1
      const maxQtd = configuracoes.value?.qtd_maxima_parcelas || 9999
      if (qtd < minQtd) { aviso(`Quantidade mínima: ${minQtd} parcelas.`); return false }
      if (qtd > maxQtd) { aviso(`Quantidade máxima: ${maxQtd} parcelas.`); return false }
    }

    const taxaDig = parseFloat(String(f.taxa_juros).replace(',', '.'))
    const min = taxaMinimaPorGarantia.value
    if (min !== null && taxaDig < min) { aviso(`Taxa mínima de juros: ${min.toLocaleString('pt-BR')}%`); return false }
    return true
  }

  const aviso = (msg) => window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: msg } }))

  // ===================================================
  // RESUMO CONSOLIDADO
  // ===================================================

  const resumoContrato = computed(() => ({
    tipo_operacao: operacao.value.tipo_operacao,
    modelo_amortizacao: operacao.value.modelo_amortizacao,
    quantidade_parcelas: operacao.value.quantidade_parcelas,
    taxa_juros: operacao.value.taxa_juros,
    valor_solicitado: operacao.value.valor_solicitado,
    tipo_cliente: cliente.value.tipo_cliente,
    garantias: garantias.value,
    juros_mora: inadimplencia.value.juros_mora,
    multa_moratoria: inadimplencia.value.multa_moratoria,
    risco_inadimplencia: inadimplencia.value.risco_inadimplencia,
  }))

  // ===================================================
  // PAYLOAD para o backend
  // ===================================================

  const construirPayload = () => {
    const op = operacao.value
    const cl = cliente.value
    const inad = inadimplencia.value
    const conc = conclusao.value
    return {
      tipo_operacao: String(op.tipo_operacao || ''),
      valor_solicitado: String(op.valor_solicitado || ''),
      periodo_amortizacao: String(op.periodo_amortizacao || ''),
      modelo_amortizacao: String(op.modelo_amortizacao || ''),
      taxa_juros: String(op.taxa_juros || ''),
      tipo_taxa: String(op.tipo_taxa || ''),
      quantidade_parcelas: String(op.quantidade_parcelas || ''),
      data_assinatura: String(op.data_assinatura || ''),
      data_primeira_parcela: String(op.data_primeira_parcela || ''),
      simples_nacional: Number(op.simples_nacional ?? 0),
      calcular_iof: Number(op.calcular_iof ?? 0),
      tipo_cliente: String(cl.tipo_cliente || ''),
      cliente_id: cl.cliente_id ? String(cl.cliente_id) : '',
      juros_mora: inad.juros_mora != null ? String(inad.juros_mora) : '',
      multa_moratoria: inad.multa_moratoria != null ? String(inad.multa_moratoria) : '',
      risco_inadimplencia: String(inad.risco_inadimplencia || 'Baixo'),
      permitir_carencia: Number(inad.permitir_carencia ?? 0),
      limite_carencia: inad.limite_carencia != null ? String(inad.limite_carencia) : '',
      tipo_assinatura: String(conc.tipo_assinatura || 'sem_assinatura'),
      enviar_registradora: Number(conc.enviar_registradora ?? 0),
      contrato_iniciado: Number(conc.contrato_iniciado ?? 0),
      status: String(conc.status || 'Ativo'),
      tipo_juros_pagamento_unico: String(op.tipo_juros_pagamento_unico || 'compostos'),
      garantias: garantias.value.map(g => {
        const copia = { ...g }
        delete copia.id
        delete copia._aberta
        return copia
      })
    }
  }

  // ===================================================
  // PREENCHER COM DADOS INICIAIS (edição)
  // ===================================================

  const mascararValor = (tipo, valor) => {
    if (!valor) return ''
    const v = String(valor).replace(/\D/g, '')
    const num = parseInt(v || '0') / 100
    return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  }

  const preencherComDados = (dados) => {
    if (!dados) return

    const valorFmt = mascararValor('real', (parseFloat(dados.valor_solicitado) * 100).toFixed(0))
    const taxaFmt = mascararValor('porcentagem', (parseFloat(dados.taxa_juros) * 100).toFixed(0))

    operacao.value = {
      tipo_operacao: dados.tipo_operacao || 'Empréstimo',
      valor_solicitado: valorFmt,
      periodo_amortizacao: dados.periodo_amortizacao || '',
      modelo_amortizacao: dados.modelo_amortizacao || 'Price',
      taxa_juros: taxaFmt,
      tipo_taxa: dados.tipo_taxa || 'mensal',
      quantidade_parcelas: dados.quantidade_parcelas || '',
      data_assinatura: dados.data_assinatura || new Date().toISOString().split('T')[0],
      data_primeira_parcela: dados.data_primeira_parcela || '',
      simples_nacional: dados.simples_nacional ?? 0,
      calcular_iof: dados.calcular_iof ?? 0,
      tipo_juros_pagamento_unico: dados.tipo_juros_pagamento_unico || 'compostos',
    }

    cliente.value = {
      tipo_cliente: dados.tipo_cliente || 'pj',
      cliente_id: dados.cliente_id || '',
      cliente_nome: dados.cliente_nome || ''
    }
    clienteLabel.value = dados.cliente_nome || ''

    inadimplencia.value = {
      juros_mora: dados.juros_mora ?? '1',
      multa_moratoria: dados.multa_moratoria ?? '',
      risco_inadimplencia: dados.risco_inadimplencia ?? 'Baixo',
      permitir_carencia: dados.permitir_carencia ?? 0,
      limite_carencia: dados.limite_carencia ?? ''
    }

    conclusao.value = {
      tipo_assinatura: dados.tipo_assinatura ?? 'sem_assinatura',
      enviar_registradora: dados.enviar_registradora ?? 0,
      contrato_iniciado: dados.contrato_iniciado ?? 0,
      status: dados.status ?? 'Ativo'
    }

    // Recuperar garantias
    recuperandoGarantias.value = true
    if (dados.garantias && Array.isArray(dados.garantias) && dados.garantias.length) {
      garantias.value = dados.garantias.map(g => ({ ...g, _aberta: false }))
      Object.keys(garantiaSelecionada).forEach(k => garantiaSelecionada[k] = false)
      let algumAtivo = false
      dados.garantias.forEach(g => {
        const chave = mapaInversoTipo[g.tipo_garantia]
        if (chave) { garantiaSelecionada[chave] = true; algumAtivo = true }
      })
      if (!algumAtivo) garantiaSelecionada.sem_garantia = true
    }
    setTimeout(() => { recuperandoGarantias.value = false }, 0)
  }

  return {
    // Estado — Operação
    operacao, garantiaSelecionada, configuracoes,
    TIPO_OPERACAO, PERIODO_AMORTIZACAO, MODELO_AMORTIZACAO, modelosDisponiveis,
    tiposGarantiaNomes, mapaInversoTipo,
    alternarTipoGarantia, alternarSwitch,
    taxaMinimaPorGarantia, taxasPorTipo, rotulaTaxaMinima, rotulaTaxaPorTipo,
    ehPagamentoUnico, ehDescontoRecebiveis,

    // Estado — Simulação
    iofCalculado, cronogramaGerado, valorTotalParcelas, valorTotalJuros, cet, formatarMoeda,
    simulacaoCarregando, solicitarSimulacao,

    // Estado — Cliente
    cliente, clienteLabel, chaveNomeCliente,
    mostrarModalCliente, modalClienteCarregando, modalClienteFormulario, tipoPessoaModal, carregandoBuscaModal,
    buscarClientes, aoAlterarTipoCliente, aoSelecionarCliente, abrirModalCliente, abrirModalPessoaGarantia, salvarModalCliente, handleSearchCEPModal, handleSearchCNPJModal,

    // Estado — Garantias
    garantias, estadosCivis, regimesBens, tiposGarantiaOpcoes, tiposRecebivel,
    ehPessoa, ehImovel, ehVeiculo, ehRecebivel, ehOutro,
    adicionarGarantia, removerGarantia, aoSelecionarPessoaGarantia, buscarPessoaGarantia, alternarGarantiaAberta,

    // Estado — Inadimplência
    inadimplencia, riscos, alternarSwitchInadimplencia,

    // Estado — Conclusão
    conclusao, declaracaoAssinada, alternarSwitchConclusao, alternarDeclaracao,

    // Estado — Resumo e Ações
    resumoContrato, construirPayload, validarOperacao, preencherComDados
  }
}
