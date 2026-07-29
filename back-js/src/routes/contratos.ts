import type { FastifyInstance } from 'fastify'
import { z } from 'zod'
import { authenticate } from '../auth.js'
import { db } from '../db.js'

const createSchema = z.object({
  tipo_operacao: z.string().min(1), valor_solicitado: z.coerce.number().nonnegative(),
  periodo_amortizacao: z.string().min(1), modelo_amortizacao: z.string().min(1), taxa_juros: z.coerce.number(),
  tipo_taxa: z.string().default('mensal'), quantidade_parcelas: z.coerce.number().int().positive(),
  data_assinatura: z.string(), data_primeira_parcela: z.string(), tipo_cliente: z.string().default('pj'), cliente_id: z.coerce.number().optional(),
})

export async function contratoRoutes(app: FastifyInstance) {
  app.get('/contrato/buscar', { preHandler: authenticate }, async (request) => {
    const { data, error, count } = await db.from('tbl_contratos').select('*', { count: 'exact' }).eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido').order('id', { ascending: false })
    if (error) throw error
    return { success: true, data: data ?? [], meta: { total: count ?? 0 } }
  })

  app.post('/contrato/inserir', { preHandler: authenticate }, async (request, reply) => {
    const body = createSchema.parse(request.body)
    const { data, error } = await db.from('tbl_contratos').insert({ ...body, empresa_id: request.user.empresa_id, cadastrado_por: request.user.usuario_id }).select().single()
    if (error) throw error
    return reply.code(201).send({ success: true, data })
  })
}
