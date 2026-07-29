<script setup>
import { onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: ''
  },
  maxWidth: {
    type: String,
    default: 'w-[90vw] md:max-w-4xl'
  }
})

const emit = defineEmits(['close'])

const close = () => {
  emit('close')
}

// Close on escape key
const handleKeydown = (e) => {
  if (e.key === 'Escape' && props.isOpen) {
    close()
  }
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Transition name="modal-fade">
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto overflow-x-hidden flex items-center justify-center p-4">
      <!-- Backdrop with blur -->
      <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="close"></div>

      <!-- Modal Container -->
      <div 
        :class="['relative w-full bg-surface rounded-2xl md:rounded-4xl shadow-2xl border border-border flex flex-col transform transition-all', maxWidth]"
        style="max-height: 90vh; margin: auto;"
      >
        <!-- Header -->
        <div class="px-6 md:px-8 py-4 md:py-6 border-b border-border flex items-center justify-between bg-surface rounded-t-2xl md:rounded-t-4xl sticky top-0 z-10">
          <div>
            <h3 class="text-xl md:text-2xl font-bold text-text-primary tracking-tight">
              {{ title }}
            </h3>
            <div class="h-1 w-8 bg-primary rounded-full mt-2"></div>
          </div>
          
          <button 
            @click="close" 
            class="p-2 ml-auto bg-background text-text-secondary hover:text-text-primary hover:bg-border rounded-xl transition-all"
          >
            <svg class="h-5 w-5 md:h-6 md:w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="p-4 md:p-8 overflow-y-auto flex-1 custom-scrollbar text-text-primary">
          <slot></slot>
        </div>

        <!-- Footer (Optional) -->
        <div v-if="$slots.footer" class="px-8 py-4 bg-background rounded-b-4xl border-t border-border">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: all 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .relative {
  animation: modal-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-fade-leave-active .relative {
  animation: modal-in 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) reverse;
}

@keyframes modal-in {
  0% { transform: scale(0.9) translateY(20px); }
  100% { transform: scale(1) translateY(0); }
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
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
