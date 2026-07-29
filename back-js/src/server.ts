import { app } from './app.js'
import { config } from './config.js'

await app.listen({ port: config.PORT, host: '0.0.0.0' })
