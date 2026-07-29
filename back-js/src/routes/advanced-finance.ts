import type { FastifyInstance } from 'fastify'
import { authenticate } from '../auth.js'
import { db } from '../db.js'

const n = (v: unknown) => Number(v ?? 0) || 0

export async function advancedFinanceRoutes(app: FastifyInstance) {
  app.get('/receita/:id/contrato', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data: receita, error } = await db.from('tbl_receitas').select('contrato_id').eq('id', id).eq('empresa_id', request.user.empresa_id).single()
    if (error) throw error
    if (!receita?.contrato_id) return { success: true, data: null }
    const result = await db.from('tbl_contratos').select('*').eq('id', receita.contrato_id).eq('empresa_id', request.user.empresa_id).single()
    if (result.error) throw result.error
    return { success: true, data: result.data }
  })

  app.get('/receita/:id/prorrogacoes', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data, error } = await db.from('tbl_receitas_prorrogacoes').select('*').eq('receita_id', id).eq('empresa_id', request.user.empresa_id).order('id', { ascending: false })
    if (error) throw error
    return { success: true, data: data ?? [] }
  })

  app.post('/receita/:id/calcular-encargos', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const { data: receita, error } = await db.from('tbl_receitas').select('*').eq('id', id).eq('empresa_id', request.user.empresa_id).single()
    if (error) throw error
    const vencimento = new Date(`${String(receita.data_vencimento)}T00:00:00`); const hoje = new Date(`${String(body.data_recebimento ?? new Date().toISOString().slice(0, 10))}T00:00:00`)
    const dias = Math.max(0, Math.floor((hoje.getTime() - vencimento.getTime()) / 86400000)); const valor = n(receita.valor_recebido)
    const juros_mora = Number((valor * 0.00033 * dias).toFixed(2)); const multa = dias > 0 ? Number((valor * 0.02).toFixed(2)) : 0
    return { success: true, data: { dias_atraso: dias, juros_mora, multa, juros_atualizacao: 0, valor_devido: Number((valor + juros_mora + multa).toFixed(2)), taxas: { juros_mora, multa } } }
  })

  app.post('/receita/:id/simular-prorrogacao', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const { data, error } = await db.from('tbl_receitas').select('*').eq('id', id).eq('empresa_id', request.user.empresa_id).single()
    if (error) throw error
    const valor = n(data.valor_recebido); const novaData = String(body.data_vencimento_nova ?? data.data_vencimento)
    return { success: true, data: { data_vencimento_anterior: data.data_vencimento, data_vencimento_nova: novaData, valor_anterior: valor, valor_novo: valor, juros_atualizacao: 0, valor_devido: valor } }
  })

  app.post('/receita/:id/prorrogar', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const { data: receita, error } = await db.from('tbl_receitas').select('*').eq('id', id).eq('empresa_id', request.user.empresa_id).single()
    if (error) throw error
    const novaData = String(body.data_vencimento_nova); const valor = n(receita.valor_recebido)
    const history = await db.from('tbl_receitas_prorrogacoes').insert({ receita_id: Number(id), contrato_id: receita.contrato_id, empresa_id: request.user.empresa_id, parcela_numero: receita.parcela_numero, data_vencimento_anterior: receita.data_vencimento, data_vencimento_nova: novaData, valor_anterior: valor, valor_novo: valor, justificativa: body.justificativa, cadastrado_por: request.user.usuario_id }).select().single()
    if (history.error) throw history.error
    const updated = await db.from('tbl_receitas').update({ data_vencimento: novaData, atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single()
    if (updated.error) throw updated.error
    return { success: true, data: updated.data, prorrogacao: history.data }
  })

  app.post('/receita/:id/pagar-parcial', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>; const pago = n(body.valor_pago)
    const { data: receita, error } = await db.from('tbl_receitas').select('*').eq('id', id).eq('empresa_id', request.user.empresa_id).single(); if (error) throw error
    const novo = Math.max(0, n(receita.valor_recebido) - pago); const status = novo === 0 ? 'Recebido' : 'Pendente'
    const result = await db.from('tbl_receitas').update({ valor_recebido: novo, status, data_recebimento: body.data_pagamento ?? new Date().toISOString().slice(0, 10), atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single(); if (result.error) throw result.error
    return { success: true, data: result.data }
  })

  app.post('/receita/:id/pagar-carencia', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const result = await db.from('tbl_receitas').update({ status: 'Recebido', data_recebimento: body.data_pagamento ?? new Date().toISOString().slice(0, 10), atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single(); if (result.error) throw result.error
    return { success: true, data: result.data }
  })
  app.post('/receita/:id/quitar-integral', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const result = await db.from('tbl_receitas').update({ status: 'Recebido', data_recebimento: body.data_recebimento ?? new Date().toISOString().slice(0, 10), conta_bancaria_destino_id: body.conta_bancaria_destino_id, atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single(); if (result.error) throw result.error
    return { success: true, data: result.data }
  })

  app.get('/relatorios/sumario-contratos', { preHandler: authenticate }, async (request) => {
    const { data, error } = await db.from('tbl_contratos').select('status,valor_solicitado').eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido'); if (error) throw error
    const rows = data ?? []; return { success: true, data: { total: rows.length, ativos: rows.filter((x) => x.status === 'Ativo').length, valor_total: rows.reduce((s, x) => s + n(x.valor_solicitado), 0) } }
  })

  app.get('/relatorios/sumario-clientes', { preHandler: authenticate }, async (request) => {
    const [pf, pj] = await Promise.all([db.from('tbl_pessoas_fisicas').select('id').eq('empresa_id', request.user.empresa_id), db.from('tbl_pessoas_juridicas').select('id').eq('empresa_id', request.user.empresa_id)]); if (pf.error) throw pf.error; if (pj.error) throw pj.error
    return { success: true, data: { pessoas_fisicas: pf.data?.length ?? 0, pessoas_juridicas: pj.data?.length ?? 0, total: (pf.data?.length ?? 0) + (pj.data?.length ?? 0) } }
  })
}
