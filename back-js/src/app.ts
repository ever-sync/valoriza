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

export const app = Fastify({ logger: true })
await app.register(cookie)
await app.register(cors, { origin: config.CORS_ORIGIN, credentials: true })
app.addHook('onSend', async (_request, reply) => {
  reply.header('Cache-Control', 'no-store')
  reply.header('X-Content-Type-Options', 'nosniff')
  reply.header('X-Frame-Options', 'DENY')
  reply.header('Referrer-Policy', 'no-referrer')
  reply.header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
})
await app.register(rateLimit, { global: false, max: 100, timeWindow: '1 minute' })
app.get('/health', async () => ({ success: true, service: 'valoriza-api', runtime: 'node' }))
await app.register(authRoutes)
await app.register(contratoRoutes)
await app.register(crudRoutes)
await app.register(financeRoutes)
await app.register(advancedFinanceRoutes)
await app.register(integrationRoutes)
app.setErrorHandler((error, _request, reply) => {
  const safeError = error instanceof Error ? error : new Error('unknown error')
  app.log.error({ name: safeError.name, message: safeError.message }, 'request failed')
  const status = safeError.name === 'ZodError' ? 400 : 500
  return reply.code(status).send({ success: false, message: status === 400 ? 'Dados inválidos.' : 'Erro interno do servidor.' })
})
