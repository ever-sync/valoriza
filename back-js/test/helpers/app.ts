import { mock } from 'node:test'
import { criarFakeDb } from './fakeDb.js'
import type { SessionUser } from '../../src/auth.js'

process.env.SUPABASE_URL ??= 'https://exemplo.supabase.co'
process.env.SUPABASE_SERVICE_ROLE_KEY ??= 'chave-de-servico-de-teste'
process.env.JWT_SECRET ??= 'segredo-de-teste-com-mais-de-32-caracteres'
process.env.LOG_LEVEL ??= 'silent'

export const EMPRESA_A = 1
export const EMPRESA_B = 2

export function usuario(perfil: string, empresaId = EMPRESA_A): SessionUser {
  return {
    usuario_id: 10,
    empresa_id: empresaId,
    nome_completo: 'Usuário de Teste',
    email: 'teste@exemplo.com',
    perfil_acesso: perfil,
  }
}

/**
 * Sobe a app real com o Supabase substituído pelo duplo. Deve ser chamada uma única
 * vez por arquivo de teste: `app` é um singleton com top-level await, então uma
 * segunda chamada reaproveitaria o módulo já carregado com o primeiro mock.
 *
 * ATENÇÃO: nenhum módulo de src/ que dependa de db.js pode ser importado
 * estaticamente pelo arquivo de teste. Imports estáticos são avaliados antes desta
 * função rodar, então o módulo carregaria o client real do Supabase e as chamadas
 * iriam para a rede (falham por timeout, não por asserção). Use
 * `await import('../src/x.js')` depois de `iniciarApp()`. Imports de tipo são
 * seguros: desaparecem na compilação.
 */
export async function iniciarApp(linhas: Record<string, Record<string, unknown>[]> = {}) {
  const fake = criarFakeDb(linhas)
  // O Node já renomeou `namedExports` para `exports`, mas @types/node 24 ainda só
  // declara o nome antigo. Usar o novo evita o DeprecationWarning a cada execução.
  mock.module('../../src/db.js', { exports: { db: fake.db } } as Parameters<typeof mock.module>[1])

  const { app } = await import('../../src/app.js')
  const { createToken } = await import('../../src/auth.js')
  await app.ready()

  const cookieDe = async (usuarioSessao: SessionUser) => `valoriza_session=${await createToken(usuarioSessao)}`

  /** Espelha o front, que envia cookie e Authorization em toda requisição. */
  const cabecalhosDe = async (usuarioSessao: SessionUser) => {
    const token = await createToken(usuarioSessao)
    return { cookie: `valoriza_session=${token}`, authorization: `Bearer ${token}` }
  }

  return { app, fake, cookieDe, cabecalhosDe }
}
