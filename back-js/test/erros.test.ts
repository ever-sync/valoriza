import { test, describe, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { iniciarApp, usuario, EMPRESA_A } from './helpers/app.js'

const { app, fake, cabecalhosDe } = await iniciarApp()
const cabecalhos = await cabecalhosDe(usuario('administrador', EMPRESA_A))

beforeEach(() => {
  fake.limpar()
  fake.definirErro('tbl_bancos', null)
  fake.definirLinhas('tbl_bancos', [{ id: 1, empresa_id: EMPRESA_A }])
})

describe('tratamento de erros', () => {
  // O handler de erro da app só passou a valer depois de ser registrado antes das
  // rotas. Antes disso o Fastify respondia com o formato padrão, devolvendo a
  // mensagem interna do erro no corpo.
  test('corpo inválido responde 400 com mensagem genérica', async () => {
    const resposta = await app.inject({ method: 'POST', url: '/contrato/inserir', headers: cabecalhos, payload: { tipo_operacao: '' } })
    assert.equal(resposta.statusCode, 400)
    assert.deepEqual(resposta.json(), { success: false, message: 'Dados inválidos.' })
  })

  test('detalhes do Zod não vazam para o cliente', async () => {
    const resposta = await app.inject({ method: 'POST', url: '/contrato/inserir', headers: cabecalhos, payload: { tipo_operacao: '' } })
    for (const vazamento of ['invalid_type', 'expected', 'path', 'valor_solicitado']) {
      assert.ok(!resposta.body.includes(vazamento), `corpo expôs "${vazamento}"`)
    }
  })

  test('falha no banco responde 500 sem revelar a causa', async () => {
    fake.definirErro('tbl_bancos', new Error('conexão recusada em db.interno:5432 (usuário postgres)'))
    const resposta = await app.inject({ method: 'GET', url: '/banco/buscar', headers: cabecalhos })
    assert.equal(resposta.statusCode, 500)
    assert.deepEqual(resposta.json(), { success: false, message: 'Erro interno do servidor.' })
    assert.ok(!resposta.body.includes('db.interno'), 'endereço interno vazou')
    assert.ok(!resposta.body.includes('postgres'), 'usuário do banco vazou')
  })

  test('rota inexistente continua respondendo 404', async () => {
    assert.equal((await app.inject({ method: 'GET', url: '/nao-existe', headers: cabecalhos })).statusCode, 404)
  })
})
