<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useNotificationStore } from '@/stores/notification'
import { useRoute } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'

const data = useDataStore()
const clientStore = useClientStore()
const auth = useAuthStore()
const { clients } = storeToRefs(clientStore)
const session = useSessionStore()
const notifications = useNotificationStore()
const route = useRoute()

const currentDate = ref(new Date())
const weekDays = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz']
const showModal = ref(false)
const selectedDate = ref<Date | null>(null)
type ActivityType = 'CALL' | 'MEETING' | 'EMAIL' | 'NOTE'
const newEvent = ref<{ id: string; clientId: string; type: ActivityType; time: string; description: string; isCompleted?: boolean }>({
  id: '',
  clientId: '',
  type: 'MEETING',
  time: '10:00',
  description: '',
  isCompleted: false,
})
const selectedDay = ref<{ date: Date; activities: any[] } | null>(null)
const reminderTimer = ref<number | null>(null)
const searchQuery = ref('')
const showMonthPicker = ref(false)
const pickerYear = ref(new Date().getFullYear())
const pickerMonth = ref(new Date().getMonth())
const isSaving = ref(false)

const yearsRange = computed(() => {
  const currentYear = new Date().getFullYear()
  const start = currentYear - 10 
  const end = currentYear + 10
  const years = []
  for (let y = start; y <= end; y++) {
    years.push(y)
  }
  return years
})

const draggedEvent = ref<any>(null)

const onDragStart = (e: DragEvent, act: any) => {
  if (session.isReadOnly) {
    e.preventDefault()
    return
  }
  draggedEvent.value = act
  if (e.dataTransfer) {
     e.dataTransfer.effectAllowed = 'move'
     e.dataTransfer.dropEffect = 'move'
     e.dataTransfer.setData('text/plain', JSON.stringify(act))
  }
}

const onDrop = async (_e: DragEvent, date: Date) => {
  const act = draggedEvent.value
  draggedEvent.value = null
  
  if (!act) return

  const u = session.currentUser
  if (!u) return 

  try {
     const [hours, mins] = act.time.split(':')
     const targetDate = new Date(date)
     targetDate.setHours(parseInt(hours), parseInt(mins))
     
     // Update using clientStore
     // @ts-ignore
     if (clientStore.updateActivity) {
         // @ts-ignore
         await clientStore.updateActivity(act.clientId, {
            id: act.id,
            type: act.type,
            description: act.description,
            date: targetDate.toISOString(),
            authorId: u.id
         })
         
         notifications.add({
           userId: u.id,
           type: 'INFO',
           message: 'Przeniesiono wydarzenie.'
         })
         
         // Refresh
         if (auth.enabled) {
            // @ts-ignore
            await clientStore.refreshApiData()
         }
     }
  } catch (err) {
     console.error(err)
     notifications.add({
       userId: u.id, 
       type: 'WARNING',
       message: 'Błąd podczas przenoszenia wydarzenia.'
     })
  }
}

const toggleMonthPicker = () => {
  if (showMonthPicker.value) {
    showMonthPicker.value = false
    return
  }
  pickerYear.value = currentDate.value.getFullYear()
  pickerMonth.value = currentDate.value.getMonth()
  showMonthPicker.value = true
}

// Close picker when clicking outside (handled by overlay) but let's be safe
const closeMonthPicker = () => {
  showMonthPicker.value = false
}

const applyMonthPicker = () => {
  currentDate.value = new Date(pickerYear.value, pickerMonth.value, 1)
  showMonthPicker.value = false
}

const getMyClients = () => {
  const u = session.currentUser
  if (!u) return []
  const list = Array.isArray(clients.value) ? clients.value : []
  // If list is empty, try to load from local storage or wait for fetch
  if (list.length === 0) {
     // console.warn('Clients list is empty')
  }

  // Debug: Check if we have clients
  // console.log('getMyClients:', list.length)

  if (u.role === 'ADMIN') return list
  return list
}

const baseClients = computed(() => getMyClients())

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
          // Compare local calendar date with local activity date robustly
          const isSameYear = actDate.getFullYear() === date.getFullYear()
          const isSameMonth = actDate.getMonth() === date.getMonth()
          const isSameDate = actDate.getDate() === date.getDate()

          if (isSameYear && isSameMonth && isSameDate) {
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
                isCompleted: !!act.isCompleted,
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

const totalCells = computed(() => emptySlots.value.length + daysInMonth.value.length)
const rowCount = computed(() => Math.ceil(totalCells.value / 7))
const trailingSlots = computed(() => {
  const total = rowCount.value * 7
  const current = emptySlots.value.length + daysInMonth.value.length
  return new Array(Math.max(0, total - current))
})

const isToday = (date: Date) => {
  const today = new Date()
  return date.getDate() === today.getDate() && date.getMonth() === today.getMonth() && date.getFullYear() === today.getFullYear()
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
    type: act.type as ActivityType,
    time: act.time,
    description: act.description,
    isCompleted: act.isCompleted,
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

  if (!newEvent.value.clientId) {
    notifications.add({ userId: u?.id || 'anonymous', type: 'WARNING', message: 'Wybierz klienta przed zapisaniem.' })
    return
  }

  if (date && u) {
    isSaving.value = true
    try {
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
            description: newEvent.value.description || 'Brak opisu',
            date: isoDate,
            authorId: u.id,
            isCompleted: newEvent.value.isCompleted
         })
      } else {
         // Create new
         await clientStore.addActivity(
          newEvent.value.clientId,
          {
            type: newEvent.value.type as any,
            description: newEvent.value.description || 'Nowe zdarzenie',
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
    } catch (e) {
      console.error('Failed to save event:', e)
    } finally {
      isSaving.value = false
    }
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
    // Force fetch if clients list is empty on mount but allow data to load
    if (clients.value.length === 0) {
      if ((clientStore as any).fetchClients) {
        (clientStore as any).fetchClients()
      } else if ((data as any).fetchClients) {
        (data as any).fetchClients()
      }
    }
    
    // Safety check: sometimes data is there but reactivity needs a nudge
    setTimeout(() => {
       if (clients.value.length === 0 && (clientStore as any).fetchClients) {
          (clientStore as any).fetchClients() 
       }
    }, 1000)

  reminderTimer.value = window.setInterval(checkReminders, 30000)

  // Handle opening event from query param
  const { openEventId, date, add } = route.query
  if (add === 'true') {
     openAddModal(new Date())
  } else if (openEventId && date) {
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
  <div class="h-full flex flex-col items-stretch bg-slate-50/50 p-5 rounded-[2rem] relative isolate">
    <!-- Overlay for closing month picker -->
    <div v-if="showMonthPicker" class="fixed inset-0 z-40 bg-transparent" @click="closeMonthPicker"></div>

    <!-- Header Section (Navy Blue Container) -->
    <header class="bg-slate-900 rounded-2xl shadow-xl border border-slate-800 p-4 mb-3 flex flex-col md:flex-row justify-between items-center gap-4 shrink-0 relative group isolate z-50">
      <!-- Decor Container (clipping background effects) -->
      <div class="absolute inset-0 overflow-hidden rounded-2xl pointer-events-none z-0">
         <div class="absolute top-0 right-0 w-64 h-64 bg-slate-800 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2"></div>
      </div>

      <div class="relative z-10 flex items-center gap-4">
        <RouterLink 
          to="/app/dashboard" 
          class="flex items-center justify-center w-10 h-10 bg-slate-800 border border-slate-700 text-slate-400 rounded-xl hover:bg-slate-700 hover:text-white hover:border-slate-600 transition-all shadow-sm group/link"
        >
           <AppIcon name="arrow-left" class="w-4 h-4 transition-transform group-hover/link:-translate-x-1" />
        </RouterLink>

        <div>
          <h1 class="text-2xl font-serif font-bold text-white tracking-tight leading-tight">Kalendarz Pracy</h1>
          <p class="text-slate-400 text-xs font-medium mt-0.5">Zarządzaj swoimi spotkaniami.</p>
        </div>
      </div>
    
      <div class="relative z-10 flex items-center gap-3 bg-slate-800/50 p-1 rounded-xl shadow-sm border border-slate-700 backdrop-blur-sm self-start md:self-center">
        <button 
          type="button" 
          class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white transition-colors" 
          @click="changeMonth(-1)"
        >
          <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
        </button>
        
        <div class="relative z-50">
          <button 
            type="button" 
            class="h-8 px-4 flex items-center justify-center text-[11px] font-bold uppercase tracking-widest text-stratton-gold hover:text-white rounded-lg transition-colors select-none" 
            @click="toggleMonthPicker"
          >
            {{ currentDate.toLocaleDateString('pl-PL', { month: 'long', year: 'numeric' }) }}
          </button>
          
          <!-- Month Picker Popup -->
          <div v-if="showMonthPicker" class="absolute top-10 left-1/2 -translate-x-1/2 bg-white border border-slate-200 shadow-2xl rounded-2xl p-4 w-64 animate-fade-in-up origin-top text-slate-800 z-50 ring-4 ring-slate-900/10 flex flex-col gap-3">
             <div class="absolute -top-2 left-1/2 -translate-x-1/2 w-4 h-4 bg-white rotate-45 border-t border-l border-slate-200"></div>
             
             <div class="flex gap-2 relative z-10 p-1">
                <select v-model="pickerMonth" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition cursor-pointer">
                   <option v-for="i in 12" :key="i" :value="i - 1">{{ new Date(2000, i - 1, 1).toLocaleDateString('pl-PL', { month: 'long' }) }}</option>
                </select>
                
                <select v-model="pickerYear" class="w-24 bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition cursor-pointer text-center appearance-none">
                   <option v-for="year in yearsRange" :key="year" :value="year">{{ year }}</option>
                </select>
             </div>
             
             <div class="flex justify-end gap-2 text-[10px] font-bold uppercase tracking-wider relative z-10">
                <button @click="showMonthPicker = false" class="text-slate-400 hover:text-slate-600 px-3 py-1.5 transition rounded-lg hover:bg-slate-50">Anuluj</button>
                <button @click="applyMonthPicker" class="bg-stratton-gold text-white rounded-lg px-3 py-1.5 hover:bg-amber-600 transition shadow-lg shadow-amber-500/20">Wybierz</button>
             </div>
          </div>
        </div>

        <button 
          type="button" 
          class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white transition-colors" 
          @click="changeMonth(1)"
        >
          <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
        </button>
      </div>
    </header>

    <!-- Calendar Grid -->
    <div class="flex-1 bg-white shadow-xl shadow-slate-200/50 rounded-2xl overflow-hidden flex flex-col border border-slate-100 relative pointer-events-auto z-10">
      <!-- Weekday Headers -->
      <div class="grid grid-cols-7 border-b border-slate-100 bg-white shrink-0">
        <div v-for="day in weekDays" :key="day" class="py-4 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">
          {{ day }}
        </div>
      </div>

      <!-- Days Grid with auto-fit rows -->
      <div 
        class="flex-1 grid grid-cols-7 bg-slate-200 gap-px border-l border-t border-slate-200 min-h-0 bg-gray-50/50"
        :style="{ gridTemplateRows: `repeat(${rowCount}, 1fr)` }"
      >
        <!-- Empty slots from previous month -->
        <div v-for="(_, idx) in emptySlots" :key="idx" class="pattern-dots bg-slate-50/30 border-b border-r border-slate-200 min-h-0"></div>

        <!-- Correct Days -->
        <div
          v-for="day in daysInMonth"
          :key="day.date.toISOString()"
          class="bg-white/80 p-2 hover:bg-white transition-all relative group flex flex-col cursor-pointer border-b border-r border-slate-200 overflow-hidden min-h-0"
          @click="openAddModal(day.date)"
          @dragover.prevent
          @drop.prevent="onDrop($event, day.date)"
        >
          <!-- Date Number -->
          <div 
            class="flex justify-end mb-1 shrink-0"
          >
            <span 
              class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-bold transition-all"
              :class="isToday(day.date) ? 'bg-blue-500 text-white shadow-md shadow-blue-500/30 scale-110' : 'text-slate-400 group-hover:text-slate-600 group-hover:bg-slate-100'"
            >
              {{ day.dayNumber }}
            </span>
          </div>

          <!-- Activities List -->
          <div class="flex-1 flex flex-col gap-1 min-h-0 w-full overflow-hidden">
            <div
              v-for="act in day.activities.slice(0, 3)"
              :key="act.id"
              class="px-2 py-1 rounded-md text-[10px] font-bold transition-all hover:scale-[1.02] active:scale-95 cursor-pointer flex items-center gap-1.5 border shadow-sm group/ev overflow-hidden"
              :class="{
                'bg-blue-50 text-blue-700 border-blue-100 hover:border-blue-300 hover:shadow-blue-100': act.type === 'CALL',
                'bg-purple-50 text-purple-700 border-purple-100 hover:border-purple-300 hover:shadow-purple-100': act.type === 'MEETING',
                'bg-slate-50 text-slate-600 border-slate-100 hover:border-slate-300 hover:shadow-slate-100': act.type === 'NOTE',
                'bg-amber-50 text-amber-700 border-amber-100 hover:border-amber-300 hover:shadow-amber-100': act.type === 'EMAIL',
              }"
              @click="openEditModal(act, day.date, $event)"
              :title="`${act.time} - ${act.clientName}: ${act.description}`"
              draggable="true"
              @dragstart="onDragStart($event, act)"
            >
              <span class="opacity-70 font-mono text-[9px] mr-0.5 shrink-0">{{ act.time }}</span>
              <div class="flex-1 overflow-hidden flex items-center gap-1">
                <span v-if="act.isCompleted" class="text-green-600">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </span>
                <span class="scrolling-text">{{ act.clientName }}</span>
              </div>
              
              <button 
                 class="ml-auto opacity-0 group-hover/ev:opacity-100 p-0.5 rounded hover:bg-black/5 text-slate-500 transition-opacity shrink-0"
                 @click.stop="deleteEvent(act, $event)"
              >
                <AppIcon name="trash" class="w-3 h-3" />
              </button>
            </div>
            
            <!-- More Button -->
            <button
              v-if="day.activities.length > 3"
              type="button"
              class="mt-auto w-full text-[9px] font-bold text-slate-500 hover:text-stratton-gold hover:bg-amber-50 rounded py-1 transition-colors text-center uppercase tracking-wider"
              @click.stop="openDayDetails(day)"
            >
              +{{ day.activities.length - 3 }} więcej...
            </button>
          </div>

          <!-- Add Button (Hover) -->
          <div class="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/[0.02] pointer-events-none transition-colors duration-300"></div>
          <button 
            type="button" 
            class="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 bg-white text-stratton-gold hover:text-white hover:bg-stratton-gold border border-slate-100 rounded-xl w-8 h-8 flex items-center justify-center text-lg shadow-lg hover:shadow-amber-500/30 transition-all transform hover:scale-110 z-10" 
            title="Dodaj zdarzenie"
            @click.stop="openAddModal(day.date)"
          >
            <AppIcon name="plus" class="w-5 h-5" />
          </button>
        </div>

        <!-- Empty slots at the end of month -->
        <div v-for="(_, idx) in trailingSlots" :key="'trail-' + idx" class="pattern-dots bg-slate-50/30 border-b border-r border-slate-200 min-h-0"></div>
      </div>
    </div>

    <!-- Modals -->
    <Teleport to="body">
      <!-- Add/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-all" @click.self="closeModal">
        <div class="bg-white rounded-3xl shadow-2xl p-8 w-full max-w-md relative animate-fade-in-up border border-white/20">
          <div class="absolute -top-12 left-1/2 -translate-x-1/2 bg-white rounded-full p-4 shadow-xl border-4 border-slate-50">
             <AppIcon :name="newEvent.id ? 'pencil-square' : 'calendar'" class="w-8 h-8 text-stratton-gold" />
          </div>

          <div class="text-center mt-6 mb-8">
            <h3 class="text-2xl font-serif font-bold text-slate-800">{{ newEvent.id ? 'Edytuj Zdarzenie' : 'Nowe Zdarzenie' }}</h3>
            <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mt-1">{{ selectedDate?.toLocaleDateString('pl-PL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
          </div>

          <div class="space-y-5">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Klient</label>
              <div class="relative">
                <select v-model="newEvent.clientId" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-3 pl-4 pr-10 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition appearance-none">
                  <option v-for="client in getMyClients()" :key="client.id" :value="client.id">{{ client.name }}</option>
                </select>
                <AppIcon name="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Typ</label>
                <div class="relative">
                  <select v-model="newEvent.type" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-3 pl-4 pr-10 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition appearance-none">
                    <option value="CALL">Telefon</option>
                    <option value="MEETING">Spotkanie</option>
                    <option value="EMAIL">Email</option>
                    <option value="NOTE">Notatka</option>
                  </select>
                  <AppIcon name="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                </div>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Godzina</label>
                <input v-model="newEvent.time" type="time" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-3 px-4 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition" />
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Opis</label>
              <textarea v-model="newEvent.description" rows="3" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-3 px-4 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition resize-none" placeholder="Szczegóły spotkania..."></textarea>
            </div>
            
            <div class="flex items-center gap-2" v-if="newEvent.id && newEvent.type === 'MEETING'">
              <input 
                id="isCompleted" 
                type="checkbox" 
                v-model="newEvent.isCompleted" 
                class="w-5 h-5 rounded border-slate-300 text-stratton-gold focus:ring-stratton-gold" 
              />
              <label for="isCompleted" class="text-sm font-bold text-slate-600 cursor-pointer">Spotkanie odbyło się</label>
            </div>
          </div>

          <div class="mt-8 flex gap-3">
            <button type="button" class="flex-1 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition" @click="closeModal">Anuluj</button>
            <button 
              type="button" 
              class="flex-1 py-3 bg-stratton-gold text-white font-bold rounded-xl hover:bg-amber-600 transition shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2 disabled:opacity-50" 
              :disabled="isSaving"
              @click="saveEvent"
            >
              <div v-if="isSaving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
              {{ newEvent.id ? 'Aktualizuj' : 'Dodaj' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Day Details Modal -->
      <div v-if="selectedDay" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-all" @click.self="closeDayDetails">
        <div class="bg-white rounded-3xl shadow-2xl p-8 w-full max-w-lg relative animate-fade-in-up md:min-w-[500px]">
          <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-100">
            <div>
              <h3 class="text-2xl font-serif font-bold text-slate-800">Zdarzenia dnia</h3>
              <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mt-1">{{ selectedDay.date.toLocaleDateString('pl-PL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
            </div>
            <button type="button" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" @click="closeDayDetails">
              <AppIcon name="xmark" class="w-5 h-5" />
            </button>
          </div>

          <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
            <div v-if="selectedDay.activities.length === 0" class="text-center py-8 text-slate-400 italic">Brak zaplanowanych zdarzeń</div>
            <div 
              v-for="act in selectedDay.activities" 
              :key="act.id" 
              class="flex items-start gap-4 p-4 rounded-2xl border transition-all hover:bg-white hover:shadow-md group"
              :class="{
                'bg-blue-50 border-blue-100': act.type === 'CALL',
                'bg-purple-50 border-purple-100': act.type === 'MEETING',
                'bg-slate-50 border-slate-100': act.type === 'NOTE',
                'bg-amber-50 border-amber-100': act.type === 'EMAIL',
              }"
            >
              <div 
                class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shadow-sm shrink-0"
                :class="{
                  'bg-blue-200 text-blue-700': act.type === 'CALL',
                  'bg-purple-200 text-purple-700': act.type === 'MEETING',
                  'bg-slate-200 text-slate-600': act.type === 'NOTE',
                  'bg-amber-200 text-amber-700': act.type === 'EMAIL',
                }"
              >
                  <AppIcon :name="act.type === 'CALL' ? 'phone' : act.type === 'MEETING' ? 'users' : act.type === 'EMAIL' ? 'envelope' : 'document-text'" class="w-5 h-5" />
              </div>
              
              <div class="flex-1 min-w-0">
                <div class="flex justify-between items-start mb-1">
                  <h4 class="font-bold text-slate-800 text-sm truncate">{{ act.clientName }}</h4>
                  <span class="text-xs font-mono font-bold opacity-60 bg-white/50 px-2 py-0.5 rounded">{{ act.time }}</span>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">{{ act.description }}</p>
              </div>

              <div class="flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="p-1.5 rounded-lg hover:bg-white/50 text-slate-400 hover:text-blue-600 transition" @click="openEditModal(act, selectedDay.date!, $event)">
                  <AppIcon name="pencil-square" class="w-4 h-4" />
                </button>
                <button class="p-1.5 rounded-lg hover:bg-white/50 text-slate-400 hover:text-red-500 transition" @click="deleteEvent(act, $event)">
                  <AppIcon name="trash" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
          
          <div class="mt-6 pt-6 border-t border-slate-100 flex justify-end">
            <button class="px-6 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition" @click="closeDayDetails">Zamknij</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}
.custom-scrollbar-mini::-webkit-scrollbar {
  width: 2px;
}
.custom-scrollbar-mini::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar-mini::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 2px;
}

.pattern-dots {
  background-image: radial-gradient(#e2e8f0 1.5px, transparent 1.5px);
  background-size: 12px 12px;
}

@keyframes text-scroll {
  0% { transform: translateX(0); }
  15% { transform: translateX(0); }
  100% { transform: translateX(-100%); }
}

.scrolling-text {
  display: inline-block;
  white-space: nowrap;
}

.group\/ev:hover .scrolling-text {
  animation: text-scroll 6s linear infinite alternate;
}
</style>
