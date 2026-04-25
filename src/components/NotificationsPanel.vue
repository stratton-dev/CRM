<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps<{
  notifications: Array<{
    id: string
    userId: string
    type: string
    message: string
    date: string
    read: boolean
  }>
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'markAsRead', id: string): void
  (e: 'clearAll'): void
}>()
</script>

<template>
  <div
    class="fixed top-20 right-4 w-96 max-w-[calc(100vw-1rem)] bg-white rounded-xl shadow-xl border border-slate-100 py-0 z-50 text-sm overflow-hidden animate-fade-in-up"
    @click.stop
  >
    <div class="bg-slate-50 px-5 py-4 border-b border-slate-100 font-bold text-slate-700 flex justify-between items-center">
      <div class="flex items-center gap-3">
        <span>Powiadomienia</span>
        <button
          v-if="notifications.length > 0"
          class="text-[10px] text-red-500 hover:text-red-700 uppercase tracking-wider font-bold bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition-colors"
          title="Wyczyść widok powiadomień (nie usuwa z historii)"
          @click="emit('clearAll')"
        >
          Wyczyść
        </button>
      </div>
      <button type="button" class="text-slate-400 hover:text-slate-600" aria-label="Zamknij panel powiadomień" @click="emit('close')">
        <AppIcon name="xmark" class="w-4 h-4" />
      </button>
    </div>
    <div class="max-h-80 overflow-y-auto">
      <div
        v-for="notif in notifications"
        :key="notif.id"
        class="px-5 py-4 hover:bg-slate-50 cursor-pointer border-b border-slate-50 transition group"
        :class="{ 'bg-blue-50': !notif.read }"
        @click="emit('markAsRead', notif.id)"
      >
        <p class="text-stratton-blue font-bold text-xs mb-1 group-hover:underline">
          <AppIcon name="info" class="w-4 h-4 mr-1" />
          {{ notif.type }}
        </p>
        <p class="text-slate-800 text-sm font-medium">{{ notif.message }}</p>
        <p class="text-slate-500 text-xs mt-1">{{ new Date(notif.date).toLocaleString() }}</p>
      </div>
      <div v-if="notifications.length === 0" class="p-8 text-center text-slate-400 text-sm">
        Brak nowych powiadomień.
      </div>
    </div>
  </div>
</template>
