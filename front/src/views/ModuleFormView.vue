<script setup>
import { ref, watch, computed, shallowRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routerConfig } from '@/stores/routerConfig'
import PageHeader from '@/components/ui/PageHeader.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()

const moduleName = computed(() => route.params.module)
const id = computed(() => route.params.id)
const isEditing = computed(() => !!id.value)

const loading = ref(false)
const initialData = ref(null)
const formComponent = shallowRef(null)
const recordTitle = ref('')
const config = ref(null)
const loadError = ref(null)

// Carrega dados dinamicamente com base na rota
const loadModuleData = async () => {
  const mod = moduleName.value
  const idVal = id.value
  
  loadError.value = null
  
  const moduleConfig = routerConfig[mod]

  if (!moduleConfig) {
    console.error(`Módulo "${mod}" não registrado na FormView.`)
    router.push('/')
    return
  }
  
  config.value = moduleConfig
  loading.value = !!idVal
  initialData.value = null
  recordTitle.value = ''

  try {
    const formModule = await moduleConfig.getForm()
    formComponent.value = formModule?.default || formModule
    
    if (!formComponent.value) {
      throw new Error(`Componente de formulário não encontrado para módulo "${mod}"`)
    }

    if (idVal) {
      const serviceModule = await moduleConfig.getService()
      const service = serviceModule
      const response = await service[moduleConfig.fetchMethod](idVal, { showLoading: false })
      if (response?.success) {
        const rawData = response.data
        let data = null
        
        if (Array.isArray(rawData)) {
          data = rawData[0]
        } else if (rawData && typeof rawData === 'object') {
          data = rawData.data || rawData
        }

        if (data) {
          initialData.value = data
          recordTitle.value = data.nome_completo || data.razao_social || data.nome || data.descricao || `#${idVal}`
        }
      }
    }
  } catch (error) {
    console.error('Erro ao processar módulo:', error)
    loadError.value = error.message
  } finally {
    loading.value = false
  }
}

// Watch nos parâmetros para suportar navegação entre registros sem remontar o componente
watch(() => [route.params.module, route.params.id], () => {
  loadModuleData()
}, { immediate: true })

const handleSaved = () => {
  router.push(config.value?.listPath || '/')
}

const handleCancel = () => {
  router.back()
}

const formContainerRef = ref(null)

const submitForm = () => {
  const form = formContainerRef.value?.querySelector('form')
  if (form) {
    form.requestSubmit()
  }
}
</script>

<template>
  <div class="p-4 md:p-8 max-w-7xl mx-auto space-y-6">
    <PageHeader 
      :title="isEditing ? `Editar ${config?.title}` : `Novo ${config?.title}`"
      :subtitle="isEditing ? recordTitle : `Preencha os dados abaixo para cadastrar`"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button variant="ghost" size="md" @click="handleCancel" class="gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Voltar
          </Button>
          <Button variant="primary" size="md" @click="submitForm" :loading="loading" class="gap-2 px-6">
            <svg v-if="!loading" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            {{ isEditing ? 'Atualizar' : 'Salvar' }} {{ config?.title }}
          </Button>
        </div>
      </template>
    </PageHeader>

    <div ref="formContainerRef" class="bg-surface rounded-2xl border border-border shadow-sm overflow-hidden">
      <div class="p-6">
        <div v-if="loadError" class="text-center py-8">
          <p class="text-red-500 font-bold">Erro ao carregar formulário:</p>
          <p class="text-gray-600 text-sm">{{ loadError }}</p>
        </div>
        <component 
          v-else-if="formComponent"
          :is="formComponent"
          :initialData="initialData"
          @saved="handleSaved"
          @cancel="handleCancel"
        />
        <div v-else class="text-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
          <p class="text-gray-500">Carregando formulário...</p>
        </div>
      </div>
    </div>
  </div>
</template>

