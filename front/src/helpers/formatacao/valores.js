/**
 * Helper de formatação de valores monetários brasileiros.
 * Centraliza todas as formatações de moeda e números.
 */

/**
 * Converte qualquer valor para formato de real brasileiro.
 * @param {string|number} valor - Valor a ser formatado
 * @returns {string} Valor formatado (ex: "R$ 1.234,56")
 */
export function formatarReal(valor) {
  if (valor === null || valor === undefined || valor === '') return 'R$ 0,00'
  
  let val
  if (typeof valor === 'number') {
    val = valor.toFixed(2)
  } else {
    val = String(valor)
    if (val.includes('.') && !isNaN(parseFloat(val))) {
      val = parseFloat(val).toFixed(2)
    } else {
      val = val.replace(/\D/g, '')
      val = (Number(val) / 100).toFixed(2)
    }
  }

  const [int, dec] = val.split('.')
  const fmtInt = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `R$ ${fmtInt},${dec}`
}

/**
 * Converte valor do banco para formato de real (sem símbolo).
 * Útil para inputs de formulários.
 * @param {string|number} valor - Valor a ser formatado
 * @returns {string} Valor formatado (ex: "1234,56")
 */
export function formatarDecimal(valor) {
  if (valor === null || valor === undefined || valor === '') return '0,00'
  
  let val
  if (typeof valor === 'number') {
    val = valor.toFixed(2)
  } else {
    val = String(valor)
    if (val.includes('.') && !isNaN(parseFloat(val))) {
      val = parseFloat(val).toFixed(2)
    } else {
      val = val.replace(/\D/g, '')
      val = (Number(val) / 100).toFixed(2)
    }
  }

  const [int, dec] = val.split('.')
  const fmtInt = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${fmtInt},${dec}`
}

/**
 * Converte valor do formulário para número (parse).
 * Aceita tanto máscara brasileira quanto número do banco.
 * @param {string|number} valor - Valor a ser parseado
 * @returns {number} Valor numérico
 */
export function parsearValor(valor) {
  if (typeof valor === 'number') return valor
  if (!valor) return 0
  
  const s = String(valor).trim()
  
  if (/^-?\d+(\.\d+)?$/.test(s)) {
    return parseFloat(s)
  }
  
  return parseFloat(s.replace(/R\$\s*/g, '').replace(/\./g, '').replace(',', '.')) || 0
}

/**
 * Formata número para exibição de porcentagem.
 * @param {string|number} valor - Valor a ser formatado (0.03 = 3%)
 * @returns {string} Porcentagem formatada (ex: "3,00 %")
 */
export function formatarPorcentagem(valor) {
  if (!valor && valor !== 0) return '0,00 %'
  
  let val = String(valor)
  
  if (val.includes('.') && !isNaN(parseFloat(val))) {
    val = parseFloat(val).toFixed(2)
  } else {
    val = val.replace(/\D/g, '')
    if (val === '') return '0,00 %'
    val = (Number(val) / 100).toFixed(2)
  }
  
  const [int, dec] = val.split('.')
  return `${int},${dec} %`
}

/**
 * Converte valor monetário formatado de volta para número.
 * Usado para preparar payloads antes de enviar ao backend.
 * @param {string} valorFormatado - Valor formatado (ex: "R$ 1.234,56")
 * @returns {string} Valor no formato do banco (ex: "1234.56")
 */
export function desformatarParaBanco(valorFormatado) {
  if (!valorFormatado) return ''
  return String(valorFormatado)
    .replace(/R\$\s*/g, '')
    .replace(/\./g, '')
    .replace(',', '.')
}

export default {
  formatarReal,
  formatarDecimal,
  parsearValor,
  formatarPorcentagem,
  desformatarParaBanco
}
