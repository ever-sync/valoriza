import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, nextTick } from 'vue'
import { useSimulacaoContrato } from '../useSimulacao.js'

// Mock do contrato service
vi.mock('../../services/contratos.service', () => ({
  simularContrato: vi.fn()
}))

import { simularContrato } from '../../services/contratos.service'

describe('Composable useSimulacaoContrato', () => {

  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
  })

  it('deve inicializar com valores zerados/padrão', async () => {
    const formulario = ref({})
    const { cronograma, iofCalculado, cet, valorTotalParcelas, simulacaoCarregando } = useSimulacaoContrato({ formulario })

    expect(cronograma.value).toEqual([])
    expect(iofCalculado.value.ativo).toBe(false)
    expect(cet.value).toEqual({ mes: '0,00', ano: '0,00' })
    expect(valorTotalParcelas.value).toBe(0)

    // Avançar tempo para concluir a tentativa de simulação inicial do watcher
    vi.advanceTimersByTime(600)
    await nextTick()

    expect(simulacaoCarregando.value).toBe(false)
  })

  it('deve solicitar simulação ao backend quando formulário completo', async () => {
    const mockResposta = {
      success: true,
      data: {
        valor_financiado: '300000.00',
        total_parcelas: '336940.65',
        total_juros: '36940.65',
        total_amortizacao: '300000.00',
        cet: { mes: '4.00', ano: '60.10' },
        iof: { diario: '0', adicional: '0', total: '0', taxa_diaria_pct: '0', taxa_adicional_pct: '0' },
        parcelas: [
          { num: 1, parcela: '67388.13', vencimento: '15/02/2026', vencimento_iso: '2026-02-15', juros: '12000.00', amortizacao: '55388.13', saldo: '244611.87' }
        ]
      }
    }

    simularContrato.mockResolvedValue(mockResposta)

    const formulario = ref({
      valor_solicitado: '300000',
      taxa_juros: '4',
      quantidade_parcelas: '5',
      modelo_amortizacao: 'Price',
      periodo_amortizacao: 'Mensal',
      tipo_operacao: 'Empréstimo',
      data_assinatura: '2026-01-15',
      data_primeira_parcela: '2026-02-15'
    })

    const { cronograma, valorTotalParcelas } = useSimulacaoContrato({ formulario })

    // Avançar o tempo do debounce (500ms)
    vi.advanceTimersByTime(600)
    await nextTick()

    expect(simularContrato).toHaveBeenCalledTimes(1)
    expect(simularContrato).toHaveBeenCalledWith(expect.objectContaining({
      valor_solicitado: '300000',
      taxa_juros: '4',
      quantidade_parcelas: '5',
      modelo_amortizacao: 'Price'
    }), { showLoading: false, showToast: false })

    expect(cronograma.value).toHaveLength(1)
    expect(cronograma.value[0].parcela).toBe('67388.13')
    expect(valorTotalParcelas.value).toBe(336940.65)
  })

})
