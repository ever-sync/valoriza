<script setup>
import { ref, onMounted, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import useUserSession from '@/composables/useAuthSession'
import { updateUsuario } from '@/modules/Usuarios/services/usuarios.service'

const emit = defineEmits(['close'])
const { user, setUser } = useUserSession()

const isSaving = ref(false)
const form = ref({
  nome: '',
  email: '',
  senha: '',
  senha_confirmacao: ''
})

const preencherDados = () => {
  if (user.value) {
    form.value.nome = user.value.nome_completo || user.value.name || user.value.nome || ''
    form.value.email = user.value.email || ''
  }
}

onMounted(() => {
  preencherDados()
})

// Garantir que os dados apareçam se o user demorar a ser carregado
watch(() => user.value, (newval) => {
  if (newval && !form.value.nome) preencherDados()
}, { deep: true })

const notify = (type, message) => {
  window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }))
}

const saveProfile = async () => {
  if (!user.value || !user.value.id) {
    notify('error', 'Sessão inválida.')
    return
  }

  if (form.value.senha && form.value.senha !== form.value.senha_confirmacao) {
    notify('error', 'As senhas não coincidem.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      nome_completo: form.value.nome,
      email: form.value.email,
      perfil_acesso: user.value.perfil_acesso // Necessário para a validação global do backend
    }
    if (form.value.senha) {
      payload.senha = form.value.senha
    }

    const resp = await updateUsuario(user.value.id, payload)
    
    // Sucesso, atualiza a sessao na UI tb
    if (resp && resp.success) {
      setUser({
        ...user.value,
        nome_completo: form.value.nome,
        name: form.value.nome,
        nome: form.value.nome,
        email: form.value.email
      })
      notify('success', 'Perfil atualizado com sucesso!')
      emit('close')
    } else {
      notify('error', resp?.message || 'Erro ao atualizar o perfil.')
    }
  } catch (error) {
    notify('error', 'Erro de conexão.')
    console.error(error)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-[9999] flex items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="emit('close')"></div>
    <div class="relative bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-md mx-4 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-200">
      
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-gray-50/50">
        <h3 class="text-xl font-bold tracking-tight text-text-primary">Meu Perfil</h3>
        <button type="button" @click="emit('close')" class="p-1.5 text-text-tertiary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-hover">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      
      <!-- Body -->
      <div class="p-6 space-y-5">
        <div class="flex justify-center mb-2">
          <div class="relative">
            <div class="w-20 h-20 rounded-full bg-primary flex items-center justify-center text-white font-black text-2xl border-4 border-white shadow-md ring-4 ring-primary/5">
              {{ form.nome?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U' }}
            </div>
          </div>
        </div>

        <Input 
          v-model="form.nome" 
          label="Nome Completo*" 
          placeholder="Seu nome"
        />
        
        <Input 
          v-model="form.email" 
          label="E-mail*" 
          type="email" 
          placeholder="seu@email.com"
        />

        <div class="pt-2 border-t border-border mt-4">
          <h4 class="text-xs font-bold text-text-tertiary uppercase tracking-wider mb-3">Alterar Senha</h4>
          <div class="space-y-4">
            <Input 
              v-model="form.senha" 
              label="Nova Senha" 
              type="password" 
              placeholder="Deixe em branco para não alterar" 
            />
            <Input 
              v-show="form.senha"
              v-model="form.senha_confirmacao" 
              label="Confirmar Nova Senha" 
              type="password" 
            />
          </div>
        </div>

      </div>
      
      <!-- Footer -->
      <div class="px-6 py-4 border-t border-border bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
        <Button variant="ghost" type="button" @click="emit('close')">Cancelar</Button>
        <Button variant="primary" type="button" :disabled="isSaving" @click="saveProfile">
          {{ isSaving ? 'Salvando...' : 'Salvar Alterações' }}
        </Button>
      </div>

    </div>
  </div>
</template>
