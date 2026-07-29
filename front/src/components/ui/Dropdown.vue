<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  label: {
    type: String,
    default: ''
  },
  align: {
    type: String,
    default: 'right' // 'left' or 'right'
  }
})

const isOpen = ref(false)
const containerRef = ref(null)
const dropdownRef = ref(null)
const dropdownStyle = ref({})

const updatePosition = () => {
  if (!containerRef.value || !isOpen.value) return
  
  const rect = containerRef.value.getBoundingClientRect()
  const scrollY = window.scrollY
  const scrollX = window.scrollX
  
  // A simple heuristic: if it's too close to the bottom of the viewport, drop UP.
  const dropUp = (window.innerHeight - rect.bottom) < 150

  const top = dropUp 
    ? (rect.top + scrollY - 8) // We will translate Y -100% in CSS
    : (rect.bottom + scrollY + 4)

  const width = 224 // w-56 is 14rem = 224px

  let left = 0
  if (props.align === 'right') {
    left = rect.right + scrollX - width
  } else {
    left = rect.left + scrollX
  }

  dropdownStyle.value = {
    position: 'absolute',
    top: `${top}px`,
    left: `${left}px`,
    width: `${width}px`,
    transform: dropUp ? 'translateY(-100%)' : 'none',
    zIndex: 9999
  }
}

const toggle = () => {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    // Delay position update to next tick
    setTimeout(updatePosition, 0)
  }
}

const close = () => {
  isOpen.value = false
}

const handleClickOutside = (event) => {
  // If clicked inside the trigger container
  if (containerRef.value && containerRef.value.contains(event.target)) return
  // If clicked inside the teleported dropdown menu
  if (dropdownRef.value && dropdownRef.value.contains(event.target)) return
  
  close()
}

const handleScrollOrResize = () => {
  if (isOpen.value) {
    updatePosition()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.addEventListener('scroll', handleScrollOrResize, true)
  window.addEventListener('resize', handleScrollOrResize)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  window.removeEventListener('scroll', handleScrollOrResize, true)
  window.removeEventListener('resize', handleScrollOrResize)
})

defineExpose({ close })
</script>

<template>
  <div class="relative inline-block text-left" ref="containerRef">
    <div>
      <slot name="trigger" :toggle="toggle" :isOpen="isOpen">
        <button
          type="button"
          @click="toggle"
          class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
        >
          {{ label }}
          <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </slot>
    </div>

    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="transform opacity-0 scale-95"
        enter-to-class="transform opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="transform opacity-100 scale-100"
        leave-to-class="transform opacity-0 scale-95"
      >
        <div
          v-if="isOpen"
          ref="dropdownRef"
          class="rounded-xl shadow-2xl bg-surface ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden border border-border"
          :style="dropdownStyle"
        >
          <div class="py-1" @click="close">
            <slot :close="close"></slot>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
