<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  label: {
    type: String,
    required: true
  },
  placeholder: {
    type: String,
    default: 'Pesquisar...'
  },
  required: {
    type: Boolean,
    default: false
  },
  searchService: {
    type: Function,
    required: true
  },
  valueKey: {
    type: String,
    default: 'id'
  },
  labelKey: {
    type: String,
    default: 'nome'
  },
  initialLabel: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'select'])

const isOpen = ref(false)
const search = ref('')
const options = ref([])
const loading = ref(false)
const selectedLabel = ref(props.initialLabel)
const containerRef = ref(null)
const triggerRef = ref(null)

// Posição do dropdown (calculada dinamicamente para evitar corte por overflow do modal)
const dropdownStyle = ref({})

const updateDropdownPosition = () => {
  if (!triggerRef.value) return
  const rect = triggerRef.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top
  const dropdownHeight = 320 // max-h-80 = 320px

  // Abre para cima se não houver espaço embaixo
  if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.top - dropdownHeight - 8}px`,
      left: `${rect.left}px`,
      width: `${rect.width}px`,
      zIndex: 9999,
    }
  } else {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 8}px`,
      left: `${rect.left}px`,
      width: `${rect.width}px`,
      zIndex: 9999,
    }
  }
}

watch(() => props.initialLabel, (newVal) => {
  if (newVal) selectedLabel.value = newVal
}, { immediate: true })

const toggleDropdown = async (e) => {
  // Impede que o clique no botão de limpar dispare o toggle
  if (e.target.closest('.clear-btn')) return

  isOpen.value = !isOpen.value
  if (isOpen.value) {
    search.value = ''
    await nextTick()
    updateDropdownPosition()
    await handleSearch()
  }
}

const handleSearch = async () => {
    loading.value = true
    options.value = [] // Limpa a lista para garantir que o "Carregando..." apareça isolado
    try {
        const response = await props.searchService({ search: search.value, limit: 10 })
        if (response && response.success) {
            options.value = response.data || []
        }
    } catch (e) {
        console.error('Erro na busca dinâmica:', e)
    } finally {
        loading.value = false
    }
}

let debounceTimeout
const onInputSearch = () => {
    clearTimeout(debounceTimeout)
    debounceTimeout = setTimeout(handleSearch, 300)
}

const selectOption = (option) => {
  selectedLabel.value = option[props.labelKey]
  emit('update:modelValue', option[props.valueKey])
  emit('select', option)
  isOpen.value = false
}

const clearSelection = (e) => {
    e.stopPropagation()
    selectedLabel.value = ''
    emit('update:modelValue', '')
    emit('select', null)
    isOpen.value = false
}

const handleClickOutside = (e) => {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    // Também verifica se o clique foi dentro do dropdown fixo
    const dropdownEl = document.getElementById('dynamic-select-dropdown-' + uid)
    if (dropdownEl && dropdownEl.contains(e.target)) return
    isOpen.value = false
  }
}

// ID único para o dropdown portal
const uid = Math.random().toString(36).slice(2)

const handleScroll = () => {
  if (isOpen.value) updateDropdownPosition()
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.addEventListener('scroll', handleScroll, true)
  window.addEventListener('resize', handleScroll)
})
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  window.removeEventListener('scroll', handleScroll, true)
  window.removeEventListener('resize', handleScroll)
})
</script>

<template>
  <div class="flex flex-col gap-1.5 relative group" ref="containerRef">
    <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary group-focus-within:text-primary transition-colors">
      {{ label }} <span v-if="required" class="text-danger">*</span>
    </label>

    <div class="relative">
        <!-- Campo Visual -->
        <div 
            ref="triggerRef"
            @click="toggleDropdown"
            class="w-full h-12 px-4 rounded-xl bg-surface border border-border flex items-center justify-between cursor-pointer hover:border-text-secondary/30 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/10 transition-all shadow-sm"
            :class="{ 'border-primary ring-2 ring-primary/10': isOpen }"
        >
            <div class="flex items-center gap-2 overflow-hidden flex-1">
                <span v-if="selectedLabel" class="text-sm font-bold text-text-primary truncate">{{ selectedLabel }}</span>
                <span v-else class="text-sm text-text-tertiary">{{ placeholder }}</span>
                <span v-if="loading && !isOpen" class="text-[10px] font-black text-primary uppercase animate-pulse ml-2 italic">Carregando...</span>
            </div>

            <div class="flex items-center gap-2">
                <button v-if="selectedLabel && !required" @click="clearSelection" class="clear-btn p-1 hover:bg-background rounded-lg text-text-tertiary hover:text-danger transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <svg class="w-4 h-4 text-text-tertiary transition-transform duration-300" :class="{ 'rotate-180 text-primary': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" stroke-width="2.5" />
                </svg>
            </div>
        </div>

        <!-- Dropdown (portal via Teleport para não ser cortado por overflow ou deslocado por transforms) -->
        <Teleport to="body">
            <Transition name="slide-up">
                <div 
                    v-if="isOpen" 
                    :id="'dynamic-select-dropdown-' + uid"
                    :style="dropdownStyle"
                    class="bg-surface rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col max-h-80"
                >
                    <div class="p-3 bg-background border-b border-border">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input 
                                v-model="search"
                                @input="onInputSearch"
                                v-focus
                                class="w-full bg-surface border border-border rounded-xl py-2 pl-9 pr-4 text-sm outline-none focus:border-primary transition-all shadow-inner"
                                :placeholder="'Pesquisar ' + label.toLowerCase() + '...'"
                            />
                        </div>
                    </div>

                    <div class="overflow-y-auto flex-1 custom-scrollbar min-h-[120px]">
                        <!-- Loading State Interno -->
                        <div v-if="loading" class="p-10 flex flex-col items-center justify-center gap-4">
                            <div class="flex gap-1.5">
                                <div class="w-2 h-2 rounded-full bg-primary animate-bounce [animation-delay:-0.3s]"></div>
                                <div class="w-2 h-2 rounded-full bg-primary animate-bounce [animation-delay:-0.15s]"></div>
                                <div class="w-2 h-2 rounded-full bg-primary animate-bounce"></div>
                            </div>
                            <span class="text-xs font-black text-primary uppercase tracking-[0.2em] animate-pulse">Carregando...</span>
                        </div>

                        <template v-else-if="options.length > 0">
                            <div 
                                v-for="(option, index) in options" 
                                :key="option[valueKey] || index"
                                @click="selectOption(option)"
                                class="px-5 py-4 hover:bg-primary/5 cursor-pointer transition-all border-b border-border/10 last:border-0 group relative overflow-hidden"
                            >
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary transform -translate-x-full group-hover:translate-x-0 transition-transform"></div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-text-primary group-hover:text-primary transition-colors">{{ option[labelKey] }}</span>
                                    <slot name="option-detail" :option="option"></slot>
                                </div>
                            </div>
                        </template>

                        <div v-else class="p-10 text-center flex flex-col items-center gap-3 text-text-tertiary">
                            <div class="p-3 bg-background rounded-full">
                               <svg class="w-6 h-6 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" /></svg>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-center">Nenhum registro encontrado</span>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
  </div>
</template>

<script>
const vFocus = {
  mounted: (el) => el.focus()
}
export default {
  directives: { focus: vFocus }
}
</script>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-up-enter-from, .slide-up-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.98);
}

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
</style>
