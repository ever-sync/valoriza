<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/ui/PageHeader.vue'
import Button from '@/components/ui/Button.vue'
import { useDashboard } from '@/composables/useDashboard'

const router = useRouter()
const { loading, stats } = useDashboard()

const formatarMoeda = (v) => {
  return (parseFloat(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const formatarData = (d) => {
  if (!d) return '-'
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR')
}

// Cálculo para o gráfico SVG
const maxValor = computed(() => {
  const valores = stats.value.grafico.map(g => g.total)
  return Math.max(...valores, 1000)
})

const quickActions = [
  { label: 'Novo Contrato', icon: 'M12 4v16m8-8H4', color: 'bg-primary text-white', path: '/contratos/novo' },
  { label: 'Novo Cliente', icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', color: 'bg-emerald-500 text-white', path: '/pessoas-fisicas/novo' },
  { label: 'Lançar Receita', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'bg-blue-500 text-white', path: '/receitas/novo' },
  { label: 'Lançar Despesa', icon: 'M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z', color: 'bg-rose-500 text-white', path: '/despesas/novo' }
]
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-8 overflow-y-auto custom-scrollbar bg-background/50">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-black text-text-primary tracking-tight">Dashboard Financeiro</h1>
        <p class="text-text-tertiary text-sm font-medium">Bem-vindo de volta! Aqui está o resumo da sua operação.</p>
      </div>
      <div class="flex gap-2">
        <Button variant="primary" size="sm" outline @click="router.push('/contratos')">Ver Contratos</Button>
        <Button variant="primary" size="sm" @click="router.push('/contratos/novo')">Novo Contrato</Button>
      </div>
    </div>

    <!-- Top Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <!-- Stat Card: Receita do Mês -->
      <div class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-7 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-emerald-500/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Faturamento Mensal</span>
            <div class="p-3 bg-emerald-500/10 text-emerald-600 rounded-2xl group-hover:rotate-12 transition-transform duration-500">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
          </div>
          <div v-if="loading" class="animate-pulse h-10 bg-gray-100 rounded-2xl w-2/3"></div>
          <div v-else>
            <h3 class="text-3xl font-black text-text-primary tracking-tighter">{{ formatarMoeda(stats.receita_mes) }}</h3>
            <div class="flex items-center gap-2 mt-2">
              <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
              <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Confirmado no mês</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stat Card: Pendentes -->
      <div class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-7 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-blue-500/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Receitas Pendentes</span>
            <div class="p-3 bg-blue-500/10 text-blue-600 rounded-2xl group-hover:rotate-12 transition-transform duration-500">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <div v-if="loading" class="animate-pulse h-10 bg-gray-100 rounded-2xl w-2/3"></div>
          <div v-else>
            <h3 class="text-3xl font-black text-text-primary tracking-tighter">{{ formatarMoeda(stats.receitas_pendentes) }}</h3>
            <div class="flex items-center gap-2 mt-2">
              <span class="flex h-2 w-2 rounded-full bg-blue-500"></span>
              <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest">A receber (Futuro)</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stat Card: Atrasos -->
      <div class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-7 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-rose-500/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-rose-500/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary">Em Atraso</span>
            <div class="p-3 bg-rose-500/10 text-rose-600 rounded-2xl group-hover:rotate-12 transition-transform duration-500">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
          </div>
          <div v-if="loading" class="animate-pulse h-10 bg-gray-100 rounded-2xl w-2/3"></div>
          <div v-else>
            <h3 class="text-3xl font-black text-rose-600 tracking-tighter">{{ formatarMoeda(stats.atrasos) }}</h3>
            <div class="flex items-center gap-2 mt-2">
              <span class="flex h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
              <p class="text-[10px] font-black text-rose-600 uppercase tracking-widest">Atenção Necessária</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stat Card: Novos Clientes -->
      <div class="group relative bg-primary rounded-[2.5rem] p-7 shadow-lg shadow-primary/20 hover:shadow-2xl hover:shadow-primary/40 hover:-translate-y-1.5 transition-all duration-500 cursor-pointer overflow-hidden border border-white/10">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-bl-full -mr-10 -mt-10 group-hover:bg-white/20 transition-colors"></div>
        <div class="relative z-10 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/70">Novos Clientes</span>
            <div class="p-3 bg-white/20 text-white rounded-2xl group-hover:rotate-12 transition-transform duration-500">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
          </div>
          <div v-if="loading" class="animate-pulse h-10 bg-white/20 rounded-2xl w-2/3"></div>
          <div v-else>
            <h3 class="text-4xl font-black text-white tracking-tighter">{{ stats.novos_clientes }}</h3>
            <div class="flex items-center gap-2 mt-2">
              <span class="flex h-2 w-2 rounded-full bg-white/50"></span>
              <p class="text-[10px] font-black text-white/80 uppercase tracking-widest">Registrados este mês</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Middle Row: Chart & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Action Panel -->
      <div class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-xl transition-all duration-500 overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-10 -mt-10"></div>
        <div class="relative z-10 flex flex-col gap-8">
          <h3 class="font-black text-text-primary uppercase tracking-widest text-xs border-b border-border/50 pb-4">Ações Rápidas</h3>
          <div class="grid grid-cols-2 gap-4">
            <button
              v-for="action in quickActions"
              :key="action.label"
              @click="router.push(action.path)"
              class="flex flex-col items-center justify-center p-6 rounded-3xl border border-border/60 hover:border-primary/40 hover:bg-primary/5 hover:-translate-y-1 transition-all group gap-3 bg-background/50 backdrop-blur-sm"
            >
              <div :class="[action.color, 'p-4 rounded-2xl shadow-lg group-hover:scale-110 group-hover:rotate-6 transition-transform duration-500']">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="action.icon" /></svg>
              </div>
              <span class="text-[10px] font-black text-text-primary uppercase tracking-widest text-center leading-tight">{{ action.label }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Graph Panel -->
      <div class="lg:col-span-2 group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-xl transition-all duration-500 overflow-hidden">
        <div class="absolute top-0 right-0 w-48 h-48 bg-primary/5 rounded-bl-full -mr-16 -mt-16"></div>
        <div class="relative z-10 flex flex-col h-full gap-8">
          <div class="flex items-center justify-between border-b border-border/50 pb-4">
            <h3 class="font-black text-text-primary uppercase tracking-widest text-xs">Histórico de Receita (6 meses)</h3>
            <span class="text-[9px] font-black text-emerald-500 bg-emerald-500/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Tendência Positiva</span>
          </div>
          
          <div class="flex-1 flex items-end gap-4 min-h-[200px] relative pt-10">
            <div v-for="item in stats.grafico" :key="item.mes" class="flex-1 flex flex-col items-center gap-4 group h-full justify-end">
              <div class="relative w-full flex justify-center items-end h-[160px]">
                <!-- Tooltip -->
                <div class="absolute -top-10 bg-text-primary text-white text-[10px] font-black px-3 py-1.5 rounded-xl opacity-0 group-hover:opacity-100 transition-all duration-300 whitespace-nowrap z-20 shadow-xl -translate-y-1 group-hover:translate-y-0">
                  {{ formatarMoeda(item.total) }}
                </div>
                <!-- Bar -->
                <div 
                  class="w-10 md:w-14 bg-gradient-to-t from-primary/60 to-primary rounded-t-2xl transition-all duration-1000 ease-out cursor-pointer hover:scale-x-105 hover:brightness-110 shadow-lg shadow-primary/10"
                  :style="{ height: `${(item.total / maxValor) * 100}%` }"
                ></div>
              </div>
              <span class="text-[10px] font-black text-text-tertiary uppercase tracking-widest">{{ item.mes }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Row: Recent Transactions -->
    <div class="bg-surface border border-border/40 rounded-[2.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] flex flex-col overflow-hidden transition-all duration-500">
      <div class="px-8 py-7 border-b border-border/40 flex items-center justify-between bg-background/30 backdrop-blur-sm">
        <div class="flex flex-col">
          <h3 class="font-black text-text-primary uppercase tracking-[0.2em] text-[11px]">Atividades Recentes</h3>
          <div class="h-1 w-6 bg-primary rounded-full mt-1"></div>
        </div>
        <button @click="router.push('/fluxo-caixa')" class="text-[10px] font-black text-primary hover:brightness-90 transition-all hover:translate-x-1 uppercase tracking-[0.2em] flex items-center gap-2">
          Fluxo Completo 
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
        </button>
      </div>
      <div class="p-8">
        <div v-if="loading" class="space-y-4">
          <div v-for="i in 5" :key="i" class="h-16 bg-gray-50/50 animate-pulse rounded-2xl"></div>
        </div>
        <div v-else-if="stats.transacoes_recentes.length" class="space-y-2">
          <div v-for="(t, idx) in stats.transacoes_recentes" :key="idx" 
            class="flex items-center justify-between p-5 rounded-[1.5rem] border border-transparent hover:border-border/60 hover:bg-background/40 hover:shadow-sm transition-all duration-300 group cursor-pointer"
          >
            <div class="flex items-center gap-5">
              <div :class="[t.tipo === 'receita' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600', 'p-3.5 rounded-2xl group-hover:rotate-6 transition-all duration-500']">
                <svg v-if="t.tipo === 'receita'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 13l-5 5m0 0l-5-5m5-5v12" /></svg>
              </div>
              <div class="flex flex-col">
                <p class="text-xs font-black text-text-primary uppercase tracking-tight group-hover:text-primary transition-colors">{{ t.nome }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="text-[9px] text-text-tertiary font-black uppercase tracking-widest">{{ formatarData(t.data) }}</span>
                  <span class="w-1 h-1 bg-border rounded-full"></span>
                  <span class="text-[9px] font-black uppercase tracking-widest" :class="t.status === 'Recebido' || t.status === 'Pago' ? 'text-emerald-500' : 'text-amber-500'">
                    {{ t.status }}
                  </span>
                </div>
              </div>
            </div>
            <div class="text-right flex flex-col items-end gap-1">
              <p :class="[t.tipo === 'receita' ? 'text-emerald-600' : 'text-rose-600', 'text-sm font-black tracking-tight']">
                {{ t.tipo === 'receita' ? '+' : '-' }} {{ formatarMoeda(t.valor) }}
              </p>
              <div class="opacity-0 group-hover:opacity-100 transition-opacity translate-x-2 group-hover:translate-x-0 transition-all duration-300">
                <svg class="w-4 h-4 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center text-text-tertiary py-20 gap-4 opacity-30 italic">
          <div class="p-6 bg-background rounded-full">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          </div>
          <p class="text-sm font-black uppercase tracking-widest">Nenhuma movimentação recente</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>
