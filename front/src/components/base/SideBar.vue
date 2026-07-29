<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { icons, menuItems } from '@/constants/navigation'
import Button from '@/components/ui/Button.vue'
import useUserSession from '@/composables/useAuthSession'

const props = defineProps(['isOpen', 'themeColor', 'isCollapsed'])
const emit = defineEmits(['toggle', 'toggle-collapse', 'logout'])
const route = useRoute()
const { user } = useUserSession()

// Filter menu items based on user role
const filteredMenuItems = computed(() => {
  const userRole = user.value?.perfil_acesso || 'administrador'
  return menuItems.filter(item => {
    if (!item.roles) return true // Se não houver roles definido, permite para todos
    return item.roles.includes(userRole)
  })
})

// State for collapsible menus
const expandedItems = ref({})

// Check if an item or its subitems are active
const isItemActive = (item) => {
  if (item.routeName && route.name === item.routeName) return true
  if (item.subItems) {
    return item.subItems.some(sub => route.name === sub.routeName)
  }
  return false
}

const isSubItemActive = (routeName) => {
  return route.name === routeName
}

// Toggle group
const toggleGroup = (itemName) => {
  if (props.isCollapsed) {
    emit('toggle-collapse')
  }
  expandedItems.value[itemName] = !expandedItems.value[itemName]
}

// Automatically expand group if a child is active
watch(() => route.name, () => {
  filteredMenuItems.value.forEach(item => {
    if (item.subItems && item.subItems.some(sub => route.name === sub.routeName)) {
      expandedItems.value[item.name] = true
    }
  })
}, { immediate: true })
</script>

<template>
  <div class="h-full relative z-30">
    <div
      v-if="isOpen"
      class="fixed inset-0 bg-black/20 backdrop-blur-sm z-20 md:hidden transition-opacity"
      @click="emit('toggle')"
    ></div>

    <aside
      class="sidebar-panel fixed h-full inset-y-0 left-0 bg-[#102d63] transform transition-all duration-500 ease-in-out md:relative md:translate-x-0 flex flex-col shadow-2xl z-30"
      :class="[
        isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        isCollapsed ? 'w-[80px]' : 'w-[280px]'
      ]"
    >
      <!-- Header / Brand -->
      <div 
        class="h-[72px] flex items-center border-b border-white/10 relative transition-all duration-300"
        :class="isCollapsed ? 'justify-center px-0' : 'px-6 justify-between'"
      >
        <div class="flex items-center gap-2 overflow-hidden transition-all duration-300" :class="isCollapsed ? 'mx-auto' : ''">
          <div class="w-9 h-9 rounded-xl bg-white/12 border border-white/15 flex items-center justify-center text-white font-black">V</div>
          <div v-if="!isCollapsed" class="flex flex-col leading-none">
            <span class="font-extrabold text-white tracking-tight text-xl">Valoriza</span>
            <span class="font-medium text-white/50 text-[10px] uppercase tracking-[.24em] mt-1">Finance</span>
          </div>
        </div>
        
        <!-- Toggle Collapse Button (Desktop Only) -->
        <button 
          @click="emit('toggle-collapse')"
          class="hidden md:flex absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-white rounded-full items-center justify-center text-primary shadow-md hover:scale-110 transition-transform cursor-pointer border border-primary/10 z-50"
        >
          <svg class="w-4 h-4 transition-transform duration-300" :class="isCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto py-6 space-y-1.5 sidebar-scrollbar" :class="isCollapsed ? 'px-2' : 'px-4'">
        <div v-for="item in filteredMenuItems" :key="item.name">
          <!-- Link normal (sem submenu) -->
          <router-link
            v-if="!item.subItems"
            :to="{ name: item.routeName }"
            class="flex items-center gap-3.5 py-3 text-sm font-bold rounded-2xl transition-all duration-300 relative group overflow-hidden"
            :class="[
              isCollapsed ? 'justify-center px-0 mx-0 w-full' : 'px-4 mx-2',
              isItemActive(item) 
                ? 'bg-white/10 text-white shadow-lg shadow-black/10 ring-1 ring-white/20' 
                : 'text-white/60 hover:bg-white/5 hover:text-white/90'
            ]"
            :title="isCollapsed ? item.name : ''"
          >
            <!-- Hover Glow Effect -->
            <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/5 to-white/0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
            
            <svg
              class="w-5 h-5 shrink-0 transition-all duration-300"
              :class="isItemActive(item) ? 'text-white scale-110' : 'text-white/40 group-hover:text-white/70 group-hover:scale-110'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              stroke-width="2.5"
              v-html="icons[item.icon]"
            ></svg>
            <span v-if="!isCollapsed" class="flex-1 tracking-tight whitespace-nowrap">{{ item.name }}</span>
          </router-link>

          <!-- Pai com submenus (Dropdown) -->
          <div v-else class="space-y-1">
            <button
              @click="toggleGroup(item.name)"
              class="w-full flex items-center gap-3.5 py-3 text-sm font-bold rounded-2xl transition-all duration-300 group relative overflow-hidden"
              :class="[
                isCollapsed ? 'justify-center px-0 mx-0 w-full' : 'px-4 mx-2',
                isItemActive(item) 
                  ? 'text-white bg-white/10 ring-1 ring-white/10' 
                  : 'text-white/60 hover:bg-white/5 hover:text-white/90'
              ]"
              :title="isCollapsed ? item.name : ''"
            >
              <svg
                class="w-5 h-5 shrink-0 transition-all duration-300"
                :class="isItemActive(item) ? 'text-white scale-110' : 'text-white/40 group-hover:text-white/70 group-hover:scale-110'"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                stroke-width="2.5"
                v-html="icons[item.icon]"
              ></svg>
              <span v-if="!isCollapsed" class="flex-1 text-left tracking-tight whitespace-nowrap">{{ item.name }}</span>
              
              <!-- Chevron -->
              <svg 
                v-if="!isCollapsed"
                class="w-4 h-4 shrink-0 transition-transform duration-500 text-white/30" 
                :class="{ 'rotate-180 text-white/60': expandedItems[item.name] }"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Submenu Items -->
            <div v-if="!isCollapsed" v-show="expandedItems[item.name]" class="pl-4 space-y-1 overflow-hidden transition-all duration-300">
              <router-link
                v-for="sub in item.subItems"
                :key="sub.name"
                :to="{ name: sub.routeName }"
                class="flex items-center gap-3 px-4 py-2.5 pl-10 text-sm font-medium rounded-xl transition-all duration-200 relative group truncate"
                :class="
                  isSubItemActive(sub.routeName)
                    ? 'text-white bg-white/12 font-bold'
                    : 'text-white/50 hover:text-white/80 hover:bg-white/6'
                "
              >
                <!-- Bullet indicator for subitems -->
                <div 
                  class="absolute left-6 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full transition-colors"
                  :class="isSubItemActive(sub.routeName) ? 'bg-white' : 'bg-white/25 group-hover:bg-white/50'"
                ></div>
                <span class="truncate">{{ sub.name }}</span>
              </router-link>
            </div>
          </div>
        </div>
      </nav>

      <!-- Footer / Logout -->
      <div class="p-4 border-t border-white/10 overflow-hidden flex" :class="isCollapsed ? 'justify-center' : ''">
        <Button
          variant="ghost"
          class="hover:bg-white/10 transition-all rounded-xl py-3"
          :class="isCollapsed ? 'w-12 h-12 px-0 justify-center' : 'w-full justify-start! gap-3 px-4'"
          :title="isCollapsed ? 'Sair do Sistema' : ''"
          @click="emit('logout')"
        >
          <svg
            class="w-5 h-5 shrink-0 text-rose-300"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            stroke-width="2"
            v-html="icons.logout"
          ></svg>
          <span v-if="!isCollapsed" class="font-bold text-sm tracking-wide text-rose-300 whitespace-nowrap">Sair do Sistema</span>
        </Button>
      </div>
    </aside>
  </div>
</template>
