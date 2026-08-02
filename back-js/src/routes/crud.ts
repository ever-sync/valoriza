import type { FastifyInstance } from 'fastify'
import bcrypt from 'bcryptjs'
import { z } from 'zod'
import { authenticate, requireRole } from '../auth.js'
import { db } from '../db.js'

// Precisa continuar espelhando os perfis de front/src/constants/navigation.js: um valor
// fora desta lista produz um usuário que loga e não enxerga nenhum item de menu. Antes
// desta validação o formulário gravava 'usuario' e 'gerente', que nenhuma tela conhece.
const perfilSchema = z.enum(['administrador', 'contador', 'operador'])
const ALIAS_PERFIL: Record<string, string> = { admin: 'administrador' }

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

// Segredos que nunca devem trafegar para o cliente. São de escrita apenas: o formulário
// manda um valor novo ou string vazia, que significa "manter o atual".
const camposSomenteEscrita: Partial<Record<Resource, string[]>> = {
  usuario: ['senha'],
  'configuracoes-contratos': ['crdc_senha'],
}

// Campos que só administradores podem ler. A leitura de configuracoes-contratos fica
// liberada porque o fluxo de contratos depende das taxas e limites de parcelas.
const camposRestritosAoAdmin: Partial<Record<Resource, string[]>> = {
  'configuracoes-contratos': ['crdc_usuario'],
}

function ocultarCampos<T>(resource: Resource, linhas: T[], perfil: string): T[] {
  const campos = [...(camposSomenteEscrita[resource] ?? [])]
  if (perfil !== 'administrador') campos.push(...(camposRestritosAoAdmin[resource] ?? []))
  if (campos.length === 0) return linhas
  return linhas.map((linha) => {
    const copia = { ...(linha as Record<string, unknown>) }
    for (const campo of campos) delete copia[campo]
    return copia as T
  })
}

function cleanPayload(payload: unknown, userId: number, empresaId: number) {
  const body = (payload && typeof payload === 'object' ? payload : {}) as Record<string, unknown>
  const forbidden = new Set(['id', 'empresa_id', 'data_cadastro', 'data_atualizacao', 'cadastrado_por', 'atualizado_por', 'status_sistema'])
  const clean = Object.fromEntries(Object.entries(body).filter(([key]) => !forbidden.has(key)))
  return { ...clean, empresa_id: empresaId, atualizado_por: userId }
}

async function prepararPayload(resource: Resource, payload: Record<string, unknown>, operacao: 'inserir' | 'editar') {
  // Como esses campos não voltam mais nas leituras, o formulário os reenvia vazios.
  // Descartar aqui evita que salvar a tela apague o segredo já guardado.
  for (const campo of camposSomenteEscrita[resource] ?? []) {
    const valor = payload[campo]
    if (typeof valor === 'string' && !valor.trim()) delete payload[campo]
  }
  // valor_original registra o que foi cobrado e é imutável depois da criação — é ele
  // que mantém a receita auditável enquanto os pagamentos parciais reduzem o saldo.
  // valor_pago só é movimentado pelas rotas de recebimento, nunca pelo CRUD.
  if (resource === 'receita') {
    delete payload.valor_pago
    if (operacao === 'inserir') payload.valor_original = Number(payload.valor_recebido ?? 0) || 0
    else delete payload.valor_original
  }
  if (resource === 'usuario') {
    if (typeof payload.senha === 'string') {
      payload.senha = await bcrypt.hash(payload.senha, 12)
    }
    if (typeof payload.perfil_acesso === 'string') {
      payload.perfil_acesso = perfilSchema.parse(ALIAS_PERFIL[payload.perfil_acesso] ?? payload.perfil_acesso)
    }
  }
  return payload
}

export async function crudRoutes(app: FastifyInstance) {
  for (const resource of Object.keys(resources) as Resource[]) {
    const table = resources[resource]

    const adminOnly = resource === 'usuario' || resource === 'empresa' || resource === 'configuracoes-contratos'
    const guard = adminOnly ? requireRole('administrador') : authenticate
    // A escrita de configuracoes-contratos é só do admin, mas a leitura precisa ficar
    // aberta: ContratoForm e useContratoStatus carregam taxas e limites por ali.
    const guardLeitura = resource === 'usuario' || resource === 'empresa' ? requireRole('administrador') : authenticate

    app.get(`/${resource}/buscar`, { preHandler: guardLeitura }, async (request) => {
      const query = request.query as Record<string, string | undefined>
      const page = Math.max(1, Number(query.pagina_atual ?? query.pagina ?? 1) || 1)
      const perPage = Math.min(100, Math.max(1, Number(query.por_pagina ?? query.porPagina ?? 10) || 10))
      let builder = db.from(table).select('*', { count: 'exact' }).eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido')
      if (query.id) builder = builder.eq('id', query.id)
      if (query.status) builder = builder.eq('status', query.status)
      const { data, error, count } = await builder.order('id', { ascending: false }).range((page - 1) * perPage, page * perPage - 1)
      if (error) throw error
      const linhas = ocultarCampos(resource, data ?? [], request.user.perfil_acesso)
      return { success: true, data: linhas, meta: { total: count ?? 0, pagina: page, pagina_atual: page, porPagina: perPage, por_pagina: perPage } }
    })

    app.post(`/${resource}/inserir`, { preHandler: guard }, async (request, reply) => {
      const payload = await prepararPayload(resource, cleanPayload(request.body, request.user.usuario_id, request.user.empresa_id), 'inserir')
      const { data, error } = await db.from(table).insert(payload).select().single()
      if (error) throw error
      const [linha] = ocultarCampos(resource, [data], request.user.perfil_acesso)
      return reply.code(201).send({ success: true, data: linha })
    })

    app.put(`/${resource}/editar/:id`, { preHandler: guard }, async (request) => {
      const { id } = request.params as { id: string }
      const payload = await prepararPayload(resource, cleanPayload(request.body, request.user.usuario_id, request.user.empresa_id), 'editar')
      const { data, error } = await db.from(table).update(payload).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
      if (error) throw error
      const [linha] = ocultarCampos(resource, [data], request.user.perfil_acesso)
      return { success: true, data: linha }
    })

    app.delete(`/${resource}/excluir/:id`, { preHandler: guard }, async (request) => {
      const { id } = request.params as { id: string }
      const { data, error } = await db.from(table).update({ status_sistema: 'excluido', atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
      if (error) throw error
      const [linha] = ocultarCampos(resource, [data], request.user.perfil_acesso)
      return { success: true, data: linha }
    })
  }
}
