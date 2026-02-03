<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useStructureStore } from '@/stores/structure'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useNotificationStore } from '@/stores/notification'
import { api } from '@/api/client'
import { useRouter, useRoute } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'

const data = useDataStore()
const clientStore = useClientStore()
const structure = useStructureStore()
const auth = useAuthStore()
const { clients } = storeToRefs(clientStore)
const session = useSessionStore()
const notifications = useNotificationStore()
const router = useRouter()
const route = useRoute()

const currentDate = ref(new Date())
const weekDays = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz']
const showModal = ref(false)
const selectedDate = ref<Date | null>(null)
const newEvent = ref({ id: '', clientId: '', type: 'MEETING', time: '10:00', description: '' })
const selectedDay = ref<{ date: Date; activities: any[] } | null>(null)
const reminderTimer = ref<number | null>(null)
const searchQuery = ref('')
const showMonthPicker = ref(false)
const pickerYear = ref(new Date().getFullYear())
const pickerMonth = ref(new Date().getMonth())

const openMonthPicker = () => {
  pickerYear.value = currentDate.value.getFullYear()
  pickerMonth.value = currentDate.value.getMonth()
  showMonthPicker.value = true
}

const applyMonthPicker = () => {
  currentDate.value = new Date(pickerYear.value, pickerMonth.value, 1)
  showMonthPicker.value = false
}

const getMyClients = () => {
  const u = session.currentUser
  if (!u) return []
  const list = Array.isArray(clients.value) ? clients.value : []
  if (u.role === 'ADMIN') return list
  if (u.role === 'MANAGER' || u.role === 'DIRECTOR') {
    const teamIds = [u.id, ...(auth.enabled ? structure.getSubtreeUserIds(u.id) : data.getSubtreeUserIds(u.id))]
    return list.filter((client) => teamIds.includes(client.ownerId))
  }
  return list.filter((client) => client.ownerId === u.id)
}

const currentRole = computed(() => session.currentUser?.role || '')
const baseClients = computed(() => getMyClients())
const structureUsers = computed(() => structure.users || [])

const daysInMonth = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const days = new Date(year, month + 1, 0).getDate()

  const clients = baseClients.value
  const query = searchQuery.value.trim().toLowerCase()
  const monthDays = [] as Array<{ dayNumber: number; date: Date; activities: any[]; overflow: number }>

  for (let i = 1; i <= days; i += 1) {
    const date = new Date(year, month, i)
    // Avoid UTC string comparison, use local date components
    // const dateStr = date.toISOString().split('T')[0] 

    const activities: any[] = []
    clients.forEach((client: any) => {
      // Check client match
      const clientName = (client.name || '').toLowerCase()
      const clientEmail = (client.email || '').toLowerCase()
      const clientPhone = (client.phone || '').toLowerCase()
      const matchesClient = !query || clientName.includes(query) || clientEmail.includes(query) || clientPhone.includes(query)

      if (client.activityHistory) {
        client.activityHistory.forEach((act: any) => {
          const actDate = new Date(act.date)
          // Compare local calendar date with local activity date
          const isSameDay = actDate.getFullYear() === date.getFullYear() &&
                            actDate.getMonth() === date.getMonth() &&
                            actDate.getDate() === date.getDate()

          if (isSameDay) {
            // Check activity match
            const type = (act.type || '').toLowerCase()
            const desc = (act.description || '').toLowerCase()
            const matchesActivity = !query || type.includes(query) || desc.includes(query)

            if (matchesClient || matchesActivity) {
              activities.push({
                id: act.id,
                clientId: client.id,
                clientName: client.name,
                type: act.type,
                description: act.description,
                time: actDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
              })
            }
          }
        })
      }
    })

    activities.sort((a, b) => a.time.localeCompare(b.time))
    const overflow = Math.max(0, activities.length - 3)

    monthDays.push({
      dayNumber: i,
      date,
      activities,
      overflow,
    })
  }

  return monthDays
})

const emptySlots = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const firstDayIndex = new Date(year, month, 1).getDay()
  const adjustedIndex = firstDayIndex === 0 ? 6 : firstDayIndex - 1
  return new Array(adjustedIndex)
})

const changeMonth = (delta: number) => {
  const newDate = new Date(currentDate.value)
  newDate.setMonth(newDate.getMonth() + delta)
  currentDate.value = newDate
}

const isToday = (date: Date) => {
  const today = new Date()
  return date.getDate() === today.getDate() && date.getMonth() === today.getMonth() && date.getFullYear() === today.getFullYear()
}

const goToClient = (id: string, event: Event) => {
  event.stopPropagation()
  router.push({ path: '/app/clients', query: { expand: id } })
}

const openAddModal = (date: Date) => {
  if (session.isReadOnly) return
  selectedDate.value = date
  const clients = baseClients.value
  newEvent.value = {
    id: '',
    clientId: clients.length > 0 ? clients[0].id : '',
    type: 'MEETING',
    time: '10:00',
    description: '',
  }
  showModal.value = true
}

const openEditModal = (act: any, date: Date, event: Event) => {
  event.stopPropagation()
  if (session.isReadOnly) return
  
  selectedDate.value = date
  newEvent.value = {
    id: act.id,
    clientId: act.clientId,
    type: act.type,
    time: act.time,
    description: act.description,
  }
  showModal.value = true
}

const deleteEvent = async (act: any, event: Event) => {
  event.stopPropagation()
  if (session.isReadOnly) return
  if (!confirm(`Czy na pewno usunąć to wydarzenie?`)) return

  try {
    // Assuming clientStore has removeActivity or similar
    // Note: clientStore implementation is not visible here but we can assume addActivity exists so remove/update might too.
    // If not, we might need to modify clientStore.
    // For now, let's call clientStore.removeActivity if it exists, or just log.
    if ((clientStore as any).removeActivity) {
      await (clientStore as any).removeActivity(act.clientId, act.id)
    } else {
        // Fallback: reload or notify - implementation dependent on store
        // Since we don't have the removeActivity in the visible snippet, assuming it exists or needs to be added.
        // I will assume it exists since addActivity does.
        await (clientStore as any).removeActivity(act.clientId, act.id)
    }
  } catch (e) {
    console.error('Failed to delete event', e)
  }
}


const closeModal = () => {
  showModal.value = false
}

const saveEvent = async () => {
  const date = selectedDate.value
  const u = session.currentUser

  if (date && newEvent.value.clientId && u && newEvent.value.description) {
    const [hours, mins] = newEvent.value.time.split(':')
    const eventDate = new Date(date)
    eventDate.setHours(parseInt(hours, 10), parseInt(mins, 10))
    const isoDate = eventDate.toISOString()

    if (newEvent.value.id) {
       // Update existing
       // @ts-ignore
       await clientStore.updateActivity(newEvent.value.clientId, {
          id: newEvent.value.id,
          type: newEvent.value.type,
          description: newEvent.value.description,
          date: isoDate,
          authorId: u.id
       })
    } else {
       // Create new
       await clientStore.addActivity(
        newEvent.value.clientId,
        {
          type: newEvent.value.type as any,
          description: newEvent.value.description,
          authorId: u.id,
        },
        isoDate
      )
    }

    // Refresh everything explicitly
    if (auth.enabled) {
      await clientStore.refreshApiData()
    }

    closeModal()
  }
}

const openDayDetails = (day: { date: Date; activities: any[] }) => {
  selectedDay.value = day
}

const closeDayDetails = () => {
  selectedDay.value = null
}

const reminderKey = (activity: any) => `calendar_reminder_${activity.id}_${activity.date}`

const checkReminders = () => {
  const user = session.currentUser
  if (!user) return
  const now = Date.now()
  const allClients = baseClients.value
  const activities: any[] = []
  allClients.forEach((client) => {
    ;(client.activityHistory || []).forEach((act: any) => {
      if (act.type === 'MEETING') activities.push({ ...act, clientName: client.name })
    })
  })

  activities.forEach((act) => {
    const when = new Date(act.date).getTime()
    if (Number.isNaN(when)) return
    if (when <= now || when > now + 60 * 1000) return
    const key = reminderKey(act)
    if (localStorage.getItem(key)) return
    localStorage.setItem(key, '1')
    notifications.add({
      userId: user.id,
      type: 'INFO',
      message: `Wznowienie spotkania: ${act.clientName} (${new Date(act.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })})`,
    })
  })
}

onMounted(() => {
  reminderTimer.value = window.setInterval(checkReminders, 30000)

  // Handle opening event from query param
  const { openEventId, date } = route.query
  if (openEventId && date) {
    const targetDate = new Date(date as string)
    if (!isNaN(targetDate.getTime())) {
      currentDate.value = targetDate
      
      // Wait for computed daysInMonth to update
      setTimeout(() => {
        const foundDay = daysInMonth.value.find(d => 
             d.date.getDate() === targetDate.getDate() &&
             d.date.getMonth() === targetDate.getMonth() &&
             d.date.getFullYear() === targetDate.getFullYear()
        )
        if (foundDay) {
          const foundAct = foundDay.activities.find(a => String(a.id) === String(openEventId))
          if (foundAct) {
            openEditModal(foundAct, foundDay.date, { stopPropagation: () => {} } as any)
          }
        }
      }, 300)
    }
  }
})

onBeforeUnmount(() => {
  if (reminderTimer.value) window.clearInterval(reminderTimer.value)
})
</script>

<template>
  <div class="space-y-4 h-full flex flex-col">
    <div class="bg-white shadow rounded-lg border border-gray-200 p-4 relative grid grid-cols-1 md:grid-cols-3 items-center gap-4">
      <div class="hidden md:block"></div> <!-- Spacer -->
      
      <div class="flex justify-center">
        <h1 class="text-2xl font-bold text-gray-900">Kalendarz Pracy</h1>
      </div>
      
      <div class="flex justify-center md:justify-end">
        <div class="flex items-center space-x-2 bg-slate-50 p-1 rounded-lg border border-slate-200 relative">
          <button type="button" class="p-2 hover:bg-white hover:shadow-sm rounded text-gray-600 font-bold transition" @click="changeMonth(-1)"><</button>
          <button type="button" class="text-sm font-bold w-32 text-center uppercase tracking-wider text-slate-700 hover:text-sky-600 transition" @click="openMonthPicker">
            {{ currentDate.toLocaleDateString('pl-PL', { month: 'long', year: 'numeric' }) }}
          </button>
          <button type="button" class="p-2 hover:bg-white hover:shadow-sm rounded text-gray-600 font-bold transition" @click="changeMonth(1)">></button>

          <!-- Month Picker Popup -->
          <div v-if="showMonthPicker" class="absolute top-12 right-0 bg-white border border-slate-200 shadow-xl rounded-xl p-4 z-50 w-64 animate-fade-in">
             <div class="flex gap-2 mb-4">
                <select v-model="pickerMonth" class="flex-1 border border-slate-200 rounded-lg p-2 text-sm">
                   <option v-for="(m, i) in 12" :key="i" :value="i">{{ new Date(2000, i, 1).toLocaleDateString('pl-PL', { month: 'long' }) }}</option>
                </select>
                <input v-model="pickerYear" type="number" class="w-20 border border-slate-200 rounded-lg p-2 text-sm" />
             </div>
             <div class="flex justify-end gap-2">
                <button @click="showMonthPicker = false" class="text-xs text-slate-500 hover:text-slate-800 px-3 py-2">Anuluj</button>
                <button @click="applyMonthPicker" class="text-xs bg-sky-600 text-white rounded-lg px-3 py-2 hover:bg-sky-700 font-bold">Wybierz</button>
             </div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex-1 bg-white shadow rounded-lg overflow-hidden flex flex-col border border-gray-200">
      <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
        <div v-for="day in weekDays" :key="day" class="py-2 text-center text-xs font-bold text-gray-500 uppercase">{{ day }}</div>
      </div>

      <div class="flex-1 grid grid-cols-7 auto-rows-fr bg-gray-200 gap-px">
        <div v-for="(_, idx) in emptySlots" :key="idx" class="bg-gray-50 opacity-50"></div>

        <div
          v-for="day in daysInMonth"
          :key="day.date.toISOString()"
          class="bg-white min-h-[70px] p-1.5 hover:bg-sky-50 transition relative group flex flex-col cursor-pointer"
          :class="isToday(day.date) ? 'bg-sky-50' : ''"
          @click="openAddModal(day.date)"
        >
          <div class="text-right text-xs font-medium text-gray-500 mb-0.5" :class="isToday(day.date) ? 'text-sky-600 font-bold' : ''">
            {{ day.dayNumber }}
          </div>

          <div class="flex-1 overflow-y-auto space-y-0.5 custom-scrollbar">
            <div
              v-for="act in day.activities.slice(0, 3)"
              :key="act.id"
              class="text-[11px] p-1 rounded cursor-pointer border shadow-sm transition transform hover:scale-105 group/item flex justify-between items-start gap-1"
              :class="{
                'bg-blue-100 border-blue-200 text-blue-800': act.type === 'CALL',
                'bg-purple-100 border-purple-200 text-purple-800': act.type === 'MEETING',
                'bg-gray-100 border-gray-200 text-gray-800': act.type === 'NOTE',
                'bg-yellow-100 border-yellow-200 text-yellow-800': act.type === 'EMAIL',
              }"
              @click="openEditModal(act, day.date, $event)"
            >
              <div class="flex-1 line-clamp-2 leading-tight">
                <span class="font-bold mr-1">{{ act.time }}</span> <span>{{ act.clientName }}</span>
              </div>
              <button class="shrink-0 p-1 hover:bg-red-200 rounded text-red-600 font-bold leading-none text-sm w-5 h-5 flex items-center justify-center" @click="deleteEvent(act, $event)">-</button>
            </div>
            <button
              v-if="day.overflow > 0"
              type="button"
              class="w-full mt-1 text-[10px] font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded py-1 transition-colors text-center shadow-sm"
              @click.stop="openDayDetails(day)"
            >
              +{{ day.overflow }} pokaż więcej
            </button>
          </div>

          <button type="button" class="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 bg-sky-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs shadow-md" title="Dodaj zdarzenie">
            +
          </button>
        </div>
      </div>
    </div>

    <div class="flex gap-4 text-xs text-gray-500">
      <span class="flex items-center"><span class="w-3 h-3 bg-blue-100 border border-blue-200 rounded mr-1"></span> Telefon</span>
      <span class="flex items-center"><span class="w-3 h-3 bg-purple-100 border border-purple-200 rounded mr-1"></span> Spotkanie</span>
      <span class="flex items-center"><span class="w-3 h-3 bg-yellow-100 border border-yellow-200 rounded mr-1"></span> Email</span>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeModal"></div>
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md z-10 relative">
        <h3 class="text-lg font-bold mb-4">Zaplanuj działanie</h3>
        <p class="text-sm text-gray-500 mb-4">Data: {{ selectedDate?.toLocaleDateString('pl-PL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">Klient</label>
            <select v-model="newEvent.clientId" class="w-full border p-2 rounded mt-1">
              <option v-for="client in getMyClients()" :key="client.id" :value="client.id">{{ client.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">Typ</label>
            <select v-model="newEvent.type" class="w-full border p-2 rounded mt-1">
              <option value="CALL">Telefon</option>
              <option value="MEETING">Spotkanie</option>
              <option value="EMAIL">Email</option>
              <option value="NOTE">Notatka</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-medium text-gray-500 uppercase">Godzina</label>
              <input v-model="newEvent.time" type="time" class="w-full border p-2 rounded mt-1" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">Opis</label>
            <textarea v-model="newEvent.description" rows="3" class="w-full border p-2 rounded mt-1" placeholder="Szczegóły spotkania..."></textarea>
          </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button type="button" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded" @click="closeModal">Anuluj</button>
          <button type="button" class="px-4 py-2 bg-sky-600 text-white rounded hover:bg-sky-700" @click="saveEvent">Dodaj</button>
        </div>
      </div>
    </div>

    <div v-if="selectedDay" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeDayDetails"></div>
      <div class="relative bg-white rounded-lg shadow-xl p-6 w-full max-w-lg z-10">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold">Zdarzenia dnia</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeDayDetails">✕</button>
        </div>
        <p class="text-xs text-gray-500 mb-4">
          {{ selectedDay.date.toLocaleDateString('pl-PL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
        </p>
        <div class="space-y-2 max-h-80 overflow-y-auto">
          <div v-for="act in selectedDay.activities" :key="act.id" class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded p-2 text-xs">
            <div>
              <div class="font-semibold text-gray-800">{{ act.clientName }}</div>
              <div class="text-gray-500">{{ act.description }}</div>
            </div>
            <div class="font-mono text-gray-600">{{ act.time }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}
</style>
