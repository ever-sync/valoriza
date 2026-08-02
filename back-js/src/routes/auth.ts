import type { FastifyInstance } from 'fastify'
import { z } from 'zod'
import { authenticate, createToken, login } from '../auth.js'
import { JANELA_MINUTOS, MAX_FALHAS, falhasRecentes, limparFalhas, registrarFalha } from '../loginThrottle.js'

const bodySchema = z.object({ email: z.string().email(), senha: z.string().min(1) })

// O cookie é SameSite=None porque o front roda em outro domínio. Escritas exigem o
// token no cabeçalho Authorization (ver authenticate), que é o que barra CSRF.
const sessionCookie = {
  httpOnly: true,
  sameSite: process.env.NODE_ENV === 'production' ? ('none' as const) : ('lax' as const),
  secure: process.env.NODE_ENV === 'production',
  path: '/',
}

export async function authRoutes(app: FastifyInstance) {
  // Sem limite por IP aqui de propósito. Atrás do proxy da Vercel request.ip chega como
  // 127.0.0.1 para todo mundo, então o antigo `rateLimit: { max: 5 }` não separava
  // usuários: barrava o sexto login por minuto da empresa inteira. Quem contém força
  // bruta agora é o limite por e-mail, em loginThrottle.ts.
  app.post('/auth/login', async (request, reply) => {
    const body = bodySchema.parse(request.body)

    if ((await falhasRecentes(body.email)) >= MAX_FALHAS) {
      return reply.code(429).send({
        success: false,
        message: `Muitas tentativas de login. Tente novamente em ${JANELA_MINUTOS} minutos.`,
      })
    }

    const user = await login(body.email, body.senha)
    if (!user) {
      await registrarFalha(body.email, request.ip)
      return reply.code(401).send({ success: false, message: 'Credenciais inválidas.' })
    }

    await limparFalhas(body.email)
    const token = await createToken(user)
    reply.setCookie('valoriza_session', token, { ...sessionCookie, maxAge: 60 * 60 * 8 })
    return { success: true, data: user, token }
  })

  app.get('/auth/me', { preHandler: authenticate }, async (request) => ({ success: true, data: request.user }))

  app.post('/auth/logout', async (_request, reply) => {
    reply.clearCookie('valoriza_session', sessionCookie)
    return { success: true, message: 'Logout realizado com sucesso.' }
  })
}
