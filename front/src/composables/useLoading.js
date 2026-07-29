import { ref } from 'vue'

const isLoading = ref(false)
const loadingCount = ref(0)

export function useLoading() {
  const start = () => {
    loadingCount.value++
    isLoading.value = true
  }

  const stop = () => {
    loadingCount.value = Math.max(0, loadingCount.value - 1)
    if (loadingCount.value === 0) {
      isLoading.value = false
    }
  }

  return { isLoading, start, stop }
}
