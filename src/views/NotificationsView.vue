<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useNotificationStore } from '@/stores/notification'
import { useSessionStore } from '@/stores/session'
import { useStructureStore } from '@/stores/structure'
import AppIcon from '@/components/AppIcon.vue'
import TabHeader from '@/components/ui/TabHeader.vue'
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
  <div class="flex flex-col h-[calc(100vh-112px)]">

    <TabHeader icon="bell" title="Powiadomienia">
      <template #actions>
        <button
          v-if="canSend"
          @click="activeTab = 'SENT'"
          :class="['px-3 py-1.5 rounded-md text-sm font-medium transition-colors border', activeTab === 'SENT' ? 'bg-stratton-gold text-white border-stratton-gold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']">
          Wysłane
        </button>
        <button
          @click="activeTab = 'INBOX'"
          :class="['px-3 py-1.5 rounded-md text-sm font-medium transition-colors border', activeTab === 'INBOX' ? 'bg-stratton-gold text-white border-stratton-gold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']">
          Odebrane
          <span v-if="inboxList.filter(n => !n.read).length > 0" class="ml-2 bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold">
            {{ inboxList.filter(n => !n.read).length }}
          </span>
        </button>
        <button
          v-if="canSend"
          @click="activeTab = 'COMPOSE'"
          :class="['px-3 py-1.5 rounded-md text-sm font-bold transition-colors border', activeTab === 'COMPOSE' ? 'bg-stratton-gold text-white border-stratton-gold shadow-inner' : 'text-stratton-gold border-stratton-gold hover:bg-stratton-gold hover:text-white bg-white']">
          Nowe powiadomienie
        </button>
      </template>
    </TabHeader>

    <div class="flex-1 overflow-y-auto p-3 md:p-4 lg:p-6 space-y-3 md:space-y-6">

    <!-- INBOX TAB -->
    <div v-if="activeTab === 'INBOX'" class="bg-white rounded-card shadow-card-hover overflow-hidden border border-slate-100">
        <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-surface-subtle">
            <h2 class="text-xl font-bold text-slate-800">Skrzynka odbiorcza</h2>
            <button type="button" class="text-sm text-stratton-gold hover:text-stratton-500 font-bold uppercase tracking-wide transition-colors" @click="markAllRead">
                Oznacz wszystkie jako przeczytane
            </button>
        </div>
      <ul class="divide-y divide-slate-100">
        <li v-for="notif in inboxList" :key="notif.id" class="p-6 hover:bg-slate-50 transition-colors group" :class="{ 'bg-blue-50/30': !notif.read }">
          <div class="flex items-start gap-5">
            <div class="flex-shrink-0 mt-1">
                <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-sm" :class="getNotificationTone(notif.type).className.replace('text-', 'bg-').replace('600', '100') + ' ' + getNotificationTone(notif.type).className">
                    <AppIcon :name="getNotificationTone(notif.type).icon" class="w-5 h-5" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-slate-900 leading-snug" :class="{ 'font-bold': !notif.read, 'font-medium': notif.read }">
                        {{ notif.message }}
                    </p>
                    <span class="text-[10px] uppercase font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded ml-3 whitespace-nowrap">{{ getTypeLabel(notif.type) }}</span>
                </div>
              <p class="text-xs text-slate-500 mt-2 font-mono">{{ new Date(notif.date).toLocaleString() }}</p>
            </div>
            <div class="self-center pl-4">
              <button v-if="!notif.read" type="button" class="text-xs font-bold bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-all shadow-sm" @click="markOne(notif.id)">
                ODZNACZ
              </button>
            </div>
          </div>
        </li>
        <li v-if="inboxList.length === 0" class="p-16 text-center text-slate-400 flex flex-col items-center">
            <AppIcon name="inbox" class="w-12 h-12 mb-4 text-slate-200" />
            <span class="font-medium">Wszystkie powiadomienia przeczytane</span>
        </li>
      </ul>
    </div>

    <!-- SENT TAB -->
    <div v-if="activeTab === 'SENT'" class="bg-white rounded-card shadow-card-hover overflow-hidden border border-slate-100 p-0">
        <div class="px-8 py-6 border-b border-slate-100 bg-surface-subtle">
             <h2 class="text-xl font-bold text-slate-800">Historia wysłanych</h2>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[640px]">
          <thead class="bg-slate-50">
            <tr>
              <th scope="col" class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">Data</th>
              <th scope="col" class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">Odbiorca</th>
              <th scope="col" class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">Typ</th>
              <th scope="col" class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200 w-1/2">Treść</th>
              <th scope="col" class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200 text-right">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-100">
                    <tr v-for="notif in paginatedSentHistory" :key="notif.id" class="hover:bg-slate-50 transition-colors group">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 font-mono">{{ new Date(notif.date).toLocaleString() }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-800 font-bold truncate max-w-xs" :title="notif.userId">
                  <div class="flex items-center gap-2">
                       <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600">{{ notif.userId.substring(0,2).toUpperCase() }}</div>
                       {{ notif.userId }}
                  </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                             <div class="flex items-center gap-2">
                                <AppIcon :name="getNotificationTone(notif.type).icon" class="w-4 h-4" :class="getNotificationTone(notif.type).className" />
                                <span class="font-medium text-xs uppercase tracking-wide">{{ getTypeLabel(notif.type) }}</span>
                             </div>
                        </td>
              <td class="px-6 py-4 text-sm text-slate-600 max-w-lg leading-snug" :title="notif.message">{{ notif.message }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                 <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Wysłano
                 </span>
              </td>
                    </tr>
                    <tr v-if="paginatedSentHistory.length === 0">
              <td colspan="5" class="px-16 py-12 text-center text-slate-400">
                  Brak historii wysłanych powiadomień.
              </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div v-if="totalSentPages > 1" class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
            <button @click="sentPage--" :disabled="sentPage === 1" class="px-4 py-2 border border-slate-300 rounded-lg text-sm bg-white font-medium text-slate-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 hover:text-slate-900 transition-colors">Poprzednia</button>
            <span class="text-sm font-mono text-slate-500">Strona <span class="font-bold text-slate-900">{{ sentPage }}</span> z {{ totalSentPages }}</span>
            <button @click="sentPage++" :disabled="sentPage === totalSentPages" class="px-4 py-2 border border-slate-300 rounded-lg text-sm bg-white font-medium text-slate-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 hover:text-slate-900 transition-colors">Następna</button>
        </div>
    </div>

    <!-- COMPOSE TAB -->
    <div v-if="activeTab === 'COMPOSE' && canSend" class="bg-white rounded-card shadow-card-hover p-4 md:p-8 max-w-5xl mx-auto border border-slate-100">
        <div class="mb-4 md:mb-8 border-b border-slate-100 pb-3 md:pb-4">
            <h2 class="text-xl md:text-2xl font-bold text-slate-800">Nowa Wiadomość</h2>
            <p class="text-sm text-slate-500 mt-1">Wypełnij formularz, aby wysłać powiadomienie do pracowników.</p>
        </div>

        <form @submit.prevent="sendNotification" class="space-y-4 md:space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                <!-- Left Column -->
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">Odbiorca</label>
                        <div class="flex p-1 bg-slate-100 rounded-lg border border-slate-200">
                            <label class="flex-1 text-center cursor-pointer">
                                <input type="radio" v-model="composeForm.recipientType" value="INDIVIDUAL" class="sr-only peer">
                                <span class="block py-2 text-sm font-medium rounded-md text-slate-500 peer-checked:bg-white peer-checked:text-stratton-gold peer-checked:shadow-sm transition-all hover:text-slate-700">Osoba</span>
                            </label>
                            <label class="flex-1 text-center cursor-pointer">
                                <input type="radio" v-model="composeForm.recipientType" value="GROUP" class="sr-only peer">
                                <span class="block py-2 text-sm font-medium rounded-md text-slate-500 peer-checked:bg-white peer-checked:text-stratton-gold peer-checked:shadow-sm transition-all hover:text-slate-700">Grupa</span>
                            </label>
                            <label class="flex-1 text-center cursor-pointer">
                                <input type="radio" v-model="composeForm.recipientType" value="ALL" class="sr-only peer">
                                <span class="block py-2 text-sm font-medium rounded-md text-slate-500 peer-checked:bg-white peer-checked:text-stratton-gold peer-checked:shadow-sm transition-all hover:text-slate-700">Wszyscy</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="composeForm.recipientType !== 'ALL'" class="transition-all duration-300 ease-in-out">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Wybierz {{ composeForm.recipientType === 'GROUP' ? 'Grupę' : 'Osobę' }}</label>
                        <div class="relative">
                            <select v-model="composeForm.recipientId" required class="block w-full pl-4 pr-10 py-3 text-base border-slate-300 focus:outline-none focus:ring-2 focus:ring-stratton-gold/50 focus:border-stratton-gold sm:text-sm rounded-lg border shadow-sm bg-white appearance-none">
                                <option value="" disabled>Kliknij, aby wybrać z listy...</option>
                                <option v-for="opt in filteredRecipients" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                     <label class="block text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">Typ Powiadomienia</label>
                     <div class="grid grid-cols-2 gap-3">
                        <label v-for="type in ['REMINDER', 'CONTACT', 'ATTENTION', 'REPRIMAND', 'INFO', 'TASK']" :key="type"
                            class="relative rounded-xl border px-3 py-3 shadow-sm flex items-center gap-3 cursor-pointer hover:bg-slate-50 transition-all group"
                            :class="composeForm.type === type ? 'ring-2 ring-stratton-gold border-stratton-gold bg-amber-50/50' : 'border-slate-200 hover:border-slate-300'">
                            <input type="radio" name="notification-type" :value="type" v-model="composeForm.type" class="sr-only">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center transition-colors" :class="composeForm.type === type ? 'bg-white' : 'bg-slate-100 group-hover:bg-white'">
                                 <AppIcon :name="getNotificationTone(type).icon" class="h-4 w-4" :class="getNotificationTone(type).className" />
                            </div>
                            <span class="text-sm font-bold" :class="composeForm.type === type ? 'text-slate-900' : 'text-slate-600'">{{ getTypeLabel(type) }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wide">Treść Wiadomości</label>
                <div class="relative">
                    <textarea v-model="composeForm.message" rows="5" required class="shadow-sm focus:ring-2 focus:ring-stratton-gold/50 focus:border-stratton-gold block w-full sm:text-sm border-slate-300 rounded-lg border p-4 resize-none placeholder-slate-400 transition-all" placeholder="Wpisz treść powiadomienia..."></textarea>
                    <div class="absolute bottom-3 right-3 text-xs text-slate-400 pointer-events-none">
                        {{ composeForm.message.length }} znaków
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100 flex justify-end">
                <button type="submit" :disabled="isSending" class="flex items-center gap-3 px-8 py-3 text-base font-bold text-white rounded-lg shadow-lg shadow-stratton-gold/20 bg-stratton-gold hover:bg-[#B08D55] hover:scale-[1.02] active:scale-[0.98] transition-all transform disabled:opacity-50 disabled:cursor-not-allowed">
                    <AppIcon name="paper-airplane" class="w-5 h-5" />
                    {{ isSending ? 'Wysyłanie...' : 'Wyślij Powiadomienie' }}
                </button>
            </div>
        </form>
    </div>
    </div>
  </div>
</template>
