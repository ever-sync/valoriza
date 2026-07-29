import { ref, onMounted } from 'vue'
import _get from '@/helpers/Connections/get'
import { BASE_API } from '@/constants/api'

export function useDashboard() {
  const loading = ref(true)
  const stats = ref({
    receita_mes: 0,
    receitas_pendentes: 0,
    atrasos: 0,
    novos_clientes: 0,
    transacoes_recentes: [],
    grafico: []
  })

  const fetchStats = async () => {
    loading.value = true
    try {
      const resp = await _get({ url: `${BASE_API}/dashboard/stats`, showLoading: false })
      if (resp && resp.success) {
        stats.value = resp.data
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
