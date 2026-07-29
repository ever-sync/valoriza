<script setup>
/**
 * Etapa 1 — Operação do Contrato.
 * Configuração de valores, taxas e modelo de amortização.
 * Redesenhado com cards para maior clareza visual.
 */
import { inject, computed } from 'vue'
import Input from '@/components/ui/Input.vue'

const store = inject('contratoStore')

const rotuloTaxaJuros = computed(() => {
  const periodo = store.operacao.value.periodo_amortizacao
  if (periodo === 'Diário') return 'Taxa de juros diária*'
  if (periodo === 'Semanal') return 'Taxa de juros semanal*'
  if (periodo === 'Mensal') return 'Taxa de juros mensal*'
  if (periodo === 'Pagamento único') return 'Taxa de juros do período*'
  return 'Taxa de juros*'
})
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto pb-4">
    <!-- Bloco 1: Dados Básicos da Operação -->
    <div class="bg-white border border-border rounded-2xl p-5 shadow-sm space-y-5">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-6 h-6 rounded-md bg-primary/10 flex items-center justify-center text-primary">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Dados Básicos</h3>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Tipo de operação*</label>
          <select v-model="store.operacao.value.tipo_operacao" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all mt-1">
            <option v-for="t in store.TIPO_OPERACAO" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <Input v-model="store.operacao.value.valor_solicitado" label="Valor solicitado*" mask="real" required />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Período de amortização*</label>
          <select v-model="store.operacao.value.periodo_amortizacao" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all mt-1">
            <option value="">Selecione uma opção</option>
            <option v-for="p in store.PERIODO_AMORTIZACAO" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div v-if="!store.ehDescontoRecebiveis.value && store.operacao.value.periodo_amortizacao !== 'Pagamento único'">
          <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Modelo de amortização*</label>
          <select v-model="store.operacao.value.modelo_amortizacao" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all mt-1">
            <option value="">Selecione uma opção</option>
            <option v-for="m in store.modelosDisponiveis.value" :key="m" :value="m">{{ m }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Bloco 2: Juros e Parcelamento -->
    <div class="bg-white border border-border rounded-2xl p-5 shadow-sm space-y-5">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-6 h-6 rounded-md bg-emerald-500/10 flex items-center justify-center text-emerald-600">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Juros & Parcelamento</h3>
      </div>

      <div v-if="store.operacao.value.periodo_amortizacao === 'Pagamento único' && !store.ehDescontoRecebiveis.value">
        <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Tipo de juros*</label>
        <select v-model="store.operacao.value.tipo_juros_pagamento_unico" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all mt-1">
          <option value="simples">Juros Simples — M = PV × (1 + i × n)</option>
          <option value="compostos">Juros Compostos — M = PV × (1 + i)ⁿ (Aderente BACEN)</option>
        </select>
        <p class="px-1 mt-1.5 text-[11px] text-text-tertiary bg-surface p-2 rounded-lg border border-border">
          <span class="font-bold text-text-secondary">Dica técnica:</span>
          {{ store.operacao.value.tipo_juros_pagamento_unico === 'simples' ? 'Cálculo linear sobre o capital inicial. Equivalência simples.' : 'Cálculo com capitalização diária via taxa equivalente composta rigorosa.' }}
        </p>
      </div>

      <div class="grid grid-cols-2 gap-4 items-start">
        <div v-if="!store.ehPagamentoUnico.value">
          <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Quantidade de parcelas*</label>
          <input v-model="store.operacao.value.quantidade_parcelas" type="number"
            :min="store.configuracoes.value?.qtd_minima_parcelas || 1"
            :max="store.configuracoes.value?.qtd_maxima_parcelas || 9999"
            class="w-full bg-surface border border-border rounded-xl py-2.5 pl-4 pr-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all mt-1" />
          <p v-if="store.configuracoes.value?.qtd_minima_parcelas || store.configuracoes.value?.qtd_maxima_parcelas" class="px-1 mt-1 text-[10px] text-text-tertiary font-medium">
            {{ store.configuracoes.value?.qtd_minima_parcelas ? `Min: ${store.configuracoes.value.qtd_minima_parcelas}` : '' }}{{ store.configuracoes.value?.qtd_minima_parcelas && store.configuracoes.value?.qtd_maxima_parcelas ? ' / ' : '' }}{{ store.configuracoes.value?.qtd_maxima_parcelas ? `Max: ${store.configuracoes.value.qtd_maxima_parcelas}` : '' }}
          </p>
        </div>
        <div>
          <Input 
            v-model="store.operacao.value.taxa_juros" 
            :label="rotuloTaxaJuros"
            placeholder="0,00 %"
            required
            mask="porcentagem"
          />
          <p v-if="store.rotulaTaxaMinima.value" class="px-1 mt-1 text-[10px] text-primary font-bold">{{ store.rotulaTaxaMinima.value }}</p>
        </div>
      </div>
    </div>

    <!-- Bloco 3: Impostos e Datas -->
    <div class="bg-white border border-border rounded-2xl p-5 shadow-sm space-y-5">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-6 h-6 rounded-md bg-amber-500/10 flex items-center justify-center text-amber-600">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Impostos & Datas</h3>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input v-model="store.operacao.value.data_assinatura" label="Data de Assinatura (Liberação)*" type="date" required />
        <Input v-model="store.operacao.value.data_primeira_parcela" label="Data da 1ª Parcela*" type="date" required />
      </div>

      <div class="flex flex-col gap-3 mt-4 p-4 rounded-xl border border-border bg-surface/50">
        <label class="flex items-center justify-between cursor-pointer group">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-white border border-border flex items-center justify-center text-text-secondary group-hover:text-primary transition-colors shadow-sm">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
              <span class="text-sm font-bold text-text-primary block">Cliente Simples Nacional</span>
              <span class="text-[11px] text-text-tertiary">Aplica alíquotas reduzidas (ex: diário 0,00274%)</span>
            </div>
          </div>
          <div class="relative w-11 h-6 rounded-full transition-colors" :class="store.operacao.value.simples_nacional ? 'bg-primary' : 'bg-border'" @click="store.alternarSwitch(store.operacao, 'simples_nacional')">
            <div class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="store.operacao.value.simples_nacional ? 'translate-x-6' : 'translate-x-1'" />
          </div>
        </label>
        
        <div class="h-px w-full bg-border"></div>

        <label class="flex items-center justify-between cursor-pointer group">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-white border border-border flex items-center justify-center text-text-secondary group-hover:text-primary transition-colors shadow-sm">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"/></svg>
            </div>
            <div>
              <span class="text-sm font-bold text-text-primary block">Calcular IOF (Decreto 6.306/07)</span>
              <span class="text-[11px] text-text-tertiary">O imposto será adicionado ao valor financiado</span>
            </div>
          </div>
          <div class="relative w-11 h-6 rounded-full transition-colors" :class="store.operacao.value.calcular_iof ? 'bg-primary' : 'bg-border'" @click="store.alternarSwitch(store.operacao, 'calcular_iof')">
            <div class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="store.operacao.value.calcular_iof ? 'translate-x-6' : 'translate-x-1'" />
          </div>
        </label>
      </div>
    </div>
  </div>
</template>
