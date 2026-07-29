/**
 * Calcula IOF financiado usando método iterativo (5 ciclos).
 * Segue a metodologia BACEN (Decreto 6.306/2007) com duas alíquotas (diário + adicional).
 * 
 * IOF Diário: incide sobre cada parcela de amortização × dias até vencimento.
 * Fórmula: Σ (amortização_i × taxa_diária × min(365, dias_i))
 * 
 * IOF Adicional: alíquota fixa sobre o valor principal.
 * 
 * @param {Object} params - Parâmetros do cálculo
 * @param {number} params.valor - Valor solicitado
 * @param {number} params.quantidadeParcelas - Número de parcelas
 * @param {number} params.taxaJuros - Taxa de juros mensal (ex: 0.03 = 3%)
 * @param {string} params.modelo - Modelo de amortização (Price, SAC, Sistema americano, Pagamento único)
 * @param {string} params.periodo - Período (Mensal, Semanal, Diário)
 * @param {string} params.dataAssinatura - Data de assinatura (yyyy-mm-dd)
 * @param {string} params.dataPrimeiraParcela - Data primeira parcela (yyyy-mm-dd)
 * @param {number} [params.taxaIofDiario=0.000082] - Taxa IOF diário em decimal (0.0082% ao dia = 0.000082)
 * @param {number} [params.taxaIofAdicional=0.0038] - Taxa IOF adicional (padrão 0.38%)
 * @returns {Object} Resultado com iofDiario, iofAdicional, iofTotal
 */
export function calcularIof(params) {
  const {
    valor,
    quantidadeParcelas,
    taxaJuros,
    modelo,
    periodo,
    dataAssinatura,
    dataPrimeiraParcela,
    taxaIofDiario = 0.000082,
    taxaIofAdicional = 0.0038
  } = params

  if (!valor || !quantidadeParcelas) {
    return { diario: 0, adicional: 0, total: 0 }
  }

  const dataAssinaturaDt = new Date(dataAssinatura + 'T00:00:00')
  const dataPrimeiraDt = new Date((dataPrimeiraParcela || dataAssinatura) + 'T00:00:00')

  // Pré-calcular dias até vencimento de cada parcela
  const diasPorParcela = []
  for (let i = 1; i <= quantidadeParcelas; i++) {
    const dataVenc = new Date(dataPrimeiraDt.getTime())
    if (periodo === 'Semanal') dataVenc.setDate(dataVenc.getDate() + (i - 1) * 7)
    else if (periodo === 'Diário') dataVenc.setDate(dataVenc.getDate() + (i - 1))
    else dataVenc.setMonth(dataVenc.getMonth() + (i - 1))

    const diffTime = dataVenc.getTime() - dataAssinaturaDt.getTime()
    const diffDays = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)))
    diasPorParcela.push(diffDays)
  }

  // Cálculo iterativo (5 ciclos para convergência — metodologia BACEN)
  let valorBase = valor
  let diarioRaw = 0
  let adicionalRaw = 0

  const r2 = (v) => Math.round(v * 100) / 100

  for (let iter = 0; iter < 5; iter++) {
    diarioRaw = 0
    const pmtTeorico = r2(taxaJuros > 0
      ? valorBase * (taxaJuros * Math.pow(1 + taxaJuros, quantidadeParcelas)) / (Math.pow(1 + taxaJuros, quantidadeParcelas) - 1)
      : valorBase / quantidadeParcelas)

    let saldoTeorico = r2(valorBase)

    for (let i = 1; i <= quantidadeParcelas; i++) {
      const jurosTeorico = r2(saldoTeorico * taxaJuros)
      let amortizacaoTeorica

      if (modelo === 'Sistema americano' || modelo === 'Pagamento único') {
        amortizacaoTeorica = (i === quantidadeParcelas) ? saldoTeorico : 0
      } else if (modelo === 'SAC') {
        amortizacaoTeorica = r2(valorBase / quantidadeParcelas)
      } else {
        amortizacaoTeorica = r2(pmtTeorico - jurosTeorico)
      }

      saldoTeorico = Math.max(0, r2(saldoTeorico - amortizacaoTeorica))

      // IOF diário: amortização × taxa_diária × min(365, dias)
      const diasIof = Math.min(365, diasPorParcela[i - 1])
      diarioRaw += (amortizacaoTeorica * diasIof * taxaIofDiario)
    }

    adicionalRaw = valor * taxaIofAdicional
    valorBase = valor + diarioRaw + adicionalRaw
  }

  const totalRaw = diarioRaw + adicionalRaw
  const total = Math.round(totalRaw * 100) / 100
  const adicional = Math.round(adicionalRaw * 100) / 100
  const diario = Math.max(0, Math.round((total - adicional) * 100) / 100)

  return { diario, adicional, total }
}

/**
 * Calcula o CET (Custo Efetivo Total) usando método Newton-Raphson.
 * Implementa IRR (Internal Rate of Return) iterativo.
 * 
 * @param {number} valor - Valor financiado
 * @param {Array<number>} fluxos - Array de valores das parcelas
 * @param {string} [periodo='Mensal'] - Período para anualização
 * @returns {Object} CET mensal e anual em percentual
 */
export function calcularCet(valor, fluxos, periodo = 'Mensal') {
  if (!valor || !fluxos.length) {
    return { mes: '0,00', ano: '0,00' }
  }

  let factor = 12
  if (periodo === 'Diário') factor = 365
  if (periodo === 'Quinzenal') factor = 24
  if (periodo === 'Semanal') factor = 52

  let guess = 0.01
  for (let i = 0; i < 100; i++) {
    let npv = -valor
    let dNpv = 0

    for (let t = 0; t < fluxos.length; t++) {
      npv += fluxos[t] / Math.pow(1 + guess, t + 1)
      dNpv -= (t + 1) * fluxos[t] / Math.pow(1 + guess, t + 2)
    }

    if (Math.abs(dNpv) < 1e-12) break
    guess = guess - npv / dNpv
  }

  const cetPeriodo = guess > 0 ? guess : 0
  const cetAno = (Math.pow(1 + cetPeriodo, factor) - 1)
  const cetMes = (Math.pow(1 + cetAno, 1 / 12) - 1)

  return {
    mes: (cetMes * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    ano: (cetAno * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  }
}

/**
 * Gera cronograma de parcelas para simulação.
 * 
 * @param {Object} params - Parâmetros da simulação
 * @param {number} params.valorFinanciado - Valor total financiado
 * @param {number} params.quantidadeParcelas - Número de parcelas
 * @param {number} params.taxaJuros - Taxa de juros mensal (decimal)
 * @param {string} params.modelo - Modelo de amortização
 * @param {string} params.periodo - Período de pagamento
 * @param {string} params.dataBase - Data base para cálculo
 * @returns {Array} Array de objetos com dados de cada parcela
 */
export function gerarCronograma(params) {
  const {
    valorFinanciado,
    quantidadeParcelas,
    taxaJuros,
    modelo,
    periodo,
    dataBase
  } = params

  if (!valorFinanciado || !quantidadeParcelas || !dataBase) {
    return []
  }

  const r2 = (v) => Math.round(v * 100) / 100
  const nEfetivo = periodo === 'Pagamento único' ? 1 : quantidadeParcelas
  const parcelas = []
  let saldo = r2(valorFinanciado)
  const dataBaseObj = new Date(dataBase + 'T00:00:00')

  // Arredondar PMT uma vez (padrão financeiro)
  let pmtPrice = 0
  if (modelo !== 'SAC' && modelo !== 'Sistema americano' && periodo !== 'Pagamento único') {
    pmtPrice = r2(valorFinanciado * (taxaJuros * Math.pow(1 + taxaJuros, nEfetivo)) / (Math.pow(1 + taxaJuros, nEfetivo) - 1))
  }

  for (let i = 1; i <= nEfetivo; i++) {
    const data = new Date(dataBaseObj.getTime())
    if (periodo === 'Semanal') data.setDate(data.getDate() + (i - 1) * 7)
    else if (periodo === 'Diário') data.setDate(data.getDate() + (i - 1))
    else if (periodo !== 'Pagamento único') data.setMonth(data.getMonth() + (i - 1))

    const juros = r2(saldo * taxaJuros)
    let amortizacao
    let parcela

    if (periodo === 'Pagamento único' || modelo === 'Sistema americano') {
      amortizacao = i === nEfetivo ? saldo : 0
      parcela = r2(juros + amortizacao)
    } else if (modelo === 'SAC') {
      amortizacao = r2(valorFinanciado / nEfetivo)
      parcela = r2(amortizacao + juros)
    } else {
      parcela = pmtPrice
      amortizacao = r2(parcela - juros)
    }

    saldo = Math.max(0, r2(saldo - amortizacao))
    if (i === nEfetivo && saldo > 0 && saldo < 0.5) {
      amortizacao = r2(amortizacao + saldo)
      parcela = r2(juros + amortizacao)
      saldo = 0
    }

    parcelas.push({
      num: i,
      parcela: parcela.toFixed(2),
      rawParcela: parcela,
      vencimento: data.toLocaleDateString('pt-BR'),
      juros: juros.toFixed(2),
      rawJuros: juros,
      amortizacao: amortizacao.toFixed(2),
      saldo: saldo.toFixed(2)
    })
  }

  return parcelas
}

/**
 * Calcula encargos de quitação (juros mora, multa).
 * 
 * @param {Object} params - Parâmetros do cálculo
 * @param {number} params.valorOriginal - Valor original da parcela
 * @param {number} params.diasAtraso - Dias em atraso
 * @param {number} params.taxaJuros - Taxa de juros mensal (decimal)
 * @param {number} params.taxaJurosMora - Taxa de juros de mora (decimal)
 * @param {number} params.multaMoratoria - Percentual de multa (ex: 2 = 2%)
 * @returns {Object} Valores calculados
 */
export function calcularEncargosQuitacao(params) {
  const {
    valorOriginal,
    diasAtraso,
    taxaJuros = 0,
    taxaJurosMora = 0.01,
    multaMoratoria = 0.02
  } = params

  if (diasAtraso <= 0) {
    return {
      jurosAtualizacao: 0,
      jurosMora: 0,
      multa: 0,
      total: valorOriginal
    }
  }

  const jurosAtualizacao = valorOriginal * taxaJuros * (diasAtraso / 30)
  const jurosMora = valorOriginal * taxaJurosMora * (diasAtraso / 30)
  const multa = valorOriginal * (multaMoratoria / 100)

  return {
    jurosAtualizacao: Math.round(jurosAtualizacao * 100) / 100,
    jurosMora: Math.round(jurosMora * 100) / 100,
    multa: Math.round(multa * 100) / 100,
    total: Math.round((valorOriginal + jurosAtualizacao + jurosMora + multa) * 100) / 100
  }
}

/**
 * Converte taxa de um período para outro (equivalência).
 * 
 * @param {number} taxaOriginal - Taxa no período original (ex: 0.03 = 3%)
 * @param {number} periodosOrigem - Períodos de origem (ex: 12 para anual)
 * @param {number} periodosDestino - Períodos de destino (ex: 1 para mensal)
 * @returns {number} Taxa convertida
 */
export function converterTaxa(taxaOriginal, periodosOrigem, periodosDestino) {
  return Math.pow(1 + taxaOriginal, periodosDestino / periodosOrigem) - 1
}

/**
 * Parse de configuração de taxa (string para número).
 * Trata formatos variados como "3,5", "3.5", "3.5%", "3,5%".
 * 
 * @param {string|number|null} valor - Valor a parsear
 * @returns {number|null} Valor numérico ou null se inválido
 */
export function parsearTaxa(valor) {
  if (valor === null || valor === undefined || valor === '') return null
  if (typeof valor === 'number') return valor

  return parseFloat(String(valor).replace(',', '.').replace('%', '').trim()) || null
}

export default {
  calcularIof,
  calcularCet,
  gerarCronograma,
  calcularEncargosQuitacao,
  converterTaxa,
  parsearTaxa
}
