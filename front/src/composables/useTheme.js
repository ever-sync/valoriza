import { ref, watch, onMounted } from "vue";

export function useTheme() {
  const DEFAULT_PRIMARY = '#1f2760'
  const DEFAULT_SECONDARY = '#476e45'

  const primaryColor = ref(localStorage.getItem('theme-primary') || DEFAULT_PRIMARY)
  const secondaryColor = ref(localStorage.getItem('theme-secondary') || DEFAULT_SECONDARY)

  const applyColors = () => {
    document.documentElement.style.setProperty('--primary', primaryColor.value)
    document.documentElement.style.setProperty('--secondary', secondaryColor.value)
  }

  const resetTheme = () => {
    primaryColor.value = DEFAULT_PRIMARY
    secondaryColor.value = DEFAULT_SECONDARY
  }

  watch([primaryColor, secondaryColor], () => {
    localStorage.setItem('theme-primary', primaryColor.value)
    localStorage.setItem('theme-secondary', secondaryColor.value)
    applyColors()
  })

  onMounted(() => {
    applyColors()
  })

  return { primaryColor, secondaryColor, resetTheme }
}