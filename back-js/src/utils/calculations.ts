export function pricePayment(principal: number, monthlyRatePercent: number, installments: number) {
  if (installments <= 0) throw new Error('Quantidade de parcelas inválida.')
  const rate = monthlyRatePercent / 100
  if (rate === 0) return principal / installments
  return principal * (rate * (1 + rate) ** installments) / ((1 + rate) ** installments - 1)
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
