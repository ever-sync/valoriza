<script setup>
import { computed, watch, ref } from 'vue'
import { applyMask } from '@/helpers/masks'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  label: {
    type: String,
    required: true
  },
  type: {
    type: String,
    default: 'text'
  },
  placeholder: {
    type: String,
    default: ''
  },
  error: {
    type: String,
    default: ''
  },
  required: {
    type: Boolean,
    default: false
  },
  mask: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  showSearch: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'search'])
const isFormatting = ref(false)

const onInput = (event) => {
  let value = event.target.value
  if (props.mask) {
    value = applyMask(props.mask, value)
    event.target.value = value
  }
  emit('update:modelValue', value)
}

const handleSearch = () => {
  if (props.loading || props.disabled) return
  emit('search', props.modelValue)
}

watch(() => props.modelValue, (newVal) => {
  if (isFormatting.value) return
  if (props.mask && newVal && typeof newVal === 'string') {
    if (newVal.includes('.') && !newVal.includes('R$') && !newVal.includes(',')) {
      isFormatting.value = true
      emit('update:modelValue', applyMask(props.mask, newVal))
      setTimeout(() => { isFormatting.value = false }, 0)
    }
  }
})

const containerClass = computed(() => {
  return [
    'group flex flex-col gap-1.5 transition-all duration-300',
    props.error ? 'text-danger' : 'text-text-primary'
  ]
})

const inputClass = computed(() => {
  const base = 'block w-full px-4 py-2.5 bg-surface border rounded-xl shadow-sm text-text-primary placeholder:text-text-tertiary focus:ring-2 focus:ring-offset-0 outline-none transition-all duration-300 sm:text-sm'
  
  if (props.disabled) {
    return `${base} bg-surface/50 border-border cursor-not-allowed opacity-70`
  }
  
  if (props.error) {
    return `${base} border-danger/50 focus:border-danger focus:ring-danger/20`
  }
  
  return `${base} border-border hover:border-text-secondary/30 focus:border-primary focus:ring-primary/10`
})
</script>

<template>
  <div :class="containerClass">
    <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary group-focus-within:text-primary transition-colors">
      {{ label }} <span v-if="required" class="text-danger">*</span>
    </label>
    
    <div class="relative">
      <input
        :type="type"
        :value="modelValue"
        @input="onInput"
        :class="[inputClass, { 'pr-12': showSearch || loading }]"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled || loading"
      />
      
      <!-- Botão de Busca / Loading -->
      <div v-if="showSearch || loading" class="absolute inset-y-0 right-0 flex items-center pr-2">
        <button 
          v-if="!loading"
          type="button" 
          @click="handleSearch"
          class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary/10 rounded-lg transition-all"
          title="Buscar dados"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>
        <div v-else class="p-1.5">
          <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
      </div>
      
      <!-- Indicador de erro visual -->
      <div v-if="error && !loading && !showSearch" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-danger">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
      </div>
    </div>
    
    <p v-if="error" class="px-1 text-[11px] font-medium leading-tight">
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1;
}
</style>
