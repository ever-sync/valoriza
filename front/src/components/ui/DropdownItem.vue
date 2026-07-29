<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'default' // 'default', 'danger', 'primary'
  },
  icon: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['click'])

const handleClick = (e) => {
  emit('click', e)
}
</script>

<template>
  <button
    @click="handleClick"
    class="flex items-center w-full px-4 py-3 text-sm transition-colors border-b border-border/10 last:border-0"
    :class="{
      'text-text-primary hover:bg-background hover:text-primary': variant === 'default',
      'text-danger hover:bg-danger/5': variant === 'danger',
      'text-primary hover:bg-primary/5': variant === 'primary'
    }"
  >
    <div v-if="$slots.icon" class="mr-3 text-current opacity-70">
      <slot name="icon"></slot>
    </div>
    <span class="font-semibold">
      <slot></slot>
    </span>
  </button>
</template>
