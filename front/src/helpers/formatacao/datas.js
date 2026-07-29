/**
 * Helper de formatação de datas brasileiras.
 * Centraliza todas as conversões de data.
 */

/**
 * Formata data para exibição brasileira (dd/mm/yyyy).
 * Aceita data no formato ISO (yyyy-mm-dd) ou objeto Date.
 * @param {string|Date} data - Data a ser formatada
 * @param {boolean} incluirHora - Se deve incluir hora (hh:mm)
 * @returns {string} Data formatada ou "-" se inválida
 */
export function formatarData(data, incluirHora = false) {
  if (!data) return '-'
  
  let dateObj
  
  if (data instanceof Date) {
    dateObj = data
  } else if (typeof data === 'string') {
    dateObj = new Date(data + 'T00:00:00')
  } else {
    return '-'
  }
  
  if (isNaN(dateObj.getTime())) return '-'
  
  const dia = String(dateObj.getDate()).padStart(2, '0')
  const mes = String(dateObj.getMonth() + 1).padStart(2, '0')
  const ano = dateObj.getFullYear()
  
  if (incluirHora) {
    const hora = String(dateObj.getHours()).padStart(2, '0')
    const minuto = String(dateObj.getMinutes()).padStart(2, '0')
    return `${dia}/${mes}/${ano} ${hora}:${minuto}`
  }
  
  return `${dia}/${mes}/${ano}`
}

/**
 * Formata data para input type="date" (yyyy-mm-dd).
 * @param {string|Date} data - Data a ser formatada
 * @returns {string} Data no formato ISO ou string vazia
 */
export function formatarDataInput(data) {
  if (!data) return ''
  
  if (data instanceof Date) {
    const dia = String(data.getDate()).padStart(2, '0')
    const mes = String(data.getMonth() + 1).padStart(2, '0')
    const ano = data.getFullYear()
    return `${ano}-${mes}-${dia}`
  }
  
  if (typeof data === 'string' && data.includes('-')) {
    const [ano, mes, dia] = data.split('T')[0].split('-')
    return `${ano}-${mes}-${dia}`
  }
  
  return ''
}

/**
 * Converte data do input (yyyy-mm-dd) para objeto Date.
 * @param {string} dataInput - Data do input
 * @returns {Date|null} Objeto Date ou null se inválido
 */
export function parsearDataInput(dataInput) {
  if (!dataInput) return null
  return new Date(dataInput + 'T00:00:00')
}

/**
 * Calcula diferença em dias entre duas datas.
 * @param {string|Date} dataInicial - Data inicial
 * @param {string|Date} dataFinal - Data final
 * @returns {number} Diferença em dias (pode ser negativo)
 */
export function diferencaDias(dataInicial, dataFinal) {
  const inicial = dataInicial instanceof Date ? dataInicial : new Date(String(dataInicial) + 'T00:00:00')
  const final = dataFinal instanceof Date ? dataFinal : new Date(String(dataFinal) + 'T00:00:00')
  
  if (isNaN(inicial.getTime()) || isNaN(final.getTime())) return 0
  
  return Math.ceil((final.getTime() - inicial.getTime()) / (1000 * 60 * 60 * 24))
}

/**
 * Adiciona dias a uma data.
 * @param {string|Date} data - Data base
 * @param {number} dias - Quantidade de dias (pode ser negativo)
 * @returns {Date} Nova data
 */
export function adicionarDias(data, dias) {
  const dateObj = data instanceof Date ? new Date(data) : new Date(String(data) + 'T00:00:00')
  dateObj.setDate(dateObj.getDate() + dias)
  return dateObj
}

/**
 * Adiciona meses a uma data.
 * @param {string|Date} data - Data base
 * @param {number} meses - Quantidade de meses
 * @returns {Date} Nova data
 */
export function adicionarMeses(data, meses) {
  const dateObj = data instanceof Date ? new Date(data) : new Date(String(data) + 'T00:00:00')
  dateObj.setMonth(dateObj.getMonth() + meses)
  return dateObj
}

/**
 * Verifica se uma data é hoje.
 * @param {string|Date} data - Data a verificar
 * @returns {boolean}
 */
export function isHoje(data) {
  const dataObj = data instanceof Date ? data : new Date(String(data) + 'T00:00:00')
  const hoje = new Date()
  
  return dataObj.getDate() === hoje.getDate() &&
         dataObj.getMonth() === hoje.getMonth() &&
         dataObj.getFullYear() === hoje.getFullYear()
}

/**
 * Verifica se uma data está atrasada (antes de hoje).
 * @param {string|Date} data - Data a verificar
 * @returns {boolean}
 */
export function isAtrasado(data) {
  if (!data) return false
  const dataObj = data instanceof Date ? data : new Date(String(data) + 'T00:00:00')
  const hoje = new Date()
  hoje.setHours(0, 0, 0, 0)
  return dataObj < hoje
}

export default {
  formatarData,
  formatarDataInput,
  parsearDataInput,
  diferencaDias,
  adicionarDias,
  adicionarMeses,
  isHoje,
  isAtrasado
}
