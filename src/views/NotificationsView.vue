<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useNotificationStore } from '@/stores/notification'
import { useSessionStore } from '@/stores/session'
import AppIcon from '@/components/AppIcon.vue'
import { getNotificationTone } from '@/utils/uiColors'

const notifStore = useNotificationStore()
const session = useSessionStore()

const { notifications } = storeToRefs(notifStore)
const { currentUser } = storeToRefs(session)

const sortedNotifications = computed(() => {
  const user = currentUser.value
  if (!user) return []
  const list = Array.isArray(notifications.value) ? notifications.value : []
  return list
    .filter((n) => n.userId === user.id)
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const markOne = (id: string) => {
  notifStore.markAsRead(id)
}

const markAllRead = () => {
  const user = currentUser.value
  if (user) notifStore.markAllAsRead(user.id)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">Powiadomienia</h1>
      <button type="button" class="text-sm text-sky-600 hover:text-sky-800 font-medium" @click="markAllRead">
        Oznacz wszystkie jako przeczytane
      </button>
    </div>

    <div class="bg-white shadow overflow-hidden rounded-lg">
      <ul class="divide-y divide-gray-200">
        <li v-for="notif in sortedNotifications" :key="notif.id" class="p-6 hover:bg-gray-50 transition" :class="!notif.read ? 'bg-sky-50' : ''">
          <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
              <AppIcon :name="getNotificationTone(notif.type).icon" class="w-6 h-6" :class="getNotificationTone(notif.type).className" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-900" :class="!notif.read ? 'font-bold' : 'font-medium'">
                {{ notif.message }}
              </p>
              <p class="text-xs text-gray-500 mt-1">{{ new Date(notif.date).toLocaleString() }}</p>
            </div>
            <div>
              <button v-if="!notif.read" type="button" class="text-xs bg-white border border-gray-300 px-2 py-1 rounded text-gray-700 hover:bg-gray-100" @click="markOne(notif.id)">
                OK
              </button>
            </div>
          </div>
        </li>
        <li v-if="sortedNotifications.length === 0" class="p-10 text-center text-gray-500">Brak powiadomień.</li>
      </ul>
    </div>
  </div>
</template>
