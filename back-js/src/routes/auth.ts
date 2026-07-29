import type { FastifyInstance } from 'fastify'
import { z } from 'zod'
import { authenticate, createToken, login } from '../auth.js'

const bodySchema = z.object({ email: z.string().email(), senha: z.string().min(1) })

export async function authRoutes(app: FastifyInstance) {
  app.post('/auth/login', async (request, reply) => {
    const body = bodySchema.parse(request.body)
    const user = await login(body.email, body.senha)
    if (!user) return reply.code(401).send({ success: false, message: 'Credenciais inválidas.' })
    const token = await createToken(user)
    reply.setCookie('valoriza_session', token, { httpOnly: true, sameSite: 'lax', secure: process.env.NODE_ENV === 'production', path: '/', maxAge: 60 * 60 * 8 })
    return { success: true, data: user }
  })

  app.get('/auth/me', { preHandler: authenticate }, async (request) => ({ success: true, data: request.user }))

  app.post('/auth/logout', async (_request, reply) => {
    reply.clearCookie('valoriza_session', { path: '/' })
    return { success: true, message: 'Logout realizado com sucesso.' }
  })
}
