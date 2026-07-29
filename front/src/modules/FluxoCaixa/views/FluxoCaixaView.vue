<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import _get from '@/helpers/Connections/get'
import { BASE_API } from '@/constants/api'
import Button from '@/components/ui/Button.vue'

// Datas iniciais (Mês Atual)
const hoje = new Date()
const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0]
const ultimoDia = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).toISOString().split('T')[0]

const filter = ref({
  inicio: primeiroDia,
  fim: ultimoDia,
  search: ''
})

const loading = ref(true)
const data = ref({
  totais: { entradas: 0, saidas: 0, saldo: 0 },
  registros: []
})

const fetchFluxo = async () => {
  loading.value = true
  try {
    const params = `inicio=${filter.value.inicio}&fim=${filter.value.fim}`
    const resp = await _get({ url: `${BASE_API}/fluxo-caixa/periodo?${params}`, showLoading: true })
    if (resp && resp.success) {
      data.value = resp.data
    }
  } catch (error) {
    console.error('Erro ao carregar fluxo de caixa:', error)
  } finally {
    loading.value = false
  }
}

const formatarMoeda = (v) => {
  return (parseFloat(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const formatarData = (d) => {
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR')
}

const filteredRegistros = computed(() => {
  if (!filter.value.search) return data.value.registros
  const search = filter.value.search.toLowerCase()
  return data.value.registros.filter(r => 
    r.pessoa.toLowerCase().includes(search) || 
    r.descricao.toLowerCase().includes(search)
  )
})

// Watchers para recarregar ao mudar datas
watch(() => [filter.value.inicio, filter.value.fim], fetchFluxo)

onMounted(fetchFluxo)
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-8 overflow-y-auto custom-scrollbar bg-background/50">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div>
        <h1 class="text-4xl font-black text-text-primary tracking-tighter uppercase">Relatório de Fluxo de Caixa</h1>
        <div class="flex flex-wrap gap-4 mt-6">
          <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black uppercase tracking-widest text-text-tertiary ml-2">De</label>
            <input 
              v-model="filter.inicio"
              type="date" 
              class="bg-surface border border-border rounded-2xl px-6 py-3 text-sm font-bold text-text-primary outline-none focus:ring-4 focus:ring-primary/10 transition-all shadow-sm"
            />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black uppercase tracking-widest text-text-tertiary ml-2">Até</label>
            <input 
              v-model="filter.fim"
              type="date" 
              class="bg-surface border border-border rounded-2xl px-6 py-3 text-sm font-bold text-text-primary outline-none focus:ring-4 focus:ring-primary/10 transition-all shadow-sm"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <!-- Entradas Card -->
      <div class="group relative bg-emerald-600 rounded-[2.5rem] p-8 shadow-lg shadow-emerald-500/20 hover:shadow-2xl hover:shadow-emerald-500/40 hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden border border-white/10">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-bl-full -mr-10 -mt-10 group-hover:bg-white/20 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-6">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/80">Total Entradas</span>
            <div class="p-3 bg-white/20 text-white rounded-2xl group-hover:rotate-12 transition-transform duration-500 backdrop-blur-md">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
            </div>
          </div>
          <div>
            <h3 class="text-4xl font-black text-white tracking-tighter">{{ formatarMoeda(data.totais.entradas) }}</h3>
            <div class="flex items-center gap-2 mt-2">
              <span class="flex h-2 w-2 rounded-full bg-white/50"></span>
              <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">No período selecionado</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Saídas Card -->
      <div class="group relative bg-rose-600 rounded-[2.5rem] p-8 shadow-lg shadow-rose-500/20 hover:shadow-2xl hover:shadow-rose-500/40 hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden border border-white/10">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-bl-full -mr-10 -mt-10 group-hover:bg-white/20 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-6">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/80">Total Saídas</span>
            <div class="p-3 bg-white/20 text-white rounded-2xl group-hover:rotate-12 transition-transform duration-500 backdrop-blur-md">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 13l-5 5m0 0l-5-5m5-5v12" /></svg>
            </div>
          </div>
          <div>
            <h3 class="text-4xl font-black text-white tracking-tighter">{{ formatarMoeda(data.totais.saidas) }}</h3>
            <div class="flex items-center gap-2 mt-2">
              <span class="flex h-2 w-2 rounded-full bg-white/50"></span>
              <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">No período selecionado</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Saldo Card -->
      <div class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-primary/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-6">
          <div class="flex items-center justify-between">
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Saldo do Período</span>
              <span class="text-[9px] font-bold text-text-tertiary/60">{{ formatarData(filter.inicio) }} - {{ formatarData(filter.fim) }}</span>
            </div>
            <div class="p-3 bg-primary/10 text-primary rounded-2xl group-hover:rotate-12 transition-transform duration-500">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
          </div>
          <h3 class="text-4xl font-black tracking-tighter" :class="data.totais.saldo >= 0 ? 'text-text-primary' : 'text-rose-600'">
            {{ formatarMoeda(data.totais.saldo) }}
          </h3>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="bg-surface border border-border rounded-[3.5rem] shadow-xl shadow-black/5 overflow-hidden flex flex-col mt-4">
      <div class="px-10 py-8 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-8 bg-surface/50">
        <div class="flex items-center gap-6">
           <button class="p-3 bg-background border border-border rounded-2xl text-text-tertiary hover:text-primary hover:border-primary transition-all shadow-sm group" title="Exportar CSV">
             <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
           </button>
           <h4 class="text-sm font-black text-text-primary uppercase tracking-widest hidden lg:block">Detalhamento Financeiro</h4>
        </div>
        <div class="flex items-center gap-4 w-full md:w-auto">
          <div class="relative w-full md:w-80">
            <input 
              v-model="filter.search"
              type="text" 
              placeholder="Buscar por descrição ou pessoa..." 
              class="w-full bg-background border border-border rounded-2xl px-12 py-3.5 text-sm font-medium outline-none focus:ring-4 focus:ring-primary/10 transition-all"
            />
            <svg class="w-5 h-5 text-text-tertiary absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="bg-background/20 border-b border-border">
              <th class="px-10 py-6 text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Data de Venc.</th>
              <th class="px-10 py-6 text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Pessoa / Cliente</th>
              <th class="px-10 py-6 text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Descrição</th>
              <th class="px-10 py-6 text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary text-center">Status</th>
              <th class="px-10 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Valor Nominal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/20">
            <tr v-for="(reg, idx) in filteredRegistros" :key="idx" class="hover:bg-primary/[0.02] transition-all group">
              <td class="px-10 py-6 text-xs font-bold text-text-primary">{{ formatarData(reg.data) }}</td>
              <td class="px-10 py-6">
                <div class="flex flex-col">
                  <span class="text-xs font-black text-text-primary uppercase tracking-tight">{{ reg.pessoa }}</span>
                  <span class="text-[9px] font-bold text-text-tertiary uppercase tracking-widest mt-0.5">{{ reg.tipo }}</span>
                </div>
              </td>
              <td class="px-10 py-6 text-xs font-medium text-text-secondary italic max-w-xs truncate">{{ reg.descricao }}</td>
              <td class="px-10 py-6 text-center">
                <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-xl inline-block min-w-[80px]" 
                  :class="reg.status === 'Recebido' || reg.status === 'Pago' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                  {{ reg.status }}
                </span>
              </td>
              <td class="px-10 py-6 text-right text-sm font-black" :class="reg.tipo === 'receita' ? 'text-emerald-600' : 'text-rose-700'">
                {{ reg.tipo === 'receita' ? '+' : '-' }} {{ formatarMoeda(reg.valor) }}
              </td>
            </tr>
            <tr v-if="filteredRegistros.length === 0">
              <td colspan="5" class="py-24 text-center">
                <div class="flex flex-col items-center justify-center gap-4 opacity-40">
                   <div class="w-16 h-16 rounded-full bg-border/20 flex items-center justify-center text-text-tertiary">
                     <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" /></svg>
                   </div>
                   <span class="text-sm font-black uppercase tracking-widest">Sem registros no período</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>
