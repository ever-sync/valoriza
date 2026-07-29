import type { SessionUser } from './auth.js'

declare module 'fastify' {
  interface FastifyRequest {
    user: SessionUser
  }
}
