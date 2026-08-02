// Duplo do client Supabase. Registra toda consulta emitida pelas rotas para que os
// testes possam afirmar que o filtro de empresa_id sempre foi aplicado — hoje o
// isolamento entre empresas é garantido só por código de aplicação, sem RLS.

export type Consulta = {
  tabela: string
  operacao: 'select' | 'insert' | 'update' | 'delete'
  filtros: Record<string, unknown>
  negados: Record<string, unknown>
  payload?: unknown
}

class FakeQuery {
  constructor(
    readonly consulta: Consulta,
    private readonly linhas: Record<string, unknown>[],
    private readonly erro: Error | null = null,
  ) {}

  private unico = false

  select() {
    return this
  }
  order() {
    return this
  }
  range() {
    return this
  }
  limit() {
    return this
  }
  eq(coluna: string, valor: unknown) {
    this.consulta.filtros[coluna] = valor
    return this
  }
  neq(coluna: string, valor: unknown) {
    this.consulta.negados[coluna] = valor
    return this
  }
  gte(coluna: string, valor: unknown) {
    this.consulta.filtros[`${coluna}>=`] = valor
    return this
  }
  lte(coluna: string, valor: unknown) {
    this.consulta.filtros[`${coluna}<=`] = valor
    return this
  }
  single() {
    this.unico = true
    return this
  }
  maybeSingle() {
    this.unico = true
    return this
  }

  private resultado() {
    if (this.erro) return { data: null, error: this.erro, count: 0 }
    if (this.unico) return { data: this.linhas[0] ?? null, error: null }
    return { data: this.linhas, error: null, count: this.linhas.length }
  }

  // O builder do Supabase é um thenable: as rotas dão await direto nele.
  then(aoResolver: (valor: unknown) => unknown, aoRejeitar?: (erro: unknown) => unknown) {
    return Promise.resolve(this.resultado()).then(aoResolver, aoRejeitar)
  }
}

export function criarFakeDb(linhasPorTabela: Record<string, Record<string, unknown>[]> = {}) {
  const consultas: Consulta[] = []
  const errosPorTabela: Record<string, Error> = {}

  const nova = (tabela: string, operacao: Consulta['operacao'], payload?: unknown) => {
    const consulta: Consulta = { tabela, operacao, filtros: {}, negados: {}, payload }
    consultas.push(consulta)
    return new FakeQuery(consulta, linhasPorTabela[tabela] ?? [], errosPorTabela[tabela] ?? null)
  }

  const db = {
    from(tabela: string) {
      return {
        select: () => nova(tabela, 'select'),
        insert: (payload: unknown) => nova(tabela, 'insert', payload),
        update: (payload: unknown) => nova(tabela, 'update', payload),
        delete: () => nova(tabela, 'delete'),
      }
    },
  }

  return {
    db,
    consultas,
    /** Faz a tabela devolver erro, como o Supabase faria numa falha real. */
    definirErro: (tabela: string, erro: Error | null) => {
      if (erro) errosPorTabela[tabela] = erro
      else delete errosPorTabela[tabela]
    },
    /** Troca as linhas devolvidas por uma tabela entre um teste e outro. */
    definirLinhas: (tabela: string, linhas: Record<string, unknown>[]) => {
      linhasPorTabela[tabela] = linhas
    },
    /** Consultas que tocaram tabelas de negócio (todas são multi-tenant). */
    consultasDeTabela: () => consultas.filter((c) => c.tabela.startsWith('tbl_')),
    limpar: () => consultas.splice(0, consultas.length),
  }
}
