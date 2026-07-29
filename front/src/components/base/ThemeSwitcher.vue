<script setup>
import { ref } from 'vue'
import { useTheme } from '@/composables/useTheme'
import Button from '@/components/ui/Button.vue'

const { primaryColor, secondaryColor, resetTheme } = useTheme()
const isOpen = ref(false)

const toggle = () => {
  isOpen.value = !isOpen.value
}

const handleReset = () => {
  resetTheme()
}
</script>

<template>
  <div class="fixed bottom-6 right-6 z-50">
    <!-- Popover -->
    <div 
      v-if="isOpen" 
      class="absolute bottom-20 right-0 bg-white rounded-[2rem] shadow-2xl p-6 border border-gray-100 min-w-[280px] transform transition-all duration-300 animate-in fade-in slide-in-from-bottom-4"
    >
      <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3">
        <h4 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em]">Cores da Marca</h4>
        <div class="flex items-center gap-2">
          <button 
            @click="handleReset" 
            class="text-[10px] font-black text-primary hover:brightness-110 uppercase tracking-wider px-2 py-1 rounded-lg hover:bg-primary/5 transition-all"
            title="Restaurar padrão"
          >
            Resetar
          </button>
          <Button variant="ghost" size="sm" @click="toggle" class="!px-2 !rounded-lg text-gray-400">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </Button>
        </div>
      </div>
      
      <div class="space-y-6">
        <div class="group">
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Primária</label>
          <div class="flex items-center gap-4 bg-gray-50 p-3 rounded-2xl border border-transparent group-hover:border-gray-200 transition-all">
            <input type="color" v-model="primaryColor" class="w-10 h-10 rounded-xl border-none cursor-pointer bg-transparent" />
            <span class="text-sm text-gray-800 font-black font-mono tracking-tighter uppercase">{{ primaryColor }}</span>
          </div>
        </div>
        
        <div class="group">
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Secundária</label>
          <div class="flex items-center gap-4 bg-gray-50 p-3 rounded-2xl border border-transparent group-hover:border-gray-200 transition-all">
            <input type="color" v-model="secondaryColor" class="w-10 h-10 rounded-xl border-none cursor-pointer bg-transparent" />
            <span class="text-sm text-gray-800 font-black font-mono tracking-tighter uppercase">{{ secondaryColor }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Toggle Button -->
    <Button 
      @click="toggle"
      class="!p-4 !rounded-3xl shadow-2xl hover:scale-110 active:scale-90 transition-all"
      variant="primary"
      title="Personalizar Identidade Visual"
    >
      <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
      </svg>
    </Button>
  </div>
</template>
