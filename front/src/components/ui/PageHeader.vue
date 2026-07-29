<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  }
})
</script>

<template>
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-1">
    <div>
      <p class="text-[10px] font-black uppercase tracking-[.2em] text-text-tertiary mb-1">Gestão financeira</p>
      <h1 class="text-2xl md:text-3xl font-black text-text-primary tracking-tight">
        {{ title }}
      </h1>
      <p v-if="subtitle" class="text-sm font-medium text-text-tertiary mt-1">
        {{ subtitle }}
      </p>
    </div>
    <div class="flex items-center gap-3 self-end md:self-auto">
      <slot name="actions"></slot>
    </div>
  </div>
</template>
