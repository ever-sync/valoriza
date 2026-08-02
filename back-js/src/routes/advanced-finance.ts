import type { FastifyInstance } from 'fastify'
import { authenticate } from '../auth.js'
import { db } from '../db.js'
import { encargosAtraso } from '../utils/calculations.js'

const n = (v: unknown) => Number(v ?? 0) || 0

/** Contrato de origem da receita, quando houver — traz as regras de inadimplência. */
async function contratoDaReceita(receita: Record<string, unknown>, empresaId: number) {
  if (!receita?.contrato_id) return null
  const { data, error } = await db
    .from('tbl_contratos')
    .select('taxa_juros,juros_mora,multa_moratoria')
    .eq('id', receita.contrato_id)
    .eq('empresa_id', empresaId)
    .maybeSingle()
  if (error) throw error
  return data
}

/**
 * Valor cobrado na receita. Linhas anteriores à migration que criou valor_original
 * caem no valor_recebido, que nelas ainda representa a cobrança cheia.
 */
const valorOriginal = (receita: Record<string, unknown>) =>
  receita.valor_original == null ? n(receita.valor_recebido) : n(receita.valor_original)

const diasEntre = (vencimento: unknown, referencia: string) => {
  const fim = new Date(`${referencia}T00:00:00`).getTime()
  const inicio = new Date(`${String(vencimento)}T00:00:00`).getTime()
  if (!Number.isFinite(fim) || !Number.isFinite(inicio)) return 0
  return Math.max(0, Math.floor((fim - inicio) / 86400000))
}

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
    const contrato = await contratoDaReceita(receita, request.user.empresa_id)
    const referencia = String(body.data_recebimento ?? new Date().toISOString().slice(0, 10))
    const encargos = encargosAtraso({
      valor: n(receita.valor_recebido),
      diasAtraso: diasEntre(receita.data_vencimento, referencia),
      jurosMoraMensal: contrato?.juros_mora,
      multaMoratoria: contrato?.multa_moratoria,
    })
    // `taxas` carrega alíquotas, não valores: a tela de recebimento as renderiza como
    // "(x% a.m.)" ao lado de cada encargo.
    return {
      success: true,
      data: {
        ...encargos,
        juros_atualizacao: 0,
        taxas: { ...encargos.taxas, juros_contrato: contrato?.taxa_juros ?? null },
      },
    }
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
    // valor_original preserva a cobrança; valor_pago acumula os recebimentos. O saldo
    // segue espelhado em valor_recebido, que é o campo lido pelas telas e relatórios.
    const original = valorOriginal(receita)
    const acumulado = n(receita.valor_pago) + pago
    const saldo = Math.max(0, Number((original - acumulado).toFixed(2)))
    const status = saldo === 0 ? 'Recebido' : 'Pendente'
    const result = await db.from('tbl_receitas').update({ valor_original: original, valor_pago: acumulado, valor_recebido: saldo, status, data_recebimento: body.data_pagamento ?? new Date().toISOString().slice(0, 10), atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single(); if (result.error) throw result.error
    return { success: true, data: result.data }
  })

  app.post('/receita/:id/pagar-carencia', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const result = await db.from('tbl_receitas').update({ status: 'Recebido', data_recebimento: body.data_pagamento ?? new Date().toISOString().slice(0, 10), atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single(); if (result.error) throw result.error
    return { success: true, data: result.data }
  })
  app.post('/receita/:id/quitar-integral', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }; const body = request.body as Record<string, unknown>
    const { data: receita, error } = await db.from('tbl_receitas').select('*').eq('id', id).eq('empresa_id', request.user.empresa_id).single(); if (error) throw error
    // Quitação integral fecha a receita: o acumulado pago passa a ser a cobrança cheia.
    const original = valorOriginal(receita)
    const result = await db.from('tbl_receitas').update({ valor_original: original, valor_pago: original, status: 'Recebido', data_recebimento: body.data_recebimento ?? new Date().toISOString().slice(0, 10), conta_bancaria_destino_id: body.conta_bancaria_destino_id, atualizado_por: request.user.usuario_id }).eq('id', id).eq('empresa_id', request.user.empresa_id).select().single(); if (result.error) throw result.error
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
