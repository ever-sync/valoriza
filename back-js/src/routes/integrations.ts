import type { FastifyInstance } from 'fastify'
import { authenticate } from '../auth.js'
import { db } from '../db.js'

export async function integrationRoutes(app: FastifyInstance) {
  app.get('/consulta/cep/:cep', { preHandler: authenticate }, async (request, reply) => {
    const { cep } = request.params as { cep: string }
    const clean = cep.replace(/\D/g, '')
    if (clean.length !== 8) return reply.code(400).send({ success: false, message: 'CEP inválido.' })
    const response = await fetch(`https://viacep.com.br/ws/${clean}/json/`)
    if (!response.ok) return reply.code(502).send({ success: false, message: 'Falha ao consultar o CEP.' })
    return { success: true, data: await response.json() }
  })

  app.get('/consulta/cnpj/:cnpj', { preHandler: authenticate }, async (request, reply) => {
    const { cnpj } = request.params as { cnpj: string }; const clean = cnpj.replace(/\D/g, '')
    if (clean.length !== 14) return reply.code(400).send({ success: false, message: 'CNPJ inválido.' })
    const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${clean}`)
    if (!response.ok) return reply.code(502).send({ success: false, message: 'Falha ao consultar o CNPJ.' })
    return { success: true, data: await response.json() }
  })

  app.get('/relatorios/contabil-recebimentos', { preHandler: authenticate }, async (request) => {
    const q = request.query as { inicio?: string; fim?: string; data_inicio?: string; data_fim?: string }
    let query = db.from('tbl_receitas').select('id,descricao,valor_recebido,valor_original,valor_pago,data_recebimento,status,forma_recebimento').eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido')
    const inicio = q.inicio ?? q.data_inicio; const fim = q.fim ?? q.data_fim
    if (inicio) query = query.gte('data_recebimento', inicio); if (fim) query = query.lte('data_recebimento', fim)
    const { data, error } = await query.order('data_recebimento', { ascending: false }); if (error) throw error
    const rows = data ?? []
    // Relatório de recebimentos, filtrado por data_recebimento: o valor fiel é o que
    // entrou (valor_pago), não a cobrança. Em linhas anteriores à migration o
    // acumulado ainda é zero, então valem pelo valor_recebido.
    const recebido = (linha: Record<string, unknown>) => (linha.valor_original == null ? Number(linha.valor_recebido ?? 0) : Number(linha.valor_pago ?? 0)) || 0
    return { success: true, data: rows, meta: { total: rows.length, valor_total: rows.reduce((s, x) => s + recebido(x), 0) } }
  })

  app.get('/relatorios/contabil-pagamentos', { preHandler: authenticate }, async (request) => {
    const q = request.query as { inicio?: string; fim?: string; data_inicio?: string; data_fim?: string }
    let query = db.from('tbl_despesas').select('id,descricao,valor_pago,data_pagamento,status,forma_pagamento').eq('empresa_id', request.user.empresa_id).neq('status_sistema', 'excluido')
    const inicio = q.inicio ?? q.data_inicio; const fim = q.fim ?? q.data_fim
    if (inicio) query = query.gte('data_pagamento', inicio); if (fim) query = query.lte('data_pagamento', fim)
    const { data, error } = await query.order('data_pagamento', { ascending: false }); if (error) throw error
    const rows = data ?? []; return { success: true, data: rows, meta: { total: rows.length, valor_total: rows.reduce((s, x) => s + Number(x.valor_pago ?? 0), 0) } }
  })
}
