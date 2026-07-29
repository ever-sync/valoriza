<script setup>
import { ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import useUserSession from '@/composables/useAuthSession'
import { useTheme } from '@/composables/useTheme'
import SideBar from '@/components/base/SideBar.vue'
import TopBar from '@/components/base/TopBar.vue'
import TheFooter from '@/components/base/TheFooter.vue'
import ThemeSwitcher from '@/components/base/ThemeSwitcher.vue'
import GlobalLoading from '@/components/ui/GlobalLoading.vue'
import GlobalToast from '@/components/ui/GlobalToast.vue'
// Assumindo que UserProfileModal será criado em components/base/UserProfileModal.vue
import UserProfileModal from '@/components/base/UserProfileModal.vue'

const { user, logout } = useUserSession()
const { primaryColor } = useTheme()
const isSidebarOpen = ref(false)
const isSidebarCollapsed = ref(false)
const route = useRoute()

const isProfileModalOpen = ref(false)

const toggleSidebar = () => (isSidebarOpen.value = !isSidebarOpen.value)
const toggleSidebarCollapse = () => (isSidebarCollapsed.value = !isSidebarCollapsed.value)
const handleLogout = () => logout()
</script>

<template>
  <div class="flex h-screen bg-gray-50 font-sans overflow-hidden">
    
    <SideBar 
      v-if="route.meta.template === true"
      :is-open="isSidebarOpen" 
      :is-collapsed="isSidebarCollapsed"
      :theme-color="primaryColor" 
      @toggle="toggleSidebar" 
      @toggle-collapse="toggleSidebarCollapse"
      @logout="handleLogout"
    />

    <div class="flex-1 flex flex-col min-w-0 h-full">
      
      <TopBar 
        v-if="route.meta.template === true"
        :userName="user?.nome_completo || user?.name || 'Usuário'" 
        :registration="user?.id || '---'" 
        @toggle-sidebar="toggleSidebar" 
        @open-profile="isProfileModalOpen = true"
      />

      <main class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
        
        <div class="flex-1">
          <RouterView />
        </div>
        
        <TheFooter v-if="route.meta.template === true" />
      </main>

    </div>
    
    <!-- Floating Theme Configuration Widget -->
    <ThemeSwitcher />

    <!-- Global UI Components -->
    <GlobalLoading />
    <GlobalToast />

    <!-- Profile Modal -->
    <UserProfileModal 
      v-if="isProfileModalOpen" 
      @close="isProfileModalOpen = false" 
    />
  </div>
</template>