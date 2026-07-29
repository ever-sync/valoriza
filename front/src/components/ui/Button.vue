<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (val) => ['primary', 'secondary', 'danger', 'ghost', 'outline'].includes(val),
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  size: {
    type: String,
    default: 'md',
    validator: (val) => ['sm', 'md', 'lg'].includes(val),
  },
})

const btnClass = computed(() => {
  const base =
    'inline-flex items-center justify-center font-semibold rounded-xl focus:outline-none transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed transform active:scale-[0.98]'

  const sizes = {
    sm: 'px-4 py-2 text-xs h-8',
    md: 'px-5 py-2.5 text-sm h-10',
    lg: 'px-6 py-3.5 text-base h-12',
  }

  const variants = {
    primary:
      'bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)] shadow-sm hover:shadow-md focus:ring-4 focus:ring-[var(--color-primary)]/20',
    secondary:
      'bg-[var(--color-secondary)] text-[var(--color-primary)] hover:brightness-95 shadow-sm hover:shadow-md focus:ring-4 focus:ring-[var(--color-primary)]/10',
    neutral:
      'bg-white border text-[var(--color-text-primary)] border-[var(--color-border)] hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-sm',
    danger:
      'bg-[var(--color-danger)] text-white hover:bg-[var(--color-danger)]/90 shadow-sm hover:shadow-md focus:ring-4 focus:ring-[var(--color-danger)]/20',
    ghost:
      'bg-transparent text-[var(--color-text-secondary)] hover:bg-gray-100 hover:text-[var(--color-text-primary)] active:scale-100',
    outline:
      'bg-transparent border-2 border-[var(--color-primary)] text-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 focus:ring-4 focus:ring-[var(--color-primary)]/20 shadow-sm',
  }

  const selectedVariant = variants[props.variant] || variants.primary

  return `${base} ${sizes[props.size]} ${selectedVariant}`
})
</script>

<template>
  <button :type="type" :class="btnClass" :disabled="disabled || loading">
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>
    <slot></slot>
  </button>
</template>
