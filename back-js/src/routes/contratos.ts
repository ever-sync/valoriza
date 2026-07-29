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
    const query = request.query as Record<string, string | undefined>
    const page = Math.max(1, Number(query.pagina_atual ?? query.pagina ?? 1) || 1)
    const perPage = Math.min(100, Math.max(1, Number(query.por_pagina ?? query.porPagina ?? 10) || 10))
    let builder = db.from('tbl_contratos').select('*', { count: 'exact' }).eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido')
    if (query.status) builder = builder.eq('status', query.status)
    if (query.tipo_operacao) builder = builder.eq('tipo_operacao', query.tipo_operacao)
    const { data, error, count } = await builder.order('id', { ascending: false }).range((page - 1) * perPage, page * perPage - 1)
    if (error) throw error
    return { success: true, data: data ?? [], meta: { total: count ?? 0, pagina: page, pagina_atual: page, porPagina: perPage, por_pagina: perPage } }
  })

  app.post('/contrato/inserir', { preHandler: authenticate }, async (request, reply) => {
    const body = createSchema.parse(request.body)
    const { data, error } = await db.from('tbl_contratos').insert({ ...body, empresa_id: request.user.empresa_id, cadastrado_por: request.user.usuario_id }).select().single()
    if (error) throw error
    return reply.code(201).send({ success: true, data })
  })

  app.put('/contrato/editar/:id', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const body = createSchema.partial().parse(request.body)
    const { data, error } = await db.from('tbl_contratos').update({ ...body, atualizado_por: request.user.usuario_id, data_atualizacao: new Date().toISOString() }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
    if (error) throw error
    return { success: true, data }
  })

  app.delete('/contrato/excluir/:id', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data, error } = await db.from('tbl_contratos').update({ status_sistema: 'excluido', atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
    if (error) throw error
    return { success: true, data }
  })

  app.get('/contrato/:id/parcelas', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data, error } = await db.from('tbl_contratos_parcelas').select('*').eq('contrato_id', id).eq('empresa_id', request.user.empresa_id).order('numero_parcela')
    if (error) throw error
    return { success: true, data: data ?? [] }
  })

  app.get('/contrato/garantias/:id', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data, error } = await db.from('tbl_contratos_garantias').select('*').eq('contrato_id', id).eq('empresa_id', request.user.empresa_id).order('id')
    if (error) throw error
    return { success: true, data: data ?? [] }
  })

  app.post('/contrato/:id/crdc', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data, error } = await db.from('tbl_contratos').update({ enviar_registradora: true, atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
    if (error) throw error
    return { success: true, data, message: 'Contrato marcado para envio à registradora.' }
  })
}
