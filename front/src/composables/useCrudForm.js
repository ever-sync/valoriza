/**
 * Composable para gerenciamento de formulário CRUD.
 * Padroniza criação, edição e submissão de formulários.
 */
import { ref, computed, watch } from 'vue'
import { useToast } from '@/composables/useToast'

/**
 * Hook principal para formulários CRUD.
 * 
 * @param {Object} options - Opções de configuração
 * @param {Object} options.dadosPadrao - Objeto com campos padrão do formulário
 * @param {Function} options.onSalvar - Função chamada ao salvar com sucesso
 * @param {Function} options.transformarDados - Função para transformar dados antes de enviar
 * @returns {Object} Métodos e estados do formulário
 */
export function useCrudForm(options = {}) {
  const {
    dadosPadrao = {},
    onSalvar = null,
    transformarDados = null
  } = options

  const toast = useToast()
  
  const formulario = ref({ ...dadosPadrao })
  const carregando = ref(false)
  const dadosIniciais = ref(null)

  const estaEditando = computed(() => !!formulario.value.id)

  /**
   * Reseta o formulário para os valores padrão.
   */
  const resetarFormulario = () => {
    formulario.value = { ...dadosPadrao }
    dadosIniciais.value = null
  }

  /**
   * Preenche o formulário com dados externos (ex: dados carregados do backend).
   * Aplica transformações necessárias para exibição.
   * 
   * @param {Object} dados - Dados para preencher
   * @param {Function} transform - Função de transformação opcional
   */
  const preencherFormulario = (dados, transform = null) => {
    if (!dados) {
      resetarFormulario()
      return
    }

    dadosIniciais.value = dados
    
    if (transform) {
      formulario.value = { ...dadosPadrao, ...transform(dados) }
    } else {
      formulario.value = { ...dadosPadrao, ...dados }
    }
  }

  /**
   * Extrai apenas os campos válidos do formulário para envio.
   * Remove campos extras que não existem no modelo de dados.
   * 
   * @returns {Object} Payload filtrado
   */
  const extrairPayload = () => {
    const chavesValidas = Object.keys(dadosPadrao)
    const payload = {}

    Object.entries(formulario.value).forEach(([chave, valor]) => {
      if (chavesValidas.includes(chave)) {
        payload[chave] = valor
      }
    })

    return payload
  }

  /**
   * Executa save genérico com callbacks.
   * 
   * @param {Function} salvarFn - Função assíncrona que executa o save
   * @returns {Promise<boolean>} True se sucesso, false se erro
   */
  const executarSalvar = async (salvarFn) => {
    if (carregando.value) return false

    carregando.value = true
    try {
      let payload = extrairPayload()
      
      if (transformarDados) {
        payload = await transformarDados(payload)
      }

      const resultado = await salvarFn(payload)

      if (resultado?.success) {
        toast.success(resultado.message || 'Operação realizada com sucesso!')
        if (onSalvar) onSalvar(resultado)
        return true
      } else {
        toast.error(resultado?.message || 'Erro ao salvar dados.')
        return false
      }
    } catch (erro) {
      console.error('Erro ao salvar:', erro)
      toast.error('Erro de conexão ou sistema.')
      return false
    } finally {
      carregando.value = false
    }
  }

  /**
   * Prepara watcher para sincronizar dados externos com o formulário.
   * 
   * @param {Ref} propsInitialData - Ref dos dados iniciais (ex: props.initialData)
   * @param {Function} [transform] - Função de transformação opcional
   * @returns {WatchHandle} Handle do watcher
   */
  const watchingDadosIniciais = (propsInitialData, transform = null) => {
    return watch(() => propsInitialData.value, (novoValor) => {
      preencherFormulario(novoValor, transform)
    }, { immediate: true })
  }

  return {
    formulario,
    carregando,
    dadosIniciais,
    estaEditando,
    resetarFormulario,
    preencherFormulario,
    extrairPayload,
    executarSalvar,
    watchingDadosIniciais
  }
}

/**
 * Composable específico para formulários com valores monetários.
 * Adiciona formatação automática de valores Brasil.
 */
export function useFormularioMonetario(dadosPadrao = {}) {
  const { formatarDecimal, parsearValor } = require('@/helpers/formatacao')

  const camposMonetarios = ref([])
  const formulario = ref({ ...dadosPadrao })

  /**
   * Formata campos monetários para exibição.
   */
  const formatarCamposMonetarios = () => {
    camposMonetarios.value.forEach(campo => {
      if (formulario.value[campo] !== undefined && formulario.value[campo] !== '') {
        const numero = parsearValor(formulario.value[campo])
        formulario.value[campo] = formatarDecimal(numero)
      }
    })
  }

  /**
   * Parse campos monetários para envio ao banco.
   */
  const parsearCamposMonetarios = () => {
    const payload = { ...formulario.value }
    camposMonetarios.value.forEach(campo => {
      if (payload[campo] !== undefined) {
        payload[campo] = parsearValor(payload[campo])
      }
    })
    return payload
  }

  return {
    formulario,
    camposMonetarios,
    formatarCamposMonetarios,
    parsearCamposMonetarios
  }
}

export default useCrudForm
