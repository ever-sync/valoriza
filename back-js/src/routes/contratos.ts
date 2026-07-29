import type { FastifyInstance } from 'fastify'
import { z } from 'zod'
import { authenticate } from '../auth.js'
import { db } from '../db.js'

const emptyAsUndefined = (value: unknown) => value === '' || value === null ? undefined : value
const emptyAsNull = (value: unknown) => value === '' || value === undefined ? null : value

const createSchema = z.object({
  tipo_operacao: z.string().min(1),
  valor_solicitado: z.coerce.number().nonnegative(),
  periodo_amortizacao: z.string().min(1),
  modelo_amortizacao: z.string().min(1),
  taxa_juros: z.coerce.number(),
  tipo_taxa: z.string().default('mensal'),
  // Pagamento único chega sem quantidade no formulário; a operação ainda representa uma parcela.
  quantidade_parcelas: z.preprocess((value) => value === '' || value == null ? 1 : value, z.coerce.number().int().positive()),
  data_assinatura: z.string().min(1),
  data_primeira_parcela: z.string().min(1),
  tipo_cliente: z.string().default('pj'),
  cliente_id: z.preprocess(emptyAsNull, z.coerce.number().nullable().optional()),
  juros_mora: z.preprocess(emptyAsUndefined, z.coerce.number().optional()),
  multa_moratoria: z.preprocess(emptyAsUndefined, z.coerce.number().optional()),
  limite_carencia: z.preprocess(emptyAsUndefined, z.coerce.number().int().optional()),
  garantias: z.array(z.record(z.string(), z.unknown())).optional(),
})

const prepararGarantia = (garantia: Record<string, unknown>, contratoId: number, empresaId: number, usuarioId: number) => {
  const ignorados = new Set(['id', '_aberta', 'contrato_id', 'empresa_id', 'data_cadastro', 'data_atualizacao', 'cadastrado_por', 'atualizado_por'])
  const dados = Object.fromEntries(Object.entries(garantia).filter(([chave, valor]) => !ignorados.has(chave) && valor !== '' && valor !== undefined))
  return { ...dados, contrato_id: contratoId, empresa_id: empresaId, cadastrado_por: usuarioId }
}

const persistirGarantias = async (garantias: Array<Record<string, unknown>> | undefined, contratoId: number, empresaId: number, usuarioId: number) => {
  if (!Array.isArray(garantias) || garantias.length === 0) return []
  const rows = garantias.map((garantia) => prepararGarantia(garantia, contratoId, empresaId, usuarioId))
  const { data, error } = await db.from('tbl_contratos_garantias').insert(rows).select()
  if (error) throw error
  return data ?? []
}

export async function contratoRoutes(app: FastifyInstance) {
  app.get('/contrato/buscar', { preHandler: authenticate }, async (request) => {
    const query = request.query as Record<string, string | undefined>
    const page = Math.max(1, Number(query.pagina_atual ?? query.pagina ?? 1) || 1)
    const perPage = Math.min(100, Math.max(1, Number(query.por_pagina ?? query.porPagina ?? 10) || 10))
    let builder = db.from('tbl_contratos').select('*', { count: 'exact' }).eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido')
    if (query.id) builder = builder.eq('id', query.id)
    if (query.status) builder = builder.eq('status', query.status)
    if (query.tipo_operacao) builder = builder.eq('tipo_operacao', query.tipo_operacao)
    const { data, error, count } = await builder.order('id', { ascending: false }).range((page - 1) * perPage, page * perPage - 1)
    if (error) throw error
    return { success: true, data: data ?? [], meta: { total: count ?? 0, pagina: page, pagina_atual: page, porPagina: perPage, por_pagina: perPage } }
  })

  app.post('/contrato/inserir', { preHandler: authenticate }, async (request, reply) => {
    const parsed = createSchema.parse(request.body)
    const { garantias, ...body } = parsed
    const { data, error } = await db.from('tbl_contratos').insert({ ...body, empresa_id: request.user.empresa_id, cadastrado_por: request.user.usuario_id }).select().single()
    if (error) throw error
    try {
      const garantiasSalvas = await persistirGarantias(garantias, data.id, request.user.empresa_id, request.user.usuario_id)
      return reply.code(201).send({ success: true, data: { ...data, garantias: garantiasSalvas } })
    } catch (garantiaError) {
      await db.from('tbl_contratos').update({ status_sistema: 'excluido', atualizado_por: request.user.usuario_id }).eq('id', data.id).eq('empresa_id', request.user.empresa_id)
      throw garantiaError
    }
  })

  app.put('/contrato/editar/:id', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const parsed = createSchema.partial().parse(request.body)
    const { garantias, ...body } = parsed
    const { data, error } = await db.from('tbl_contratos').update({ ...body, atualizado_por: request.user.usuario_id, data_atualizacao: new Date().toISOString() }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
    if (error) throw error
    if (Array.isArray(garantias)) {
      const contratoId = Number(id)
      const removidas = await db.from('tbl_contratos_garantias').delete().eq('contrato_id', contratoId).eq('empresa_id', request.user.empresa_id)
      if (removidas.error) throw removidas.error
      const garantiasSalvas = await persistirGarantias(garantias, contratoId, request.user.empresa_id, request.user.usuario_id)
      return { success: true, data: { ...data, garantias: garantiasSalvas } }
    }
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
