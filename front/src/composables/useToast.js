import { ref } from 'vue'

const toasts = ref([])
let nextId = 0

export function useToast() {
  const add = ({ message, type = 'info', duration = 4000 }) => {
    const id = ++nextId
    toasts.value.push({ id, message, type, visible: true })

    setTimeout(() => {
      remove(id)
    }, duration)

    return id
  }

  const remove = (id) => {
    const idx = toasts.value.findIndex(t => t.id === id)
    if (idx !== -1) {
      toasts.value[idx].visible = false
      setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id)
      }, 350)
    }
  }

  /**
   * Automatically shows a toast based on HTTP status code.
   * 200–205 → success (green)
   * anything else → error (red)
   */
  const fromResponse = (status, message) => {
    const isSuccess = status >= 200 && status <= 205
    add({
      message: message || (isSuccess ? 'Operação realizada com sucesso!' : 'Ocorreu um erro na operação.'),
      type: isSuccess ? 'success' : 'error'
    })
  }

  const success = (message) => add({ message, type: 'success' })
  const error = (message) => add({ message, type: 'error' })
  const warning = (message) => add({ message, type: 'warning' })
  const info = (message) => add({ message, type: 'info' })

  return { toasts, add, remove, fromResponse, success, error, warning, info }
}
