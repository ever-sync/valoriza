import { test, describe, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import bcrypt from 'bcryptjs'
import { iniciarApp, EMPRESA_A } from './helpers/app.js'

const { app, fake } = await iniciarApp()
// Importado depois de iniciarApp de propósito — ver o aviso em helpers/app.ts.
const { MAX_FALHAS } = await import('../src/loginThrottle.js')

const EMAIL = 'operador@exemplo.com'
const SENHA = 'SenhaCorreta123'
// Custo baixo só para o teste não gastar 200ms por hash.
const hash = await bcrypt.hash(SENHA, 4)

const usuarioAtivo = {
  id: 10,
  empresa_id: EMPRESA_A,
  email: EMAIL,
  senha: hash,
  nome_completo: 'Operador',
  perfil_acesso: 'operador',
  ativo: true,
}

/** Cada linha representa uma falha de login já registrada dentro da janela. */
const comFalhas = (quantidade: number) =>
  fake.definirLinhas('tbl_tentativas_login', Array.from({ length: quantidade }, (_, i) => ({ id: i + 1, email: EMAIL })))

const entrar = (senha = SENHA) =>
  app.inject({ method: 'POST', url: '/auth/login', payload: { email: EMAIL, senha } })

beforeEach(() => {
  fake.limpar()
  fake.definirLinhas('tbl_usuarios', [usuarioAtivo])
  comFalhas(0)
})

describe('limite de tentativas de login', () => {
  test('senha correta entra e devolve o token', async () => {
    const resposta = await entrar()
    assert.equal(resposta.statusCode, 200)
    assert.ok(resposta.json().token, 'o front depende do token no corpo')
  })

  test('senha errada responde 401 e registra a falha', async () => {
    const resposta = await entrar('senhaErrada')
    assert.equal(resposta.statusCode, 401)
    const registro = fake.consultas.find((c) => c.tabela === 'tbl_tentativas_login' && c.operacao === 'insert')
    assert.ok(registro, 'a falha precisa ser contabilizada')
    assert.equal((registro.payload as Record<string, unknown>).email, EMAIL)
  })

  test(`bloqueia com 429 a partir de ${MAX_FALHAS} falhas na janela`, async () => {
    comFalhas(MAX_FALHAS)
    const resposta = await entrar()
    assert.equal(resposta.statusCode, 429)
  })

  test('bloqueio acontece antes de verificar a senha', async () => {
    // O ponto do limite é não deixar o atacante testar mais uma senha sequer.
    comFalhas(MAX_FALHAS)
    await entrar()
    assert.equal(fake.consultas.filter((c) => c.tabela === 'tbl_usuarios').length, 0)
  })

  test('uma falha abaixo do limite ainda permite tentar', async () => {
    comFalhas(MAX_FALHAS - 1)
    assert.equal((await entrar()).statusCode, 200)
  })

  test('login bem-sucedido zera as falhas acumuladas', async () => {
    comFalhas(MAX_FALHAS - 1)
    await entrar()
    const limpeza = fake.consultas.find((c) => c.tabela === 'tbl_tentativas_login' && c.operacao === 'delete')
    assert.ok(limpeza, 'quem acertou a senha não pode seguir perto do bloqueio')
    assert.equal(limpeza.filtros.email, EMAIL)
  })

  test('a contagem é por e-mail e restrita à janela de tempo', async () => {
    await entrar('senhaErrada')
    const contagem = fake.consultas.find((c) => c.tabela === 'tbl_tentativas_login' && c.operacao === 'select')
    assert.ok(contagem)
    assert.equal(contagem.filtros.email, EMAIL)
    assert.ok(contagem.filtros['criado_em>='], 'sem recorte de janela o bloqueio seria permanente')
  })
})

describe('disponibilidade do login', () => {
  test('vários usuários seguem conseguindo logar em sequência', async () => {
    // Regressão: o limite por IP do @fastify/rate-limit contava 127.0.0.1 para todos
    // atrás do proxy da Vercel, barrando o sexto login por minuto da empresa inteira.
    for (let i = 0; i < MAX_FALHAS + 5; i++) {
      fake.limpar()
      fake.definirLinhas('tbl_usuarios', [usuarioAtivo])
      comFalhas(0)
      assert.equal((await entrar()).statusCode, 200, `login nº ${i + 1} foi barrado`)
    }
  })
})
