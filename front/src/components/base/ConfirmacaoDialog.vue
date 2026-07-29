<script setup>
const props = defineProps({
  visivel: { type: Boolean, default: false },
  titulo: { type: String, default: 'Confirmação' },
  mensagem: { type: String, default: 'Tem certeza que deseja continuar?' },
  textoBotaoConfirmar: { type: String, default: 'Confirmar' },
  textoBotaoCancelar: { type: String, default: 'Cancelar' },
  variante: { type: String, default: 'perigo' }, // 'perigo' ou 'padrao'
})

const emit = defineEmits(['confirmar', 'cancelar'])

const estilosBotao = {
  perigo: 'bg-red-600 hover:bg-red-700 text-white shadow-sm shadow-red-100',
  padrao: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm shadow-indigo-100',
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="visivel" class="fixed inset-0 z-[9998] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="emit('cancelar')"></div>

        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="scale-95 opacity-0"
          enter-to-class="scale-100 opacity-100"
        >
          <div
            v-if="visivel"
            class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden"
          >
            <div class="p-6 text-center">
              <div
                class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
                :class="variante === 'perigo' ? 'bg-red-50' : 'bg-indigo-50'"
              >
                <svg
                  class="w-7 h-7"
                  :class="variante === 'perigo' ? 'text-red-500' : 'text-indigo-500'"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    v-if="variante === 'perigo'"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                  />
                  <path
                    v-else
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>

              <h3 class="text-lg font-bold text-gray-900 mb-1">{{ titulo }}</h3>
              <p class="text-sm text-gray-500 leading-relaxed">{{ mensagem }}</p>
            </div>

            <div class="px-6 pb-6 flex gap-3">
              <button
                @click="emit('cancelar')"
                class="flex-1 px-4 py-2.5 text-sm font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors"
              >
                {{ textoBotaoCancelar }}
              </button>
              <button
                @click="emit('confirmar')"
                class="flex-1 px-4 py-2.5 text-sm font-bold rounded-xl transition-colors"
                :class="estilosBotao[variante]"
              >
                {{ textoBotaoConfirmar }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
