import Fastify from 'fastify'
import cookie from '@fastify/cookie'
import cors from '@fastify/cors'
import { config } from './config.js'
import { authRoutes } from './routes/auth.js'
import { contratoRoutes } from './routes/contratos.js'
import { crudRoutes } from './routes/crud.js'
import { financeRoutes } from './routes/finance.js'

const app = Fastify({ logger: true })
await app.register(cookie)
await app.register(cors, { origin: config.CORS_ORIGIN, credentials: true })

app.get('/health', async () => ({ success: true, service: 'valoriza-api', runtime: 'node' }))
await app.register(authRoutes)
await app.register(contratoRoutes)
await app.register(crudRoutes)
await app.register(financeRoutes)

app.setErrorHandler((error, _request, reply) => {
  app.log.error(error)
  const status = error instanceof Error && error.name === 'ZodError' ? 400 : 500
  return reply.code(status).send({ success: false, message: status === 400 ? 'Dados inválidos.' : 'Erro interno do servidor.' })
})

await app.listen({ port: config.PORT, host: '0.0.0.0' })
