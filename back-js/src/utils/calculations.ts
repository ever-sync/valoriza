export function pricePayment(principal: number, monthlyRatePercent: number, installments: number) {
  if (installments <= 0) throw new Error('Quantidade de parcelas inválida.')
  const rate = monthlyRatePercent / 100
  if (rate === 0) return principal / installments
  return principal * (rate * (1 + rate) ** installments) / ((1 + rate) ** installments - 1)
}

// Aplicados quando a receita não tem contrato vinculado ou o contrato não definiu a
// regra. Correspondem à prática de mercado (1% a.m. + 2%) e ao que o backend usava
// fixo antes de passar a ler os campos do contrato.
export const PADRAO_JUROS_MORA_MENSAL = 1
export const PADRAO_MULTA_MORATORIA = 2

export type EncargosAtraso = {
  dias_atraso: number
  juros_mora: number
  multa: number
  total_encargos: number
  valor_devido: number
  taxas: { juros_mora: number; multa_moratoria: number }
}

const emCentavos = (valor: number) => Number(valor.toFixed(2))

/**
 * Juros de mora pro rata die a partir da taxa mensal do contrato, mais multa
 * moratória de incidência única. Taxa zero explícita no contrato é respeitada —
 * o padrão só entra quando o campo é nulo.
 */
export function encargosAtraso(params: {
  valor: number
  diasAtraso: number
  jurosMoraMensal?: number | null
  multaMoratoria?: number | null
}): EncargosAtraso {
  const dias = Math.max(0, Math.trunc(params.diasAtraso))
  const taxaMora = params.jurosMoraMensal ?? PADRAO_JUROS_MORA_MENSAL
  const taxaMulta = params.multaMoratoria ?? PADRAO_MULTA_MORATORIA
  const valor = Number.isFinite(params.valor) ? params.valor : 0

  const juros_mora = dias > 0 ? emCentavos(valor * (taxaMora / 100) * (dias / 30)) : 0
  const multa = dias > 0 ? emCentavos(valor * (taxaMulta / 100)) : 0

  return {
    dias_atraso: dias,
    juros_mora,
    multa,
    total_encargos: emCentavos(juros_mora + multa),
    valor_devido: emCentavos(valor + juros_mora + multa),
    taxas: { juros_mora: taxaMora, multa_moratoria: taxaMulta },
  }
}

export function sacSchedule(principal: number, monthlyRatePercent: number, installments: number) {
  if (installments <= 0) throw new Error('Quantidade de parcelas inválida.')
  const rate = monthlyRatePercent / 100
  const amortization = principal / installments
  return Array.from({ length: installments }, (_, index) => {
    const balance = Math.max(0, principal - amortization * index)
    const interest = balance * rate
    return { installment: index + 1, amortization, interest, payment: amortization + interest, balance: Math.max(0, balance - amortization) }
  })
}
