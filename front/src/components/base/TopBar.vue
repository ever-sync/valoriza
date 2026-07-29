<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { icons, menuItems } from '@/constants/navigation'

defineProps(['userName', 'registration'])
const emit = defineEmits(['toggleSidebar', 'open-profile'])
const route = useRoute()
const router = useRouter()
const searchTerm = ref('')

const pageTitle = computed(() => {
  const labels = { home: 'Visão geral', 'fluxo-caixa': 'Fluxo de caixa', relatorios: 'Relatórios', contratos: 'Contratos', receitas: 'Receitas', despesas: 'Despesas', usuarios: 'Usuários', configuracoes: 'Configurações', 'pessoas-fisicas': 'Pessoas físicas', 'pessoas-juridicas': 'Pessoas jurídicas', bancos: 'Contas bancárias' }
  return labels[route.name] || 'Visão geral'
})

const searchOptions = computed(() => menuItems.flatMap((item) => [
  ...(item.routeName ? [{ name: item.name, routeName: item.routeName }] : []),
  ...(item.subItems || []).map((sub) => ({ name: `${item.name} · ${sub.name}`, routeName: sub.routeName }))
]))

const searchResults = computed(() => {
  const term = searchTerm.value.trim().toLocaleLowerCase('pt-BR')
  if (!term) return []
  return searchOptions.value.filter((item) => item.name.toLocaleLowerCase('pt-BR').includes(term)).slice(0, 6)
})

const goToSearchResult = (result) => {
  router.push({ name: result.routeName })
  searchTerm.value = ''
}

const handleSearchKeydown = (event) => {
  if (event.key === 'Enter' && searchResults.value[0]) goToSearchResult(searchResults.value[0])
  if (event.key === 'Escape') searchTerm.value = ''
}
</script>

<template>
  <header class="bg-surface/90 backdrop-blur-md min-h-[76px] border-b border-border flex items-center justify-between px-4 md:px-8 z-10 sticky top-0">
    <div class="flex items-center gap-4">
      <button
        @click="emit('toggleSidebar')"
        class="md:hidden text-text-tertiary hover:text-primary transition-colors focus:outline-none"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" v-html="icons.menu"></svg>
      </button>
      <div class="hidden md:block">
        <p class="text-[10px] uppercase tracking-[.18em] font-bold text-text-tertiary">Painel financeiro</p>
        <h1 class="text-lg font-extrabold text-text-primary tracking-tight">{{ pageTitle }}</h1>
      </div>
    </div>

    <div class="flex items-center justify-end w-full gap-4 md:gap-6">
      <div class="hidden md:flex relative items-center bg-background border border-border rounded-full px-4 py-1.5 w-64 lg:w-80 focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-all">
        <svg class="w-4 h-4 text-text-tertiary font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" v-html="icons.search"></svg>
        <input v-model="searchTerm" @keydown="handleSearchKeydown" type="search" aria-label="Buscar na plataforma" placeholder="Buscar na plataforma" class="bg-transparent outline-none ring-0 w-full text-sm ml-2 text-text-primary placeholder-text-tertiary" />
        <div v-if="searchResults.length" class="absolute top-full left-0 right-0 mt-2 rounded-2xl border border-border bg-surface shadow-xl overflow-hidden z-50">
          <button v-for="result in searchResults" :key="result.routeName" type="button" class="w-full text-left px-4 py-3 text-xs font-bold text-text-secondary hover:bg-primary/5 hover:text-primary transition-colors" @mousedown.prevent="goToSearchResult(result)">
            {{ result.name }}
          </button>
        </div>
      </div>

      <div class="flex items-center gap-5">
        <!-- Notifications -->
        <button aria-label="Abrir notificações" class="relative text-text-tertiary hover:text-primary transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" v-html="icons.bell"></svg>
          <span class="absolute -top-1 -right-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white ring-2 ring-surface">28</span>
        </button>

        <!-- Credits -->
        <div class="hidden md:flex items-center gap-2 text-sm font-bold text-text-secondary bg-background border border-border rounded-lg px-3 py-1">
          <div class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[11px]">$</div>
          <span>Central financeira</span>
        </div>

        <!-- Help -->
        <button aria-label="Abrir ajuda" class="text-text-tertiary hover:text-primary transition-colors hidden sm:block">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1010 10A10 10 0 0012 2zm1 14h-2v-2h2zm.93-5.22l-.73.74c-.38.38-.6.88-.6 1.48h-2v-.5c0-.82.34-1.57.88-2.12l1-1c.25-.26.42-.61.42-1a1.5 1.5 0 00-3 0h-2a3.5 3.5 0 017 0 2.45 2.45 0 01-.97 1.4z"/></svg>
        </button>
      </div>

      <!-- Avatar & Username -->
      <div class="h-8 w-px bg-border hidden md:block mx-1"></div>
      
      <div 
        class="flex items-center gap-3 cursor-pointer hover:bg-background px-2 py-1 -mr-2 rounded-xl transition-colors shrink-0"
        @click="emit('open-profile')"
        title="Meu Perfil"
      >
        <div class="hidden md:flex flex-col items-end">
          <span class="text-sm font-bold text-text-primary leading-tight">{{ userName || 'Usuário' }}</span>
          <span class="text-[10px] font-semibold text-text-tertiary uppercase tracking-wider">Editar Perfil</span>
        </div>
        <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-white font-black text-xs shadow-sm ring-2 ring-primary/20">
          {{ userName?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U' }}
        </div>
      </div>
    </div>
  </header>
</template>
