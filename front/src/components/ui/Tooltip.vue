<template>
  <div class="relative inline-flex items-center group" @mouseenter="mostrar" @mouseleave="esconder">
    <!-- Slot para o elemento que ativa o tooltip -->
    <slot />

    <!-- O Tooltip -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div
        v-if="visivel"
        class="absolute z-50 px-2 py-1 text-[10px] font-medium text-white bg-gray-900 rounded shadow-lg pointer-events-none whitespace-nowrap"
        :class="posicaoClasses"
      >
        {{ texto }}
        <div class="absolute w-2 h-2 bg-gray-900 transform rotate-45" :class="setaClasses"></div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  texto: {
    type: String,
    required: true
  },
  posicao: {
    type: String,
    default: 'top', // top, bottom, left, right
    validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value)
  }
})

const visivel = ref(false)
let timeout

const mostrar = () => {
  clearTimeout(timeout)
  visivel.value = true
}

const esconder = () => {
  timeout = setTimeout(() => {
    visivel.value = false
  }, 100)
}

const posicaoClasses = computed(() => {
  switch (props.posicao) {
    case 'top':
      return 'bottom-full left-1/2 -translate-x-1/2 mb-2'
    case 'bottom':
      return 'top-full left-1/2 -translate-x-1/2 mt-2'
    case 'left':
      return 'right-full top-1/2 -translate-y-1/2 mr-2'
    case 'right':
      return 'left-full top-1/2 -translate-y-1/2 ml-2'
    default:
      return 'bottom-full left-1/2 -translate-x-1/2 mb-2'
  }
})

const setaClasses = computed(() => {
  switch (props.posicao) {
    case 'top':
      return 'bottom-[-4px] left-1/2 -translate-x-1/2'
    case 'bottom':
      return 'top-[-4px] left-1/2 -translate-x-1/2'
    case 'left':
      return 'right-[-4px] top-1/2 -translate-y-1/2'
    case 'right':
      return 'left-[-4px] top-1/2 -translate-y-1/2'
    default:
      return 'bottom-[-4px] left-1/2 -translate-x-1/2'
  }
})
</script>
