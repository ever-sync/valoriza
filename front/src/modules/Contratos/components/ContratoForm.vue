<script setup>
/**
 * Formulário principal de contratos — orquestra todas as etapas.
 * Utiliza o store centralizado (useContratoStore) via provide/inject.
 * Redesenhado com Layout Split-Screen.
 */
import { ref, provide, onMounted } from 'vue'
import Button from '@/components/ui/Button.vue'
import { createContrato, updateContrato } from '../services/contratos.service'
import { getConfiguracoes } from '@/modules/Configuracoes/services/configuracoesContratos.service'
import { criarContratoStore } from '../composables/useContratoStore'
import { parsearValor } from '@/helpers/formatacao/valores'

import ContratoOperacao from './steps/ContratoOperacao.vue'
import ContratoCliente from './steps/ContratoCliente.vue'
import ContratoGarantias from './steps/ContratoGarantias.vue'
import ContratoInadimplencia from './steps/ContratoInadimplencia.vue'
import ContratoConclusao from './steps/ContratoConclusao.vue'

const props = defineProps({ initialData: { type: Object, default: null } })
const emit = defineEmits(['saved', 'cancel'])

const etapaAtual = ref(1)
const totalEtapas = 5
const estaCarregando = ref(false)

const etapas = [
  { id: 1, label: 'Operação', icon: 'M9 14l6-6h6m-6 6v6m-3-3a9 9 0 11-18 0 9 9 0 0118 0z' }, // placeholder icons
  { id: 2, label: 'Cliente', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { id: 3, label: 'Garantias', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
  { id: 4, label: 'Inadimplência', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
  { id: 5, label: 'Conclusão', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' }
]

/** Store centralizado — criado AQUI e compartilhado com todos os steps */
const store = criarContratoStore({ dadosIniciais: props.initialData })
provide('contratoStore', store)

/** Carregar configurações do sistema */
onMounted(async () => {
  try {
    const res = await getConfiguracoes({ por_pagina: 1 })
    if (res?.success && res.data?.length) {
      store.configuracoes.value = res.data[0]
    }
  } catch (e) { console.warn('Configurações não carregadas:', e) }

  if (props.initialData) store.preencherComDados(props.initialData)
})

/** Avançar para a próxima etapa */
const irProximaEtapa = () => {
  if (etapaAtual.value === 1 && !store.validarOperacao()) return
  if (etapaAtual.value < totalEtapas) etapaAtual.value++
}

/** Voltar para a etapa anterior */
const irEtapaAnterior = () => {
  if (etapaAtual.value > 1) etapaAtual.value--
}

/** Salvar o contrato */
const save = async (iniciar = false) => {
  if (!store.validarOperacao()) {
    etapaAtual.value = 1
    estaCarregando.value = false
    return
  }

  estaCarregando.value = true
  try {
    const payload = store.construirPayload()
    if (iniciar) payload.contrato_iniciado = 1

    const ehEdicao = !!props.initialData?.id
    const resp = ehEdicao
      ? await updateContrato(props.initialData.id, payload)
      : await createContrato(payload)

    if (resp?.success) {
      emit('saved')
    } else {
      window.dispatchEvent(new CustomEvent('toast', {
        detail: { type: 'error', message: resp?.message || 'Erro ao salvar.' }
      }))
    }
  } catch (e) { console.error(e) }
  finally { estaCarregando.value = false }
}

defineExpose({ save })

const fmtValor = (valor) => {
  return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full max-h-[85vh] bg-surface rounded-2xl overflow-hidden shadow-sm border border-border">
    <!-- ESQUERDA: Wizard Passo a Passo -->
    <div class="flex-1 flex flex-col min-w-0 border-r border-border bg-white">
      <!-- Header / Stepper -->
      <div class="px-6 pt-6 pb-4 border-b border-border bg-white/80 backdrop-blur-md sticky top-0 z-10">
        <nav aria-label="Progress">
          <ol role="list" class="flex items-center">
            <li v-for="(step, stepIdx) in etapas" :key="step.id" :class="[stepIdx !== etapas.length - 1 ? 'pr-8 sm:pr-20' : '', 'relative']">
              <template v-if="etapaAtual > step.id">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="h-0.5 w-full bg-primary" />
                </div>
                <button @click="etapaAtual = step.id" class="relative flex h-8 w-8 items-center justify-center rounded-full bg-primary hover:bg-primary-hover transition-colors shadow-sm">
                  <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                  </svg>
                </button>
              </template>
              <template v-else-if="etapaAtual === step.id">
                <div class="absolute inset-0 flex items-center" aria-hidden="true" v-if="stepIdx !== etapas.length - 1">
                  <div class="h-0.5 w-full bg-border" />
                </div>
                <button class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-primary bg-white shadow-sm" aria-current="step">
                  <span class="h-2.5 w-2.5 rounded-full bg-primary" />
                </button>
              </template>
              <template v-else>
                <div class="absolute inset-0 flex items-center" aria-hidden="true" v-if="stepIdx !== etapas.length - 1">
                  <div class="h-0.5 w-full bg-border" />
                </div>
                <button @click="step.id < etapaAtual ? etapaAtual = step.id : null" class="group relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-border bg-white hover:border-gray-400 transition-colors">
                  <span class="h-2.5 w-2.5 rounded-full bg-transparent group-hover:bg-gray-300 transition-colors" />
                </button>
              </template>
              <!-- Rótulo do passo -->
              <span class="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold text-text-tertiary whitespace-nowrap" :class="{ 'text-primary': etapaAtual === step.id }">{{ step.label }}</span>
            </li>
          </ol>
        </nav>
      </div>

      <!-- Área de Conteúdo (View do Step) -->
      <div class="flex-1 overflow-y-auto custom-scrollbar p-6 relative">
        <transition name="fade-slide" mode="out-in">
          <keep-alive>
            <component 
              :is="etapaAtual === 1 ? ContratoOperacao : 
                   etapaAtual === 2 ? ContratoCliente : 
                   etapaAtual === 3 ? ContratoGarantias : 
                   etapaAtual === 4 ? ContratoInadimplencia : 
                   ContratoConclusao" 
            />
          </keep-alive>
        </transition>
      </div>

      <!-- Rodapé de Ações -->
      <div class="px-6 py-4 border-t border-border bg-surface flex items-center justify-between">
        <Button variant="ghost" type="button" @click="etapaAtual > 1 ? irEtapaAnterior() : emit('cancel')">
          {{ etapaAtual > 1 ? 'Voltar' : 'Cancelar' }}
        </Button>
        <div class="flex gap-3">
          <template v-if="etapaAtual < totalEtapas">
            <Button variant="primary" type="button" @click="irProximaEtapa" class="px-8 shadow-sm">
              Avançar
              <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </Button>
          </template>
          <template v-else>
            <Button variant="ghost" type="button" :disabled="estaCarregando" @click="save(false)">Salvar e fechar (sem iniciar)</Button>
            <Button variant="primary" type="button" :disabled="estaCarregando || !store.declaracaoAssinada.value" @click="save(true)" class="px-6 shadow-sm">
              <template v-if="estaCarregando">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Salvando...
              </template>
              <template v-else>
                Iniciar contrato
                <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              </template>
            </Button>
          </template>
        </div>
      </div>
    </div>

    <!-- DIREITA: Painel de Simulação Persistente -->
    <div class="hidden lg:flex w-96 flex-col bg-background/50 border-l border-border relative">
      <div class="px-6 py-5 border-b border-border bg-white/80 backdrop-blur-md sticky top-0 z-10 flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Simulação & Resumo</h3>
      </div>
      
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <!-- Resumo Rápido -->
        <div class="p-6 border-b border-border/50 bg-white space-y-4">
          <div class="flex justify-between items-end">
            <p class="text-xs font-semibold text-text-tertiary uppercase tracking-wider">Valor Financiado</p>
            <p class="text-xl font-bold text-text-primary">
              {{ store.formatarMoeda((parsearValor(store.operacao.value.valor_solicitado) || 0) + (store.iofCalculado.value.ativo ? store.iofCalculado.value.total : 0)) }}
            </p>
          </div>
          <div class="flex justify-between items-end">
            <p class="text-xs font-semibold text-text-tertiary uppercase tracking-wider">Total a Pagar</p>
            <p class="text-lg font-bold text-primary">
              {{ store.formatarMoeda(store.valorTotalParcelas.value) }}
            </p>
          </div>
        </div>

        <!-- Indicadores -->
        <div class="grid grid-cols-2 gap-px bg-border/50 border-b border-border/50">
          <div class="bg-white p-4">
            <p class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider mb-1">CET a.m.</p>
            <p class="text-sm font-bold text-text-primary">{{ store.cet.value.mes }}%</p>
          </div>
          <div class="bg-white p-4">
            <p class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider mb-1">CET a.a.</p>
            <p class="text-sm font-bold text-text-primary">{{ store.cet.value.ano }}%</p>
          </div>
          <div class="bg-white p-4">
            <p class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider mb-1">Taxa</p>
            <p class="text-sm font-bold text-text-primary">{{ store.operacao.value.taxa_juros || '0,00' }}%</p>
          </div>
          <div class="bg-white p-4">
            <p class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider mb-1">Parcelas</p>
            <p class="text-sm font-bold text-text-primary">{{ store.operacao.value.quantidade_parcelas || 0 }}x</p>
          </div>
        </div>

        <!-- IOF -->
        <div class="p-6 border-b border-border/50 bg-white">
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-bold text-text-primary uppercase tracking-wider">Imposto (IOF)</p>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider" :class="store.iofCalculado.value.ativo ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-500'">
              {{ store.iofCalculado.value.ativo ? 'Ativo' : 'Inativo' }}
            </span>
          </div>
          <template v-if="store.iofCalculado.value.ativo">
            <p class="text-lg font-bold text-text-primary mb-1">{{ store.formatarMoeda(store.iofCalculado.value.total) }}</p>
            <div class="flex flex-col gap-1 mt-2">
              <div class="flex justify-between text-[11px]">
                <span class="text-text-tertiary">IOF Diário ({{ store.iofCalculado.value.taxa_diaria_pct.toLocaleString('pt-BR', { minimumFractionDigits: 4 }) }}%/dia)</span>
                <span class="font-medium text-text-secondary">{{ store.formatarMoeda(store.iofCalculado.value.diario) }}</span>
              </div>
              <div class="flex justify-between text-[11px]">
                <span class="text-text-tertiary">IOF Adicional ({{ store.iofCalculado.value.taxa_adicional_pct.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}%)</span>
                <span class="font-medium text-text-secondary">{{ store.formatarMoeda(store.iofCalculado.value.adicional) }}</span>
              </div>
            </div>
          </template>
          <template v-else>
            <p class="text-[11px] text-text-tertiary">O cálculo do IOF está desativado para esta operação.</p>
          </template>
        </div>

        <!-- Cronograma -->
        <div class="p-6 bg-white flex-1">
          <p class="text-xs font-bold text-text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
            Cronograma
            <div v-if="store.simulacaoCarregando?.value" class="animate-spin rounded-full h-3 w-3 border-b-2 border-primary"></div>
          </p>
          
          <div v-if="store.simulacaoCarregando?.value" class="flex flex-col items-center justify-center text-xs text-primary italic text-center py-10 opacity-70">
            Calculando simulação...
          </div>
          <div v-else-if="store.cronogramaGerado.value.length === 0" class="flex items-center justify-center text-[11px] text-text-tertiary italic text-center py-10 bg-surface rounded-xl border border-dashed border-border">
            Preencha valor, taxa e parcelas<br>para visualizar o cronograma.
          </div>
          <template v-else>
            <div class="space-y-2">
              <!-- Header pseudo-tabela -->
              <div class="grid grid-cols-[30px_1fr_80px] gap-2 px-2 pb-1 border-b border-border text-[10px] font-bold text-text-tertiary uppercase tracking-wider">
                <div>#</div>
                <div>Vencimento</div>
                <div class="text-right">Valor</div>
              </div>
              <!-- Lista de parcelas -->
              <div v-for="p in store.cronogramaGerado.value.slice(0, 5)" :key="p.num" class="grid grid-cols-[30px_1fr_80px] gap-2 px-2 py-1.5 items-center hover:bg-surface/60 rounded-md transition-colors">
                <div class="text-[11px] font-bold text-text-tertiary">{{ p.num }}</div>
                <div class="text-[11px] text-text-secondary">{{ p.vencimento }}</div>
                <div class="text-[11px] font-bold text-text-primary text-right">{{ store.formatarMoeda(p.parcela) }}</div>
              </div>
              <div v-if="store.cronogramaGerado.value.length > 5" class="text-center py-2 text-[10px] font-bold text-primary uppercase tracking-wider bg-primary/5 rounded-md mt-2">
                + {{ store.cronogramaGerado.value.length - 5 }} parcelas omitidas
              </div>
            </div>
          </template>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

/* Transições do Stepper */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(10px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}
</style>
