import { db } from './db.js'

// O limite por instância do @fastify/rate-limit não vale em serverless: cada instância
// da Vercel tem o próprio contador e elas são descartadas a cada requisição. Contar no
// banco dá estado compartilhado.
//
// O eixo é o e-mail, não o IP: atrás do proxy da Vercel o request.ip chega como
// 127.0.0.1, e passar a confiar no x-forwarded-for tornaria o limite falsificável pelo
// próprio atacante. O IP é gravado apenas para auditoria.
export const JANELA_MINUTOS = 15
export const MAX_FALHAS = 5

const inicioDaJanela = () => new Date(Date.now() - JANELA_MINUTOS * 60_000).toISOString()

/** Quantas falhas recentes este e-mail acumulou. */
export async function falhasRecentes(email: string) {
  const { data, error } = await db
    .from('tbl_tentativas_login')
    .select('id')
    .eq('email', email)
    .gte('criado_em', inicioDaJanela())
  if (error) throw error
  return data?.length ?? 0
}

export async function registrarFalha(email: string, ip?: string) {
  const { error } = await db.from('tbl_tentativas_login').insert({ email, ip: ip ?? null })
  if (error) throw error
}

/** Chamado no login bem-sucedido: quem acertou a senha não deve seguir bloqueado. */
export async function limparFalhas(email: string) {
  const { error } = await db.from('tbl_tentativas_login').delete().eq('email', email)
  if (error) throw error
}
