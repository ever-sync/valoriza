import { test, describe, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { iniciarApp, usuario, EMPRESA_A } from './helpers/app.js'

const { app, fake, cabecalhosDe } = await iniciarApp()
const cabecalhos = await cabecalhosDe(usuario('administrador', EMPRESA_A))

const mesAtual = new Date().toISOString().slice(0, 7)
const venceEsteMes = `${mesAtual}-15`

/** Cobrada em 1000, já recebida por completo em pagamentos parciais. */
const quitadaEmParcelas = {
  id: 1,
  empresa_id: EMPRESA_A,
  descricao: 'Parcela quitada',
  valor_original: 1000,
  valor_pago: 1000,
  valor_recebido: 0,
  status: 'Recebido',
  data_vencimento: venceEsteMes,
  data_recebimento: venceEsteMes,
}

/** Cobrada em 1000, com 400 recebidos e 600 em aberto. */
const parcialmentePaga = {
  id: 2,
  empresa_id: EMPRESA_A,
  descricao: 'Parcela parcial',
  valor_original: 1000,
  valor_pago: 400,
  valor_recebido: 600,
  status: 'Pendente',
  data_vencimento: venceEsteMes,
  data_recebimento: venceEsteMes,
}

const stats = async () => (await app.inject({ method: 'GET', url: '/dashboard/stats', headers: cabecalhos })).json()

beforeEach(() => {
  fake.limpar()
  fake.definirLinhas('tbl_receitas', [quitadaEmParcelas, parcialmentePaga])
  fake.definirLinhas('tbl_despesas', [])
  fake.definirLinhas('tbl_contratos', [])
  fake.definirLinhas('tbl_pessoas_fisicas', [])
  fake.definirLinhas('tbl_pessoas_juridicas', [])
})

describe('faturamento por competência', () => {
  test('receita do mês soma o valor cobrado, não o saldo', async () => {
    const { data } = await stats()
    // Antes desta mudança daria 600: a parcela quitada sumia e a parcial encolhia.
    assert.equal(data.receita_mes, 2000)
  })

  test('quitar em parcelas e quitar integralmente produzem o mesmo faturamento', async () => {
    const { data: comParciais } = await stats()

    fake.definirLinhas('tbl_receitas', [
      { ...quitadaEmParcelas, valor_pago: 1000, valor_recebido: 0 },
      { ...parcialmentePaga, valor_pago: 1000, valor_recebido: 0, status: 'Recebido' },
    ])
    const { data: tudoQuitado } = await stats()

    assert.equal(comParciais.receita_mes, tudoQuitado.receita_mes, 'o caminho de quitação não pode alterar o faturamento')
  })

  test('receitas pendentes e atrasos refletem o saldo em aberto', async () => {
    const { data } = await stats()
    // Só a parcialmente paga segue pendente, com 600 em aberto.
    assert.equal(data.receitas_pendentes, 600)
  })

  test('gráfico dos últimos meses também usa o valor cobrado', async () => {
    const { data } = await stats()
    const mesCorrente = data.grafico.at(-1)
    assert.equal(mesCorrente.total, 2000)
  })

  test('receita anterior à migration continua contabilizada pelo valor_recebido', async () => {
    fake.definirLinhas('tbl_receitas', [
      { id: 9, empresa_id: EMPRESA_A, valor_original: null, valor_pago: 0, valor_recebido: 750, status: 'Pendente', data_vencimento: venceEsteMes },
    ])
    const { data } = await stats()
    assert.equal(data.receita_mes, 750)
    assert.equal(data.receitas_pendentes, 750)
  })
})

describe('relatório contábil de recebimentos', () => {
  const recebimentos = async () =>
    (await app.inject({ method: 'GET', url: '/relatorios/contabil-recebimentos', headers: cabecalhos })).json()

  test('soma o que efetivamente entrou, não a cobrança', async () => {
    const { meta } = await recebimentos()
    // 1000 quitados + 400 recebidos da parcial.
    assert.equal(meta.valor_total, 1400)
  })

  test('linha anterior à migration é contada pelo valor_recebido', async () => {
    fake.definirLinhas('tbl_receitas', [
      { id: 9, empresa_id: EMPRESA_A, valor_original: null, valor_pago: 0, valor_recebido: 500, status: 'Recebido', data_recebimento: venceEsteMes },
    ])
    const { meta } = await recebimentos()
    assert.equal(meta.valor_total, 500)
  })
})
