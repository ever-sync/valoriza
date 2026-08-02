<script setup>
import { reactive, nextTick } from 'vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import useUserSession from '@/composables/useAuthSession'
import _post from '@/helpers/Connections/post'
import { BASE_API, BASE_HOME } from '@/constants/api'
const model = reactive({
  email: '',
  senha: '',
})

const state = reactive({
  loading: false,
  error: '',
  success: false,
})

const { setUser, clearUser } = useUserSession()

const handleLogin = () => {
  state.loading = true
  state.error = ''

  _post({
    url: `${BASE_API}/auth/login`,
    showLoading: false,
    body: {
      email: model.email,
      senha: model.senha,
    },
    callback: (status, data) => {
      state.loading = false
      if (status <= 205) {
        state.success = true
        setUser(data.data || data, data.token)
        nextTick(() => {
          setTimeout(() => {
            window.location.href = BASE_HOME
          }, 800)
        })
        return
      }
      clearUser()
      state.error = data.message || 'Falha na autenticação. Verifique suas credenciais.'
    },
  })
}
</script>

<template>
  <div class="min-h-screen w-full flex bg-white font-sans overflow-hidden">
    <!-- Left Side: Visual Branding (Conta Azul style: clean, professional, engaging) -->
    <div
      class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-[var(--primary)] to-blue-900 items-center justify-center overflow-hidden"
    >
      <!-- Subtle Background Pattern -->
      <div class="absolute inset-0 opacity-10">
        <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
              <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5" />
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#grid)" />
        </svg>
      </div>

      <!-- Glowing Orbs for modern feel -->
      <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
      <div
        class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-[var(--secondary)]/10 rounded-full blur-3xl"
      ></div>

      <!-- Content -->
      <div class="relative z-10 flex flex-col items-start px-12 xl:px-24 max-w-2xl text-left">
        <!-- Logo Text -->
        <div class="mb-12 text-white font-bold text-3xl tracking-tight flex items-center gap-3">
          <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-lg">
            <span class="text-[var(--primary)] text-2xl font-black leading-none">V</span>
          </div>
          Valoriza Credit
        </div>

        <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-6">
          Gestão financeira completa para o sucesso do seu negócio.
        </h1>
        <p class="text-white/80 text-lg xl:text-xl leading-relaxed mb-12 max-w-lg">
          Emita notas, controle o financeiro e integre sua contabilidade em um só lugar. Simples,
          rápido e seguro.
        </p>

        <!-- Trust indicators -->
        <div class="flex items-center gap-8 text-white/80 text-sm font-medium">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center">
              <svg
                class="w-3.5 h-3.5 text-green-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="3"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>
            Dados seguros
          </div>
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center">
              <svg
                class="w-3.5 h-3.5 text-green-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="3"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>
            100% em nuvem
          </div>
        </div>
      </div>
    </div>

    <!-- Right Side: Interaction Form (Clean, well-proportioned) -->
    <div
      class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-24 bg-white relative"
    >
      <div class="w-full max-w-sm xl:max-w-md">
        <!-- Mobile Logo -->
        <div class="lg:hidden flex items-center gap-3 mb-10">
          <div
            class="w-10 h-10 bg-(--primary) rounded-lg flex items-center justify-center shadow-md"
          >
            <span class="text-white text-xl font-black">V</span>
          </div>
          <span class="text-2xl font-bold text-gray-900 tracking-tight">Valoriza Credit</span>
        </div>

        <div class="text-left mb-8 space-y-2">
          <h2 class="text-3xl font-bold text-gray-900 tracking-tight">Acesse sua conta</h2>
          <p class="text-gray-500 text-sm font-medium">
            Bem-vindo(a) de volta! Por favor, insira seus dados.
          </p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6">
          <!-- Notification Area -->
          <Transition name="fade">
            <div
              v-if="state.error"
              class="p-4 bg-red-50 text-red-700 text-sm font-medium rounded-lg border border-red-100 flex items-start gap-3"
            >
              <svg
                class="w-5 h-5 shrink-0 mt-0.5 text-red-500"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clip-rule="evenodd"
                />
              </svg>
              <span>{{ state.error }}</span>
            </div>
          </Transition>

          <Transition name="fade">
            <div
              v-if="state.success"
              class="p-4 bg-green-50 text-green-700 text-sm font-medium rounded-lg border border-green-100 flex items-center gap-3"
            >
              <svg class="w-5 h-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"
                />
              </svg>
              Autenticado com sucesso! Entrando...
            </div>
          </Transition>

          <div class="space-y-5">
            <div>
              <Input
                label="E-mail"
                type="email"
                v-model="model.email"
                placeholder="seu@email.com.br"
                required
              />
            </div>

            <div class="space-y-3">
              <Input
                label="Senha"
                type="password"
                v-model="model.senha"
                placeholder="••••••••"
                required
              />
              <div class="flex justify-between items-center px-0.5 mt-2">
                <label class="flex items-center gap-2 cursor-pointer group">
                  <input
                    type="checkbox"
                    class="rounded border-gray-300 text-[var(--primary)] focus:ring-[var(--primary)] focus:ring-opacity-50 transition w-4 h-4 cursor-pointer"
                  />
                  <span
                    class="text-sm text-gray-500 font-medium group-hover:text-gray-700 transition-colors"
                    >Lembrar-me</span
                  >
                </label>
                <a
                  href="#"
                  class="text-sm font-semibold text-[var(--primary)] hover:text-blue-700 transition-colors"
                  >Esqueceu a senha?</a
                >
              </div>
            </div>
          </div>

          <div class="pt-2">
            <Button
              type="submit"
              variant="primary"
              class="w-full h-12 text-base font-semibold shadow-sm hover:shadow-md transition-shadow"
              :loading="state.loading"
            >
              Entrar
            </Button>
          </div>
        </form>

        <!-- Footer Info -->
        <div class="mt-10 pt-6 border-t border-gray-100 text-center">
          <p class="text-[13px] font-medium text-gray-400 flex items-center justify-center gap-2">
            <svg
              class="w-4 h-4 text-green-500"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
              />
            </svg>
            Ambiente Seguro e Criptografado
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}
</style>
