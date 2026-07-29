<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  mensagem: { type: String, default: '' },
  tipo: { type: String, default: 'info' }, // 'sucesso', 'erro', 'aviso', 'info'
  visivel: { type: Boolean, default: false },
  duracao: { type: Number, default: 4000 },
})

const emit = defineEmits(['fechar'])
const mostrar = ref(false)

watch(
  () => props.visivel,
  (novoValor) => {
    if (novoValor) {
      mostrar.value = true
      if (props.duracao > 0) {
        setTimeout(() => {
          fechar()
        }, props.duracao)
      }
    }
  },
)

function fechar() {
  mostrar.value = false
  emit('fechar')
}

const icones = {
  sucesso:
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
  erro: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />',
  aviso:
    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />',
  info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
}

const estilos = {
  sucesso: 'bg-emerald-50 border-emerald-200 text-emerald-800',
  erro: 'bg-red-50 border-red-200 text-red-800',
  aviso: 'bg-amber-50 border-amber-200 text-amber-800',
  info: 'bg-blue-50 border-blue-200 text-blue-800',
}

const iconeCor = {
  sucesso: 'text-emerald-500',
  erro: 'text-red-500',
  aviso: 'text-amber-500',
  info: 'text-blue-500',
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="translate-y-4 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-2 opacity-0"
    >
      <div v-if="mostrar" class="fixed top-6 right-6 z-[9999] max-w-sm w-full">
        <div
          class="flex items-start gap-3 px-5 py-4 rounded-xl border shadow-lg backdrop-blur-sm"
          :class="estilos[tipo]"
        >
          <svg
            class="w-5 h-5 shrink-0 mt-0.5"
            :class="iconeCor[tipo]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            v-html="icones[tipo]"
          ></svg>
          <p class="text-sm font-medium flex-1">{{ mensagem }}</p>
          <button @click="fechar" class="shrink-0 opacity-50 hover:opacity-100 transition-opacity">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
