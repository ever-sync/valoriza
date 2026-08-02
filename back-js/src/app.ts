import Fastify from 'fastify'
import cookie from '@fastify/cookie'
import cors from '@fastify/cors'
import rateLimit from '@fastify/rate-limit'
import { config } from './config.js'
import { authRoutes } from './routes/auth.js'
import { contratoRoutes } from './routes/contratos.js'
import { crudRoutes } from './routes/crud.js'
import { financeRoutes } from './routes/finance.js'
import { advancedFinanceRoutes } from './routes/advanced-finance.js'
import { integrationRoutes } from './routes/integrations.js'

export const app = Fastify({ logger: { level: process.env.LOG_LEVEL ?? 'info' } })
await app.register(cookie)
// @fastify/cors v11 usa 'GET,HEAD,POST' como default, o que barraria as rotas de editar/excluir.
await app.register(cors, {
  origin: (origin, cb) => {
    if (!origin) return cb(null, true)
    const allowed = config.CORS_ORIGIN.split(',').map((s) => s.trim())
    if (allowed.includes('*') || allowed.includes(origin) || origin.endsWith('.vercel.app')) {
      return cb(null, true)
    }
    return cb(null, false)
  },
  credentials: true,
  methods: ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'],
})
app.addHook('onSend', async (_request, reply) => {
  reply.header('Cache-Control', 'no-store')
  reply.header('X-Content-Type-Options', 'nosniff')
  reply.header('X-Frame-Options', 'DENY')
  reply.header('Referrer-Policy', 'no-referrer')
  reply.header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
})
await app.register(rateLimit, { global: false, max: 100, timeWindow: '1 minute' })

// Precisa ficar antes dos register() das rotas: cada register cria um contexto de
// encapsulamento que herda o handler de erro vigente naquele momento. Registrado
// depois, ele não valia para rota nenhuma — as validações do Zod respondiam 500 com o
// erro bruto no corpo em vez de 400, e mensagens internas vazavam para o cliente.
app.setErrorHandler((error, _request, reply) => {
  const safeError = error instanceof Error ? error : new Error('unknown error')
  app.log.error({ name: safeError.name, message: safeError.message }, 'request failed')
  const status = safeError.name === 'ZodError' ? 400 : 500
  return reply.code(status).send({ success: false, message: status === 400 ? 'Dados inválidos.' : 'Erro interno do servidor.' })
})

app.get('/health', async () => ({ success: true, service: 'valoriza-api', runtime: 'node' }))
await app.register(authRoutes)
await app.register(contratoRoutes)
await app.register(crudRoutes)
await app.register(financeRoutes)
await app.register(advancedFinanceRoutes)
await app.register(integrationRoutes)
