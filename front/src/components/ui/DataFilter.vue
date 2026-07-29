<script setup>
import { ref, reactive, watch, onMounted, onUnmounted } from 'vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  },
  schema: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: 'Pesquisar...'
  }
})

const emit = defineEmits(['update:modelValue', 'apply', 'clear'])

const isOpen = ref(false)
const popoverRef = ref(null)
const triggerRef = ref(null)

const localFilters = reactive({ ...props.modelValue })

// Count active filters (excluding global search)
const activeFiltersCount = ref(0)
watch(() => props.modelValue, (val) => {
  Object.assign(localFilters, val)
  activeFiltersCount.value = Object.keys(val).filter(key => key !== 'search' && val[key]).length
}, { deep: true, immediate: true })

const togglePopover = () => {
  isOpen.value = !isOpen.value
}

const handleSearch = () => {
  emit('update:modelValue', { ...localFilters })
  emit('apply', { ...localFilters })
}

const applyAdvanced = () => {
  isOpen.value = false
  handleSearch()
}

const clearFilters = () => {
  Object.keys(localFilters).forEach(key => localFilters[key] = '')
  isOpen.value = false
  emit('update:modelValue', { ...localFilters })
  emit('clear')
}

const handleClickOutside = (event) => {
  if (isOpen.value && 
      popoverRef.value && !popoverRef.value.contains(event.target) &&
      triggerRef.value && !triggerRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
  <div class="flex items-center gap-2 relative">
    <!-- Main Search Bar -->
    <div class="flex-1 relative group max-w-sm">
      <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none text-text-tertiary group-focus-within:text-primary transition-all duration-300">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <input 
        type="text" 
        v-model="localFilters.search"
        @keyup.enter="handleSearch"
        :placeholder="placeholder"
        class="w-full bg-surface border border-border/60 text-text-primary text-sm rounded-[1.5rem] py-3.5 pl-13 pr-4 focus:ring-4 focus:ring-primary/5 focus:border-primary/50 outline-none transition-all shadow-sm group-hover:border-border group-hover:shadow-md"
      />
    </div>

    <!-- Filter Trigger -->
    <div class="relative" ref="triggerRef">
      <Button 
        variant="ghost" 
        @click="togglePopover"
        class="p-3.5! rounded-[1.25rem]! transition-all relative"
        :class="isOpen ? 'bg-primary/10 text-primary ring-1 ring-primary/20' : 'text-text-tertiary hover:bg-background hover:text-primary border border-transparent hover:border-border/60'"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        
        <!-- Filter Badge -->
        <span 
          v-if="activeFiltersCount > 0"
          class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[9px] font-black text-white ring-2 ring-surface shadow-lg animate-in zoom-in"
        >
          {{ activeFiltersCount }}
        </span>
      </Button>

      <!-- Advanced Filter Popover -->
      <Transition name="popover">
        <div 
          v-if="isOpen" 
          ref="popoverRef"
          class="absolute right-0 top-full mt-4 w-85 bg-surface rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-border/50 z-50 overflow-hidden backdrop-blur-xl flex flex-col max-h-[min(520px,75vh)]"
        >
          <!-- Fixed Header -->
          <div class="px-8 pt-8 pb-6 border-b border-border/10 bg-surface/80 backdrop-blur-md z-10 shadow-sm">
            <div class="flex items-center justify-between">
              <div class="flex flex-col">
                <h4 class="text-xs font-black text-text-primary uppercase tracking-[0.2em]">Filtros Avançados</h4>
                <div class="h-1 w-8 bg-primary rounded-full mt-1"></div>
              </div>
              <button @click="clearFilters" class="text-[10px] font-black text-text-tertiary hover:text-danger transition-colors uppercase tracking-widest">Limpar Tudo</button>
            </div>
          </div>

          <!-- Scrollable Body -->
          <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            <div class="space-y-6">
              <template v-for="field in schema" :key="field.key">
                <!-- Text / Masked Field -->
                <div v-if="field.type === 'text' || field.type === 'mask'" class="space-y-1">
                  <Input 
                    v-model="localFilters[field.key]"
                    :label="field.label"
                    :placeholder="field.placeholder"
                    :mask="field.mask"
                    class="py-0.5!"
                  />
                </div>

                <!-- Select Field -->
                <div v-else-if="field.type === 'select'" class="space-y-2">
                  <label class="block text-[10px] font-black text-text-tertiary uppercase tracking-widest px-1">{{ field.label }}</label>
                  <div class="relative">
                    <select 
                      v-model="localFilters[field.key]"
                      class="w-full bg-background/50 border border-border/60 text-text-primary text-sm rounded-xl p-3.5 focus:ring-4 focus:ring-primary/5 focus:border-primary/50 outline-none transition-all appearance-none cursor-pointer hover:bg-background"
                    >
                      <option value="">Todos os Registros</option>
                      <option v-for="opt in field.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                      <svg class="w-4 h-4 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                  </div>
                </div>

                <!-- Date Range Field -->
                <div v-else-if="field.type === 'date-range'" class="space-y-2">
                   <label class="block text-[10px] font-black text-text-tertiary uppercase tracking-widest px-1">{{ field.label }}</label>
                   <div class="grid grid-cols-2 gap-3">
                      <div class="relative">
                        <input 
                          type="date" 
                          v-model="localFilters[field.key + '_start']"
                          class="w-full bg-background/50 border border-border/60 text-text-primary text-xs rounded-xl p-3 outline-none focus:border-primary/50 hover:bg-background transition-all"
                        />
                      </div>
                      <div class="relative">
                        <input 
                          type="date" 
                          v-model="localFilters[field.key + '_end']"
                          class="w-full bg-background/50 border border-border/60 text-text-primary text-xs rounded-xl p-3 outline-none focus:border-primary/50 hover:bg-background transition-all"
                        />
                      </div>
                   </div>
                </div>

                <!-- Dynamic Select Field -->
                <div v-else-if="field.type === 'dynamic-select'" class="space-y-2">
                  <DynamicSelect
                    v-model="localFilters[field.key]"
                    :label="field.label"
                    :placeholder="field.placeholder"
                    :search-service="field.searchService"
                    :value-key="field.valueKey"
                    :label-key="field.labelKey"
                    class="py-0.5!"
                  />
                </div>
              </template>
            </div>
          </div>

          <!-- Fixed Footer -->
          <div class="p-8 border-t border-border/10 bg-surface/80 backdrop-blur-md z-10 shadow-[0_-10px_20px_rgba(0,0,0,0.02)]">
            <Button variant="primary" size="lg" class="w-full h-14 rounded-2xl shadow-xl shadow-primary/20 hover:shadow-primary/30 active:scale-[0.98] transition-all" @click="applyAdvanced">
              Aplicar Filtros
            </Button>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.popover-enter-active, .popover-leave-active {
  transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.popover-enter-from, .popover-leave-to {
  opacity: 0;
  transform: translateY(-12px) scale(0.98);
}

.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

select {
  background-image: none;
}
</style>
