import type { FastifyInstance } from 'fastify'
import { authenticate, requireRole } from '../auth.js'
import { db } from '../db.js'

const resources = {
  empresa: 'tbl_empresas',
  banco: 'tbl_bancos',
  usuario: 'tbl_usuarios',
  'configuracoes-contratos': 'tbl_configuracoes_contratos',
  'pessoa-fisica': 'tbl_pessoas_fisicas',
  'pessoa-juridica': 'tbl_pessoas_juridicas',
  receita: 'tbl_receitas',
  despesa: 'tbl_despesas',
} as const

type Resource = keyof typeof resources

function cleanPayload(payload: unknown, userId: number, empresaId: number) {
  const body = (payload && typeof payload === 'object' ? payload : {}) as Record<string, unknown>
  const forbidden = new Set(['id', 'empresa_id', 'data_cadastro', 'data_atualizacao', 'cadastrado_por', 'atualizado_por'])
  const clean = Object.fromEntries(Object.entries(body).filter(([key]) => !forbidden.has(key)))
  return { ...clean, empresa_id: empresaId, atualizado_por: userId }
}

export async function crudRoutes(app: FastifyInstance) {
  for (const resource of Object.keys(resources) as Resource[]) {
    const table = resources[resource]

    app.get(`/${resource}/buscar`, { preHandler: authenticate }, async (request) => {
      const query = request.query as Record<string, string | undefined>
      let builder = db.from(table).select('*', { count: 'exact' }).eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido')
      if (query.id) builder = builder.eq('id', query.id)
      if (query.status) builder = builder.eq('status', query.status)
      const { data, error, count } = await builder.order('id', { ascending: false })
      if (error) throw error
      return { success: true, data: data ?? [], meta: { total: count ?? 0 } }
    })

    const adminOnly = resource === 'usuario' || resource === 'empresa' || resource === 'configuracoes-contratos'
    const guard = adminOnly ? requireRole('administrador') : authenticate
    app.post(`/${resource}/inserir`, { preHandler: guard }, async (request, reply) => {
      const payload = cleanPayload(request.body, request.user.usuario_id, request.user.empresa_id)
      const { data, error } = await db.from(table).insert(payload).select().single()
      if (error) throw error
      return reply.code(201).send({ success: true, data })
    })

    app.put(`/${resource}/editar/:id`, { preHandler: guard }, async (request) => {
      const { id } = request.params as { id: string }
      const payload = cleanPayload(request.body, request.user.usuario_id, request.user.empresa_id)
      const { data, error } = await db.from(table).update(payload).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
      if (error) throw error
      return { success: true, data }
    })

    app.delete(`/${resource}/excluir/:id`, { preHandler: guard }, async (request) => {
      const { id } = request.params as { id: string }
      const { data, error } = await db.from(table).update({ status_sistema: 'excluido', atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
      if (error) throw error
      return { success: true, data }
    })
  }
}
