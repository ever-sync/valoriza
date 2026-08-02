import { test, describe, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { iniciarApp, usuario, EMPRESA_A } from './helpers/app.js'

const { app, fake, cabecalhosDe } = await iniciarApp()
const cabecalhos = await cabecalhosDe(usuario('administrador', EMPRESA_A))

const comContrato = { id: 3, empresa_id: EMPRESA_A, contrato_id: 7, valor_recebido: 2000, data_vencimento: '2026-07-01' }
const semContrato = { ...comContrato, contrato_id: null }
const contrato = { id: 7, empresa_id: EMPRESA_A, taxa_juros: 2.5, juros_mora: 5, multa_moratoria: 10 }

const calcular = (payload: Record<string, unknown> = { data_recebimento: '2026-07-31' }) =>
  app.inject({ method: 'POST', url: '/receita/3/calcular-encargos', headers: cabecalhos, payload })

beforeEach(() => {
  fake.limpar()
  fake.definirLinhas('tbl_receitas', [comContrato])
  fake.definirLinhas('tbl_contratos', [contrato])
})

describe('encargos de atraso vêm do contrato', () => {
  test('aplica juros de mora e multa definidos no contrato', async () => {
    const corpo = (await calcular()).json()
    // 30 dias de atraso sobre 2000: 5% a.m. = 100 de mora, 10% = 200 de multa.
    assert.equal(corpo.data.dias_atraso, 30)
    assert.equal(corpo.data.juros_mora, 100)
    assert.equal(corpo.data.multa, 200)
    assert.equal(corpo.data.valor_devido, 2300)
  })

  test('taxas devolvidas são alíquotas, não valores em reais', async () => {
    // A tela de recebimento renderiza estes campos como "(x% a.m.)".
    const corpo = (await calcular()).json()
    assert.deepEqual(corpo.data.taxas, { juros_mora: 5, multa_moratoria: 10, juros_contrato: 2.5 })
  })

  test('busca do contrato respeita o isolamento por empresa', async () => {
    await calcular()
    const consulta = fake.consultas.find((c) => c.tabela === 'tbl_contratos')
    assert.ok(consulta, 'o contrato precisa ser consultado para as regras valerem')
    assert.equal(consulta.filtros.empresa_id, EMPRESA_A)
    assert.equal(consulta.filtros.id, 7)
  })

  test('receita sem contrato cai no padrão de mercado', async () => {
    fake.definirLinhas('tbl_receitas', [semContrato])
    const corpo = (await calcular()).json()
    assert.equal(corpo.data.juros_mora, 20, '1% a.m. sobre 2000 por 30 dias')
    assert.equal(corpo.data.multa, 40, '2% sobre 2000')
    assert.equal(fake.consultas.filter((c) => c.tabela === 'tbl_contratos').length, 0)
  })

  test('sem atraso não cobra nada', async () => {
    const corpo = (await calcular({ data_recebimento: '2026-06-20' })).json()
    assert.equal(corpo.data.dias_atraso, 0)
    assert.equal(corpo.data.total_encargos, 0)
    assert.equal(corpo.data.valor_devido, 2000)
  })

  test('data de recebimento ausente usa a data de hoje', async () => {
    const corpo = (await calcular({})).json()
    const esperado = Math.floor((Date.now() - new Date('2026-07-01T00:00:00').getTime()) / 86400000)
    assert.equal(corpo.data.dias_atraso, Math.max(0, esperado))
  })
})
