import { ref, onMounted } from 'vue'
import _get from '@/helpers/Connections/get'
import { BASE_API } from '@/constants/api'

export function useDashboard() {
  const emptyStats = () => ({
    receita_mes: 0,
    receitas_pendentes: 0,
    atrasos: 0,
    novos_clientes: 0,
    transacoes_recentes: [],
    grafico: []
  })

  const loading = ref(true)
  const stats = ref(emptyStats())

  const fetchStats = async () => {
    loading.value = true
    try {
      const resp = await _get({ url: `${BASE_API}/dashboard/stats`, showLoading: false })
      if (resp && resp.success) {
        const data = resp.data || {}
        stats.value = {
          ...emptyStats(),
          ...data,
          transacoes_recentes: Array.isArray(data.transacoes_recentes) ? data.transacoes_recentes : [],
          grafico: Array.isArray(data.grafico) ? data.grafico : []
        }
      }
    } catch (error) {
      console.error('Erro ao carregar dashboard:', error)
    } finally {
      loading.value = false
    }
  }

  onMounted(fetchStats)

  return {
    loading,
    stats,
    fetchStats
  }
}
