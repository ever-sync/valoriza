/**
 * Composable para gerenciar formulários de forma padronizada.
 * Fornece estado e métodos para criação e edição de registros.
 * 
 * @example
 * import { useDadosFormulario } from '@/composables/useDadosFormulario'
 * import { salvar, buscarPorId } from '../services/moduloService'
 * 
 * const dadosPadrao = { nome: '', email: '', status: 'ativo' }
 * 
 * const { 
 *   formulario, carregando, modoEdicao, 
 *   carregarDados, salvarDados, resetarFormulario 
 * } = useDadosFormulario({
 *   dadosPadrao,
 *   serviceSalvar: salvar,
 *   serviceBuscar: buscarPorId
 * })
 */
import { ref, computed, watch } from 'vue'
import { parsearValor, formatarDecimal, formatarData, formatarDataInput } from '@/helpers/formatacao'

/**
 * Cria um composable para gerenciamento de formulário.
 * 
 * @param {Object} opcoes - Opções de configuração
 * @param {Object} opcoes.dadosPadrao - Objeto com campos padrão do formulário
 * @param {Function} opcoes.serviceSalvar - Função para salvar dados (async)
 * @param {Function} opcoes.serviceBuscar - Função para buscar dados por ID (async)
 * @param {Function} opcoes.transformarCarregamento - Função para transformar dados ao carregar
 * @param {Function} opcoes.transformarEnvio - Função para transformar dados antes de enviar
 * @param {Function} opcoes.onSucesso - Callback chamado após salvar com sucesso
 * @returns {Object} Estado e métodos do composable
 */
export function useDadosFormulario(opcoes = {}) {
  const {
    dadosPadrao = {},
    serviceSalvar = null,
    serviceBuscar = null,
    transformarCarregamento = null,
    transformarEnvio = null,
    onSucesso = null
  } = opcoes

  // ============================================
  // ESTADO
  // ============================================

  /** Dados do formulário */
  const formulario = ref({ ...dadosPadrao })

  /** Indica se está carregando dados */
  const carregando = ref(false)

  /** Indica se está salvando dados */
  const salvando = ref(false)

  /** Dados originais carregados (para comparação) */
  const dadosOriginais = ref(null)

  // ============================================
  // COMPUTED
  // ============================================

  /** Verifica se está em modo de edição (já tem ID) */
  const modoEdicao = computed(() => !!formulario.value.id)

  /** Verifica se o formulário está vazio (apenas valores padrão) */
  const formularioVazio = computed(() => {
    const valoresAtuais = Object.values(formulario.value)
    const valoresPadrao = Object.values(dadosPadrao)
    return JSON.stringify(valoresAtuais) === JSON.stringify(valoresPadrao)
  })

  /** Verifica se houve alterações no formulário */
  const temAlteracoes = computed(() => {
    if (!dadosOriginais.value) return false
    return JSON.stringify(formulario.value) !== JSON.stringify(dadosOriginais.value)
  })

  // ============================================
  // MÉTODOS DE ESTADO
  // ============================================

  /**
   * Reseta o formulário para os valores padrão.
   * 
   * @returns {void}
   */
  function resetarFormulario() {
    formulario.value = { ...dadosPadrao }
    dadosOriginais.value = null
  }

  /**
   * Preenche o formulário com dados externos.
   * 
   * @param {Object} dados - Dados para preencher
   * @returns {void}
   */
  function preencherFormulario(dados) {
    if (!dados) {
      resetarFormulario()
      return
    }

    let dadosTransformados = { ...dados }

    // Aplica transformação se fornecida
    if (transformarCarregamento) {
      dadosTransformados = transformarCarregamento(dados)
    } else {
      // Transformação padrão: campos monetários
      Object.keys(dadosPadrao).forEach(chave => {
        if (dados[chave] !== undefined) {
          // Se o campo padrão é monetário (termina com _valor, _montante, etc ou é campo específico)
          const camposMonetarios = ['valor', 'montante', 'valor_pago', 'valor_recebido', 'renda', 'limite', 'saldo']
          const campoMonetario = camposMonetarios.some(m => chave.toLowerCase().includes(m))
          
          if (campoMonetario && dados[chave]) {
            dadosTransformados[chave] = formatarDecimal(parsearValor(dados[chave]))
          }
        }
      })
    }

    formulario.value = { ...dadosPadrao, ...dadosTransformados }
    dadosOriginais.value = { ...formulario.value }
  }

  /**
   * Atualiza um campo específico do formulário.
   * 
   * @param {string} campo - Nome do campo
   * @param {any} valor - Novo valor
   * @returns {void}
   */
  function atualizarCampo(campo, valor) {
    formulario.value[campo] = valor
  }

  /**
   * Atualiza múltiplos campos de uma vez.
   * 
   * @param {Object} campos - Objeto com campos para atualizar
   * @returns {void}
   */
  function atualizarCampos(campos) {
    formulario.value = { ...formulario.value, ...campos }
  }

  // ============================================
  // MÉTODOS DE DADOS
  // ============================================

  /**
   * Carrega dados de um registro existente para edição.
   * 
   * @param {number|string} id - ID do registro
   * @returns {Promise<boolean>} True se carregou com sucesso
   */
  async function carregarDados(id) {
    if (!serviceBuscar) {
      console.warn('useDadosFormulario: serviceBuscar não configurado')
      return false
    }

    if (!id) {
      resetarFormulario()
      return false
    }

    carregando.value = true

    try {
      const resposta = await serviceBuscar(id)

      if (resposta?.success && resposta.data) {
        // Extrai dados da resposta (pode vir em diferentes formatos)
        let dados = resposta.data
        
        if (Array.isArray(dados)) {
          dados = dados[0]
        } else if (dados.data) {
          dados = dados.data
        }

        preencherFormulario(dados)
        return true
      } else {
        window.dispatchEvent(new CustomEvent('toast', { 
          detail: { type: 'error', message: resposta?.message || 'Erro ao carregar dados.' } 
        }))
        return false
      }
    } catch (erro) {
      console.error('Erro ao carregar dados:', erro)
      window.dispatchEvent(new CustomEvent('toast', { 
        detail: { type: 'error', message: 'Erro de conexão.' } 
      }))
      return false
    } finally {
      carregando.value = false
    }
  }

  /**
   * Salva os dados do formulário (criar ou atualizar).
   * 
   * @returns {Promise<boolean>} True se salvou com sucesso
   */
  async function salvarDados() {
    if (!serviceSalvar) {
      console.warn('useDadosFormulario: serviceSalvar não configurado')
      return false
    }

    salvando.value = true

    try {
      // Prepara payload
      let payload = { ...formulario.value }

      // Aplica transformação customizada se fornecida
      if (transformarEnvio) {
        payload = transformarEnvio(payload)
      } else {
        // Transformação padrão: campos monetários para número
        Object.keys(dadosPadrao).forEach(chave => {
          const camposMonetarios = ['valor', 'montante', 'valor_pago', 'valor_recebido', 'renda', 'limite', 'saldo']
          const campoMonetario = camposMonetarios.some(m => chave.toLowerCase().includes(m))
          
          if (campoMonetario && payload[chave]) {
            payload[chave] = parsearValor(payload[chave])
          }
        })
      }

      let resposta

      if (modoEdicao.value) {
        resposta = await serviceSalvar(payload.id, payload)
      } else {
        resposta = await serviceSalvar(payload)
      }

      if (resposta?.success) {
        window.dispatchEvent(new CustomEvent('toast', { 
          detail: { type: 'success', message: resposta?.message || 'Salvo com sucesso!' } 
        }))
        
        if (onSucesso) {
          onSucesso(resposta)
        }
        
        return true
      } else {
        window.dispatchEvent(new CustomEvent('toast', { 
          detail: { type: 'error', message: resposta?.message || 'Erro ao salvar.' } 
        }))
        return false
      }
    } catch (erro) {
      console.error('Erro ao salvar:', erro)
      window.dispatchEvent(new CustomEvent('toast', { 
        detail: { type: 'error', message: 'Erro de conexão.' } 
      }))
      return false
    } finally {
      salvando.value = false
    }
  }

  // ============================================
  // WATCHER PRÉ-CONFIGURADO
  // ============================================

  /**
   * Cria um watcher para sincronizar dados iniciais automaticamente.
   * 
   * @param {Ref} propsInitialData - Ref dos dados iniciais das props
   * @returns {void}
   */
  function watcherDadosIniciais(propsInitialData) {
    watch(() => propsInitialData.value, (novoValor) => {
      if (novoValor) {
        preencherFormulario(novoValor)
      } else {
        resetarFormulario()
      }
    }, { immediate: true })
  }

  // ============================================
  // RETORNO
  // ============================================

  return {
    // Estado
    formulario,
    carregando,
    salvando,
    dadosOriginais,

    // Computed
    modoEdicao,
    formularioVazio,
    temAlteracoes,

    // Métodos de estado
    resetarFormulario,
    preencherFormulario,
    atualizarCampo,
    atualizarCampos,

    // Métodos de dados
    carregarDados,
    salvarDados,

    // Utilitários
    watcherDadosIniciais
  }
}

export default useDadosFormulario