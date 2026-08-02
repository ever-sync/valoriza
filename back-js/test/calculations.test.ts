import test from 'node:test'
import assert from 'node:assert/strict'
import { encargosAtraso, pricePayment, sacSchedule } from '../src/utils/calculations.js'

test('Price sem juros divide o principal igualmente', () => {
  assert.equal(pricePayment(1200, 0, 12), 100)
})

test('Price com juros retorna parcela positiva e amortiza o valor', () => {
  const payment = pricePayment(1000, 2, 10)
  assert.ok(payment > 100)
  assert.ok(payment < 130)
})

test('SAC reduz o saldo e mantém amortização constante', () => {
  const schedule = sacSchedule(1000, 1, 4)
  assert.equal(schedule.length, 4)
  assert.equal(schedule[0].amortization, 250)
  assert.ok(schedule[3].payment < schedule[0].payment)
  assert.equal(schedule[3].balance, 0)
})

test('Cálculos rejeitam quantidade de parcelas inválida', () => {
  assert.throws(() => pricePayment(1000, 1, 0))
  assert.throws(() => sacSchedule(1000, 1, -1))
})

test('Sem atraso não há encargo algum', () => {
  const encargos = encargosAtraso({ valor: 1000, diasAtraso: 0, jurosMoraMensal: 3, multaMoratoria: 5 })
  assert.deepEqual([encargos.juros_mora, encargos.multa, encargos.total_encargos], [0, 0, 0])
  assert.equal(encargos.valor_devido, 1000)
})

test('Juros de mora são pro rata die sobre a taxa mensal do contrato', () => {
  // 3% a.m. sobre 1000, por 30 dias, é a mensalidade cheia.
  assert.equal(encargosAtraso({ valor: 1000, diasAtraso: 30, jurosMoraMensal: 3, multaMoratoria: 0 }).juros_mora, 30)
  // Metade do mês, metade dos juros.
  assert.equal(encargosAtraso({ valor: 1000, diasAtraso: 15, jurosMoraMensal: 3, multaMoratoria: 0 }).juros_mora, 15)
})

test('Multa moratória é de incidência única, não proporcional aos dias', () => {
  const dezDias = encargosAtraso({ valor: 1000, diasAtraso: 10, jurosMoraMensal: 0, multaMoratoria: 2 })
  const noventaDias = encargosAtraso({ valor: 1000, diasAtraso: 90, jurosMoraMensal: 0, multaMoratoria: 2 })
  assert.equal(dezDias.multa, 20)
  assert.equal(noventaDias.multa, 20)
})

test('Encargos usam as taxas do contrato, não valores fixos', () => {
  const contrato = encargosAtraso({ valor: 2000, diasAtraso: 30, jurosMoraMensal: 5, multaMoratoria: 10 })
  assert.equal(contrato.juros_mora, 100)
  assert.equal(contrato.multa, 200)
  assert.equal(contrato.valor_devido, 2300)
  assert.deepEqual(contrato.taxas, { juros_mora: 5, multa_moratoria: 10 })
})

test('Taxa zero no contrato é respeitada e não cai no padrão', () => {
  const encargos = encargosAtraso({ valor: 1000, diasAtraso: 60, jurosMoraMensal: 0, multaMoratoria: 0 })
  assert.equal(encargos.total_encargos, 0)
  assert.equal(encargos.valor_devido, 1000)
})

test('Sem contrato vinculado aplica o padrão de 1% a.m. e 2%', () => {
  const encargos = encargosAtraso({ valor: 1000, diasAtraso: 30, jurosMoraMensal: null, multaMoratoria: null })
  assert.equal(encargos.juros_mora, 10)
  assert.equal(encargos.multa, 20)
  assert.deepEqual(encargos.taxas, { juros_mora: 1, multa_moratoria: 2 })
})
