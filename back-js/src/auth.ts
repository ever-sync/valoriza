import bcrypt from 'bcryptjs'
import { SignJWT, jwtVerify } from 'jose'
import type { FastifyReply, FastifyRequest } from 'fastify'
import { config } from './config.js'
import { db } from './db.js'

const secret = new TextEncoder().encode(config.JWT_SECRET)

export type SessionUser = {
  usuario_id: number
  empresa_id: number
  nome_completo: string
  email: string
  perfil_acesso: string
}

export async function createToken(user: SessionUser) {
  return new SignJWT(user as unknown as Record<string, unknown>)
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime('8h')
    .sign(secret)
}

// Como o front consome a API de outro domínio, o cookie de sessão é SameSite=None e o
// navegador o anexa em requisições cross-site. Escritas passam a exigir o cabeçalho
// Authorization: um site externo não consegue defini-lo sem disparar preflight, e o
// preflight morre no CORS. Leituras seguem aceitando o cookie — ali o CORS já impede
// ler a resposta cross-site.
const METODOS_DE_LEITURA = new Set(['GET', 'HEAD', 'OPTIONS'])

export async function authenticate(request: FastifyRequest, reply: FastifyReply) {
  const cabecalho = request.headers.authorization
  const bearer = cabecalho?.startsWith('Bearer ') ? cabecalho.substring(7) : undefined
  const cookie = METODOS_DE_LEITURA.has(request.method) ? request.cookies.valoriza_session : undefined
  const token = bearer ?? cookie

  if (!token) {
    const bloqueadoPorFaltaDeCabecalho = !bearer && !!request.cookies.valoriza_session
    return reply.code(401).send({
      success: false,
      message: bloqueadoPorFaltaDeCabecalho
        ? 'Esta operação exige o token de sessão no cabeçalho Authorization.'
        : 'Usuário não logado.',
    })
  }

  try {
    const { payload } = await jwtVerify(token, secret)
    request.user = payload as unknown as SessionUser
  } catch {
    return reply.code(401).send({ success: false, message: 'Sessão inválida ou expirada.' })
  }
}

export function requireRole(...roles: string[]) {
  return async (request: FastifyRequest, reply: FastifyReply) => {
    await authenticate(request, reply)
    if (reply.sent) return
    if (!roles.includes(request.user.perfil_acesso)) {
      return reply.code(403).send({ success: false, message: 'Acesso não permitido.' })
    }
  }
}

export async function login(email: string, senha: string): Promise<SessionUser | null> {
  const { data, error } = await db.from('tbl_usuarios').select('*').eq('email', email).eq('ativo', true).maybeSingle()
  if (error) throw error
  if (!data || !(await bcrypt.compare(senha, data.senha))) return null
  return {
    usuario_id: data.id,
    empresa_id: data.empresa_id,
    nome_completo: data.nome_completo,
    email: data.email,
    perfil_acesso: data.perfil_acesso,
  }
}
