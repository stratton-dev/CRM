<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  block?: boolean
}>()

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'secondary':
      return 'bg-surface border border-border text-slate-600 hover:bg-surface-subtle'
    case 'outline':
      return 'bg-transparent border border-primary text-primary hover:bg-primary-light/10'
    case 'ghost':
      return 'bg-transparent text-slate-600 hover:bg-surface-subtle border-transparent'
    case 'danger':
      return 'bg-red-500 text-white hover:bg-red-600 border-red-500'
    case 'primary':
    default:
      return 'bg-primary text-slate-900 border-primary hover:bg-primary-hover shadow-md hover:shadow-lg'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'px-3 py-1.5 text-xs'
    case 'lg':
      return 'px-8 py-4 text-base'
    case 'md':
    default:
      return 'px-6 py-3 text-sm'
  }
})
</script>

<template>
  <button 
    class="rounded-btn font-bold transition-all duration-300 active:scale-95 flex items-center justify-center gap-2 border disabled:opacity-50 disabled:cursor-not-allowed"
    :class="[
      variantClasses, 
      sizeClasses,
      { 'w-full': block }
    ]"
    v-bind="$attrs"
  >
    <slot />
  </button>
</template>
