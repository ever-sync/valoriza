<script setup>
import { ref, onMounted } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import * as service from '../services/configuracoesContratos.service.js'
import { applyMask } from '@/helpers/masks'

const formData = ref({
  id: null,
  taxa_juros_minima_sem_garantia: '0,00 %',
  taxa_juros_avalista: '0,00 %',
  taxa_juros_imovel: '0,00 %',
  taxa_juros_veiculo: '0,00 %',
  taxa_juros_outras_garantias: '0,00 %',
  qtd_minima_parcelas: 1,
  qtd_maxima_parcelas: 120,
  tipo_registro_crdc: 'integracao_esc',
  crdc_usuario: '',
  crdc_senha: '',
  crdc_cnpj: '',
  dias_notificacao_vencimento: '3,1',
  notificar_vencimento_email: 1,
  notificar_vencimento_whatsapp: 1,
  exibir_notificacoes_vencimento: 1,
  frequencia_notificacao_atraso: '7',
  notificar_atraso_email: 1,
  notificar_atraso_whatsapp: 1,
  exibir_notificacoes_atraso: 1,
  copiar_avalistas_atraso: '1',
})

const hasConfig = ref(false)
const loading = ref(true)

const loadConfig = async () => {
  loading.value = true
  try {
    const response = await service.getConfiguracoes({ limit: 1 })
    if (response && response.success && response.data?.length > 0) {
      const data = response.data[0]
      hasConfig.value = true

      // Apply masks for initial display
      const fieldsToMask = {
        taxa_juros_minima_sem_garantia: 'porcentagem',
        taxa_juros_avalista: 'porcentagem',
        taxa_juros_imovel: 'porcentagem',
        taxa_juros_veiculo: 'porcentagem',
        taxa_juros_outras_garantias: 'porcentagem',
        crdc_cnpj: 'cnpj',
      }

      Object.entries(fieldsToMask).forEach(([field, mask]) => {
        if (data[field]) {
          data[field] = applyMask(mask, data[field])
        }
      })

      // Garante que o ID e os dados vindo do back povoem o formulário
      formData.value.id = data.id
      formData.value = { ...formData.value, ...data }
    } else {
      hasConfig.value = false
      formData.value.id = null
    }
  } catch (e) {
    console.error('Erro ao carregar configurações:', e)
    hasConfig.value = false
  } finally {
    loading.value = false
  }
}

onMounted(loadConfig)

const save = async () => {
  try {
    let response
    // Criamos uma cópia para não enviar o ID no corpo da requisição
    const payload = { ...formData.value }
    const id = payload.id
    delete payload.id

    if (hasConfig.value) {
      response = await service.updateConfiguracao(id, payload)
    } else {
      response = await service.createConfiguracao(payload)
    }

    if (response && response.success) {
      await loadConfig()
    }
  } catch (e) {
    console.error('Erro ao salvar configurações:', e)
  }
}

defineExpose({
  save,
})
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center p-12">
    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
  </div>
  <form v-else @submit.prevent="save" class="space-y-8">
    <!-- Taxas de Juros -->
    <section class="bg-surface p-6 rounded-2xl border border-border shadow-sm">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-text-primary">Taxas de Juros Mensais (%)</h3>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Input
          v-model="formData.taxa_juros_minima_sem_garantia"
          label="Sem Garantia"
          mask="porcentagem"
        />
        <Input v-model="formData.taxa_juros_avalista" label="Com Avalista" mask="porcentagem" />
        <Input v-model="formData.taxa_juros_imovel" label="Garantia Imóvel" mask="porcentagem" />
        <Input v-model="formData.taxa_juros_veiculo" label="Garantia Veículo" mask="porcentagem" />
        <Input
          v-model="formData.taxa_juros_outras_garantias"
          label="Outras Garantias"
          mask="porcentagem"
        />
      </div>
    </section>


    <!-- Parcelas e CRDC -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <section class="bg-surface p-6 rounded-2xl border border-border shadow-sm">
        <h3 class="text-base font-bold text-text-primary mb-6">Limites de Parcelas</h3>
        <div class="grid grid-cols-2 gap-4">
          <Input v-model="formData.qtd_minima_parcelas" label="Mínimo" type="number" />
          <Input v-model="formData.qtd_maxima_parcelas" label="Máximo" type="number" />
        </div>
      </section>

      <section class="bg-surface p-6 rounded-2xl border border-border shadow-sm">
        <h3 class="text-base font-bold text-text-primary mb-6">Integração CRDC</h3>
        <div class="space-y-4">
          <div class="flex flex-col gap-1.5">
            <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary"
              >Tipo de Registro</label
            >
            <select
              v-model="formData.tipo_registro_crdc"
              class="block w-full px-4 py-2.5 bg-surface border border-border rounded-xl shadow-sm text-text-primary outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all sm:text-sm"
            >
              <option value="integracao_esc">Integração ESC</option>
              <option value="manual">Manual</option>
              <option value="acesso_crdc">Acesso Direto CRDC</option>
            </select>
          </div>
          <div
            v-if="formData.tipo_registro_crdc !== 'manual'"
            class="grid grid-cols-1 md:grid-cols-3 gap-4"
          >
            <Input
              v-model="formData.crdc_cnpj"
              label="CNPJ CRDC"
              mask="cnpj"
              placeholder="00.000.000/0000-00"
            />
            <Input v-model="formData.crdc_usuario" label="Usuário CRDC" />
            <Input v-model="formData.crdc_senha" label="Senha CRDC" type="password" />
          </div>
        </div>
      </section>
    </div>

    <!-- Notificações -->
    <section class="bg-surface p-6 rounded-2xl border border-border shadow-sm">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-600"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
            />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-text-primary">Réguas de Cobrança e Notificações</h3>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Vencimento -->
        <div class="space-y-6">
          <h4 class="text-sm font-extrabold uppercase tracking-widest text-text-tertiary">
            Pré-Vencimento
          </h4>
          <div class="space-y-4">
            <Input
              v-model="formData.dias_notificacao_vencimento"
              label="Dias para notificar (ex: 3)"
            />
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.notificar_vencimento_email"
                  :true-value="1"
                  :false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">E-mail</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.notificar_vencimento_whatsapp"
                  :true-value="1"
                  :false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">WhatsApp</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.exibir_notificacoes_vencimento"
                  :true-value="1"
                  :false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">Alertas no Sistema</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Atraso -->
        <div class="space-y-6">
          <h4 class="text-sm font-extrabold uppercase tracking-widest text-text-tertiary">
            Pós-Vencimento (Atraso)
          </h4>
          <div class="space-y-4">
            <Input
              v-model="formData.frequencia_notificacao_atraso"
              label="Frequência em dias (ex: 7)"
            />
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.notificar_atraso_email"
                  :true-value="1"
                  :false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">E-mail</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.notificar_atraso_whatsapp"
                  :true-value="1"
                  :false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">WhatsApp</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formData.exibir_notificacoes_atraso"
                  :true-value="1"
                  :false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">Alertas no Sistema</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer mt-2 w-full">
                <input
                  type="checkbox"
                  v-model="formData.copiar_avalistas_atraso"
                  true-value="1"
                  false-value="0"
                  class="rounded border-border text-primary focus:ring-primary"
                />
                <span class="text-sm font-medium">Copiar Avalistas nas notificações de atraso</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </section>
  </form>
</template>
