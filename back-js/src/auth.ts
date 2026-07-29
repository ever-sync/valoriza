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

export async function authenticate(request: FastifyRequest, reply: FastifyReply) {
  const token = request.cookies.valoriza_session
  if (!token) return reply.code(401).send({ success: false, message: 'Usuário não logado.' })

  try {
    const { payload } = await jwtVerify(token, secret)
    request.user = payload as unknown as SessionUser
  } catch {
    return reply.code(401).send({ success: false, message: 'Sessão inválida ou expirada.' })
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
