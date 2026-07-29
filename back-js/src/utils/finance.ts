import type { FastifyInstance } from 'fastify'
import { authenticate } from '../auth.js'
import { db } from '../db.js'
import { pricePayment } from '../utils/calculations.js'

const money = (value: unknown) => Number(value ?? 0) || 0

export async function financeRoutes(app: FastifyInstance) {
  app.get('/dashboard/stats', { preHandler: authenticate }, async (request) => {
    const empresa = request.user.empresa_id
    const [receitas, despesas, contratos] = await Promise.all([
      db.from('tbl_receitas').select('valor_recebido,status,data_vencimento').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
      db.from('tbl_despesas').select('valor_pago,status,data_vencimento').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
      db.from('tbl_contratos').select('id,status').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
    ])
    if (receitas.error) throw receitas.error
    if (despesas.error) throw despesas.error
    if (contratos.error) throw contratos.error
    const now = new Date(); const month = now.toISOString().slice(0, 7)
    const receitaRows = receitas.data ?? []; const despesaRows = despesas.data ?? []
    const receitaMes = receitaRows.filter((r) => String(r.data_vencimento ?? '').startsWith(month)).reduce((s, r) => s + money(r.valor_recebido), 0)
    const despesaMes = despesaRows.filter((r) => String(r.data_vencimento ?? '').startsWith(month)).reduce((s, r) => s + money(r.valor_pago), 0)
    return { success: true, data: { receita_mes: receitaMes, despesa_mes: despesaMes, saldo_mes: receitaMes - despesaMes, receitas_pendentes: receitaRows.filter((r) => r.status !== 'Recebido').reduce((s, r) => s + money(r.valor_recebido), 0), contratos_ativos: (contratos.data ?? []).filter((c) => c.status === 'Ativo').length } }
  })

  app.get('/fluxo-caixa/periodo', { preHandler: authenticate }, async (request) => {
    const query = request.query as { inicio?: string; fim?: string; data_inicio?: string; data_fim?: string }
    const inicio = query.inicio ?? query.data_inicio
    const fim = query.fim ?? query.data_fim
    const empresa = request.user.empresa_id
    const [r, d] = await Promise.all([
      db.from('tbl_receitas').select('id,descricao,valor_recebido,data_vencimento,status').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
      db.from('tbl_despesas').select('id,descricao,valor_pago,data_vencimento,status').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
    ])
    if (r.error) throw r.error; if (d.error) throw d.error
    const inRange = (date: unknown) => (!inicio || String(date ?? '') >= inicio) && (!fim || String(date ?? '') <= fim)
    const registros = [
      ...(r.data ?? []).filter((x) => inRange(x.data_vencimento)).map((x) => ({ ...x, tipo: 'receita', valor: money(x.valor_recebido) })),
      ...(d.data ?? []).filter((x) => inRange(x.data_vencimento)).map((x) => ({ ...x, tipo: 'despesa', valor: money(x.valor_pago) })),
    ].sort((a, b) => String(a.data_vencimento ?? '').localeCompare(String(b.data_vencimento ?? '')))
    return { success: true, data: registros, meta: { total: registros.length } }
  })

  app.get('/fluxo-caixa/projetado', { preHandler: authenticate }, async (request) => {
    const empresa = request.user.empresa_id
    const [receitas, despesas] = await Promise.all([
      db.from('tbl_receitas').select('id,descricao,valor_recebido,data_vencimento,status').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
      db.from('tbl_despesas').select('id,descricao,valor_pago,data_vencimento,status').eq('empresa_id', empresa).neq('status_sistema', 'excluido'),
    ])
    if (receitas.error) throw receitas.error; if (despesas.error) throw despesas.error
    const data = [...(receitas.data ?? []).map((x) => ({ ...x, tipo: 'receita', valor: money(x.valor_recebido) })), ...(despesas.data ?? []).map((x) => ({ ...x, tipo: 'despesa', valor: money(x.valor_pago) }))].sort((a, b) => String(a.data_vencimento ?? '').localeCompare(String(b.data_vencimento ?? '')))
    return { success: true, data, meta: { total: data.length } }
  })

  app.post('/contrato/:id/lancar-parcelas', { preHandler: authenticate }, async (request) => {
    const { id } = request.params as { id: string }
    const { data: contrato, error: contratoError } = await db.from('tbl_contratos').select('*').eq('id', id).eq('empresa_id', request.user.empresa_id).single()
    if (contratoError) throw contratoError
    const total = Number(contrato.valor_solicitado); const qtd = Number(contrato.quantidade_parcelas)
    const parcela = contrato.modelo_amortizacao?.toLowerCase() === 'sac' ? total / qtd : pricePayment(total, Number(contrato.taxa_juros), qtd)
    const rows = Array.from({ length: qtd }, (_, i) => { const date = new Date(`${contrato.data_primeira_parcela}T00:00:00`); date.setMonth(date.getMonth() + i); return { empresa_id: request.user.empresa_id, contrato_id: Number(id), numero_parcela: i + 1, valor_parcela: Number(parcela.toFixed(2)), data_vencimento: date.toISOString().slice(0, 10), status_sistema: 'incluido' } })
    const { data, error } = await db.from('tbl_contratos_parcelas').insert(rows).select()
    if (error) throw error
    return { success: true, data: data ?? [] }
  })

  app.post('/contrato/simular', { preHandler: authenticate }, async (request) => {
    const body = request.body as Record<string, unknown>; const total = money(body.valor_solicitado); const qtd = Number(body.quantidade_parcelas)
    const parcela = pricePayment(total, Number(body.taxa_juros), qtd)
    const inicio = new Date(`${String(body.data_primeira_parcela)}T00:00:00`)
    const parcelas = Array.from({ length: qtd }, (_, i) => { const date = new Date(inicio); date.setMonth(date.getMonth() + i); return { num: i + 1, numero_parcela: i + 1, valor_parcela: Number(parcela.toFixed(2)), data_vencimento: date.toISOString().slice(0, 10) } })
    return { success: true, data: { parcelas, total_parcelas: Number((parcela * qtd).toFixed(2)), valor_parcela: Number(parcela.toFixed(2)) } }
  })
}
