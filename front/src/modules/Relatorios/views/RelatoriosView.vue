<script setup>
import { ref, onMounted, computed } from 'vue'
import _get from '@/helpers/Connections/get'
import { BASE_API } from '@/constants/api'
import Button from '@/components/ui/Button.vue'

const currentView = ref('menu') // 'menu', 'contratos', 'clientes', 'contabil_recebimentos', 'contabil_pagamentos'
const loading = ref(false)
const sumarioContratos = ref({})
const sumarioClientes = ref([])
const dataContabil = ref([])
const filterCliente = ref('')

const contabilFilter = ref({
  inicio: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  fim: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0],
  status: 'Todos',
  tipoData: 'vencimento',
  cliente: ''
})

const fetchContratos = async () => {
  loading.value = true
  try {
    const resp = await _get({ url: `${BASE_API}/relatorios/sumario-contratos`, showLoading: true })
    if (resp && resp.success) {
      sumarioContratos.value = resp.data
    }
  } catch (error) {
    console.error('Erro ao buscar sumário de contratos:', error)
  } finally {
    loading.value = false
  }
}

const fetchClientes = async () => {
  loading.value = true
  try {
    const resp = await _get({ url: `${BASE_API}/relatorios/sumario-clientes`, showLoading: true })
    if (resp && resp.success) {
      sumarioClientes.value = resp.data
    }
  } catch (error) {
    console.error('Erro ao buscar sumário de clientes:', error)
  } finally {
    loading.value = false
  }
}

const formatarMoeda = (v) => {
  return (parseFloat(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const fetchContabil = async (tipo) => {
  loading.value = true
  try {
    const endpoint = tipo === 'recebimentos' ? 'contabil-recebimentos' : 'contabil-pagamentos'
    const params = new URLSearchParams({
      inicio: contabilFilter.value.inicio,
      fim: contabilFilter.value.fim,
      status: contabilFilter.value.status,
      tipoData: contabilFilter.value.tipoData
    }).toString()
    
    const resp = await _get({ url: `${BASE_API}/relatorios/${endpoint}?${params}`, showLoading: true })
    if (resp && resp.success) {
      dataContabil.value = resp.data
    }
  } catch (error) {
    console.error(`Erro ao buscar relatório contábil de ${tipo}:`, error)
  } finally {
    loading.value = false
  }
}

const openReport = (view) => {
  currentView.value = view
  if (view === 'contratos') fetchContratos()
  if (view === 'clientes') fetchClientes()
  if (view === 'contabil_recebimentos') fetchContabil('recebimentos')
  if (view === 'contabil_pagamentos') fetchContabil('pagamentos')
}

const filteredContabil = computed(() => {
  if (!contabilFilter.value.cliente) return dataContabil.value
  const search = contabilFilter.value.cliente.toLowerCase()
  return dataContabil.value.filter(item => 
    (item.cliente || item.favorecido || '').toLowerCase().includes(search)
  )
})

const totaisContabil = computed(() => {
  return filteredContabil.value.reduce((acc, curr) => acc + (parseFloat(curr.valor) || 0), 0)
})

const filteredClientes = computed(() => {
  if (!filterCliente.value) return sumarioClientes.value
  const search = filterCliente.value.toLowerCase()
  return sumarioClientes.value.filter(c => 
    c.cliente.toLowerCase().includes(search)
  )
})
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-8 overflow-y-auto custom-scrollbar bg-background/50">
    
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black text-text-primary tracking-tighter uppercase">Central de Relatórios</h1>
        <p class="text-text-tertiary text-sm font-medium">Gestão de dados e performance.</p>
      </div>
      <Button v-if="currentView !== 'menu'" variant="ghost" size="sm" @click="currentView = 'menu'" class="flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        Voltar ao Menu
      </Button>
    </div>

    <!-- MENU VIEW (Grid of Cards) -->
    <div v-if="currentView === 'menu'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
      <!-- Card Contratos -->
      <div 
        @click="openReport('contratos')"
        class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 cursor-pointer overflow-hidden"
      >
        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-primary/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col h-full">
          <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-8 group-hover:rotate-12 transition-transform duration-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <h3 class="text-lg font-black text-text-primary uppercase tracking-tight leading-tight mb-3">Sumário de Contratos</h3>
          <p class="text-text-tertiary text-[11px] font-medium leading-relaxed mb-8">Visão panorâmica de contratos, fluxos e inadimplência.</p>
          <div class="mt-auto">
            <div class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-primary">
              <span>Acessar</span>
              <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Clientes -->
      <div 
        @click="openReport('clientes')"
        class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 cursor-pointer overflow-hidden"
      >
        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-emerald-500/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col h-full">
          <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 mb-8 group-hover:rotate-12 transition-transform duration-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
          </div>
          <h3 class="text-lg font-black text-text-primary uppercase tracking-tight leading-tight mb-3">Análise por Clientes</h3>
          <p class="text-text-tertiary text-[11px] font-medium leading-relaxed mb-8">Performance individualizada de carteira por pagador.</p>
          <div class="mt-auto">
            <div class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-emerald-600">
              <span>Acessar</span>
              <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Contábil Recebimentos -->
      <div 
        @click="openReport('contabil_recebimentos')"
        class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 cursor-pointer overflow-hidden"
      >
        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-blue-500/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col h-full">
          <div class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500 mb-8 group-hover:rotate-12 transition-transform duration-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zM17 16v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2h2m3-4H9a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-1M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
          </div>
          <h3 class="text-lg font-black text-text-primary uppercase tracking-tight leading-tight mb-3">Contábil Recebimentos</h3>
          <p class="text-text-tertiary text-[11px] font-medium leading-relaxed mb-8">Rastreio detalhado de entradas e liquidações.</p>
          <div class="mt-auto">
            <div class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-blue-600">
              <span>Acessar</span>
              <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Contábil Pagamentos -->
      <div 
        @click="openReport('contabil_pagamentos')"
        class="group relative bg-surface border border-border/40 rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 cursor-pointer overflow-hidden"
      >
        <div class="absolute top-0 right-0 w-32 h-32 bg-rose-500/5 rounded-bl-full -mr-10 -mt-10 group-hover:bg-rose-500/10 transition-colors"></div>
        <div class="relative z-10 flex flex-col h-full">
          <div class="w-16 h-16 rounded-2xl bg-rose-500/10 flex items-center justify-center text-rose-500 mb-8 group-hover:rotate-12 transition-transform duration-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <h3 class="text-lg font-black text-text-primary uppercase tracking-tight leading-tight mb-3">Contábil Pagamentos</h3>
          <p class="text-text-tertiary text-[11px] font-medium leading-relaxed mb-8">Controle rigoroso de saídas e obrigações pagas.</p>
          <div class="mt-auto">
            <div class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-rose-600">
              <span>Acessar</span>
              <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REPORT: CONTRATOS -->
    <div v-if="currentView === 'contratos'" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div class="bg-surface border border-border rounded-[3.5rem] p-12 shadow-sm flex flex-col gap-8 max-w-5xl mx-auto">
        <h3 class="text-center font-black text-text-primary uppercase tracking-widest text-sm border-b border-border/50 pb-8">Sumário dos Contratos Ativos</h3>
        
        <div v-if="loading" class="animate-pulse space-y-6">
          <div v-for="i in 8" :key="i" class="h-8 bg-gray-50 rounded-xl"></div>
        </div>
        <div v-else class="flex flex-col divide-y divide-border/50">
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Número de clientes</span><span class="text-sm font-black text-text-primary">{{ sumarioContratos.num_clientes }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Prazo médio dos contratos</span><span class="text-sm font-black text-text-primary">{{ sumarioContratos.prazo_medio }} meses</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Total do valor dos contratos</span><span class="text-sm font-black text-text-primary">{{ formatarMoeda(sumarioContratos.total_valor) }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Valor médio do crédito concedido</span><span class="text-sm font-black text-text-primary">{{ formatarMoeda(sumarioContratos.valor_medio) }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Total a receber</span><span class="text-sm font-black text-text-primary">{{ formatarMoeda(sumarioContratos.total_a_receber) }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Restante do crédito a amortizar</span><span class="text-sm font-black text-text-primary">{{ formatarMoeda(sumarioContratos.restante_amortizar) }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-text-secondary uppercase tracking-tighter">Restante dos juros a receber</span><span class="text-sm font-black text-text-primary">{{ formatarMoeda(sumarioContratos.restante_juros) }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-rose-500 uppercase tracking-tighter">Atrasados no Mês (Qtd)</span><span class="text-sm font-black text-rose-600">{{ sumarioContratos.atrasados_mes_qtd }}</span></div>
          <div class="py-5 flex justify-between items-center"><span class="text-xs font-bold text-rose-500 uppercase tracking-tighter">Atrasados no Mês (Valor)</span><span class="text-sm font-black text-rose-600">{{ formatarMoeda(sumarioContratos.atrasos_mes_valor) }}</span></div>
          <div class="py-5 flex justify-between items-center border-t-2 border-border pt-8 mt-2"><span class="text-xs font-black text-rose-700 uppercase tracking-tighter">Total Atrasado Acumulado</span><span class="text-lg font-black text-rose-700">{{ formatarMoeda(sumarioContratos.atrasos_total_valor) }}</span></div>
        </div>

        <div class="bg-amber-50 border border-amber-100 p-6 rounded-[2rem] flex items-start gap-4">
          <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p class="text-[10px] font-black text-amber-800 uppercase tracking-widest leading-relaxed">Nota: Valores baseados em contratos ativos. Não considera registros arquivados ou liquidados.</p>
        </div>
      </div>
    </div>

    <!-- REPORT: CLIENTES -->
    <div v-if="currentView === 'clientes'" class="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-8">
      <div class="bg-surface border border-border/40 rounded-[2.5rem] p-10 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
          <div class="space-y-1">
            <h3 class="text-xl font-black text-text-primary uppercase tracking-tight">Performance por Cliente</h3>
            <p class="text-[10px] font-black text-text-tertiary uppercase tracking-widest">Resumo consolidado da carteira ativa</p>
          </div>
          <div class="w-full md:w-96">
            <input 
              v-model="filterCliente" 
              type="text" 
              placeholder="Pesquisar cliente..." 
              class="w-full bg-background border border-border/60 rounded-[1.25rem] px-6 py-4 text-sm outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/50 transition-all font-medium shadow-sm"
            />
          </div>
        </div>

        <div v-if="loading" class="flex flex-col items-center justify-center p-20">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mb-4"></div>
          <span class="text-[10px] font-black text-emerald-600 uppercase tracking-[0.2em] animate-pulse">Cruzando dados de clientes...</span>
        </div>

        <div v-else class="overflow-x-auto custom-scrollbar">
          <table class="w-full text-left border-collapse">
            <thead class="text-[10px] text-text-tertiary bg-background/50 border-b border-border/40 font-black uppercase tracking-[0.2em]">
              <tr>
                <th class="px-8 py-6">Cliente</th>
                <th class="px-8 py-6 text-center">Contratos</th>
                <th class="px-8 py-6 text-right">Vlr. Total</th>
                <th class="px-8 py-6 text-right">Vlr. Pago</th>
                <th class="px-8 py-6 text-right">Vlr. Pendente</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/30">
              <tr v-for="(c, idx) in filteredClientes" :key="idx" class="group hover:bg-emerald-500/[0.02] transition-all duration-300">
                <td class="px-8 py-6 text-xs font-black text-text-primary uppercase">{{ c.cliente }}</td>
                <td class="px-8 py-6 text-center">
                  <span class="px-3 py-1 bg-background border border-border rounded-lg text-[10px] font-black text-text-secondary">{{ c.qtd_contratos }}</span>
                </td>
                <td class="px-8 py-6 text-right text-xs font-bold text-text-primary">{{ formatarMoeda(c.total_valor) }}</td>
                <td class="px-8 py-6 text-right text-xs font-bold text-emerald-600">{{ formatarMoeda(c.valor_pago) }}</td>
                <td class="px-8 py-6 text-right text-xs font-black text-rose-600">{{ formatarMoeda(c.valor_pendente) }}</td>
              </tr>
              <tr v-if="filteredClientes.length === 0">
                <td colspan="5" class="py-20 text-center text-text-tertiary text-xs font-bold uppercase tracking-widest opacity-30">Nenhum cliente encontrado</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- REPORT: CONTABIL (RECEBIMENTOS & PAGAMENTOS) -->
    <div v-if="currentView === 'contabil_recebimentos' || currentView === 'contabil_pagamentos'" class="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-8">
      <!-- FILTERS REDESIGNED -->
      <div class="bg-surface border border-border/40 rounded-[2.5rem] p-10 shadow-[0_8px_30px_rgb(0,0,0,0.04)] relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-bl-full -mr-32 -mt-32 transition-all group-hover:bg-primary/10"></div>
        <div class="relative z-10 flex flex-col lg:flex-row gap-8 items-end">
          
          <!-- Period Group -->
          <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-6 w-full">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary ml-2">Início do Período</label>
              <div class="relative">
                <input v-model="contabilFilter.inicio" type="date" class="w-full bg-background border border-border/60 rounded-[1.25rem] px-6 py-4 text-sm outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/50 transition-all font-bold text-text-primary shadow-sm" />
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary ml-2">Final do Período</label>
              <div class="relative">
                <input v-model="contabilFilter.fim" type="date" class="w-full bg-background border border-border/60 rounded-[1.25rem] px-6 py-4 text-sm outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/50 transition-all font-bold text-text-primary shadow-sm" />
              </div>
            </div>
          </div>

          <!-- Selects Group -->
          <div class="flex gap-6 w-full lg:w-auto">
            <div class="flex-1 lg:w-52 space-y-2">
              <label class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary ml-2">Critério de Data</label>
              <div class="relative">
                <select v-model="contabilFilter.tipoData" class="w-full bg-background border border-border/60 rounded-[1.25rem] px-6 py-4 text-sm outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/50 transition-all font-bold text-text-primary appearance-none cursor-pointer">
                  <option value="vencimento">Por Vencimento</option>
                  <option value="pagamento">Por Liquidação</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                  <svg class="w-4 h-4 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>
              </div>
            </div>
            <div class="flex-1 lg:w-52 space-y-2">
              <label class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary ml-2">Situação</label>
              <div class="relative">
                <select v-model="contabilFilter.status" class="w-full bg-background border border-border/60 rounded-[1.25rem] px-6 py-4 text-sm outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/50 transition-all font-bold text-text-primary appearance-none cursor-pointer">
                  <option value="Todos">Todos os Status</option>
                  <option v-if="currentView === 'contabil_recebimentos'" value="Recebido">Recebido</option>
                  <option v-if="currentView === 'contabil_recebimentos'" value="Pendente">Pendente</option>
                  <option v-if="currentView === 'contabil_recebimentos'" value="Parcial">Parcial</option>
                  <option v-if="currentView === 'contabil_pagamentos'" value="Pago">Pago</option>
                  <option v-if="currentView === 'contabil_pagamentos'" value="Pendente">Pendente</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                  <svg class="w-4 h-4 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>
              </div>
            </div>
          </div>

          <!-- Search & Action -->
          <div class="flex gap-6 w-full lg:w-auto items-end">
            <div class="flex-1 lg:w-72 space-y-2">
              <label class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary ml-2">Pessoa / Descrição</label>
              <input v-model="contabilFilter.cliente" type="text" placeholder="Filtrar por nome..." class="w-full bg-background border border-border/60 rounded-[1.25rem] px-6 py-4 text-sm outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/50 transition-all font-medium shadow-sm" />
            </div>
            <Button @click="fetchContabil(currentView === 'contabil_recebimentos' ? 'recebimentos' : 'pagamentos')" variant="primary" class="h-14 px-10 rounded-2xl font-black uppercase tracking-[0.2em] text-[11px] shadow-xl shadow-primary/20 hover:shadow-primary/40 active:scale-95 transition-all">
              Filtrar
            </Button>
          </div>

        </div>
      </div>

      <!-- TOTALS BAR -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-surface border border-border rounded-[2rem] p-8 shadow-sm relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-bl-full -mr-12 -mt-12 transition-colors group-hover:bg-primary/10"></div>
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary mb-2">Total Geral do Período</p>
          <h4 class="text-3xl font-black tracking-tighter" :class="currentView === 'contabil_recebimentos' ? 'text-blue-600' : 'text-rose-600'">
            {{ formatarMoeda(totaisContabil) }}
          </h4>
        </div>
        <div class="bg-surface border border-border rounded-[2rem] p-8 shadow-sm relative overflow-hidden group">
          <div class="absolute top-0 right-0 w-24 h-24 bg-background rounded-bl-full -mr-12 -mt-12"></div>
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-text-tertiary mb-2">Registros Encontrados</p>
          <h4 class="text-3xl font-black text-text-primary tracking-tighter">{{ filteredContabil.length }}</h4>
        </div>
      </div>

      <!-- TABLE -->
      <div class="bg-surface border border-border/40 rounded-[2.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden relative">
        <div v-if="loading" class="absolute inset-0 z-20 bg-surface/60 backdrop-blur-md flex flex-col items-center justify-center p-20">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mb-4"></div>
          <span class="text-[10px] font-black text-primary uppercase tracking-[0.2em] animate-pulse">Processando relatório...</span>
        </div>
        
        <div class="overflow-x-auto min-h-[400px] custom-scrollbar">
          <table class="w-full text-left border-collapse">
            <thead class="text-[10px] text-text-tertiary bg-background/50 border-b border-border/40 font-black uppercase tracking-[0.2em] select-none">
              <tr>
                <th class="px-8 py-6">Data</th>
                <th class="px-8 py-6">Pessoa</th>
                <th class="px-8 py-6">Descrição</th>
                <th class="px-8 py-6 text-center">Status</th>
                <th class="px-8 py-6 text-right">Valor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/30">
              <tr v-for="item in filteredContabil" :key="item.id" class="group hover:bg-background/40 transition-all duration-300">
                <td class="px-8 py-6 text-xs font-bold text-text-primary uppercase tracking-tighter">
                  {{ new Date(contabilFilter.tipoData === 'pagamento' ? (item.data_recebimento || item.data_pagamento) : item.data_vencimento).toLocaleDateString('pt-BR') }}
                </td>
                <td class="px-8 py-6 text-xs font-black text-text-primary uppercase">{{ item.cliente || item.favorecido }}</td>
                <td class="px-8 py-6 text-xs font-medium text-text-tertiary italic max-w-[300px] truncate">{{ item.descricao }}</td>
                <td class="px-8 py-6 text-center">
                  <span class="text-[9px] font-black uppercase tracking-[0.15em] px-4 py-1.5 rounded-full border shadow-sm" 
                    :class="{
                      'bg-emerald-50 text-emerald-600 border-emerald-100': item.status === 'Recebido' || item.status === 'Pago',
                      'bg-amber-50 text-amber-600 border-amber-100': item.status === 'Pendente' || item.status === 'Parcial',
                    }">
                    {{ item.status }}
                  </span>
                </td>
                <td class="px-8 py-6 text-right text-sm font-black tracking-tight" :class="currentView === 'contabil_recebimentos' ? 'text-blue-600' : 'text-rose-600'">
                  {{ formatarMoeda(item.valor) }}
                </td>
              </tr>
              <tr v-if="filteredContabil.length === 0 && !loading">
                <td colspan="5" class="py-32 text-center">
                  <div class="flex flex-col items-center justify-center gap-4 opacity-30">
                    <div class="p-6 bg-background rounded-full">
                      <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <p class="text-sm font-black uppercase tracking-widest">Nenhum registro encontrado no período</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
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
