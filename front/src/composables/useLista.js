/**
 * Composable para gerenciar listas com paginação, filtros e ordenação.
 * Padroniza o comportamento de todas as views de lista do sistema.
 * 
 * @example
 * import { useLista } from '@/composables/useLista'
 * import { listar, excluir } from '../services/moduloService'
 * 
 * const { 
 *   carregando, itens, meta, filtros,
 *   buscar, aplicarFiltros, ordenar, navegar,
 *   excluirItem, confirmarExclusao
 * } = useLista({ 
 *   serviceBuscar: listar,
 *   serviceExcluir: excluir
 * })
 */
import { ref, reactive, computed, watch } from 'vue'

/**
 * Cria um composable para gerenciamento de lista.
 * 
 * @param {Object} opcoes - Opções de configuração
 * @param {Function} opcoes.serviceBuscar - Função para buscar dados (async)
 * @param {Function} opcoes.serviceExcluir - Função para excluir item (async)
 * @param {Object} opcoes.filtrosIniciais - Filtros iniciais padrão
 * @param {Object} opcoes.ordenacaoInicial - Ordenação inicial padrão
 * @returns {Object} Estado e métodos do composable
 */
export function useLista(opcoes = {}) {
  const {
    serviceBuscar = null,
    serviceExcluir = null,
    filtrosIniciais = {},
    ordenacaoInicial = { coluna: 'id', direcao: 'desc' },
    mapearParametros = null, // Função customizada para mapear parâmetros da API
    globalLoading = false // Novo: controla se usa o preload global (default false para listas)
  } = opcoes

  // ============================================
  // ESTADO
  // ============================================
  
  /** Indica se está carregando dados */
  const carregando = ref(false)
  
  /** Lista de itens retornados */
  const itens = ref([])
  
  /** Metadados da paginação */
  const meta = reactive({
    pagina: 1,
    porPagina: parseInt(localStorage.getItem('pref_por_pagina')) || 10,
    total: 0,
    totalPaginas: 0
  })
  
  /** Filtros ativos da lista */
  const filtros = reactive({ ...filtrosIniciais })
  
  /** Estado de ordenação */
  const ordenacao = reactive({ ...ordenacaoInicial })

  // Persistência automática da preferência de itens por página
  watch(() => meta.porPagina, (newVal) => {
    localStorage.setItem('pref_por_pagina', newVal)
  })
  
  /** Estado do modal de exclusão */
  const modalExclusao = reactive({
    visivel: false,
    itemExcluir: null,
    carregando: false,
    mensagem: '',
    titulo: 'Confirmar Exclusão'
  })

  // ============================================
  // COMPUTED
  // ============================================

  /** Verifica se tem itens na lista */
  const temItens = computed(() => itens.value.length > 0)

  /** Verifica se está na primeira página */
  const primeiraPagina = computed(() => meta.pagina === 1)

  /** Verifica se está na última página */
  const ultimaPagina = computed(() => meta.pagina >= meta.totalPaginas)

  /** texto da paginação */
  const textoPaginacao = computed(() => {
    const inicio = (meta.pagina - 1) * meta.porPagina + 1
    const fim = Math.min(meta.pagina * meta.porPagina, meta.total)
    return `Mostrando ${inicio} - ${fim} de ${meta.total} registros`
  })

  // ============================================
  // MÉTODOS PRINCIPAIS
  // ============================================

  /**
   * Carrega dados da lista.
   * Se serviceBuscar for passado, usa ele, caso contrário espera configuração externa.
   * 
   * @param {Object} parametros - Parâmetros adicionais para a busca
   * @returns {Promise<void>}
   */
  async function buscar(parametros = {}) {
    if (!serviceBuscar) {
      console.warn('useLista: serviceBuscar não configurado')
      return
    }

    carregando.value = true

    try {
      let parametrosBusca = {
        pagina: meta.pagina,
        porPagina: meta.porPagina,
        ordenacaoColuna: ordenacao.coluna,
        ordenacaoDirecao: ordenacao.direcao,
        ...filtros,
        ...parametros
      }

      // Se há função customizada de mapeamento, usa ela
      if (mapearParametros) {
        parametrosBusca = mapearParametros(parametrosBusca)
      }

      const resposta = await serviceBuscar(parametrosBusca, { showLoading: globalLoading })

      if (resposta?.success) {
        itens.value = resposta.data || []
        
        if (resposta.meta) {
          meta.total = resposta.meta.total ?? resposta.meta.total_registros ?? 0
          meta.pagina = resposta.meta.pagina ?? resposta.meta.pagina_atual ?? 1
          meta.porPagina = resposta.meta.porPagina ?? resposta.meta.por_pagina ?? meta.porPagina
          meta.totalPaginas = Math.ceil(meta.total / meta.porPagina)
        }
      } else {
        itens.value = []
        meta.total = 0
      }
    } catch (erro) {
      console.error('Erro ao carregar lista:', erro)
      itens.value = []
      meta.total = 0
    } finally {
      carregando.value = false
    }
  }

  /**
   * Reinicia a lista para a primeira página e limpa filtros.
   * 
   * @returns {void}
   */
  function limparFiltros() {
    Object.keys(filtros).forEach(chave => {
      filtros[chave] = ''
    })
    meta.pagina = 1
    buscar()
  }

  /**
   * Aplica novos filtros e busca.
   * 
   * @param {Object} novosFiltros - Novos filtros a aplicar
   * @returns {void}
   */
  function aplicarFiltros(novosFiltros) {
    Object.assign(filtros, novosFiltros)
    meta.pagina = 1
    buscar()
  }

  /**
   * Ordena a lista por uma coluna.
   * 
   * @param {string} coluna - Nome da coluna para ordenar
   * @returns {void}
   */
  function ordenarPorColuna(coluna) {
    if (ordenacao.coluna === coluna) {
      ordenacao.direcao = ordenacao.direcao === 'asc' ? 'desc' : 'asc'
    } else {
      ordenacao.coluna = coluna
      ordenacao.direcao = 'asc'
    }
    buscar()
  }

  /**
   * Navega para uma página específica.
   * 
   * @param {number} pagina - Número da página
   * @returns {void}
   */
  function irParaPagina(pagina) {
    if (pagina >= 1 && pagina <= meta.totalPaginas) {
      meta.pagina = pagina
      buscar()
    }
  }

  /**
   * Vai para a próxima página.
   * 
   * @returns {void}
   */
  function proximaPagina() {
    if (!ultimaPagina.value) {
      meta.pagina++
      buscar()
    }
  }

  /**
   * Vai para a página anterior.
   * 
   * @returns {void}
   */
  function paginaAnterior() {
    if (!primeiraPagina.value) {
      meta.pagina--
      buscar()
    }
  }

  /**
   * Altera a quantidade de itens por página.
   * 
   * @param {number} novaPorPagina - Nova quantidade por página
   * @returns {void}
   */
  function alterarPorPagina(novaPorPagina) {
    meta.porPagina = novaPorPagina
    localStorage.setItem('pref_por_pagina', novaPorPagina)
    meta.pagina = 1
    buscar()
  }

  // ============================================
  // MÉTODOS DE EXCLUSÃO
  // ============================================

  /**
   * Abre o modal de confirmação de exclusão.
   * 
   * @param {Object} item - Item a ser excluído
   * @param {string} mensagem - Mensagem personalizada de confirmação
   * @returns {void}
   */
  function confirmarExclusao(item, mensagem = 'Tem certeza que deseja excluir este registro?') {
    modalExclusao.itemExcluir = item
    modalExclusao.mensagem = mensagem
    modalExclusao.visivel = true
  }

  /**
   * Fecha o modal de exclusão.
   * 
   * @returns {void}
   */
  function fecharModalExclusao() {
    modalExclusao.visivel = false
    modalExclusao.itemExcluir = null
    modalExclusao.mensagem = ''
    modalExclusao.carregando = false
  }

  /**
   * Executa a exclusão do item confirmationado.
   * 
   * @returns {Promise<boolean>} True se exclusão foi bem-sucedida
   */
  async function excluirItem() {
    if (!modalExclusao.itemExcluir || !serviceExcluir) {
      console.warn('useLista: item ou serviceExcluir não configurado')
      return false
    }

    modalExclusao.carregando = true

    try {
      const resposta = await serviceExcluir(modalExclusao.itemExcluir.id)

      if (resposta?.success) {
        buscar()
        window.dispatchEvent(new CustomEvent('toast', { 
          detail: { type: 'success', message: 'Exclusão realizada com sucesso!' } 
        }))
        fecharModalExclusao()
        return true
      } else {
        window.dispatchEvent(new CustomEvent('toast', { 
          detail: { type: 'error', message: resposta?.message || 'Erro ao excluir.' } 
        }))
        return false
      }
    } catch (erro) {
      console.error('Erro ao excluir:', erro)
      window.dispatchEvent(new CustomEvent('toast', { 
        detail: { type: 'error', message: 'Erro de conexão.' } 
      }))
      return false
    } finally {
      modalExclusao.carregando = false
    }
  }

  // ============================================
  // RETORNO
  // ============================================

  return {
    // Estado
    carregando,
    itens,
    meta,
    filtros,
    ordenacao,
    modalExclusao,

    // Computed
    temItens,
    primeiraPagina,
    ultimaPagina,
    textoPaginacao,

    // Métodos principais
    buscar,
    limparFiltros,
    aplicarFiltros,
    ordenarPorColuna,
    irParaPagina,
    proximaPagina,
    paginaAnterior,
    alterarPorPagina,

    // Métodos de exclusão
    confirmarExclusao,
    fecharModalExclusao,
    excluirItem
  }
}

export default useLista