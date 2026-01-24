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
import { useRouter } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'

const data = useDataStore()
const clientStore = useClientStore()
const structure = useStructureStore()
const auth = useAuthStore()
const { clients } = storeToRefs(clientStore)
const session = useSessionStore()
const notifications = useNotificationStore()
const router = useRouter()

const currentDate = ref(new Date())
const weekDays = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz']
const showModal = ref(false)
const selectedDate = ref<Date | null>(null)
const newEvent = ref({ clientId: '', type: 'MEETING', time: '10:00', description: '' })
const selectedDay = ref<{ date: Date; activities: any[] } | null>(null)
const reminderTimer = ref<number | null>(null)
const filters = ref({
  statuses: [] as string[],
  companies: [] as string[],
  eventTypes: [] as string[],
  people: [] as string[],
  teams: [] as string[],
})
const filterQuery = ref('')
const statusCatalog = ref<Array<{ key: string; label: string; active?: boolean | null }>>([])

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
const teamOptions = computed(() => {
  const paths = new Set<string>()
  structureUsers.value.forEach((user) => {
    if (user.teamGroupPath) paths.add(user.teamGroupPath)
  })
  return Array.from(paths)
})
const peopleOptions = computed(() => {
  const user = session.currentUser
  if (!user) return []
  if (user.role === 'ADMIN') return structureUsers.value
  if (user.role === 'DIRECTOR' || user.role === 'MANAGER') {
    const teamIds = [user.id, ...(auth.enabled ? structure.getSubtreeUserIds(user.id) : data.getSubtreeUserIds(user.id))]
    return structureUsers.value.filter((item) => teamIds.includes(item.id))
  }
  return structureUsers.value.filter((item) => item.id === user.id)
})
const statusOptions = computed(() => {
  if (statusCatalog.value.length > 0) {
    return statusCatalog.value
      .filter((status) => status.active !== false)
      .map((status) => ({ key: status.key, label: status.label }))
  }
  const fallback = new Set<string>()
  baseClients.value.forEach((client: any) => {
    if (client.status) fallback.add(String(client.status))
  })
  return Array.from(fallback).map((key) => ({ key, label: key }))
})
const companyOptions = computed(() => {
  return baseClients.value.map((client: any) => ({ id: client.id, name: client.name })).sort((a, b) => a.name.localeCompare(b.name))
})
const eventOptions = [
  { value: 'CALL', label: 'Telefon' },
  { value: 'MEETING', label: 'Spotkanie' },
  { value: 'EMAIL', label: 'Email' },
  { value: 'NOTE', label: 'Notatka' },
]

const normalizeQuery = (value: string) => value.trim().toLowerCase()

const filteredStatusOptions = computed(() => {
  const query = normalizeQuery(filterQuery.value)
  if (!query) return statusOptions.value
  return statusOptions.value.filter((status) => status.label.toLowerCase().includes(query) || status.key.toLowerCase().includes(query))
})

const filteredCompanyOptions = computed(() => {
  const query = normalizeQuery(filterQuery.value)
  if (!query) return companyOptions.value
  return companyOptions.value.filter((company) => company.name.toLowerCase().includes(query))
})

const filteredPeopleOptions = computed(() => {
  const query = normalizeQuery(filterQuery.value)
  if (!query) return peopleOptions.value
  return peopleOptions.value.filter((user: any) => user.name?.toLowerCase().includes(query))
})

const filteredEventOptions = computed(() => {
  const query = normalizeQuery(filterQuery.value)
  if (!query) return eventOptions
  return eventOptions.filter((event) => event.label.toLowerCase().includes(query) || event.value.toLowerCase().includes(query))
})

const filteredTeamOptions = computed(() => {
  const query = normalizeQuery(filterQuery.value)
  if (!query) return teamOptions.value
  return teamOptions.value.filter((team) => team.toLowerCase().includes(query))
})

const resetFilters = () => {
  filters.value = {
    statuses: [],
    companies: [],
    eventTypes: [],
    people: [],
    teams: [],
  }
  filterQuery.value = ''
}

const filteredClients = computed(() => {
  let list = baseClients.value
  if (filters.value.statuses.length > 0) {
    list = list.filter((client: any) => filters.value.statuses.includes(String(client.status)))
  }
  if (filters.value.companies.length > 0) {
    list = list.filter((client: any) => filters.value.companies.includes(client.id))
  }
  if (filters.value.teams.length > 0) {
    const teamByUser = new Map(structureUsers.value.map((user: any) => [user.id, user.teamGroupPath || '']))
    list = list.filter((client: any) => filters.value.teams.includes(teamByUser.get(client.ownerId) || ''))
  }
  if (filters.value.people.length > 0) {
    list = list.filter((client: any) => filters.value.people.includes(client.ownerId))
  }
  return list
})

const daysInMonth = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const days = new Date(year, month + 1, 0).getDate()

  const clients = filteredClients.value
  const monthDays = [] as Array<{ dayNumber: number; date: Date; activities: any[]; overflow: number }>

  for (let i = 1; i <= days; i += 1) {
    const date = new Date(year, month, i)
    const dateStr = date.toISOString().split('T')[0]

    const activities: any[] = []
    clients.forEach((client) => {
      if (client.activityHistory) {
        client.activityHistory.forEach((act) => {
          if (act.date.startsWith(dateStr)) {
            if (filters.value.eventTypes.length > 0 && !filters.value.eventTypes.includes(act.type)) return
            activities.push({
              id: act.id,
              clientId: client.id,
              clientName: client.name,
              type: act.type,
              description: act.description,
              time: new Date(act.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            })
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
  const clients = filteredClients.value
  newEvent.value = {
    clientId: clients.length > 0 ? clients[0].id : '',
    type: 'MEETING',
    time: '10:00',
    description: '',
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveEvent = () => {
  const date = selectedDate.value
  const u = session.currentUser

  if (date && newEvent.value.clientId && u && newEvent.value.description) {
    const [hours, mins] = newEvent.value.time.split(':')
    const eventDate = new Date(date)
    eventDate.setHours(parseInt(hours, 10), parseInt(mins, 10))
    const isoDate = eventDate.toISOString()

    clientStore.addActivity(
      newEvent.value.clientId,
      {
        type: newEvent.value.type as any,
        description: newEvent.value.description,
        authorId: u.id,
      },
      isoDate
    )

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
  const allClients = filteredClients.value
  const activities: any[] = []
  allClients.forEach((client) => {
    ;(client.activityHistory || []).forEach((act) => {
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
  if (auth.enabled) {
    api.get('/v1/crm-statuses')
      .then(({ data }) => {
        statusCatalog.value = Array.isArray(data)
          ? data.map((item: any) => ({ key: String(item.key), label: String(item.label || item.key), active: item.active }))
          : []
      })
      .catch(() => {
        statusCatalog.value = []
      })
  }
  reminderTimer.value = window.setInterval(checkReminders, 30000)
})

onBeforeUnmount(() => {
  if (reminderTimer.value) window.clearInterval(reminderTimer.value)
})
</script>

<template>
  <div class="space-y-6 h-full flex flex-col">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">Kalendarz Pracy</h1>
      <div class="flex items-center space-x-4 bg-white p-2 rounded shadow-sm">
        <button type="button" class="p-2 hover:bg-gray-100 rounded text-gray-600 font-bold" @click="changeMonth(-1)"><</button>
        <span class="text-lg font-bold w-40 text-center">
          {{ currentDate.toLocaleDateString('pl-PL', { month: 'long', year: 'numeric' }) }}
        </span>
        <button type="button" class="p-2 hover:bg-gray-100 rounded text-gray-600 font-bold" @click="changeMonth(1)">></button>
      </div>
    </div>

    <div class="bg-white shadow rounded-lg border border-gray-200 p-4 space-y-4">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold text-gray-700">Filtry</div>
        <button
          type="button"
          class="text-xs text-gray-500 hover:text-gray-700"
          @click="resetFilters"
        >
          Wyczyść filtry
        </button>
      </div>
      <div>
        <input
          v-model="filterQuery"
          type="text"
          placeholder="Szukaj w filtrach..."
          class="w-full border border-gray-200 rounded p-2 text-xs"
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
          <label class="text-xs font-semibold text-gray-500">Statusy</label>
          <select v-model="filters.statuses" multiple class="mt-1 w-full border border-gray-200 rounded p-2 text-xs">
            <option v-for="status in filteredStatusOptions" :key="status.key" :value="status.key">{{ status.label }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-500">Firmy</label>
          <select v-model="filters.companies" multiple class="mt-1 w-full border border-gray-200 rounded p-2 text-xs">
            <option v-for="company in filteredCompanyOptions" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-500">Wydarzenia</label>
          <select v-model="filters.eventTypes" multiple class="mt-1 w-full border border-gray-200 rounded p-2 text-xs">
            <option v-for="event in filteredEventOptions" :key="event.value" :value="event.value">{{ event.label }}</option>
          </select>
        </div>
        <div v-if="currentRole === 'ADMIN' || currentRole === 'DIRECTOR' || currentRole === 'MANAGER'">
          <label class="text-xs font-semibold text-gray-500">Osoby</label>
          <select v-model="filters.people" multiple class="mt-1 w-full border border-gray-200 rounded p-2 text-xs">
            <option v-for="user in filteredPeopleOptions" :key="user.id" :value="user.id">{{ user.name }}</option>
          </select>
        </div>
        <div v-if="currentRole === 'ADMIN'">
          <label class="text-xs font-semibold text-gray-500">Zespoły</label>
          <select v-model="filters.teams" multiple class="mt-1 w-full border border-gray-200 rounded p-2 text-xs">
            <option v-for="team in filteredTeamOptions" :key="team" :value="team">{{ team }}</option>
          </select>
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
          class="bg-white min-h-[100px] p-2 hover:bg-sky-50 transition relative group flex flex-col cursor-pointer"
          :class="isToday(day.date) ? 'bg-sky-50' : ''"
          @click="openAddModal(day.date)"
        >
          <div class="text-right text-sm font-medium text-gray-500 mb-1" :class="isToday(day.date) ? 'text-sky-600 font-bold' : ''">
            {{ day.dayNumber }}
          </div>

          <div class="flex-1 overflow-y-auto space-y-1 custom-scrollbar">
            <div
              v-for="act in day.activities.slice(0, 3)"
              :key="act.id"
              class="text-[10px] p-1.5 rounded cursor-pointer border shadow-sm transition transform hover:scale-105"
              :class="{
                'bg-blue-100 border-blue-200 text-blue-800': act.type === 'CALL',
                'bg-purple-100 border-purple-200 text-purple-800': act.type === 'MEETING',
                'bg-gray-100 border-gray-200 text-gray-800': act.type === 'NOTE',
                'bg-yellow-100 border-yellow-200 text-yellow-800': act.type === 'EMAIL',
              }"
              @click="goToClient(act.clientId, $event)"
            >
              <div class="font-bold truncate">
                <AppIcon v-if="act.type === 'CALL'" name="phone" class="w-3 h-3 inline-block mr-1" />
                <AppIcon v-else-if="act.type === 'MEETING'" name="users" class="w-3 h-3 inline-block mr-1" />
                <AppIcon v-else-if="act.type === 'EMAIL'" name="envelope" class="w-3 h-3 inline-block mr-1" />
                {{ act.time }}
              </div>
              <div class="truncate">{{ act.clientName }}</div>
            </div>
            <button
              v-if="day.overflow > 0"
              type="button"
              class="text-[10px] text-slate-500 hover:text-slate-700"
              @click.stop="openDayDetails(day)"
            >
              +{{ day.overflow }} więcej
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
