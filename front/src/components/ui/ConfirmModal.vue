<script setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: 'Confirmar ação'
  },
  message: {
    type: String,
    default: 'Tem certeza que deseja continuar?'
  },
  detail: {
    type: String,
    default: ''
  },
  confirmLabel: {
    type: String,
    default: 'Confirmar'
  },
  cancelLabel: {
    type: String,
    default: 'Cancelar'
  },
  type: {
    type: String,
    default: 'danger', // 'danger' | 'warning' | 'info'
    validator: (v) => ['danger', 'warning', 'info'].includes(v)
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['confirm', 'cancel'])

const iconConfig = computed(() => {
  const configs = {
    danger: {
      bg: 'bg-red-50',
      ring: 'ring-red-100',
      icon: 'text-red-500',
      confirmVariant: 'danger',
      path: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'
    },
    warning: {
      bg: 'bg-amber-50',
      ring: 'ring-amber-100',
      icon: 'text-amber-500',
      confirmVariant: 'secondary',
      path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
    },
    info: {
      bg: 'bg-blue-50',
      ring: 'ring-blue-100',
      icon: 'text-blue-500',
      confirmVariant: 'primary',
      path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    }
  }
  return configs[props.type]
})
</script>

<template>
  <Transition name="confirm-fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4"
      @mousedown.self="emit('cancel')"
    >
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" @click="emit('cancel')" />

      <!-- Modal -->
      <Transition name="confirm-scale">
        <div
          v-if="isOpen"
          class="relative z-10 w-full max-w-sm bg-surface rounded-3xl shadow-2xl border border-border overflow-hidden"
        >
          <!-- Top accent bar -->
          <div
            class="h-1 w-full"
            :class="{
              'bg-red-500': type === 'danger',
              'bg-amber-500': type === 'warning',
              'bg-blue-500': type === 'info'
            }"
          />

          <div class="p-6">
            <!-- Icon + Title -->
            <div class="flex items-start gap-4 mb-4">
              <div
                class="flex-shrink-0 w-11 h-11 rounded-2xl flex items-center justify-center ring-8"
                :class="[iconConfig.bg, iconConfig.ring]"
              >
                <svg
                  class="w-5 h-5"
                  :class="iconConfig.icon"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconConfig.path" />
                </svg>
              </div>

              <div class="flex-1 min-w-0">
                <h3 class="text-base font-bold text-text-primary leading-tight">{{ title }}</h3>
                <p class="text-sm text-text-secondary mt-1 leading-relaxed">{{ message }}</p>
                <p v-if="detail" class="text-xs font-semibold text-text-primary mt-2 bg-background px-3 py-2 rounded-xl border border-border truncate">
                  {{ detail }}
                </p>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 mt-6">
              <Button
                variant="ghost"
                class="flex-1"
                :disabled="loading"
                @click="emit('cancel')"
              >
                {{ cancelLabel }}
              </Button>
              <button
                class="flex-1 px-4 py-2 rounded-xl text-sm font-bold text-white transition-all disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                :class="{
                  'bg-red-500 hover:bg-red-600 active:scale-95': type === 'danger',
                  'bg-amber-500 hover:bg-amber-600 active:scale-95': type === 'warning',
                  'bg-primary hover:brightness-110 active:scale-95': type === 'info',
                }"
                :disabled="loading"
                @click="emit('confirm')"
              >
                <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ confirmLabel }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<style scoped>
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}

.confirm-scale-enter-active {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s ease;
}
.confirm-scale-leave-active {
  transition: transform 0.15s ease, opacity 0.15s ease;
}
.confirm-scale-enter-from {
  opacity: 0;
  transform: scale(0.88) translateY(8px);
}
.confirm-scale-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>
