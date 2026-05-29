<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'

withDefaults(defineProps<{
  icon: string
  title: string
  embedded?: boolean
}>(), {
  embedded: false,
})

const router = useRouter()

const goBack = () => {
  // Try history first, fall back to dashboard if there's nothing to go back to.
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/app/dashboard')
  }
}
</script>

<template>
  <div
    class="bg-slate-50 border-b border-slate-200 p-2 flex flex-wrap items-center gap-y-2 shadow-sm flex-shrink-0"
    :class="embedded ? 'rounded-t-card' : ''"
  >
    <div class="flex items-center gap-2 ml-2">
      <button
        v-if="!embedded"
        type="button"
        title="Wróć"
        class="w-9 h-9 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white flex items-center justify-center shadow-sm transition-colors group"
        @click="goBack"
      >
        <AppIcon name="arrow-left" class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" />
      </button>
      <AppIcon :name="icon" class="w-5 h-5 text-primary ml-1" />
      <h3 class="font-black text-slate-800 text-xl tracking-tight">{{ title }}</h3>
    </div>
    <div class="flex-1 flex flex-wrap items-center justify-end px-2 md:px-4 gap-2 md:gap-4">
      <slot name="actions" />
    </div>
  </div>
</template>
