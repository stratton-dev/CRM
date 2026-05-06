<script setup lang="ts">
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'

const toastStore = useToastStore()
</script>

<template>
  <div class="fixed top-16 right-4 z-[9999] flex flex-col gap-2 pointer-events-none">
    <div
      v-for="toast in toastStore.toasts"
      :key="toast.id"
      class="pointer-events-auto min-w-[300px] max-w-md bg-white border-l-4 shadow-lg rounded p-4 animate-slide-in flex items-start"
      :class="{
        'border-green-500': toast.type === 'success',
        'border-red-500': toast.type === 'error',
        'border-blue-500': toast.type === 'info',
        'border-amber-500': toast.type === 'warning',
      }"
    >
      <div class="flex-1">
        <div class="flex items-start">
          <AppIcon
            :name="toast.type === 'success' ? 'check-circle' : toast.type === 'error' ? 'x-circle' : toast.type === 'info' ? 'info' : 'exclamation-triangle'"
            class="w-5 h-5 mr-3 mt-0.5 shrink-0 text-gray-500"
          />
          <p class="text-sm font-medium text-gray-800 flex-1">{{ toast.message }}</p>
          <button
            v-if="!toast.actions?.length"
            type="button"
            class="text-gray-400 hover:text-gray-600 ml-4 shrink-0"
            @click="toastStore.remove(toast.id)"
          >
            <AppIcon name="xmark" class="w-4 h-4" />
          </button>
        </div>
        <div v-if="toast.actions?.length" class="flex gap-2 mt-3 ml-8">
          <button
            v-for="action in toast.actions"
            :key="action.label"
            type="button"
            class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors"
            :class="action.label === 'Przerwij'
              ? 'bg-red-100 text-red-700 hover:bg-red-200'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
            @click="action.onClick(); toastStore.remove(toast.id)"
          >
            {{ action.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-slide-in {
  animation: slideIn 0.3s ease-out forwards;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>
