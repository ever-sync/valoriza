<template>
  <button
    :type="type"
    class="cursor-pointer"
    :class="`btn btn-${variant} ${styles[define] || styles.normal}`"
  >
    <slot />
  </button>
</template>
<script setup>
const props = defineProps({
  type: {
    type: String,
    default: 'button',
  },
  variant: {
    type: String,
    default: 'primary',
  },
  define: {
    type: String,
    default: 'normal',
  },
})

const styles = {
  normal: 'h-12 text-base px-4 md:h-10 md:text-sm',
  small: 'h-8 text-sm px-3 md:h-7 md:text-xs',
  medium: 'h-11 text-base px-4 md:h-11 md:text-sm',
  large: 'h-14 text-lg px-6 md:h-12 md:text-base',
}
</script>

<style scoped>
.btn {
  font-family:
    'Inter',
    system-ui,
    -apple-system,
    sans-serif;
}
.btn-primary {
  background-color: var(--primary-color);
  color: white;
}
</style>
