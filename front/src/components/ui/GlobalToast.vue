<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useToast } from '@/composables/useToast'

const { toasts, remove, add } = useToast()

onMounted(() => {
  window.addEventListener('toast', handleToastEvent)
})

onUnmounted(() => {
  window.removeEventListener('toast', handleToastEvent)
})

const handleToastEvent = (event) => {
  add(event.detail)
}

const getIcon = (type) => {
  switch (type) {
    case 'success':
      return 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
    case 'error':
      return 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'
    case 'warning':
      return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
    default:
      return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  }
}
</script>

<template>
  <div class="fixed top-6 right-6 z-101 flex flex-col gap-3 pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        role="alert"
        aria-live="assertive"
        class="pointer-events-auto flex items-center gap-3 px-5 py-4 rounded-2xl shadow-xl border min-w-[320px] max-w-md backdrop-blur-md transition-all duration-300"
        :class="{
          'bg-emerald-50/90 border-emerald-200 text-emerald-800': toast.type === 'success',
          'bg-rose-50/90 border-rose-200 text-rose-800': toast.type === 'error',
          'bg-amber-50/90 border-amber-200 text-amber-800': toast.type === 'warning',
          'bg-blue-50/90 border-blue-200 text-blue-800': toast.type === 'info',
          'opacity-0 translate-x-12': !toast.visible
        }"
      >
        <!-- Icon -->
        <div 
          class="shrink-0 w-8 h-8 rounded-xl flex items-center justify-center"
          :class="{
            'bg-emerald-500/10': toast.type === 'success',
            'bg-rose-500/10': toast.type === 'error',
            'bg-amber-500/10': toast.type === 'warning',
            'bg-blue-500/10': toast.type === 'info',
          }"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIcon(toast.type)" />
          </svg>
        </div>

        <!-- Content -->
        <div class="flex-1 text-sm font-semibold pr-2">
          {{ toast.message }}
        </div>

        <!-- Close Button -->
        <button 
          @click="remove(toast.id)"
          class="shrink-0 w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 active:scale-90 transition-all opacity-40 hover:opacity-100"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(50px) scale(0.9);
}

.toast-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

.toast-move {
  transition: transform 0.4s ease;
}
</style>
