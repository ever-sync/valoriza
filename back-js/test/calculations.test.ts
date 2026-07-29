import test from 'node:test'
import assert from 'node:assert/strict'
import { pricePayment, sacSchedule } from '../src/utils/calculations.js'

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
