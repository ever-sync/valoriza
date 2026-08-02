import { test, describe, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { iniciarApp, usuario, EMPRESA_A } from './helpers/app.js'

const { app, fake, cabecalhosDe } = await iniciarApp()
const cabecalhos = await cabecalhosDe(usuario('administrador', EMPRESA_A))

const receita = (extra: Record<string, unknown> = {}) => ({
  id: 3,
  empresa_id: EMPRESA_A,
  valor_original: 1000,
  valor_pago: 0,
  valor_recebido: 1000,
  data_vencimento: '2026-07-01',
  status: 'Pendente',
  ...extra,
})

const gravado = () => fake.consultas.find((c) => c.tabela === 'tbl_receitas' && c.operacao === 'update')?.payload as Record<string, unknown>

beforeEach(() => {
  fake.limpar()
  fake.definirLinhas('tbl_receitas', [receita()])
})

const pagarParcial = (valor: number) =>
  app.inject({ method: 'POST', url: '/receita/3/pagar-parcial', headers: cabecalhos, payload: { valor_pago: valor } })

describe('pagamento parcial preserva o valor cobrado', () => {
  test('o valor original não é sobrescrito pelo saldo', async () => {
    await pagarParcial(300)
    const payload = gravado()
    assert.equal(payload.valor_original, 1000, 'a cobrança precisa continuar auditável')
    assert.equal(payload.valor_pago, 300)
    assert.equal(payload.valor_recebido, 700, 'valor_recebido segue espelhando o saldo')
    assert.equal(payload.status, 'Pendente')
  })

  test('pagamentos sucessivos acumulam em vez de recomeçar', async () => {
    fake.definirLinhas('tbl_receitas', [receita({ valor_pago: 300, valor_recebido: 700 })])
    await pagarParcial(200)
    const payload = gravado()
    assert.equal(payload.valor_pago, 500, '300 já pagos + 200 agora')
    assert.equal(payload.valor_recebido, 500)
    assert.equal(payload.valor_original, 1000)
  })

  test('quitar o saldo restante fecha a receita', async () => {
    fake.definirLinhas('tbl_receitas', [receita({ valor_pago: 900, valor_recebido: 100 })])
    await pagarParcial(100)
    const payload = gravado()
    assert.equal(payload.valor_pago, 1000)
    assert.equal(payload.valor_recebido, 0)
    assert.equal(payload.status, 'Recebido')
  })

  test('pagar acima do saldo não gera saldo negativo', async () => {
    await pagarParcial(1500)
    const payload = gravado()
    assert.equal(payload.valor_recebido, 0)
    assert.equal(payload.status, 'Recebido')
    assert.equal(payload.valor_original, 1000)
  })

  test('receita anterior à migration usa valor_recebido como original', async () => {
    // Linhas legadas têm valor_original nulo; a primeira escrita as normaliza.
    fake.definirLinhas('tbl_receitas', [receita({ valor_original: null, valor_pago: 0, valor_recebido: 800 })])
    await pagarParcial(300)
    const payload = gravado()
    assert.equal(payload.valor_original, 800)
    assert.equal(payload.valor_pago, 300)
    assert.equal(payload.valor_recebido, 500)
  })
})

describe('quitação integral', () => {
  test('marca o acumulado como a cobrança cheia', async () => {
    await app.inject({ method: 'POST', url: '/receita/3/quitar-integral', headers: cabecalhos, payload: {} })
    const payload = gravado()
    assert.equal(payload.valor_original, 1000)
    assert.equal(payload.valor_pago, 1000, 'quitação integral e pagamento parcial total precisam convergir')
    assert.equal(payload.status, 'Recebido')
  })
})

describe('CRUD de receitas', () => {
  test('criação registra o valor original a partir do valor cobrado', async () => {
    await app.inject({ method: 'POST', url: '/receita/inserir', headers: cabecalhos, payload: { descricao: 'Parcela 1', valor_recebido: 450 } })
    const payload = fake.consultas.find((c) => c.operacao === 'insert')?.payload as Record<string, unknown>
    assert.equal(payload.valor_original, 450)
  })

  test('edição não permite reescrever o valor original nem o acumulado pago', async () => {
    await app.inject({
      method: 'PUT',
      url: '/receita/editar/3',
      headers: cabecalhos,
      payload: { descricao: 'Editada', valor_original: 999999, valor_pago: 999999 },
    })
    const payload = gravado()
    assert.ok(!('valor_original' in payload), 'valor_original é imutável após a criação')
    assert.ok(!('valor_pago' in payload), 'valor_pago só se move pelas rotas de recebimento')
    assert.equal(payload.descricao, 'Editada')
  })
})
