/**
 * Máscaras para formatos brasileiros comuns
 */

export const masks = {
  // Máscara para CEP: 00000-000
  cep: (v) => {
    v = v.replace(/\D/g, '')
    if (v.length > 8) v = v.slice(0, 8)
    return v.replace(/(\d{5})(\d)/, '$1-$2')
  },

  // Máscara para Telefone Fixo: (00) 0000-0000
  telefone: (v) => {
    v = v.replace(/\D/g, '')
    if (v.length > 10) v = v.slice(0, 10)
    return v.replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2')
  },

  // Máscara para Celular: (00) 00000-0000
  celular: (v) => {
    v = v.replace(/\D/g, '')
    if (v.length > 11) v = v.slice(0, 11)
    return v.replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2')
  },

  // Máscara para WhatsApp (mesma do celular)
  whatsapp: (v) => masks.celular(v),

  // Máscara para CPF: 000.000.000-00
  cpf: (v) => {
    v = v.replace(/\D/g, '')
    if (v.length > 11) v = v.slice(0, 11)
    return v.replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
  },

  // Máscara para CNPJ: 00.000.000/0000-00
  cnpj: (v) => {
    v = v.replace(/\D/g, '')
    if (v.length > 14) v = v.slice(0, 14)
    return v.replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2')
  },

  // Máscara para Real (Moeda): 1.234,56
  real: (v) => {
    if (!v) return 'R$ 0,00'
    let val = v.toString()
    
    // Se já for um número com ponto decimal (vindo do banco), formata direto
    if (val.includes('.') && !isNaN(parseFloat(val))) {
      val = parseFloat(val).toFixed(2)
    } else {
      val = val.replace(/\D/g, '')
      val = (Number(val) / 100).toFixed(2)
    }

    const [int, dec] = val.split('.')
    const fmtInt = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
    return `R$ ${fmtInt},${dec}`
  },

  // Máscara para Porcentagem: 0,00 %
  porcentagem: (v) => {
    if (!v) return '0,00 %'
    let val = v.toString()

    // Se vier do banco (float/decimal com ponto), formata direto
    if (val.includes('.') && !isNaN(parseFloat(val))) {
      val = parseFloat(val).toFixed(2)
    } else {
      val = val.replace(/\D/g, '')
      if (val === '') return '0,00 %'
      val = (Number(val) / 100).toFixed(2)
    }
    
    const [int, dec] = val.split('.')
    return `${int},${dec} %`
  },

  // Máscara para Decimal Genérico: 0,00
  decimal: (v) => {
    if (!v) return '0,00'
    let val = v.toString()

    if (val.includes('.') && !isNaN(parseFloat(val))) {
      val = parseFloat(val).toFixed(2)
    } else {
      val = val.replace(/\D/g, '')
      if (val === '') return '0,00'
      val = (Number(val) / 100).toFixed(2)
    }
    
    const [int, dec] = val.split('.')
    return `${int},${dec}`
  }
}

/**
 * Função utilitária para aplicar a máscara dinamicamente pelo nome
 */
export const applyMask = (name, value) => {
  if (!name || !masks[name] || !value) return value
  return masks[name](value.toString())
}

export default masks
