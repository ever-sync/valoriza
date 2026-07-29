<script setup>
/**
 * Etapa 4 — Inadimplência.
 * Configuração de juros de mora, multa moratória, risco e carência.
 * Redesenhado para maior clareza visual.
 */
import { inject } from 'vue'
import Input from '@/components/ui/Input.vue'

const store = inject('contratoStore')

const corRisco = (risco) => {
  if (risco === 'Baixo') return 'text-emerald-600 bg-emerald-50'
  if (risco === 'Médio') return 'text-amber-600 bg-amber-50'
  if (risco === 'Alto') return 'text-orange-600 bg-orange-50'
  if (risco === 'Muito Alto') return 'text-red-600 bg-red-50'
  return 'text-text-primary bg-surface'
}
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto pb-4">
    <div class="bg-white border border-border rounded-2xl p-6 shadow-sm space-y-6">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-6 h-6 rounded-md bg-red-500/10 flex items-center justify-center text-red-600">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Regras de Inadimplência</h3>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Juros de mora*</label>
          <div class="relative mt-1">
            <input v-model="store.inadimplencia.value.juros_mora" type="number" step="0.01" min="0"
              class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 pl-4 pr-10 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" />
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-text-tertiary font-bold">% ao mês</span>
          </div>
        </div>

        <div>
          <div class="flex justify-between px-1">
            <label class="text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Multa moratória</label>
            <span class="text-[9px] font-medium text-text-tertiary uppercase tracking-wider mt-0.5">Opcional</span>
          </div>
          <div class="relative mt-1">
            <input v-model="store.inadimplencia.value.multa_moratoria" type="number" step="0.01" min="0" placeholder="0.00"
              class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 pl-4 pr-10 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" />
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-text-tertiary font-bold">%</span>
          </div>
        </div>
      </div>

      <div class="pt-2">
        <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Risco Estimado da Operação*</label>
        <div class="relative mt-1">
          <select v-model="store.inadimplencia.value.risco_inadimplencia"
            class="w-full border border-border rounded-xl py-2.5 px-4 text-sm font-semibold outline-none focus:ring-2 transition-all appearance-none"
            :class="[corRisco(store.inadimplencia.value.risco_inadimplencia), 'focus:border-current focus:ring-current/20']"
          >
            <option v-for="r in store.riscos" :key="r" :value="r" class="text-text-primary bg-white">{{ r }}</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4" :class="corRisco(store.inadimplencia.value.risco_inadimplencia)">
            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
          </div>
        </div>
        <p class="px-1 mt-1.5 text-[10px] text-text-tertiary">Métrica interna para provisionamento de risco de crédito.</p>
      </div>

      <div class="border-t border-border/50 pt-5">
        <label class="flex items-start gap-3 cursor-pointer group bg-surface/30 p-3 rounded-xl border border-transparent hover:border-border transition-colors">
          <div class="relative w-11 h-6 rounded-full transition-colors flex-shrink-0 mt-0.5 shadow-inner" :class="store.inadimplencia.value.permitir_carencia ? 'bg-primary' : 'bg-border'" @click="store.alternarSwitchInadimplencia('permitir_carencia')">
            <div class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="store.inadimplencia.value.permitir_carencia ? 'translate-x-6' : 'translate-x-1'" />
          </div>
          <div @click="store.alternarSwitchInadimplencia('permitir_carencia')">
            <span class="text-sm font-bold text-text-primary block">Permitir carência de principal</span>
            <span class="text-[11px] text-text-tertiary leading-tight block mt-0.5">Autoriza o cliente a pagar apenas os juros do mês, postergando a amortização do principal para a próxima parcela.</span>
          </div>
        </label>
        
        <transition name="fade">
          <div v-if="store.inadimplencia.value.permitir_carencia" class="mt-4 pl-14">
            <label class="text-[10px] font-bold uppercase tracking-wider text-text-tertiary">Limite de usos da carência</label>
            <input v-model="store.inadimplencia.value.limite_carencia" type="number" min="1" placeholder="Ex: 3"
              class="w-full max-w-[200px] bg-surface border border-border rounded-xl py-2 px-4 text-sm outline-none focus:border-primary transition-all mt-1 block" />
            <p class="mt-1 text-[10px] text-text-tertiary">Deixe vazio para permitir usos ilimitados durante o contrato.</p>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
