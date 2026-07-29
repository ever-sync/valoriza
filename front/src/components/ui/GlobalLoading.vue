<script setup>
import { useLoading } from '@/composables/useLoading'

const { isLoading } = useLoading()
</script>

<template>
  <Transition name="fade">
    <div v-if="isLoading" class="fixed inset-0 z-100 flex items-center justify-center bg-gray-900/40 backdrop-blur-sm">
      <div class="relative flex flex-col items-center">
        <!-- Modern Spinner -->
        <div class="w-16 h-16 relative">
          <div class="absolute inset-0 border-4 border-primary/20 rounded-full"></div>
          <div class="absolute inset-0 border-4 border-primary rounded-full border-t-transparent animate-spin"></div>
        </div>
        
        <!-- Animated Dots -->
        <div class="mt-4 flex gap-1">
          <div class="w-1.5 h-1.5 bg-primary rounded-full animate-bounce [animation-delay:-0.3s]"></div>
          <div class="w-1.5 h-1.5 bg-primary rounded-full animate-bounce [animation-delay:-0.15s]"></div>
          <div class="w-1.5 h-1.5 bg-primary rounded-full animate-bounce"></div>
        </div>

        <span class="mt-4 text-xs font-bold text-white uppercase tracking-widest drop-shadow-md">Carregando</span>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
