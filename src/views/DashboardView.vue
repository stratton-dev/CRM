<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '@/stores/dashboard'
import { useClientStore } from '@/stores/client'
import { useMailboxStore } from '@/stores/mailbox'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import AdminPanelView from '@/views/admin/AdminPanelView.vue'
import AppIcon from '@/components/AppIcon.vue'

type UserRole = 'SALES' | 'MANAGER' | 'DIRECTOR' | 'ADMIN' | 'CLIENT_HR'

const session = useSessionStore()
const router = useRouter()
const dashboard = useDashboardStore()
const clientStore = useClientStore()
const mailboxStore = useMailboxStore()
const viewPermissions = useViewPermissionsStore()
const { clients } = storeToRefs(clientStore)
const { emails: mailboxEmails } = storeToRefs(mailboxStore)
const props = withDefaults(defineProps<{ showAdminPanel?: boolean }>(), {
  showAdminPanel: true,
})

const userRole = ref<UserRole>('SALES')
const viewMode = ref<'hub' | 'stats'>('hub')
const isAdmin = computed(() => userRole.value === 'ADMIN')
const firstName = computed(() => session.currentUser?.name?.split(' ')[0] || 'Użytkowniku')

const canAddClient = computed(() => viewPermissions.isViewAllowed('sales-start', session.currentUser?.role))
const handleAddClientClick = (e: Event) => {
  if (!canAddClient.value) {
    e.preventDefault()
    alert(`Brak uprawnień do "Dodaj Klienta". Twoja rola: ${session.currentUser?.role || 'Nieznana'}`)
  }
}

const selectedPeriod = ref('current')
const viewScope = ref('structure')
const showTargets = ref(true)
const isRefreshing = ref(false)

const leadCount = computed(() => {
  const list = Array.isArray(clients.value) ? clients.value : []
  return list.filter((client: any) => client.status === 'NEW').length
})

const todaysMeetings = computed(() => {
  const today = new Date()
  const list = Array.isArray(clients.value) ? clients.value : []
  let count = 0
  list.forEach((c) => {
    if (c.activityHistory) {
      c.activityHistory.forEach((a: any) => {
        if (a.type === 'MEETING') {
          const d = new Date(a.date)
          if (d.getDate() === today.getDate() && d.getMonth() === today.getMonth() && d.getFullYear() === today.getFullYear()) {
            count++
          }
        }
      })
    }
  })
  return count
})

const upcomingEvents = computed(() => {
  const list: any[] = []
  const now = new Date()
  const limitDate = new Date()
  limitDate.setDate(now.getDate() + 3)
  limitDate.setHours(23, 59, 59, 999)

  const relevantTypes = ['MEETING', 'CALL']
  const allClients = Array.isArray(clients.value) ? clients.value : []
  
  allClients.forEach((client: any) => {
    if (client.activityHistory) {
      client.activityHistory.forEach((act: any) => {
        const d = new Date(act.date)
        if (d >= now && d <= limitDate && relevantTypes.includes(act.type)) {
           list.push({
             id: act.id,
             dateObj: d,
             month: d.toLocaleDateString('pl-PL', { month: 'short' }).toUpperCase(),
             day: String(d.getDate()).padStart(2, '0'),
             time: d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
             title: `${act.type === 'MEETING' ? 'Spotkanie' : 'Telefon'}: ${client.name}`,
           })
        }
      })
    }
  })

  // Mix in dashboard general events if any
  if (Array.isArray(dashboard.events)) {
    dashboard.events.forEach((ev) => {
      const d = new Date(ev.start_at)
      if (d >= now && d <= limitDate) {
         list.push({
             id: ev.id,
             dateObj: d,
             month: d.toLocaleDateString('pl-PL', { month: 'short' }).toUpperCase(),
             day: String(d.getDate()).padStart(2, '0'),
             time: d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
             title: ev.title,
         })
      }
    })
  }

  list.sort((a, b) => a.dateObj.getTime() - b.dateObj.getTime())
  
  return list
})

const goToEvent = (event: any) => {
  if (event.id && event.dateObj) {
    router.push({ 
      path: '/app/calendar', 
      query: { 
        openEventId: event.id, 
        date: event.dateObj.toISOString() 
      } 
    })
  }
}

const newsItems = computed(() => dashboard.news.map((item) => ({
  id: item.id,
  tag: item.tag || 'INFO',
  title: item.title,
  desc: item.description,
})))

const kpis = computed(() => dashboard.kpis.map((item) => ({
  id: item.id,
  title: item.title,
  value: item.value,
  score: item.score,
  minTarget: item.min_target,
  subtitle: item.subtitle,
  missing: item.missing,
})))

const primaryKpi = computed(() => kpis.value[0] || null)
const goalScore = computed(() => {
  const score = Number(primaryKpi.value?.score ?? 0)
  return Math.min(100, Math.max(0, score))
})
const goalLabel = computed(() => primaryKpi.value?.title || 'Cel sprzedażowy')

const unreadCount = computed(() => {
  const userEmail = session.currentUser?.email
  const list = Array.isArray(mailboxEmails.value) ? mailboxEmails.value : []
  if (!userEmail) return 0
  return list.filter((email) => email.toEmail === userEmail && email.folder === 'INBOX' && !email.read).length
})

const activeCalculations = computed(() => dashboard.calculations.map((calc) => {
  const validUntil = calc.valid_until ? new Date(calc.valid_until) : null
  const daysLeft = validUntil ? Math.ceil((validUntil.getTime() - Date.now()) / (1000 * 60 * 60 * 24)) : null
  return {
    id: calc.id,
    company: calc.company,
    nip: calc.nip || '',
    meetingId: calc.meeting_id || '',
    clientId: calc.client_id || '',
    date: calc.calculation_date || '',
    validUntil: calc.valid_until || '',
    daysLeft,
    status: calc.status || '',
  }
}))

const openCalculation = (calc: { meetingId?: string; clientId?: string }) => {
  router.push({
    path: '/app/calculator',
    query: {
      meetingId: calc.meetingId || undefined,
      clientId: calc.clientId || undefined,
    },
  })
}

const overdueInvoices = computed(() => dashboard.overdueInvoices.map((invoice) => {
  const dueDate = invoice.due_date ? new Date(invoice.due_date) : null
  const daysOverdue = typeof invoice.days_overdue === 'number'
    ? invoice.days_overdue
    : dueDate
      ? Math.max(0, Math.ceil((Date.now() - dueDate.getTime()) / (1000 * 60 * 60 * 24)))
      : null
  return {
    id: invoice.id,
    company: invoice.company,
    number: invoice.number,
    amountGross: invoice.amount_gross,
    issueDate: invoice.issue_date || '',
    dueDate: invoice.due_date || '',
    daysOverdue,
    status: invoice.status || 'UNPAID',
  }
}))

const refreshData = () => {
  isRefreshing.value = true
  dashboard.fetchDashboard(session.currentUser?.id, {
    from_date: dateFrom.value || undefined,
    to_date: dateTo.value || undefined,
  }).finally(() => {
    isRefreshing.value = false
  })
}

const toggleRole = () => {
  const roles: UserRole[] = ['SALES', 'MANAGER', 'DIRECTOR', 'ADMIN', 'CLIENT_HR']
  const current = userRole.value
  const next = roles[(roles.indexOf(current) + 1) % roles.length]
  userRole.value = next
}

const getScoreColor = (score: number) => {
  if (score >= 90) return 'border-green-500'
  if (score >= 70) return 'border-stratton-gold'
  return 'border-red-400'
}

onMounted(() => {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  dateFrom.value = start.toISOString().slice(0, 10)
  dateTo.value = end.toISOString().slice(0, 10)
  dashboard.fetchDashboard(session.currentUser?.id, {
    from_date: dateFrom.value,
    to_date: dateTo.value,
  })
})

watch(
  () => session.currentUser?.role,
  (role) => {
    if (role) userRole.value = role as UserRole
  },
  { immediate: true }
)

watch(
  () => [dateFrom.value, dateTo.value],
  ([from, to]) => {
    if (!from || !to) return
    refreshData()
  }
)
</script>

<template>
  <div class="view-transition pb-20 space-y-8">
    <AdminPanelView v-if="isAdmin && props.showAdminPanel" />

    <div v-else-if="viewMode === 'hub'" class="max-w-7xl mx-auto pt-6">
      <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="flex-1">
          <h1 class="font-serif font-bold text-4xl text-slate-900 mb-3">Dzień dobry, {{ firstName }}</h1>
          <div class="flex flex-col gap-2 max-w-md">
            <div class="flex justify-between items-baseline">
              <span class="text-sm font-bold text-slate-500">{{ goalLabel }}:</span>
              <span class="text-sm font-bold text-stratton-gold">{{ goalScore }}% zrealizowane</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-stratton-gold rounded-full" :style="{ width: `${goalScore}%` }"></div>
            </div>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="bg-white border border-slate-200 shadow-sm rounded-2xl p-4 w-32 flex flex-col items-center justify-center h-24">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Leady Nowe</span>
            <span class="text-3xl font-bold text-slate-900">{{ leadCount }}</span>
          </div>
          <div class="bg-white border border-slate-200 shadow-sm rounded-2xl p-4 w-32 flex flex-col items-center justify-center h-24">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Spotkania</span>
            <span class="text-3xl font-bold text-blue-600">{{ todaysMeetings }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-12">
        <RouterLink :to="canAddClient ? '/app/sales/start' : ''" @click="handleAddClientClick" class="bg-white rounded-2xl shadow-sm p-8 flex flex-col items-center justify-center gap-4 text-center border-2 border-green-400 transition group h-48" :class="canAddClient ? 'cursor-pointer hover:shadow-lg hover:-translate-y-1' : 'opacity-50 grayscale cursor-not-allowed'">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-2xl group-hover:bg-green-600 group-hover:text-white transition">
            <AppIcon name="user-plus" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Dodaj Klienta</h3>
            <p class="text-xs text-slate-400 mt-1">Rozpocznij proces</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/quick-calculator" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl group-hover:bg-blue-600 group-hover:text-white transition">
            <AppIcon name="calculator" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Kalkulator oszczędności</h3>
            <p class="text-xs text-slate-400 mt-1">Szybka wycena</p>
          </div>
        </RouterLink>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48" @click="viewMode = 'stats'">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl group-hover:bg-indigo-600 group-hover:text-white transition">
            <AppIcon name="chart-pie" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Dashboard</h3>
            <p class="text-xs text-slate-400 mt-1">Wyniki i Prowizje</p>
          </div>
        </div>

        <RouterLink to="/app/mailbox" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-2xl group-hover:bg-amber-600 group-hover:text-white transition">
            <AppIcon name="envelope" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Poczta</h3>
            <p class="text-xs text-slate-400 mt-1">Skrzynka ({{ unreadCount }})</p>
          </div>
        </RouterLink>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-red-50 text-red-600 flex items-center justify-center text-2xl group-hover:bg-red-600 group-hover:text-white transition">
            <AppIcon name="filter" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Leady</h3>
            <p class="text-xs text-slate-400 mt-1">Kampanie</p>
          </div>
        </div>

        <RouterLink to="/app/knowledge-base" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center text-2xl group-hover:bg-teal-600 group-hover:text-white transition">
            <AppIcon name="graduation-cap" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Baza Wiedzy</h3>
            <p class="text-xs text-slate-400 mt-1">Wiedza i Certyfikaty</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/structure" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center text-2xl group-hover:bg-orange-600 group-hover:text-white transition">
            <AppIcon name="people-group" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Mój Zespół</h3>
            <p class="text-xs text-slate-400 mt-1">Struktura i Wyniki</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/clients" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-cyan-50 text-cyan-600 flex items-center justify-center text-2xl group-hover:bg-cyan-600 group-hover:text-white transition">
            <AppIcon name="address-book" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Klienci</h3>
            <p class="text-xs text-slate-400 mt-1">Baza Kontaktów</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/calendar" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-pink-50 text-pink-600 flex items-center justify-center text-2xl group-hover:bg-pink-600 group-hover:text-white transition">
            <AppIcon name="calendar" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Kalendarz</h3>
            <p class="text-xs text-slate-400 mt-1">Harmonogram</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/settlements" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 flex flex-col items-center justify-center gap-4 text-center cursor-pointer hover:shadow-lg hover:-translate-y-1 transition group h-48">
          <div class="w-14 h-14 min-w-14 min-h-14 aspect-square shrink-0 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-2xl group-hover:bg-purple-600 group-hover:text-white transition">
            <AppIcon name="wallet" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-800 text-lg">Rozliczenia</h3>
            <p class="text-xs text-slate-400 mt-1">Finanse</p>
          </div>
        </RouterLink>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
          <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-slate-700 uppercase text-xs tracking-wider border-l-4 border-stratton-gold pl-3">Najbliższe Wydarzenia</h3>
            <div class="flex gap-2 text-slate-400">
              <AppIcon name="list" class="w-4 h-4 cursor-pointer hover:text-slate-600" />
              <AppIcon name="calendar" class="w-4 h-4 cursor-pointer hover:text-slate-600" />
            </div>
          </div>
          <div class="space-y-4">
            <div v-for="event in upcomingEvents" :key="event.id" class="bg-slate-50 rounded-xl p-4 flex items-center gap-4 hover:bg-slate-100 transition cursor-pointer group" @click="goToEvent(event)">
              <div class="bg-white rounded-lg p-2 text-center w-14 shadow-sm">
                <div class="text-[10px] text-slate-400 uppercase font-bold">{{ event.month }}</div>
                <div class="text-xl font-bold text-slate-800">{{ event.day }}</div>
              </div>
              <div class="flex-1">
                <h4 class="font-bold text-slate-800 text-sm group-hover:text-stratton-blue">{{ event.title }}</h4>
                <div class="text-xs text-slate-500 mt-1">
                  <AppIcon name="clock" class="w-3 h-3 mr-1 inline-block" /> {{ event.time }}
                </div>
              </div>
              <div class="text-slate-300">
                <AppIcon name="chevron-right" class="w-3 h-3" />
              </div>
            </div>
          </div>
          <div class="mt-6 text-center">
            <RouterLink to="/app/calendar" class="text-xs font-bold text-stratton-gold hover:text-stratton-dark uppercase tracking-wide">
              Pełny Kalendarz <AppIcon name="arrow-right" class="w-3 h-3 ml-1 inline-block" />
            </RouterLink>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
          <div class="flex justify-between items-center mb-6">
            <h3 class="font-bold text-slate-700 uppercase text-xs tracking-wider border-l-4 border-stratton-gold pl-3">Aktualności</h3>
            <div class="flex bg-slate-100 rounded-lg p-1">
              <button class="px-3 py-1 bg-white rounded shadow-sm text-[10px] font-bold text-slate-800">Dane</button>
              <button class="px-3 py-1 text-[10px] font-bold text-slate-500 hover:text-slate-700">Obraz</button>
            </div>
          </div>
          <div class="space-y-4">
            <div v-for="item in newsItems" :key="item.id" class="bg-white border border-slate-100 rounded-xl p-5 hover:shadow-md transition cursor-pointer">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded text-white" :class="item.tag === 'PRODUKT' ? 'bg-blue-500' : 'bg-amber-500'">
                  {{ item.tag }}
                </span>
              </div>
              <h4 class="font-bold text-slate-800 mb-1">{{ item.title }}</h4>
              <p class="text-xs text-slate-500">{{ item.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="space-y-8">
      <div class="flex items-center justify-between border-b border-slate-200 pb-6 animate-fade-in">
        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all shadow-sm group" @click="viewMode = 'hub'">
          <AppIcon name="arrow-left" class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
          <span class="text-xs font-bold uppercase tracking-widest">Powrót</span>
        </button>
        <div class="text-center">
          <h2 class="font-serif font-bold text-3xl text-slate-900">Dashboard</h2>
          <p class="text-xs text-slate-400 cursor-pointer hover:text-blue-500 mt-2 uppercase tracking-wider" @click="toggleRole">Widok: {{ userRole }}</p>
        </div>
        <div class="w-20"></div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col lg:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-4 w-full lg:w-auto">
          <div class="relative w-full lg:w-64">
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Od</label>
            <input v-model="dateFrom" type="date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 py-3 px-4 rounded-xl text-sm font-bold focus:outline-none focus:border-stratton-gold cursor-pointer transition hover:bg-slate-100" />
          </div>

          <div class="relative w-full lg:w-64">
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Do</label>
            <input v-model="dateTo" type="date" class="w-full bg-slate-50 border border-slate-200 text-slate-900 py-3 px-4 rounded-xl text-sm font-bold focus:outline-none focus:border-stratton-gold cursor-pointer transition hover:bg-slate-100" />
          </div>

          <div class="relative w-full lg:w-64">
            <select v-model="viewScope" class="w-full bg-slate-50 border border-slate-200 text-slate-900 py-3 pl-5 pr-10 rounded-xl text-sm font-bold focus:outline-none focus:border-stratton-gold cursor-pointer appearance-none transition hover:bg-slate-100">
              <option value="mine">Widok: Moje</option>
              <option v-if="userRole === 'MANAGER' || userRole === 'DIRECTOR'" value="structure">Widok: Struktura</option>
              <option v-if="userRole === 'DIRECTOR'" value="team">Widok: Zespół</option>
            </select>
            <AppIcon name="users" class="w-3 h-3 absolute right-4 top-4 text-slate-400 pointer-events-none" />
          </div>
        </div>

        <div class="flex items-center gap-8 w-full lg:w-auto justify-end">
          <label class="flex items-center cursor-pointer select-none group">
            <div class="relative">
              <input v-model="showTargets" type="checkbox" class="sr-only" />
              <div class="block bg-slate-200 w-12 h-7 rounded-full transition group-hover:bg-slate-300" :class="showTargets ? 'bg-stratton-gold' : ''"></div>
              <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full transition transform shadow-sm" :class="showTargets ? 'translate-x-5' : ''"></div>
            </div>
            <div class="ml-3 text-sm text-slate-600 font-bold">Pokaż cele</div>
          </label>
          <div class="h-10 w-px bg-slate-200"></div>
          <button type="button" class="flex items-center gap-3 text-slate-600 hover:text-stratton-blue transition px-6 py-3 rounded-xl bg-slate-50 hover:bg-white border border-slate-200 hover:shadow-sm" @click="refreshData">
            <AppIcon name="refresh" class="w-4 h-4" :class="isRefreshing ? 'animate-spin' : ''" />
            <span class="text-sm font-bold">Odśwież dane</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-6">
        <div v-for="kpi in kpis" :key="kpi.id" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 relative overflow-hidden group hover:shadow-lg hover:-translate-y-1 transition flex flex-col justify-between min-h-[200px]">
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest leading-tight w-2/3">{{ kpi.title }}</h3>
            <div class="w-3 h-3 rounded-full shadow-sm ring-2 ring-white" :class="kpi.score >= 90 ? 'bg-green-500' : kpi.score >= 70 ? 'bg-stratton-gold' : 'bg-red-500'"></div>
          </div>
          <div class="flex items-end justify-between">
            <div class="relative w-28 h-14 overflow-hidden shrink-0">
              <div class="absolute top-0 left-0 w-28 h-28 rounded-full border-[12px] border-slate-100"></div>
              <div class="absolute top-0 left-0 w-28 h-28 rounded-full border-[12px]" :class="getScoreColor(kpi.score)" :style="{ borderBottomColor: 'transparent', borderRightColor: 'transparent', transform: `rotate(${45 + kpi.score * 1.8}deg)` }"></div>
              <div class="absolute bottom-0 left-1/2 -translate-x-1/2 text-xs font-bold text-slate-400">{{ kpi.score }}%</div>
            </div>
            <div class="text-right">
              <p class="text-4xl font-bold text-slate-900 tracking-tighter">{{ kpi.value }}</p>
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-slate-50 text-right">
            <template v-if="showTargets">
              <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Cel minimalny</p>
              <p class="text-xs font-bold text-slate-700">{{ kpi.minTarget }}</p>
            </template>
            <p v-else class="text-[10px] uppercase font-bold text-slate-300 tracking-wider">Cele ukryte</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- New Gauges Row based on user snippet -->
        <div class="p-8 border-b border-slate-100 bg-slate-50/50">
           <div class="flex flex-col md:flex-row justify-around items-center gap-8 font-sans">
             
             <!-- Gauge 1: Zlecone kalkulacje -->
             <div class="flex flex-col items-center gap-4">
               <div class="relative w-[260px] h-[130px] overflow-hidden">
                  <div class="absolute top-0 left-0 w-full h-[200%] rounded-[50%]"
                      style="background: conic-gradient(from 270deg, #2ecc71 0deg, #f1c40f 90deg, #e74c3c 180deg);
                             mask: radial-gradient(circle, transparent 55%, black 56%);
                             -webkit-mask: radial-gradient(circle, transparent 55%, black 56%);">
                  </div>
                  <div class="absolute bottom-0 left-1/2 w-1.5 h-[90%] bg-red-600 z-10 origin-bottom rounded-t-full shadow-sm"
                       :style="{ transform: `translateX(-50%) rotate(${(Math.min(activeCalculations.length / 10, 1) * 180) - 90}deg)`, transition: 'transform 1s ease-in-out' }">
                  </div>
                  <div class="absolute bottom-[-6px] left-1/2 w-4 h-4 bg-slate-400 rounded-full -translate-x-1/2 z-20 border-2 border-white shadow-sm"></div>
                  <div class="absolute w-full h-full text-xs font-bold text-slate-500 uppercase">
                    <span class="absolute left-2 bottom-3 rotate-[-45deg]">0</span>
                    <span class="absolute left-1/2 top-2 -translate-x-1/2">5</span>
                    <span class="absolute right-2 bottom-3 rotate-[45deg]">10</span>
                  </div>
               </div>
               <div class="text-center">
                  <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">Zlecone kalkulacje</div>
                  <div class="text-2xl font-bold text-slate-900 mt-1">{{ activeCalculations.length }} szt.</div>
               </div>
             </div>

             <!-- Gauge 2: Ilość wprowadzonych klientów -->
             <div class="flex flex-col items-center gap-4">
               <div class="relative w-[260px] h-[130px] overflow-hidden">
                  <div class="absolute top-0 left-0 w-full h-[200%] rounded-[50%]"
                      style="background: conic-gradient(from 270deg, #2ecc71 0deg, #f1c40f 90deg, #e74c3c 180deg);
                             mask: radial-gradient(circle, transparent 55%, black 56%);
                             -webkit-mask: radial-gradient(circle, transparent 55%, black 56%);">
                  </div>
                  <div class="absolute bottom-0 left-1/2 w-1.5 h-[90%] bg-red-600 z-10 origin-bottom rounded-t-full shadow-sm"
                       :style="{ transform: `translateX(-50%) rotate(${(Math.min((clients.filter(c => c.status === 'NEW').length) / 10, 1) * 180) - 90}deg)`, transition: 'transform 1s ease-in-out' }">
                  </div>
                  <div class="absolute bottom-[-6px] left-1/2 w-4 h-4 bg-slate-400 rounded-full -translate-x-1/2 z-20 border-2 border-white shadow-sm"></div>
                  <div class="absolute w-full h-full text-xs font-bold text-slate-500 uppercase">
                    <span class="absolute left-2 bottom-3 rotate-[-45deg]">0</span>
                    <span class="absolute left-1/2 top-2 -translate-x-1/2">5</span>
                    <span class="absolute right-2 bottom-3 rotate-[45deg]">10</span>
                  </div>
               </div>
               <div class="text-center">
                  <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">Wprowadzeni Klienci</div>
                  <div class="text-2xl font-bold text-slate-900 mt-1">{{ clients.filter(c => c.status === 'NEW').length }} szt.</div>
               </div>
             </div>

             <!-- Gauge 3: Liczba podpisanych umów -->
             <div class="flex flex-col items-center gap-4">
               <div class="relative w-[260px] h-[130px] overflow-hidden">
                  <div class="absolute top-0 left-0 w-full h-[200%] rounded-[50%]"
                      style="background: conic-gradient(from 270deg, #e74c3c 0deg, #f1c40f 90deg, #2ecc71 180deg); /* Reversed colors for 'good is high' */
                             mask: radial-gradient(circle, transparent 55%, black 56%);
                             -webkit-mask: radial-gradient(circle, transparent 55%, black 56%);">
                  </div>
                  <div class="absolute bottom-0 left-1/2 w-1.5 h-[90%] bg-red-600 z-10 origin-bottom rounded-t-full shadow-sm"
                       :style="{ transform: `translateX(-50%) rotate(${(Math.min((clients.filter(c => c.status === 'SIGNED').length) / 10, 1) * 180) - 90}deg)`, transition: 'transform 1s ease-in-out' }">
                  </div>
                  <div class="absolute bottom-[-6px] left-1/2 w-4 h-4 bg-slate-400 rounded-full -translate-x-1/2 z-20 border-2 border-white shadow-sm"></div>
                  <div class="absolute w-full h-full text-xs font-bold text-slate-500 uppercase">
                    <span class="absolute left-2 bottom-3 rotate-[-45deg]">Min</span>
                    <span class="absolute left-1/2 top-2 -translate-x-1/2">Cel</span>
                    <span class="absolute right-2 bottom-3 rotate-[45deg]">Max</span>
                  </div>
               </div>
               <div class="text-center">
                  <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">Podpisane Umowy</div>
                  <div class="text-2xl font-bold text-slate-900 mt-1">{{ clients.filter(c => c.status === 'SIGNED').length }} szt.</div>
               </div>
             </div>

             <!-- Gauge 4: Punkty Prowizyjne -->
             <div class="flex flex-col items-center gap-4">
               <div class="relative w-[260px] h-[130px] overflow-hidden">
                  <div class="absolute top-0 left-0 w-full h-[200%] rounded-[50%]"
                      style="background: conic-gradient(from 270deg, #e74c3c 0deg, #f1c40f 90deg, #2ecc71 180deg);
                             mask: radial-gradient(circle, transparent 55%, black 56%);
                             -webkit-mask: radial-gradient(circle, transparent 55%, black 56%);">
                  </div>
                  <!-- Mock value 450k for demo -->
                  <div class="absolute bottom-0 left-1/2 w-1.5 h-[90%] bg-red-600 z-10 origin-bottom rounded-t-full shadow-sm"
                       :style="{ transform: `translateX(-50%) rotate(${(Math.min(450000 / 1000000, 1) * 180) - 90}deg)`, transition: 'transform 1s ease-in-out' }">
                  </div>
                  <div class="absolute bottom-[-6px] left-1/2 w-4 h-4 bg-slate-400 rounded-full -translate-x-1/2 z-20 border-2 border-white shadow-sm"></div>
                  <div class="absolute w-full h-full text-xs font-bold text-slate-500 uppercase">
                    <span class="absolute left-2 bottom-3 rotate-[-45deg]">0</span>
                    <span class="absolute left-1/2 top-2 -translate-x-1/2">500k</span>
                    <span class="absolute right-2 bottom-3 rotate-[45deg]">1M</span>
                  </div>
               </div>
               <div class="text-center">
                  <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">Punkty Prowizyjne</div>
                  <div class="text-2xl font-bold text-slate-900 mt-1">450 000 pkt</div>
               </div>
             </div>
             
           </div>
        </div>

        <div class="p-8 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
          <div class="flex items-center gap-5">
            <div class="bg-amber-100 p-4 rounded-xl text-amber-600 shadow-sm min-w-12 min-h-12 aspect-square shrink-0 flex items-center justify-center"><AppIcon name="stopwatch" class="w-6 h-6" /></div>
            <div>
              <h3 class="font-bold text-slate-900 text-xl font-serif">Aktywne kalkulacje (PLUS 14 dni)</h3>
              <p class="text-sm text-slate-500 mt-1">Gwarancja warunków handlowych w toku.</p>
            </div>
          </div>
          <span class="text-[10px] text-slate-500 font-bold bg-white border border-slate-200 px-4 py-2 rounded-full uppercase tracking-wide shadow-sm">Sortowanie: Czas do wygaśnięcia</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-200 font-bold tracking-wider">
              <tr>
                <th class="px-8 py-5">Firma</th>
                <th class="px-8 py-5 hidden md:table-cell">NIP</th>
                <th class="px-8 py-5">Nr Spotkania</th>
                <th class="px-8 py-5 hidden lg:table-cell">Data</th>
                <th class="px-8 py-5">Ważność PLUS</th>
                <th class="px-8 py-5 text-slate-700">Pozostało</th>
                <th class="px-8 py-5">Status</th>
                <th class="px-8 py-5 text-right">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="calc in activeCalculations" :key="calc.id" class="hover:bg-slate-50 transition group">
                <td class="px-8 py-5 font-bold text-slate-800 relative text-base">
                  {{ calc.company }}
                  <span v-if="calc.daysLeft != null && calc.daysLeft <= 3" class="absolute top-4 left-3 w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse border-2 border-white" title="Pilne"></span>
                </td>
                <td class="px-8 py-5 text-slate-500 hidden md:table-cell font-mono text-xs">{{ calc.nip }}</td>
                <td class="px-8 py-5 font-mono text-xs text-slate-500">{{ calc.meetingId }}</td>
                <td class="px-8 py-5 text-slate-500 hidden lg:table-cell">{{ calc.date }}</td>
                <td class="px-8 py-5 text-slate-700 font-medium">{{ calc.validUntil }}</td>
                <td class="px-8 py-5">
                  <span
                    class="inline-flex items-center px-3 py-1 rounded text-xs font-bold shadow-sm"
                    :class="(calc.daysLeft ?? 0) <= 3 ? 'bg-red-100 text-red-700' : (calc.daysLeft ?? 0) <= 7 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                  >
                    {{ calc.daysLeft ?? '—' }}{{ calc.daysLeft != null ? ' dni' : '' }}
                    <span v-if="calc.daysLeft != null && calc.daysLeft <= 3" class="ml-2 text-[9px] uppercase opacity-80 border-l border-red-300 pl-2">Alarm</span>
                  </span>
                </td>
                <td class="px-8 py-5">
                  <span class="text-[10px] font-bold text-slate-500 border border-slate-200 px-3 py-1 rounded uppercase bg-white">{{ calc.status }}</span>
                </td>
                <td class="px-8 py-5 text-right">
                  <button type="button" class="text-stratton-blue hover:text-white font-bold text-xs bg-blue-50 hover:bg-stratton-blue px-4 py-2 rounded-lg transition shadow-sm" @click="openCalculation(calc)">
                    Otwórz
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-12">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-red-50">
          <div class="flex items-center gap-5">
            <div class="bg-white p-4 rounded-xl text-red-600 shadow-sm border border-red-100 min-w-12 min-h-12 aspect-square shrink-0 flex items-center justify-center"><AppIcon name="file-invoice-dollar" class="w-6 h-6" /></div>
            <div>
              <h3 class="font-bold text-slate-900 text-xl font-serif">Zaległości Płatnicze</h3>
              <p class="text-sm text-red-700 mt-1 font-bold">Wymagana interwencja doradcy.</p>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-200 font-bold">
              <tr>
                <th class="px-8 py-5">Firma</th>
                <th class="px-8 py-5">Nr Faktury</th>
                <th class="px-8 py-5 font-bold text-slate-700">Kwota</th>
                <th class="px-8 py-5">Termin Płatności</th>
                <th class="px-8 py-5 text-red-600 font-bold">Opóźnienie</th>
                <th class="px-8 py-5">Status</th>
                <th class="px-8 py-5 text-right">Akcja</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="overdueInvoices.length === 0" class="bg-white">
                <td colspan="7" class="px-8 py-6 text-sm text-slate-500">Brak zaległości płatniczych.</td>
              </tr>
              <tr v-for="inv in overdueInvoices" :key="inv.id" class="hover:bg-red-50/10 transition bg-white group">
                <td class="px-8 py-5 font-bold text-slate-800 text-base">{{ inv.company }}</td>
                <td class="px-8 py-5 text-slate-500 font-mono text-xs">{{ inv.number }}</td>
                <td class="px-8 py-5 font-bold text-slate-900 text-base">{{ inv.amountGross.toFixed(2) }} PLN</td>
                <td class="px-8 py-5 text-slate-500">{{ inv.dueDate }}</td>
                <td class="px-8 py-5 text-red-600 font-bold bg-red-50">+{{ inv.daysOverdue ?? 0 }} dni</td>
                <td class="px-8 py-5"><span class="bg-red-100 text-red-700 px-3 py-1 rounded text-[10px] font-bold border border-red-200">UNPAID</span></td>
                <td class="px-8 py-5 text-right"><button type="button" class="text-xs font-bold text-white bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition shadow-sm">Szczegóły</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
