import { test, describe, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { iniciarApp, usuario, EMPRESA_A, EMPRESA_B } from './helpers/app.js'
import type { Consulta } from './helpers/fakeDb.js'

// O backend fala com o Supabase pela service_role key, que ignora RLS. O isolamento
// entre empresas existe apenas enquanto toda consulta filtrar por empresa_id — um
// .eq() esquecido vaza dados de outra empresa sem nenhuma barreira no banco.

const contrato = {
  id: 7,
  empresa_id: EMPRESA_A,
  valor_solicitado: 12000,
  quantidade_parcelas: 12,
  taxa_juros: 2,
  modelo_amortizacao: 'price',
  data_primeira_parcela: '2026-09-10',
  status: 'Ativo',
}
const receita = { id: 3, empresa_id: EMPRESA_A, contrato_id: 7, parcela_numero: 1, valor_recebido: 1000, data_vencimento: '2026-07-01' }

const { app, fake, cabecalhosDe } = await iniciarApp()

const linhasPadrao = () => {
  fake.definirLinhas('tbl_contratos', [contrato])
  fake.definirLinhas('tbl_receitas', [receita])
  fake.definirLinhas('tbl_despesas', [{ id: 4, empresa_id: EMPRESA_A, valor_pago: 500, data_vencimento: '2026-07-02' }])
  fake.definirLinhas('tbl_contratos_parcelas', [])
  fake.definirLinhas('tbl_contratos_garantias', [])
  fake.definirLinhas('tbl_receitas_prorrogacoes', [])
  fake.definirLinhas('tbl_pessoas_fisicas', [{ id: 1, empresa_id: EMPRESA_A }])
  fake.definirLinhas('tbl_pessoas_juridicas', [{ id: 2, empresa_id: EMPRESA_A }])
  fake.definirLinhas('tbl_bancos', [{ id: 5, empresa_id: EMPRESA_A }])
}

beforeEach(() => {
  fake.limpar()
  linhasPadrao()
})

/** Toda consulta a tabela de negócio precisa carregar o empresa_id da sessão. */
function conferirIsolamento(consultas: Consulta[], empresaId: number, contexto: string) {
  const deNegocio = consultas.filter((c) => c.tabela.startsWith('tbl_'))
  assert.ok(deNegocio.length > 0, `${contexto}: nenhuma consulta foi emitida, o teste não prova nada`)

  for (const consulta of deNegocio) {
    const onde = `${contexto} → ${consulta.operacao} ${consulta.tabela}`
    if (consulta.operacao === 'insert') {
      const linhas = Array.isArray(consulta.payload) ? consulta.payload : [consulta.payload]
      for (const linha of linhas) {
        assert.equal((linha as Record<string, unknown>).empresa_id, empresaId, `${onde}: insert sem empresa_id da sessão`)
      }
    } else {
      assert.equal(consulta.filtros.empresa_id, empresaId, `${onde}: consulta sem filtro de empresa_id`)
    }
  }
}

type Requisicao = { metodo: 'GET' | 'POST' | 'PUT' | 'DELETE'; url: string; payload?: unknown }

const rotasDeLeitura: Requisicao[] = [
  { metodo: 'GET', url: '/banco/buscar' },
  { metodo: 'GET', url: '/despesa/buscar' },
  { metodo: 'GET', url: '/receita/buscar' },
  { metodo: 'GET', url: '/pessoa-fisica/buscar' },
  { metodo: 'GET', url: '/pessoa-juridica/buscar' },
  { metodo: 'GET', url: '/contrato/buscar' },
  { metodo: 'GET', url: '/contrato/7/parcelas' },
  { metodo: 'GET', url: '/contrato/garantias/7' },
  { metodo: 'GET', url: '/dashboard/stats' },
  { metodo: 'GET', url: '/fluxo-caixa/periodo?inicio=2026-01-01&fim=2026-12-31' },
  { metodo: 'GET', url: '/fluxo-caixa/projetado' },
  { metodo: 'GET', url: '/receita/3/contrato' },
  { metodo: 'GET', url: '/receita/3/prorrogacoes' },
  { metodo: 'GET', url: '/relatorios/sumario-contratos' },
  { metodo: 'GET', url: '/relatorios/sumario-clientes' },
  { metodo: 'GET', url: '/relatorios/contabil-recebimentos' },
  { metodo: 'GET', url: '/relatorios/contabil-pagamentos' },
]

const rotasDeEscrita: Requisicao[] = [
  { metodo: 'POST', url: '/banco/inserir', payload: { banco: 'Banco Novo', agencia: '1', conta: '2' } },
  { metodo: 'PUT', url: '/banco/editar/5', payload: { banco: 'Banco Editado' } },
  { metodo: 'DELETE', url: '/banco/excluir/5' },
  { metodo: 'POST', url: '/despesa/inserir', payload: { descricao: 'Aluguel', valor_pago: 100 } },
  {
    metodo: 'POST',
    url: '/contrato/inserir',
    payload: {
      tipo_operacao: 'emprestimo',
      valor_solicitado: 5000,
      periodo_amortizacao: 'mensal',
      modelo_amortizacao: 'price',
      taxa_juros: 2,
      quantidade_parcelas: 6,
      data_assinatura: '2026-08-01',
      data_primeira_parcela: '2026-09-01',
      garantias: [{ tipo: 'imovel', valor: 100000 }],
    },
  },
  { metodo: 'PUT', url: '/contrato/editar/7', payload: { valor_solicitado: 6000, garantias: [{ tipo: 'veiculo' }] } },
  { metodo: 'DELETE', url: '/contrato/excluir/7' },
  { metodo: 'POST', url: '/contrato/7/crdc' },
  { metodo: 'POST', url: '/contrato/7/lancar-parcelas' },
  { metodo: 'POST', url: '/receita/3/calcular-encargos', payload: { data_recebimento: '2026-08-01' } },
  { metodo: 'POST', url: '/receita/3/prorrogar', payload: { data_vencimento_nova: '2026-09-01', justificativa: 'acordo' } },
  { metodo: 'POST', url: '/receita/3/pagar-parcial', payload: { valor_pago: 300 } },
  { metodo: 'POST', url: '/receita/3/pagar-carencia', payload: {} },
  { metodo: 'POST', url: '/receita/3/quitar-integral', payload: {} },
]

describe('isolamento por empresa', () => {
  for (const empresaId of [EMPRESA_A, EMPRESA_B]) {
    test(`leituras filtram sempre pela empresa da sessão (empresa ${empresaId})`, async () => {
      const cabecalhos = await cabecalhosDe(usuario('administrador', empresaId))
      for (const { metodo, url } of rotasDeLeitura) {
        fake.limpar()
        linhasPadrao()
        const resposta = await app.inject({ method: metodo, url, headers: cabecalhos })
        assert.equal(resposta.statusCode, 200, `${url} respondeu ${resposta.statusCode}: ${resposta.body}`)
        conferirIsolamento(fake.consultas, empresaId, `${metodo} ${url}`)
      }
    })

    test(`escritas gravam e filtram pela empresa da sessão (empresa ${empresaId})`, async () => {
      const cabecalhos = await cabecalhosDe(usuario('administrador', empresaId))
      for (const { metodo, url, payload } of rotasDeEscrita) {
        fake.limpar()
        linhasPadrao()
        const resposta = await app.inject({ method: metodo, url, headers: cabecalhos, payload: payload ?? {} })
        assert.ok(resposta.statusCode < 400, `${metodo} ${url} respondeu ${resposta.statusCode}: ${resposta.body}`)
        conferirIsolamento(fake.consultas, empresaId, `${metodo} ${url}`)
      }
    })
  }
})

describe('acesso entre empresas', () => {
  test('editar registro de outra empresa não escapa do filtro', async () => {
    const cabecalhos = await cabecalhosDe(usuario('administrador', EMPRESA_B))
    await app.inject({ method: 'PUT', url: '/contrato/editar/7', headers: cabecalhos, payload: { valor_solicitado: 999 } })

    const update = fake.consultas.find((c) => c.tabela === 'tbl_contratos' && c.operacao === 'update')
    assert.ok(update)
    // O id da empresa A é alcançável na URL; o filtro é o que impede a escrita.
    assert.equal(update.filtros.id, '7')
    assert.equal(update.filtros.empresa_id, EMPRESA_B)
  })

  test('empresa_id enviado no corpo é ignorado em favor da sessão', async () => {
    const cabecalhos = await cabecalhosDe(usuario('administrador', EMPRESA_B))
    await app.inject({
      method: 'POST',
      url: '/banco/inserir',
      headers: cabecalhos,
      payload: { banco: 'Banco', agencia: '1', conta: '2', empresa_id: EMPRESA_A, id: 999, cadastrado_por: 1 },
    })

    const insert = fake.consultas.find((c) => c.operacao === 'insert')
    const linha = insert?.payload as Record<string, unknown>
    assert.equal(linha.empresa_id, EMPRESA_B, 'empresa_id do corpo não pode sobrescrever o da sessão')
    assert.ok(!('id' in linha), 'id não pode ser definido pelo cliente')
  })

  test('parcelas de contrato de outra empresa não são listadas sem o filtro', async () => {
    const cabecalhos = await cabecalhosDe(usuario('contador', EMPRESA_B))
    await app.inject({ method: 'GET', url: '/contrato/7/parcelas', headers: cabecalhos })

    const consulta = fake.consultas.find((c) => c.tabela === 'tbl_contratos_parcelas')
    assert.ok(consulta)
    assert.equal(consulta.filtros.contrato_id, '7')
    assert.equal(consulta.filtros.empresa_id, EMPRESA_B)
  })
})
