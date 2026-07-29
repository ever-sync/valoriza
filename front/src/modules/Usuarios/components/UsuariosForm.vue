<script setup>
import { toRef } from 'vue'
import { useCrudForm } from '@/composables/useCrudForm'
import * as service from '../services/usuarios.service.js'

const props = defineProps({
  initialData: { type: Object, default: null }
})

const emit = defineEmits(['saved', 'cancel'])

const dadosPadrao = {
  nome_completo: '',
  email: '',
  telefone: '',
  perfil_acesso: 'user',
  ativo: 1,
  notificar_contratos: 0,
  senha: ''
}

const { formulario, estaEditando, watchingDadosIniciais, extrairPayload } = useCrudForm({ dadosPadrao })

watchingDadosIniciais(
  toRef(props, 'initialData'),
  (dados) => {
    if (!dados) return dados
    const { senha, ...rest } = dados
    return { ...rest, senha: '' }
  }
)

const handleSalvar = async () => {
  const payload = extrairPayload()
  if (estaEditando.value && !payload.senha) delete payload.senha

  const resposta = estaEditando.value
    ? await service.updateUsuario(formulario.value.id, payload)
    : await service.createUsuario(payload)

  if (resposta?.success) emit('saved')
  else window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: resposta?.message || 'Erro.' } }))
}

defineExpose({ save: handleSalvar })
</script>

<template>
  <form @submit.prevent="handleSalvar" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Nome Completo</label>
        <input v-model="formulario.nome_completo" type="text" required placeholder="Ex: João Silva" class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium" />
      </div>
      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">E-mail</label>
        <input v-model="formulario.email" type="email" required placeholder="exemplo@email.com" class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium" />
      </div>
      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Telefone</label>
        <input v-model="formulario.telefone" type="text" placeholder="(00) 00000-0000" class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium" />
      </div>
      <div class="space-y-2">
        <label class="text-sm font-bold text-text-secondary ml-1">Perfil de Acesso</label>
        <select v-model="formulario.perfil_acesso" class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium appearance-none">
          <option value="admin">Administrador</option>
          <option value="user">Usuário</option>
          <option value="manager">Gerente</option>
        </select>
      </div>
      <div class="space-y-2 col-span-full">
        <label class="text-sm font-bold text-text-secondary ml-1">{{ formulario.id ? 'Alterar Senha (deixe vazio para manter)' : 'Senha de Acesso' }}</label>
        <input v-model="formulario.senha" type="password" :required="!formulario.id" placeholder="••••••••" class="w-full h-12 px-4 rounded-xl bg-background border border-border focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none text-sm font-medium" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="flex items-center gap-3 p-4 rounded-2xl bg-surface border border-border group cursor-pointer transition-all" :class="{ 'border-primary/30 bg-primary/5': formulario.ativo }" @click="formulario.ativo = formulario.ativo ? 0 : 1">
        <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all" :class="formulario.ativo ? 'bg-primary border-primary' : 'bg-white border-border'">
          <svg v-if="formulario.ativo" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        </div>
        <span class="text-sm font-bold text-text-primary">Usuário Ativo</span>
      </div>
      <div class="flex items-center gap-3 p-4 rounded-2xl bg-surface border border-border group cursor-pointer transition-all" :class="{ 'border-primary/30 bg-primary/5': formulario.notificar_contratos }" @click="formulario.notificar_contratos = formulario.notificar_contratos ? 0 : 1">
        <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all" :class="formulario.notificar_contratos ? 'bg-primary border-primary' : 'bg-white border-border'">
          <svg v-if="formulario.notificar_contratos" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        </div>
        <span class="text-sm font-bold text-text-primary">Notificar novos contratos</span>
      </div>
    </div>
  </form>
</template>
