import { ref } from 'vue'

import _get from '@/helpers/Connections/get'
import { BASE_API } from '@/constants/api'

const STORAGE_KEY = 'user_session'
const user = ref(getUser())

function setUser(data) {
  user.value = data
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
}

function clearUser() {
  user.value = null
  localStorage.removeItem(STORAGE_KEY)
}

function getUser() {
  const raw = localStorage.getItem(STORAGE_KEY)
  return raw ? JSON.parse(raw) : null
}

async function logout() {
  try {
    await _get({ url: `${BASE_API}/auth/logout` })
  } catch (error) {
    console.error('Erro ao deslogar no servidor:', error)
  } finally {
    clearUser()
    window.location.href = '/login'
  }
}

export default function useUserSession() {
  return {
    user,
    setUser,
    clearUser,
    logout
  }
}
