<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  thead: {
    type: String,
    default: '' // e.g. "Produtos {produto_nome}, Marcas {marcas}, Teste"
  },
  columns: {
    type: Array,
    default: () => []
  },
  data: {
    type: Array,
    required: true
  },
  currentPage: {
    type: Number,
    default: 1
  },
  perPage: {
    type: Number,
    default: 10
  },
  totalItems: {
    type: Number,
    default: 0
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:page', 'update:perPage', 'edit', 'delete', 'sort'])

// Parse columns from `thead` prop or fallback to `columns` array
const parsedColumns = computed(() => {
  if (props.thead) {
    return props.thead.split(',').map((part, index) => {
      const trimmed = part.trim()
      const match = trimmed.match(/^(.*?)(?:\{([^}]+)\})?$/)
      const label = match ? match[1].trim() : trimmed
      const key = match && match[2] ? match[2].trim() : `col_${index}`
      const sortable = !!(match && match[2])
      return { label, key, sortable }
    })
  }
  return props.columns
})

const totalPages = computed(() => Math.ceil(props.totalItems / props.perPage))
const startIndex = computed(() => (props.currentPage - 1) * props.perPage + 1)
const endIndex = computed(() => Math.min(props.currentPage * props.perPage, props.totalItems))

// Sorting state
const currentSortKey = ref('')
const currentSortOrder = ref('') // 'asc' or 'desc'

function handleSort(col) {
  if (!col.sortable) return

  if (currentSortKey.value === col.key) {
    currentSortOrder.value = currentSortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    currentSortKey.value = col.key
    currentSortOrder.value = 'asc' // default to asc on first click
  }

  emit('sort', { column: currentSortKey.value, direction: currentSortOrder.value })
}
</script>

<template>
  <div class="surface-card overflow-hidden relative transition-all duration-500">
    
    <!-- Loading Overlay -->
    <div v-if="loading" class="absolute inset-0 z-20 bg-[var(--color-surface)]/60 backdrop-blur-md flex items-center justify-center">
      <div class="flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-[var(--color-primary)]"></div>
        <span class="text-[10px] font-black text-primary uppercase tracking-[0.2em] animate-pulse">Carregando dados...</span>
      </div>
    </div>

    <div class="overflow-x-auto min-h-[300px] custom-scrollbar">
      <table class="w-full text-sm text-left text-text-secondary border-collapse">
        <thead class="text-[10px] text-text-tertiary bg-background/70 border-b border-border font-black uppercase tracking-[0.15em] select-none">
          <tr>
            <th v-for="col in parsedColumns" :key="col.key" scope="col" class="px-8 py-5 whitespace-nowrap">
              <div 
                class="flex items-center gap-2 group/col" 
                :class="{ 'cursor-pointer hover:text-primary transition-all active:scale-95': col.sortable }"
                @click="handleSort(col)"
              >
                {{ col.label }}
                <!-- Sort Icons -->
                <div v-if="col.sortable" class="flex flex-col opacity-40 group-hover/col:opacity-100 transition-opacity">
                   <svg v-if="currentSortKey !== col.key || currentSortOrder === 'asc'" class="w-2.5 h-2.5 transition-all" :class="{ 'text-primary scale-125': currentSortKey === col.key && currentSortOrder === 'asc' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                   <svg v-if="currentSortKey !== col.key || currentSortOrder === 'desc'" class="w-2.5 h-2.5 -mt-0.5 transition-all" :class="{ 'text-primary scale-125': currentSortKey === col.key && currentSortOrder === 'desc' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                </div>
              </div>
            </th>
            <!-- Actions Header -->
            <th scope="col" class="px-8 py-5 text-right sticky right-0 bg-surface z-10 border-l border-border/10 shadow-[-12px_0_20px_-10px_rgba(0,0,0,0.12)] min-w-[120px]">
              <span class="sr-only">Ações</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border/30">
          <tr v-if="data.length === 0 && !loading">
            <td :colspan="parsedColumns.length + 1" class="px-8 py-24 text-center">
              <div class="flex flex-col items-center justify-center gap-4 opacity-40">
                <div class="p-6 bg-background rounded-full">
                  <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                </div>
                <span class="text-sm font-black uppercase tracking-widest">Nenhum registro encontrado</span>
              </div>
            </td>
          </tr>
          <tr v-for="row in data" :key="row.id || Math.random()" class="group hover:bg-background/40 transition-all duration-300">
            
            <td v-for="col in parsedColumns" :key="col.key" class="px-8 py-5 font-bold text-text-primary whitespace-nowrap text-xs tracking-tight">
              <slot :name="'col-' + col.key" :row="row" :value="row[col.key]">
                {{ row[col.key] }}
              </slot>
            </td>

            <!-- Actions Cell -->
            <td class="px-8 py-5 text-right sticky right-0 bg-surface z-10 border-l border-border/10 shadow-[-12px_0_20px_-10px_rgba(0,0,0,0.12)] transition-all group-hover:bg-slate-50 min-w-[120px]">
              <div class="flex items-center justify-end gap-1 opacity-80 group-hover:opacity-100 transition-all duration-300">
                <slot name="actions" :row="row">
                  <button @click="emit('edit', row)" class="text-primary hover:bg-primary/10 p-2.5 rounded-xl transition-all active:scale-90" title="Editar">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                  </button>
                  <button @click="emit('delete', row)" class="text-danger hover:bg-danger/10 p-2.5 rounded-xl transition-all active:scale-90" title="Excluir">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </slot>
              </div>
            </td>

          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination Footer -->
    <div class="flex flex-col sm:flex-row items-center justify-between px-8 py-6 border-t border-border/40 bg-surface">
      <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-text-tertiary">
        Exibindo <span class="text-text-primary px-2 py-1 bg-background rounded-lg">{{ startIndex }} - {{ endIndex }}</span> de <span class="text-text-primary">{{ totalItems }}</span>
      </div>
      
      <div class="flex items-center gap-8">
        <div class="hidden sm:flex items-center gap-3">
          <span class="text-[10px] font-black uppercase tracking-widest text-text-tertiary">Por Página:</span>
          <div class="relative">
            <select 
              :value="perPage" 
              @change="e => emit('update:perPage', Number(e.target.value))"
              class="bg-background/50 border border-border/60 text-text-primary text-xs font-black rounded-xl px-4 py-2 pr-8 cursor-pointer outline-none transition-all hover:bg-background focus:ring-4 focus:ring-primary/5 appearance-none"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
            <div class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none">
              <svg class="w-3 h-3 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button 
            @click="emit('update:page', currentPage - 1)" 
            :disabled="currentPage <= 1"
            class="w-10 h-10 flex items-center justify-center rounded-xl border border-border/60 text-text-tertiary hover:bg-background hover:text-primary disabled:opacity-30 disabled:cursor-not-allowed transition-all active:scale-90"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          
          <div class="px-5 py-2 bg-primary/5 border border-primary/10 rounded-xl text-[10px] font-black text-primary uppercase tracking-widest">
            {{ currentPage }} / {{ totalPages || 1 }}
          </div>

          <button 
            @click="emit('update:page', currentPage + 1)" 
            :disabled="currentPage >= totalPages"
            class="w-10 h-10 flex items-center justify-center rounded-xl border border-border/60 text-text-tertiary hover:bg-background hover:text-primary disabled:opacity-30 disabled:cursor-not-allowed transition-all active:scale-90"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
