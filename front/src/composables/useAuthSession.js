import { ref } from 'vue'

import _post from '@/helpers/Connections/post'
import { BASE_API } from '@/constants/api'

const STORAGE_KEY = 'user_session'
const TOKEN_KEY = 'auth_token'
const user = ref(getUser())

function setUser(data, token) {
  user.value = data
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  }
}

function clearUser() {
  user.value = null
  localStorage.removeItem(STORAGE_KEY)
  localStorage.removeItem(TOKEN_KEY)
}

function getUser() {
  const raw = localStorage.getItem(STORAGE_KEY)
  if (!raw) return null
  try {
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : null
  } catch {
    localStorage.removeItem(STORAGE_KEY)
    return null
  }
}

async function logout() {
  try {
    // A rota é POST no backend; com GET o servidor devolvia 404 e o cookie de sessão
    // seguia válido pelas 8h restantes, mesmo com a UI parecendo deslogada.
    await _post({ url: `${BASE_API}/auth/logout`, showToast: false })
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
