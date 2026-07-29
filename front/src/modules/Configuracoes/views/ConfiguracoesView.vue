<script setup>
import { ref } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Button from '@/components/ui/Button.vue'
import ConfiguracoesContratosForm from '../components/ConfiguracoesContratosForm.vue'

const formRef = ref(null)
const saving = ref(false)

const handleSave = async () => {
  if (!formRef.value) return
  saving.value = true
  try {
    await formRef.value.save()
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6 max-w-6xl mx-auto w-full">
    <PageHeader title="Configurações dos Contratos" subtitle="Gerencie as taxas, prazos e regras de automação para seus contratos.">
      <template #actions>
        <Button variant="primary" @click="handleSave" :loading="saving">
          <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Salvar Configurações
        </Button>
      </template>
    </PageHeader>
    
    <div class="flex-1">
        <ConfiguracoesContratosForm ref="formRef" />
    </div>
  </div>
</template>
