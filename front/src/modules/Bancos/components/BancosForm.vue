<script setup>
import { toRef } from 'vue'
import { useCrudForm } from '@/composables/useCrudForm'
import * as service from '../services/bancos.service.js'

const props = defineProps({
  initialData: { type: Object, default: null }
})

const emit = defineEmits(['saved', 'cancel'])

const dadosPadrao = {
  banco: '',
  agencia: '',
  conta: '',
  chave_pix: '',
  padrao: 0
}

const { formulario, estaEditando, watchingDadosIniciais, extrairPayload } = useCrudForm({
  dadosPadrao
})

watchingDadosIniciais(
  toRef(props, 'initialData'),
  (dados) => ({ ...dados })
)

const handleSalvar = async () => {
  try {
    const payload = extrairPayload()
    let resposta
    if (estaEditando.value) {
      resposta = await service.updateBanco(formulario.value.id, payload)
    } else {
      resposta = await service.createBanco(payload)
    }

    if (resposta?.success) {
      emit('saved')
    } else {
      window.dispatchEvent(new CustomEvent('toast', { 
        detail: { type: 'error', message: resposta?.message || 'Erro ao salvar.' } 
      }))
    }
  } catch (e) {
    console.error(e)
  }
}

defineExpose({
  save: handleSalvar
})
</script>

<template>
  <form @submit.prevent="handleSalvar" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Instituição Bancária</label>
        <input 
          v-model="formulario.banco" 
          type="text" 
          required
          placeholder="Ex: Banco do Brasil, Itaú, etc"
          class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium"
        />
      </div>

      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Chave PIX (Opcional)</label>
        <input 
          v-model="formulario.chave_pix" 
          type="text" 
          placeholder="E-mail, CPF, CNPJ ou Celular"
          class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium"
        />
      </div>

      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Agência</label>
        <input 
          v-model="formulario.agencia" 
          type="text" 
          required
          placeholder="Ex: 0001-9"
          class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium"
        />
      </div>

      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Conta Corrente</label>
        <input 
          v-model="formulario.conta" 
          type="text" 
          required
          placeholder="Ex: 12345-6"
          class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium"
        />
      </div>
    </div>

    <div 
      class="flex items-center gap-3 p-4 rounded-2xl bg-primary/5 border border-primary/10 group cursor-pointer transition-all"
      :class="{ 'border-primary/30': formulario.padrao }"
      @click="formulario.padrao = formulario.padrao ? 0 : 1"
    >
      <div 
        class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all"
        :class="formulario.padrao ? 'bg-primary border-primary' : 'bg-white border-border group-hover:border-primary/50'"
      >
        <svg v-if="formulario.padrao" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <div>
        <span class="text-sm font-bold text-text-primary block">Definir como conta padrão</span>
        <span class="text-xs text-text-secondary">Esta conta será selecionada automaticamente em novos lançamentos.</span>
      </div>
    </div>
  </form>
</template>
