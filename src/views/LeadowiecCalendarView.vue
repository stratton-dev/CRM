<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import TabHeader from '@/components/ui/TabHeader.vue'

interface Meeting {
  id: number
  client_id: number
  client?: { id: number; name: string }
  user?: { id: number; name: string }
  status: string
  valid_until?: string
  created_at: string
}

const meetings = ref<Meeting[]>([])
const loading = ref(false)
const currentMonth = ref(new Date())

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString('pl-PL', { month: 'long', year: 'numeric' })
)

const fetchMeetings = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/v1/meetings', { params: { per_page: 200 } })
    meetings.value = Array.isArray(data) ? data : data.data ?? []
  } catch {
    meetings.value = []
  } finally {
    loading.value = false
  }
}

const prevMonth = () => {
  const d = new Date(currentMonth.value)
  d.setMonth(d.getMonth() - 1)
  currentMonth.value = d
}

const nextMonth = () => {
  const d = new Date(currentMonth.value)
  d.setMonth(d.getMonth() + 1)
  currentMonth.value = d
}

const meetingsInMonth = computed(() => {
  const y = currentMonth.value.getFullYear()
  const m = currentMonth.value.getMonth()
  return meetings.value.filter((meet) => {
    const d = new Date(meet.created_at)
    return d.getFullYear() === y && d.getMonth() === m
  })
})

const calendarDays = computed(() => {
  const y = currentMonth.value.getFullYear()
  const m = currentMonth.value.getMonth()
  const firstDay = new Date(y, m, 1)
  const lastDay = new Date(y, m + 1, 0)

  const startDow = (firstDay.getDay() + 6) % 7
  const days: Array<{ date: Date | null; meetings: Meeting[] }> = []

  for (let i = 0; i < startDow; i++) days.push({ date: null, meetings: [] })

  for (let d = 1; d <= lastDay.getDate(); d++) {
    const date = new Date(y, m, d)
    const dayMeetings = meetings.value.filter((meet) => {
      const md = new Date(meet.created_at)
      return md.getFullYear() === y && md.getMonth() === m && md.getDate() === d
    })
    days.push({ date, meetings: dayMeetings })
  }

  while (days.length % 7 !== 0) days.push({ date: null, meetings: [] })
  return days
})

const statusLabel = (s: string) => {
  const map: Record<string, string> = { open: 'Aktywne', completed: 'Zakończone', expired: 'Wygasłe' }
  return map[s] ?? s
}

const statusColor = (s: string) => {
  const map: Record<string, string> = {
    open: 'bg-green-100 text-green-700',
    completed: 'bg-blue-100 text-blue-700',
    expired: 'bg-red-100 text-red-600',
  }
  return map[s] ?? 'bg-slate-100 text-slate-600'
}

const isToday = (date: Date | null) => {
  if (!date) return false
  const t = new Date()
  return date.getDate() === t.getDate() && date.getMonth() === t.getMonth() && date.getFullYear() === t.getFullYear()
}

onMounted(fetchMeetings)
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)] bg-slate-50">

    <TabHeader icon="calendar" title="Kalendarz spotkań">
      <template #actions>
        <div class="flex items-center gap-2">
          <button @click="prevMonth" class="p-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 transition-colors">
            <AppIcon name="chevron-left" class="w-4 h-4 text-slate-600" />
          </button>
          <span class="text-sm font-semibold text-slate-700 capitalize min-w-[140px] text-center">{{ monthLabel }}</span>
          <button @click="nextMonth" class="p-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 transition-colors">
            <AppIcon name="chevron-right" class="w-4 h-4 text-slate-600" />
          </button>
        </div>
      </template>
    </TabHeader>

    <div class="flex-1 overflow-y-auto p-6">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-slate-300 border-t-[#C5A059] rounded-full animate-spin" />
    </div>

    <template v-else>
      <!-- Calendar grid -->
      <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm mb-6">
        <!-- Day headers -->
        <div class="grid grid-cols-7 border-b border-slate-100">
          <div
            v-for="day in ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz']"
            :key="day"
            class="py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider"
          >{{ day }}</div>
        </div>

        <!-- Weeks -->
        <div class="grid grid-cols-7">
          <div
            v-for="(cell, i) in calendarDays"
            :key="i"
            class="min-h-[80px] border-b border-r border-slate-100 last:border-r-0 p-1.5"
            :class="isToday(cell.date) ? 'bg-blue-50/50' : ''"
          >
            <div v-if="cell.date">
              <span
                class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium mb-1"
                :class="isToday(cell.date) ? 'text-white' : 'text-slate-600'"
                :style="isToday(cell.date) ? 'background: linear-gradient(135deg, #001f3d, #003366)' : ''"
              >{{ cell.date.getDate() }}</span>
              <div v-for="meet in cell.meetings" :key="meet.id" class="mb-0.5">
                <span class="text-[10px] px-1.5 py-0.5 rounded block truncate" :class="statusColor(meet.status)">
                  {{ meet.client?.name || 'Klient' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Meeting list for current month -->
      <div>
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider mb-3">
          Spotkania w {{ monthLabel }} ({{ meetingsInMonth.length }})
        </h2>

        <div v-if="!meetingsInMonth.length" class="text-sm text-slate-400 py-4 text-center">
          Brak spotkań w tym miesiącu.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="meet in meetingsInMonth"
            :key="meet.id"
            class="bg-white rounded-xl border border-slate-200 px-4 py-3 flex items-center justify-between gap-4 shadow-sm"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #001f3d, #003366)">
                <AppIcon name="calendar" class="w-4 h-4 text-white" />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ meet.client?.name || 'Nieznany klient' }}</p>
                <p class="text-xs text-slate-400">
                  Opiekun: {{ meet.user?.name || '—' }} ·
                  {{ new Date(meet.created_at).toLocaleDateString('pl-PL') }}
                </p>
              </div>
            </div>
            <span class="text-xs px-2.5 py-1 rounded-full font-medium shrink-0" :class="statusColor(meet.status)">
              {{ statusLabel(meet.status) }}
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
