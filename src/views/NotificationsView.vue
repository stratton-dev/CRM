<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useNotificationStore } from '@/stores/notification'
import { useSessionStore } from '@/stores/session'
import { useStructureStore } from '@/stores/structure'
import AppIcon from '@/components/AppIcon.vue'
import { getNotificationTone } from '@/utils/uiColors'
import type { Notification } from '@/types/models'

const notifStore = useNotificationStore()
const session = useSessionStore()
const structureStore = useStructureStore()

const { notifications, sentNotifications } = storeToRefs(notifStore)
const { currentUser } = storeToRefs(session)
const { users, teamGroups } = storeToRefs(structureStore)

const activeTab = ref<'INBOX' | 'SENT' | 'COMPOSE'>('INBOX')

const canSend = computed(() => {
  const role = currentUser.value?.role
  return role === 'MANAGER' || role === 'DIRECTOR' || role === 'ADMIN'
})

// Inbox Logic
const inboxList = computed(() => {
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

// Sent History Logic
const sentPage = ref(1)
const sentPerPage = 10

const sentHistory = computed(() => {
  const list = Array.isArray(sentNotifications.value) ? sentNotifications.value : []
  return list.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const paginatedSentHistory = computed(() => {
  const start = (sentPage.value - 1) * sentPerPage
  const end = start + sentPerPage
  return sentHistory.value.slice(start, end)
})

const totalSentPages = computed(() => Math.ceil(sentHistory.value.length / sentPerPage))

// Compose Logic
const composeForm = ref({
  recipientType: 'INDIVIDUAL' as 'ALL' | 'GROUP' | 'INDIVIDUAL',
  recipientId: '',
  type: 'INFO' as Notification['type'],
  message: ''
})

const isSending = ref(false)

const availableRecipients = computed(() => {
  if (composeForm.value.recipientType === 'GROUP') {
     // Return team groups (paths)
     return teamGroups.value.map(g => ({ id: g, label: g }))
  }
  if (composeForm.value.recipientType === 'INDIVIDUAL') {
    // Return users
    return users.value.map(u => ({ id: u.id, label: `${u.name} (${u.email})` }))
  }
  return [] // ALL doesn't need options
})

const filteredRecipients = computed(() => {
    // Search logic could appear here
    return availableRecipients.value
})

const sendNotification = async () => {
  if (!composeForm.value.message) return
  
  isSending.value = true
  try {
    let recipients: string[] = []
    
    if (composeForm.value.recipientType === 'ALL') {
        recipients = users.value.map(u => u.id)
    } else if (composeForm.value.recipientType === 'GROUP') {
        // Filter users by group
        // Assuming user.teamGroupPath matches the selected group
        // If teamGroups stores paths like /A/B/C
        const group = composeForm.value.recipientId
        recipients = users.value.filter(u => (u as any).teamGroupPath?.startsWith(group)).map(u => u.id)
    } else {
        recipients = [composeForm.value.recipientId]
    }

    if (recipients.length === 0) {
        alert('Brak odbiorców') // Consider creating a proper toast
        return
    }

    await notifStore.sendBatch(recipients, composeForm.value.type, composeForm.value.message)
    
    // Reset form
    composeForm.value.message = ''
    composeForm.value.recipientId = ''
    activeTab.value = 'SENT'
    notifStore.fetchSentNotifications()
  } catch (e) {
    console.error(e)
    alert('Błąd wysyłania')
  } finally {
    isSending.value = false
  }
}

onMounted(() => {
    notifStore.fetchNotifications()
    if (canSend.value) {
        activeTab.value = 'SENT'
        notifStore.fetchSentNotifications()
        structureStore.fetchStructure()
        structureStore.fetchTeams()
    }
})

// Helper for type labels
const getTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        INFO: 'Informacja',
        WARNING: 'Ostrzeżenie',
        CRITICAL: 'Krytyczne',
        TASK: 'Zadanie',
        NOTE: 'Notatka',
        REMINDER: 'Przypomnienie',
        CONTACT: 'Kontakt',
        ATTENTION: 'Uwaga',
        REPRIMAND: 'Nagana'
    }
    return map[type] || type
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center border-b pb-4">
        <div class="flex items-center space-x-4">
            <h1 class="text-2xl font-bold text-gray-900">Powiadomienia</h1>
        </div>
      <div class="flex space-x-2">
        <button 
            v-if="canSend"
            @click="activeTab = 'SENT'"
            :class="['px-4 py-2 rounded-md text-sm font-medium transition-colors', activeTab === 'SENT' ? 'bg-sky-100 text-sky-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50']">
            Wysłane
        </button>
        <button 
            @click="activeTab = 'INBOX'"
            :class="['px-4 py-2 rounded-md text-sm font-medium transition-colors', activeTab === 'INBOX' ? 'bg-sky-100 text-sky-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50']">
            Odebrane
            <span v-if="inboxList.filter(n => !n.read).length > 0" class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                {{ inboxList.filter(n => !n.read).length }}
            </span>
        </button>
        <button 
            v-if="canSend"
            @click="activeTab = 'COMPOSE'"
            :class="['px-4 py-2 rounded-md text-sm font-medium transition-colors border', activeTab === 'COMPOSE' ? 'bg-purple-700 text-white border-purple-800 shadow-inner' : 'bg-purple-600 text-white hover:bg-purple-700 border-purple-600 shadow-sm']">
            Nowe Powiadomienie
        </button>
      </div>
    </div>

    <!-- INBOX TAB -->
    <div v-if="activeTab === 'INBOX'" class="bg-white shadow overflow-hidden rounded-lg">
        <div class="px-6 py-4 border-b flex justify-end bg-gray-50">
            <button type="button" class="text-sm text-sky-600 hover:text-sky-800 font-medium" @click="markAllRead">
                Oznacz wszystkie jako przeczytane
            </button>
        </div>
      <ul class="divide-y divide-gray-200">
        <li v-for="notif in inboxList" :key="notif.id" class="p-6 hover:bg-gray-50 transition" :class="{ 'bg-blue-50': !notif.read }">
          <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
              <AppIcon :name="getNotificationTone(notif.type).icon" class="w-6 h-6" :class="getNotificationTone(notif.type).className" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-900" :class="{ 'font-bold': !notif.read, 'font-medium': notif.read }">
                {{ notif.message }}
              </p>
              <p class="text-xs text-gray-500 mt-1">{{ new Date(notif.date).toLocaleString() }} • {{ getTypeLabel(notif.type) }}</p>
            </div>
            <div>
              <button v-if="!notif.read" type="button" class="text-xs bg-white border border-gray-300 px-2 py-1 rounded text-gray-700 hover:bg-gray-100" @click="markOne(notif.id)">
                OK
              </button>
            </div>
          </div>
        </li>
        <li v-if="inboxList.length === 0" class="p-10 text-center text-gray-500">Brak powiadomień.</li>
      </ul>
    </div>

    <!-- SENT TAB -->
    <div v-if="activeTab === 'SENT'" class="bg-white shadow overflow-hidden rounded-lg p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Do kogo</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Typ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Treść</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="notif in paginatedSentHistory" :key="notif.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(notif.date).toLocaleString() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium truncate max-w-xs" :title="notif.userId">{{ notif.userId }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                             <div class="flex items-center">
                                <AppIcon :name="getNotificationTone(notif.type).icon" class="w-4 h-4 mr-2" :class="getNotificationTone(notif.type).className" />
                                {{ getTypeLabel(notif.type) }}
                             </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs" :title="notif.message">{{ notif.message }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                             <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Wysłano
                             </span>
                        </td>
                    </tr>
                    <tr v-if="paginatedSentHistory.length === 0">
                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">Brak wysłanych powiadomień.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div v-if="totalSentPages > 1" class="flex justify-between items-center mt-4">
            <button @click="sentPage--" :disabled="sentPage === 1" class="px-3 py-1 border rounded text-sm disabled:opacity-50 hover:bg-gray-50">Poprzednia</button>
            <span class="text-sm text-gray-600">Strona {{ sentPage }} z {{ totalSentPages }}</span>
            <button @click="sentPage++" :disabled="sentPage === totalSentPages" class="px-3 py-1 border rounded text-sm disabled:opacity-50 hover:bg-gray-50">Następna</button>
        </div>
    </div>

    <!-- COMPOSE TAB -->
    <div v-if="activeTab === 'COMPOSE' && canSend" class="bg-white shadow rounded-lg p-6 max-w-4xl mx-auto">
        <form @submit.prevent="sendNotification" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Odbiorca</label>
                <div class="mt-2 flex space-x-4">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" v-model="composeForm.recipientType" value="INDIVIDUAL" class="form-radio text-sky-600">
                        <span class="ml-2">Osoba</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" v-model="composeForm.recipientType" value="GROUP" class="form-radio text-sky-600">
                        <span class="ml-2">Grupa</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" v-model="composeForm.recipientType" value="ALL" class="form-radio text-sky-600">
                        <span class="ml-2">Wszyscy</span>
                    </label>
                </div>
            </div>

            <div v-if="composeForm.recipientType !== 'ALL'">
                <label class="block text-sm font-medium text-gray-700 mt-4">Wybierz {{ composeForm.recipientType === 'GROUP' ? 'Grupę' : 'Osobę' }}</label>
                <select v-model="composeForm.recipientId" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500 sm:text-sm rounded-md border">
                    <option value="" disabled>Wybierz...</option>
                    <option v-for="opt in filteredRecipients" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mt-4 mb-2">Typ Powiadomienia</label>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <label v-for="type in ['REMINDER', 'CONTACT', 'ATTENTION', 'REPRIMAND', 'INFO', 'TASK']" :key="type"
                        class="relative rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm flex items-center space-x-3 cursor-pointer hover:bg-gray-50 transition-colors"
                        :class="composeForm.type === type ? 'ring-2 ring-sky-500 border-sky-500 bg-sky-50' : ''">
                        <input type="radio" name="notification-type" :value="type" v-model="composeForm.type" class="sr-only">
                        <div class="flex-shrink-0">
                             <AppIcon :name="getNotificationTone(type).icon" class="h-6 w-6" :class="getNotificationTone(type).className" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="absolute inset-0" aria-hidden="true"></span>
                            <p class="text-sm font-medium text-gray-900">{{ getTypeLabel(type) }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mt-4">Treść Wiadomości</label>
                <div class="mt-1">
                    <textarea v-model="composeForm.message" rows="4" required class="shadow-sm focus:ring-sky-500 focus:border-sky-500 block w-full sm:text-sm border-gray-300 rounded-md border p-2" placeholder="Wpisz treść powiadomienia..."></textarea>
                </div>
            </div>

            <div class="pt-5 border-t border-gray-200 flex justify-end">
                <button type="submit" :disabled="isSending" class="ml-3 inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 disabled:opacity-50 transition-colors">
                    <AppIcon name="paper-airplane" class="w-4 h-4 mr-2" />
                    {{ isSending ? 'Wysyłanie...' : 'Wyślij Powiadomienie' }}
                </button>
            </div>
        </form>
    </div>
  </div>
</template>
