<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '@/stores/dashboard'
import { useNewsStore } from '@/stores/news'
import { useClientStore } from '@/stores/client'
import { useMailboxStore } from '@/stores/mailbox'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import ClientsView from '@/views/ClientsView.vue'
import MeetingsManagementView from '@/views/MeetingsManagementView.vue'
import StructureView from '@/views/StructureView.vue'
import AppIcon from '@/components/AppIcon.vue'
import GaugeChart from '@/components/GaugeChart.vue'

type UserRole = 'SALES' | 'MANAGER' | 'DIRECTOR' | 'ADMIN' | 'CLIENT_HR'

const session = useSessionStore()
const router = useRouter()
const dashboard = useDashboardStore()
const newsStore = useNewsStore()
const clientStore = useClientStore()
const mailboxStore = useMailboxStore()
const viewPermissions = useViewPermissionsStore()
const { clients } = storeToRefs(clientStore)
const { emails: mailboxEmails } = storeToRefs(mailboxStore)

const userRole = ref<UserRole>('SALES')
const viewMode = ref<'hub' | 'stats'>('hub')
const firstName = computed(() => session.currentUser?.name?.split(' ')[0] || 'Użytkowniku')

const roleDisplayName = computed(() => {
  const mapping: Record<string, string> = {
    'SALES': 'DORADCA BIZNESOWY',
    'MANAGER': 'MANAGER',
    'DIRECTOR': 'DYREKTOR',
    'ADMIN': 'ADMIN',
    'CLIENT_HR': 'KLIENT HR'
  }
  return mapping[userRole.value] || userRole.value
})

const canAddClient = computed(() => viewPermissions.isViewAllowed('sales-start', session.currentUser?.role))
const handleAddClientClick = (e?: Event) => {
  if (e) e.preventDefault()
  if (canAddClient.value) {
    router.push({ path: '/app/sales/start' })
  } else {
    alert(`Brak uprawnień do "Strefa Klienta". Twoja rola: ${session.currentUser?.role || 'Nieznana'}`)
  }
}

const viewScope = ref('structure')
const showTargets = ref(true)
const showClientsTab = ref(true)
const showMeetingsTab = ref(true)
const showTeamTab = ref(true)
const showKPIsTab = ref(true)
const showCalculationsTab = ref(true)

const showArrearsTab = ref(true)
const isRefreshing = ref(false)
const dateFrom = ref('')
const dateTo = ref('')

const leadCount = computed(() => {
  const list = Array.isArray(clients.value) ? clients.value : []
  return list.filter((client: any) => client.status === 'NEW').length
})

const todaysMeetings = computed(() => {
  const today = new Date()
  const list = Array.isArray(clients.value) ? clients.value : []
  const uniqueMeetings = new Set<string>()

  list.forEach((c) => {
    if (c.activityHistory) {
      c.activityHistory.forEach((a: any) => {
        if (a.type === 'MEETING') {
          const d = new Date(a.date)
          if (
            d.getDate() === today.getDate() && 
            d.getMonth() === today.getMonth() && 
            d.getFullYear() === today.getFullYear()
          ) {
            const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            const title = `${c.name}`
            const key = `${time}_${title}`.toLowerCase().trim()
            uniqueMeetings.add(key)
          }
        }
      })
    }
  })
  return uniqueMeetings.size
})

const upcomingEvents = computed(() => {
  const eventsMap = new Map<string, any>()
  const now = new Date()
  const limitDate = new Date()
  limitDate.setDate(now.getDate() + 3)
  limitDate.setHours(23, 59, 59, 999)

  const relevantTypes = ['MEETING', 'CALL', 'EMAIL']
  const allClients = Array.isArray(clients.value) ? clients.value : []
  
  allClients.forEach((client: any) => {
    if (client.activityHistory) {
      client.activityHistory.forEach((act: any) => {
        const d = new Date(act.date)
        if (d >= now && d <= limitDate && relevantTypes.includes(act.type)) {
           const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
           const dateKey = d.toISOString().split('T')[0]
           const title = `${act.type === 'MEETING' ? 'Spotkanie' : 'Telefon'}: ${client.name}`
           // Composite key to detect duplicates even if IDs differ (e.g. from different stores/sources)
           const compositeKey = `${dateKey}_${time}_${title}`.toLowerCase().trim()

           eventsMap.set(compositeKey, {
             id: act.id,
             dateObj: d,
             month: d.toLocaleDateString('pl-PL', { month: 'short' }).toUpperCase(),
             day: String(d.getDate()).padStart(2, '0'),
             time,
             title,
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
         const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
         const dateKey = d.toISOString().split('T')[0]
         const title = ev.title
         const compositeKey = `${dateKey}_${time}_${title}`.toLowerCase().trim()

         // Only add if not already present from client history or has same title/time
         if (!eventsMap.has(compositeKey)) {
           eventsMap.set(compositeKey, {
               id: ev.id,
               dateObj: d,
               month: d.toLocaleDateString('pl-PL', { month: 'short' }).toUpperCase(),
               day: String(d.getDate()).padStart(2, '0'),
               time,
               title,
           })
         }
      }
    })
  }

  const list = Array.from(eventsMap.values())
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

const getAssetUrl = (path: string | null) => {
  if (!path) return null
  if (path.startsWith('http')) return path
  const apiBase = (import.meta.env.VITE_API_BASE_URL as string) || 'http://localhost:8000/api'
  const root = apiBase.replace(/\/api\/?$/, '')
  return `${root}${path.startsWith('/') ? '' : '/'}${path}`
}

const newsItems = computed(() => {
  if (Array.isArray(newsStore.feed) && newsStore.feed.length > 0) {
    return newsStore.feed.map((item) => {
      let tag = 'INFO'
      let color = 'bg-slate-500'
      if (item.category === 'EVENTS') { tag = 'WYDARZENIE'; color = 'bg-purple-500' }
      else if (item.category === 'UPDATE') { tag = 'AKTUALIZACJA'; color = 'bg-blue-500' }
      else if (item.category === 'SALES') { tag = 'SPRZEDAŻ'; color = 'bg-green-500' }
      else if (item.category === 'ANNOUNCEMENT') { tag = 'OGŁOSZENIE'; color = 'bg-primary' }

      const dateStr = item.created_at ? new Date(item.created_at).toLocaleDateString() : ''

      return {
        id: item.id,
        tag,
        color,
        title: item.title,
        date: dateStr,
        content: item.content || '',
        image: getAssetUrl(item.image_url),
        attachment: getAssetUrl(item.attachment_url),
        attachmentName: item.attachment_name || null
      }
    })
  }

  // Fallback
  const fallbackList = Array.isArray(dashboard.news) ? dashboard.news : []
  return fallbackList.map((item) => ({
    id: item.id,
    tag: item.tag || 'INFO',
    color: item.tag === 'PRODUKT' ? 'bg-blue-500' : 'bg-primary',
    title: item.title,
    date: '', 
    content: item.description || '',
    image: null,
    attachment: null,
    attachmentName: null
  }))
})

// News Carousel Logic
const currentNewsIndex = ref(0)
const newsHovered = ref(false)
const selectedNews = ref<any>(null)
const isNewsModalOpen = ref(false)
let newsInterval: number | null = null

const startCarousel = () => {
  if (newsInterval) clearInterval(newsInterval)
  newsInterval = window.setInterval(() => {
    if (!newsHovered.value && !isNewsModalOpen.value && newsItems.value.length > 1) {
      currentNewsIndex.value = (currentNewsIndex.value + 1) % newsItems.value.length
    }
  }, 5000)
}

onMounted(() => {
  startCarousel()
})

onUnmounted(() => {
  if (newsInterval) clearInterval(newsInterval)
})

const openNewsModal = (item: any) => {
  selectedNews.value = item
  isNewsModalOpen.value = true
}

const closeNewsModal = () => {
  isNewsModalOpen.value = false
  selectedNews.value = null
}

const kpis = computed(() => dashboard.kpis.map((item) => ({
  id: item.id,
  title: item.title,
  value: item.value,
  score: item.score,
  minTarget: item.min_target,
  subtitle: item.subtitle,
  missing: item.missing,
})))

const goalScore = computed(() => {
  return 0
})
const goalLabel = computed(() => 'Osiągnięty cel miesięczny')

const unreadCount = computed(() => {
  const userEmail = session.currentUser?.email
  const list = Array.isArray(mailboxEmails.value) ? mailboxEmails.value : []
  if (!userEmail) return 0
  return list.filter((email) => email.toEmail === userEmail && email.folder === 'INBOX' && !email.read).length
})

const now = new Date()
const maxDate = now.toISOString().slice(0, 10)

const activeCalculationsPage = ref(1)
const itemsPerPageCalculations = 10

const activeCalculations = computed(() => {
  let list = dashboard.calculations.map((calc) => {
    const validUntilDate = calc.valid_until ? new Date(calc.valid_until) : null
    const daysLeft = validUntilDate ? Math.ceil((validUntilDate.getTime() - Date.now()) / (1000 * 60 * 60 * 24)) : null
    return {
      id: calc.id,
      company: calc.company,
      nip: calc.nip || '',
      meetingId: calc.meeting_id || '',
      clientId: calc.client_id || '',
      date: calc.calculation_date ? calc.calculation_date.slice(0, 10) : '',
      validUntil: calc.valid_until ? calc.valid_until.slice(0, 10) : '',
      daysLeft,
      status: calc.status || '',
    }
  })

  // Filter functionality for active calculations
  if (dateFrom.value || dateTo.value) {
    list = list.filter((calc) => {
      // Filter by 'date' (calculation_date)
      if (!calc.date) return false
      const cDate = new Date(calc.date)
      cDate.setHours(0, 0, 0, 0)
      
      if (dateFrom.value) {
        const from = new Date(dateFrom.value)
        from.setHours(0, 0, 0, 0)
        if (cDate < from) return false
      }
      if (dateTo.value) {
        const to = new Date(dateTo.value)
        to.setHours(0, 0, 0, 0)
        if (cDate > to) return false
      }
      return true
    })
  }

  return list
})

const paginatedActiveCalculations = computed(() => {
  const start = (activeCalculationsPage.value - 1) * itemsPerPageCalculations
  const end = start + itemsPerPageCalculations
  return activeCalculations.value.slice(start, end)
})

const totalActiveCalculationsPages = computed(() => Math.ceil(activeCalculations.value.length / itemsPerPageCalculations))

const nextActiveCalculationsPage = () => {
  if (activeCalculationsPage.value < totalActiveCalculationsPages.value) activeCalculationsPage.value += 1
}

const prevActiveCalculationsPage = () => {
  if (activeCalculationsPage.value > 1) activeCalculationsPage.value -= 1
}

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
    issueDate: invoice.issue_date ? invoice.issue_date.slice(0, 10) : '',
    dueDate: invoice.due_date ? invoice.due_date.slice(0, 10) : '',
    daysOverdue,
    status: invoice.status || 'UNPAID',
  }
}))

const overdueInvoicesPage = ref(1)
const itemsPerPageOverdue = 10

const paginatedOverdueInvoices = computed(() => {
  const start = (overdueInvoicesPage.value - 1) * itemsPerPageOverdue
  const end = start + itemsPerPageOverdue
  return overdueInvoices.value.slice(start, end)
})

const totalOverdueInvoicesPages = computed(() => Math.ceil(overdueInvoices.value.length / itemsPerPageOverdue))

const nextOverdueInvoicesPage = () => {
  if (overdueInvoicesPage.value < totalOverdueInvoicesPages.value) overdueInvoicesPage.value += 1
}

const prevOverdueInvoicesPage = () => {
  if (overdueInvoicesPage.value > 1) overdueInvoicesPage.value -= 1
}

const refreshData = () => {
  isRefreshing.value = true
  newsStore.fetchFeed()
  dashboard.fetchDashboard(session.currentUser?.id, {
    from_date: dateFrom.value || undefined,
    to_date: dateTo.value || undefined,
    view_scope: viewScope.value || undefined,
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


onMounted(() => {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  dateFrom.value = start.toISOString().slice(0, 10)
  newsStore.fetchFeed()
  dateTo.value = end.toISOString().slice(0, 10)
  dashboard.fetchDashboard(session.currentUser?.id, {
    from_date: dateFrom.value,
    to_date: dateTo.value,
    view_scope: viewScope.value
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
  () => [dateFrom.value, dateTo.value, viewScope.value],
  ([from, to]) => {
    if (!from || !to) return
    refreshData()
  }
)
</script>

<template>
  <div class="view-transition pb-10 space-y-4">
    <div v-if="viewMode === 'hub'" class="w-full pt-2">
      <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 rounded-card shadow-card-hover border border-slate-800 p-8 mb-6 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="flex-1">
          <h1 class="font-serif font-bold text-3xl text-white mb-2 tracking-tight">Dzień dobry, {{ firstName }}</h1>
          <div class="flex flex-col gap-1.5 max-w-md">
            <div class="flex justify-end items-baseline gap-2">
              <span class="text-xs font-bold text-slate-400">{{ goalLabel }}:</span>
              <span class="text-xs font-bold text-primary">{{ goalScore }}% zrealizowane</span>
            </div>
            <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
              <div class="h-full bg-primary rounded-full" :style="{ width: `${goalScore}%` }"></div>
            </div>
          </div>
        </div>
        <div class="flex gap-4">
          <div class="bg-slate-800/50 border border-slate-700 shadow-sm rounded-card p-4 w-32 flex flex-col items-center justify-center h-24">
            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-0.5">Leady Nowe</span>
            <span class="text-2xl font-bold text-primary">{{ leadCount }}</span>
          </div>
          <div class="bg-slate-800/50 border border-slate-700 shadow-sm rounded-card p-4 w-32 flex flex-col items-center justify-center h-24">
            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-0.5">Spotkania</span>
            <span class="text-2xl font-bold text-primary">{{ todaysMeetings }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-8 mb-10 px-4">
        <div @click="viewMode = 'stats'" class="crm-tile h-44 group cursor-pointer relative overflow-hidden bg-slate-900 border border-slate-700">
          <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Dashboard" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="layout-dashboard" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Dashboard</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Statystyki i raporty</p>
            </div>
          </div>
        </div>
        
        <div v-if="canAddClient" @click="handleAddClientClick" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
          <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Strefa Klienta" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="user-plus" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Strefa Klienta</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Rozpocznij proces</p>
            </div>
          </div>
        </div>
        
        <div v-else @click="handleAddClientClick" class="crm-tile h-44 opacity-60 grayscale cursor-not-allowed relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-20" alt="Strefa Klienta" />
             <div class="absolute inset-0 bg-slate-900/80"></div>
          </div>
           <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-slate-500">
               <AppIcon name="user-plus" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-slate-400 mb-1">Strefa Klienta</h3>
              <p class="crm-tile-desc text-xs text-slate-600 font-medium">Brak uprawnień</p>
            </div>
          </div>
        </div>

        <RouterLink to="/app/quick-calculator" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
          <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Kalkulator" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="calculator" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Kalkulator</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Nowoczesna symulacja</p>
            </div>
          </div>
        </RouterLink>

        <!-- Removed duplicate Payroll Link -->

        <RouterLink to="/app/knowledge-base" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?q=80&w=2428&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Baza Wiedzy" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="book-open" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Baza Wiedzy</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Wiedza i Certyfikaty</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink to="/app/settlements" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1580519542036-c47de6196ba5?q=80&w=2671&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Moje rozliczenia" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="wallet" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Moje rozliczenia</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Moje finanse</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink to="/app/clients" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=2669&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Klienci" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="file-contract" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Klienci w obsłudze</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Zarządzaj umowami</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink v-if="userRole !== 'SALES'" to="/app/recruitment" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Rekrutacja" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="users" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Rekrutacja</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Zarządzaj kandydatami</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink to="/app/mailbox" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1596526131083-e8c633c948d2?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Poczta" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="mail" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Poczta</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Korespondencja</p>
            </div>
          </div>
        </RouterLink>

        <RouterLink to="/app/calendar" class="crm-tile h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
           <div class="absolute inset-0 z-0">
             <img src="https://images.unsplash.com/photo-1506784983877-45594efa4cbe?q=80&w=2668&auto=format&fit=crop" class="w-full h-full object-cover opacity-40 transition-transform duration-700 group-hover:scale-105" alt="Kalendarz" />
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-slate-900/20"></div>
          </div>
          <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
            <div class="text-stratton-gold">
               <AppIcon name="calendar" class="w-8 h-8" />
            </div>
            <div>
              <h3 class="crm-tile-title text-xl text-white mb-1">Kalendarz</h3>
              <p class="crm-tile-desc text-xs text-slate-300 font-medium">Harmonogram</p>
            </div>
          </div>
        </RouterLink>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <div class="relative bg-white rounded-card shadow-card border border-slate-100 p-6 group transition-all duration-300 overflow-hidden hover:shadow-card-hover hover:-translate-y-0.5">
          <div class="absolute inset-0 bg-linear-to-br from-slate-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

          <div class="relative z-10">
            <div class="flex justify-between items-center mb-4">
              <h3 class="font-bold text-slate-700 uppercase text-[10px] tracking-wider border-l-4 border-primary pl-3 transition-colors duration-300 group-hover:text-primary">Najbliższe Wydarzenia</h3>
            </div>
            
            <div class="space-y-3">
              <div v-if="upcomingEvents.length === 0" class="text-center py-6 text-slate-400 italic text-sm">Brak nadchodzących wydarzeń</div>
              <div v-for="event in upcomingEvents" :key="event.id" class="relative group/item bg-slate-50 rounded-xl p-3 flex items-center gap-3 hover:bg-white hover:shadow-md transition-all cursor-pointer border border-transparent hover:border-slate-100 ring-1 ring-slate-100" @click="goToEvent(event)">
                <div class="bg-white group-hover/item:bg-primary group-hover/item:text-white transition-colors rounded-lg p-1.5 text-center w-12 shadow-sm shrink-0 ring-1 ring-slate-200 group-hover/item:ring-primary">
                  <div class="text-[9px] text-slate-400 uppercase font-bold group-hover/item:text-white/80">{{ event.month }}</div>
                  <div class="text-lg font-bold text-slate-800 group-hover/item:text-white">{{ event.day }}</div>
                </div>
                <div class="flex-1">
                  <h4 class="font-bold text-slate-800 text-xs group-hover/item:text-primary transition-colors">{{ event.title }}</h4>
                  <div class="text-[10px] text-slate-500 mt-0.5 flex items-center group-hover/item:text-slate-600">
                    <AppIcon name="clock" class="w-3 h-3" />
                    <span class="text-sm ml-1">{{ event.time }}</span>
                  </div>
                </div>
                <div class="text-slate-300 group-hover/item:text-primary group-hover/item:translate-x-1 transition-all">
                  <AppIcon name="chevron-right" class="w-2.5 h-2.5" />
                </div>
              </div>
            </div>

            <div class="mt-4 text-center">
              <RouterLink to="/app/calendar" class="text-[10px] font-bold text-primary hover:text-slate-800 uppercase tracking-widest transition group/link inline-flex items-center relative z-10">
                Pełny Kalendarz <span class="ml-1 group-hover/link:translate-x-1 transition-transform">→</span>
              </RouterLink>
            </div>
          </div>
        </div>

        <div class="relative bg-white rounded-card shadow-card border border-slate-100 p-6 group transition-all duration-300 overflow-hidden hover:shadow-card-hover hover:-translate-y-0.5">
          <!-- News Background Image -->
          <Transition name="fade">
             <div 
              v-if="newsItems.length > 0 && newsItems[currentNewsIndex]?.image"
              :key="newsItems[currentNewsIndex]?.id"
              class="absolute inset-0 bg-cover bg-center transition-all duration-700 opacity-10 group-hover:scale-110 group-hover:opacity-20"
              :style="{ backgroundImage: `url('${newsItems[currentNewsIndex]?.image}')` }"
             ></div>
          </Transition>

          <div class="absolute inset-0 bg-linear-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

          <div class="relative z-10">
            <div class="flex justify-between items-center mb-4">
              <h3 class="font-bold text-slate-700 uppercase text-[10px] tracking-wider border-l-4 border-primary pl-3 transition-colors duration-300 group-hover:text-primary">Aktualności</h3>
            </div>
            
            <div class="relative h-80 flex flex-col" @mouseenter="newsHovered = true" @mouseleave="newsHovered = false">
              <div class="flex-1 relative overflow-hidden" @click="newsItems.length > 0 && openNewsModal(newsItems[currentNewsIndex])">
                <TransitionGroup name="news-slide" tag="div" class="h-full w-full relative">
                  <div 
                    v-if="newsItems.length > 0"
                    :key="newsItems[currentNewsIndex]?.id"
                    class="absolute inset-0 flex flex-col justify-start bg-transparent p-1 cursor-pointer bg-cover bg-center rounded-lg transition-all duration-500"
                    :style="newsItems[currentNewsIndex]?.title?.toUpperCase().includes('KONKURS') ? { backgroundImage: 'url(https://images.unsplash.com/photo-1507525428034-b723cf961d3e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80)', color: 'white', textShadow: '0 2px 4px rgba(0,0,0,0.8)' } : {}"
                  >
                    <div v-if="newsItems[currentNewsIndex]?.title?.toUpperCase().includes('KONKURS')" class="absolute inset-0 bg-black/30 rounded-lg"></div>
                    <div class="relative z-10 flex flex-col h-full">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded text-white shadow-sm transition-transform hover:scale-105" :class="newsItems[currentNewsIndex]?.color">
                        {{ newsItems[currentNewsIndex]?.tag }}
                      </span>
                      <span v-if="newsItems[currentNewsIndex]?.date" class="text-[10px] text-slate-400 font-bold uppercase tracking-wider" :class="{'text-white': newsItems[currentNewsIndex]?.title?.toUpperCase().includes('KONKURS')}">{{ newsItems[currentNewsIndex]?.date }}</span>
                    </div>
                    <h4 class="font-bold text-slate-900 text-lg mb-2 uppercase tracking-tight hover:text-primary transition-colors line-clamp-2" :class="{'text-white hover:text-white': newsItems[currentNewsIndex]?.title?.toUpperCase().includes('KONKURS')}">{{ newsItems[currentNewsIndex]?.title }}</h4>
                    <div class="news-content-area text-slate-700 leading-relaxed text-base line-clamp-5" :class="{'text-white': newsItems[currentNewsIndex]?.title?.toUpperCase().includes('KONKURS')}" v-html="newsItems[currentNewsIndex]?.content || '<i>Brak dodatkowej treści</i>'"></div>
                    </div>
                  </div>
                  <div v-else key="empty" class="flex items-center justify-center h-full text-slate-400 italic">Brak aktualności</div>
                </TransitionGroup>
              </div>
              
              <div class="mt-auto text-center pt-2 flex flex-col items-center gap-1.5">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest group-hover:text-primary transition-colors cursor-pointer" @click="newsItems.length > 0 && openNewsModal(newsItems[currentNewsIndex])">Kliknij aby czytać więcej</span>
                <div class="flex gap-1.5 mt-0.5 z-20">
                  <div 
                    v-for="(_, index) in newsItems" 
                    :key="index"
                    class="w-1.5 h-1.5 rounded-full transition-all duration-300 cursor-pointer hover:scale-125 hover:bg-primary"
                    :class="index === currentNewsIndex ? 'bg-primary scale-110' : 'bg-slate-300 opacity-50'"
                    @click.stop="currentNewsIndex = index"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="space-y-8 max-w-7xl mx-auto pt-6">
      <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 rounded-card shadow-card-hover border border-slate-800 p-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 pb-8 border-b border-slate-800 gap-6">
          <div class="flex items-center gap-6">
            <button type="button" class="inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-md text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group" @click="viewMode = 'hub'">
              <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
            </button>
            <div>
              <h2 class="font-serif font-bold text-4xl text-white tracking-tight">Dashboard</h2>
              <p class="text-xs text-slate-500 cursor-pointer hover:text-primary mt-1 uppercase tracking-widest font-bold" @click="toggleRole">Widok: {{ roleDisplayName }}</p>
            </div>
          </div>
          
          <div class="flex flex-wrap items-center gap-6 w-full md:w-auto">
            <div class="relative w-full md:w-48">
              <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Od</label>
              <input v-model="dateFrom" type="date" :max="maxDate" class="w-full bg-slate-800 border border-slate-700 text-white py-2 px-4 rounded-lg text-sm font-bold focus:outline-none focus:border-primary cursor-pointer transition hover:bg-slate-750" />
            </div>

            <div class="relative w-full md:w-48">
              <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Do</label>
              <input v-model="dateTo" type="date" :max="maxDate" class="w-full bg-slate-800 border border-slate-700 text-white py-2 px-4 rounded-lg text-sm font-bold focus:outline-none focus:border-primary cursor-pointer transition hover:bg-slate-750" />
            </div>

            <div class="relative w-full md:w-56">
              <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Zakres</label>
              <div class="relative">
                <select v-model="viewScope" class="w-full bg-slate-800 border border-slate-700 text-white py-2 pl-4 pr-10 rounded-lg text-sm font-bold focus:outline-none focus:border-primary cursor-pointer appearance-none transition hover:bg-slate-750">
                  <option value="all" v-if="userRole === 'ADMIN'">Cała firma</option>
                  <option value="mine">Widok: Moje</option>
                  <option v-if="userRole === 'ADMIN'" value="role:DIRECTOR">Widok: Dyrektorzy</option>
                  <option v-if="userRole === 'ADMIN'" value="role:MANAGER">Widok: Menadżerowie</option>
                  <option v-if="userRole === 'ADMIN'" value="role:SALES">Widok: Doradcy Biznesowi</option>
                  <option v-if="userRole === 'MANAGER' || userRole === 'DIRECTOR'" value="structure">Widok: Struktura</option>
                  <option v-if="userRole === 'DIRECTOR'" value="team">Widok: Zespół</option>
                </select>
                <AppIcon name="users" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none" />
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-4 flex-wrap">
        <button 
          @click="showKPIsTab = !showKPIsTab"
          class="px-3 py-2.5 rounded-lg font-bold text-base transition-all shadow-lg flex items-center justify-center gap-2 group hover:-translate-y-0.5 w-44 h-14"
          :class="showKPIsTab ? 'bg-primary text-white shadow-amber-900/20 ring-2 ring-offset-2 ring-primary ring-offset-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 hover:text-white shadow-transparent'"
        >
          <AppIcon name="chart-pie" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" :class="showKPIsTab ? 'text-white' : 'text-slate-500'" />
          <span class="leading-tight text-center">Liczniki</span>
        </button>
        <button 
          @click="showClientsTab = !showClientsTab"
          class="px-3 py-2.5 rounded-lg font-bold text-base transition-all shadow-lg flex items-center justify-center gap-2 group hover:-translate-y-0.5 w-44 h-14"
          :class="showClientsTab ? 'bg-primary text-white shadow-amber-900/20 ring-2 ring-offset-2 ring-primary ring-offset-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 hover:text-white shadow-transparent'"
        >
          <AppIcon name="address-book" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" :class="showClientsTab ? 'text-white' : 'text-slate-500'" />
          <span class="leading-tight text-center">Klienci w obsłudze</span>
        </button>
        <button 
          @click="showMeetingsTab = !showMeetingsTab"
          class="px-3 py-2.5 rounded-lg font-bold text-base transition-all shadow-lg flex items-center justify-center gap-2 group hover:-translate-y-0.5 w-44 h-14"
          :class="showMeetingsTab ? 'bg-primary text-white shadow-amber-900/20 ring-2 ring-offset-2 ring-primary ring-offset-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 hover:text-white shadow-transparent'"
        >
          <AppIcon name="calendar" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" :class="showMeetingsTab ? 'text-white' : 'text-slate-500'" />
          <span class="leading-tight text-center">Spotkania w obsłudze</span>
        </button>
        <button 
          @click="showTeamTab = !showTeamTab"
          class="px-3 py-2.5 rounded-lg font-bold text-base transition-all shadow-lg flex items-center justify-center gap-2 group hover:-translate-y-0.5 w-44 h-14"
          :class="showTeamTab ? 'bg-primary text-white shadow-amber-900/20 ring-2 ring-offset-2 ring-primary ring-offset-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 hover:text-white shadow-transparent'"
        >
          <AppIcon name="people-group" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" :class="showTeamTab ? 'text-white' : 'text-slate-500'" />
          <span class="leading-tight text-center">Mój Zespół</span>
        </button>
        <button 
          @click="showArrearsTab = !showArrearsTab"
          class="px-3 py-2.5 rounded-lg font-bold text-base transition-all shadow-lg flex items-center justify-center gap-2 group hover:-translate-y-0.5 w-44 h-14"
          :class="showArrearsTab ? 'bg-primary text-white shadow-amber-900/20 ring-2 ring-offset-2 ring-primary ring-offset-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 hover:text-white shadow-transparent'"
        >
          <AppIcon name="file-invoice-dollar" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" :class="showArrearsTab ? 'text-white' : 'text-slate-500'" />
          <span class="leading-tight text-center">Zaległości Płatnicze</span>
        </button>
        <button 
          @click="showCalculationsTab = !showCalculationsTab"
          class="px-3 py-2.5 rounded-lg font-bold text-base transition-all shadow-lg flex items-center justify-center gap-2 group hover:-translate-y-0.5 w-44 h-14"
          :class="showCalculationsTab ? 'bg-primary text-white shadow-amber-900/20 ring-2 ring-offset-2 ring-primary ring-offset-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 hover:text-white shadow-transparent'"
        >
          <AppIcon name="stopwatch" class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" :class="showCalculationsTab ? 'text-white' : 'text-slate-500'" />
          <span class="leading-tight text-center">Wysłane kalkulacje</span>
        </button>

      </div>

      </div>

      <!-- Quick Toggles removed from here -->

      <div class="grid grid-cols-1 md:grid-cols-2 pb-10 xl:grid-cols-4 gap-6" v-if="showKPIsTab">
        <div v-for="kpi in kpis" :key="kpi.id" class="bg-surface rounded-card shadow-card border border-slate-100 p-2 relative overflow-visible group hover:shadow-card-hover hover:-translate-y-1 transition flex flex-col justify-between min-h-[340px]">
          <div class="flex justify-between items-start mb-2 px-4 pt-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest leading-tight w-full text-center">{{ kpi.title }}</h3>
            <div class="hidden w-2 h-2 rounded-full shadow-sm ring-2 ring-white" :class="kpi.score >= 90 ? 'bg-green-500' : kpi.score >= 70 ? 'bg-primary' : 'bg-red-500'"></div>
          </div>
          
          <div class="flex-1 flex items-center justify-center w-full h-full">
            <GaugeChart 
              :value="kpi.score"
              :min="0"
              :max="100"
              :label="String(kpi.value)"
              :sublabel="showTargets ? `CEL: ${kpi.minTarget}` : 'Cele ukryte'"
            />
          </div>

        </div>
      </div>

      <!-- Injected Clients View (Stats Mode) -->
      <div v-if="showClientsTab" class="bg-surface rounded-card shadow-card border border-slate-100 overflow-hidden mb-8 animate-fade-in-down">
        <ClientsView :embedded="true" :date-from="dateFrom" :date-to="dateTo" />
      </div>

      <!-- Injected Meetings View (Stats Mode) -->
      <div v-if="showMeetingsTab" class="bg-surface rounded-card shadow-card border border-slate-100 overflow-hidden mb-8 animate-fade-in-down">
        <MeetingsManagementView :embedded="true" />
      </div>

      <!-- Injected Team View (Stats Mode) -->
      <div v-if="showTeamTab" class="bg-surface rounded-card shadow-card border border-slate-100 p-6 mb-8 animate-fade-in-down">
        <StructureView :embedded="true" />
      </div>

      <div class="bg-surface rounded-card shadow-card border border-slate-100 overflow-hidden mb-8" v-if="showCalculationsTab">
        <div class="bg-slate-50 border-b border-slate-100 p-2 flex items-center shadow-sm shrink-0">
          <div class="flex items-center gap-3 ml-4 flex-1">
            <AppIcon name="stopwatch" class="w-5 h-5 text-primary" />
            <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
              <h3 class="font-bold text-slate-800 text-lg tracking-tight">Wysłane kalkulacje</h3>
              <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">Termin do 14 dni dla oferty Eliton Prime+TM</p>
            </div>
          </div>
          <span class="text-[10px] text-slate-500 font-bold bg-white border border-slate-200 px-4 py-2 rounded-full uppercase tracking-wide shadow-sm mr-4 shrink-0">Sortowanie: Czas do wygaśnięcia</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-200 font-bold tracking-wider">
              <tr>
                <th class="px-4 py-4">Firma</th>
                <th class="px-4 py-4 hidden md:table-cell">NIP</th>
                <th class="px-4 py-4 hidden lg:table-cell">Data</th>
                <th class="px-4 py-4">Ważność PLUS</th>
                <th class="px-4 py-4 text-slate-700">Pozostało</th>
                <th class="px-4 py-4">Status</th>
                <th class="px-4 py-4 text-right">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-600">
              <tr v-for="calc in paginatedActiveCalculations" :key="calc.id" class="hover:bg-slate-50/80 transition group">
                <td class="px-4 py-4 font-bold text-slate-800 relative text-sm max-w-[350px] truncate" :title="calc.company">
                  <span v-if="calc.daysLeft != null && calc.daysLeft <= 3" class="inline-block w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse border-2 border-white mr-2" title="Pilne"></span>
                  {{ calc.company }}
                </td>
                <td class="px-4 py-4 text-slate-500 hidden md:table-cell font-mono text-xs whitespace-nowrap">{{ calc.nip }}</td>
                <td class="px-4 py-4 text-slate-500 hidden lg:table-cell whitespace-nowrap">{{ calc.date }}</td>
                <td class="px-4 py-4 text-slate-700 font-medium whitespace-nowrap">{{ calc.validUntil }}</td>
                <td class="px-4 py-4 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-3 py-1 rounded text-xs font-bold shadow-sm"
                    :class="(calc.daysLeft ?? 0) <= 3 ? 'bg-red-100 text-red-700' : (calc.daysLeft ?? 0) <= 7 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                  >
                    {{ calc.daysLeft ?? '—' }}{{ calc.daysLeft != null ? ' dni' : '' }}
                    <span v-if="calc.daysLeft != null && calc.daysLeft <= 3" class="ml-2 text-[9px] uppercase opacity-80 border-l border-red-300 pl-2">Alarm</span>
                  </span>
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                  <span class="text-[10px] font-bold text-slate-500 border border-slate-200 px-3 py-1 rounded uppercase bg-white">{{ calc.status }}</span>
                </td>
                <td class="px-4 py-4 text-right whitespace-nowrap">
                  <button type="button" class="text-primary hover:text-white font-bold text-xs bg-blue-50 hover:bg-primary px-4 py-2 rounded-lg transition shadow-sm" @click="openCalculation(calc)">
                    Otwórz
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination for Active Calculations -->
        <div class="border-t border-slate-100 px-6 py-4 bg-gray-50 flex items-center justify-between" v-if="totalActiveCalculationsPages > 1">
          <button 
            @click="prevActiveCalculationsPage" 
            :disabled="activeCalculationsPage === 1"
            class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
          >
            Poprzednia
          </button>
          <span class="text-sm font-medium text-slate-600">
            Strona {{ activeCalculationsPage }} z {{ totalActiveCalculationsPages }}
          </span>
          <button 
            @click="nextActiveCalculationsPage" 
            :disabled="activeCalculationsPage === totalActiveCalculationsPages"
            class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
          >
            Następna
          </button>
        </div>
      </div>

      <div class="bg-surface rounded-card shadow-card border border-slate-100 overflow-hidden mb-12" v-if="showArrearsTab">
        <div class="bg-slate-50 border-b border-slate-100 p-2 flex items-center shadow-sm shrink-0">
          <div class="flex items-center gap-3 ml-4">
            <AppIcon name="file-invoice-dollar" class="w-5 h-5 text-red-600" />
            <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
              <h3 class="font-bold text-slate-800 text-lg tracking-tight">Zaległości Płatnicze</h3>
              <p class="text-[10px] text-red-700 font-bold uppercase tracking-widest mt-0.5">Wymagana interwencja doradcy.</p>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-200 font-bold">
              <tr>
                <th class="px-6 py-4">Firma</th>
                <th class="px-6 py-4">Nr Faktury</th>
                <th class="px-6 py-4 font-bold text-slate-700">Kwota</th>
                <th class="px-6 py-4">Termin Płatności</th>
                <th class="px-6 py-4 text-red-600 font-bold">Opóźnienie</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Akcja</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-600">
              <tr v-if="overdueInvoices.length === 0" class="bg-white">
                <td colspan="7" class="px-6 py-6 text-sm text-slate-500">Brak zaległości płatniczych.</td>
              </tr>
              <tr v-for="inv in paginatedOverdueInvoices" :key="inv.id" class="hover:bg-red-50/10 transition bg-white group">
                <td class="px-6 py-4 font-bold text-slate-800 text-sm max-w-[350px] truncate" :title="inv.company">{{ inv.company }}</td>
                <td class="px-6 py-4 text-slate-500 font-mono text-xs whitespace-nowrap">{{ inv.number }}</td>
                <td class="px-6 py-4 font-bold text-slate-900 text-sm whitespace-nowrap">{{ inv.amountGross.toFixed(2) }} PLN</td>
                <td class="px-6 py-4 text-slate-500 whitespace-nowrap">{{ inv.dueDate }}</td>
                <td class="px-6 py-4 text-red-600 font-bold bg-red-50 whitespace-nowrap">+{{ inv.daysOverdue ?? 0 }} dni</td>
                <td class="px-6 py-4 whitespace-nowrap"><span class="bg-red-100 text-red-700 px-3 py-1 rounded text-[10px] font-bold border border-red-200">UNPAID</span></td>
                <td class="px-6 py-4 text-right whitespace-nowrap"><button type="button" class="text-xs font-bold text-white bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition shadow-sm">Szczegóły</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination for Overdue Invoices -->
        <div class="border-t border-slate-100 px-6 py-4 bg-gray-50 flex items-center justify-between" v-if="totalOverdueInvoicesPages > 1">
          <button 
            @click="prevOverdueInvoicesPage" 
            :disabled="overdueInvoicesPage === 1"
            class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
          >
            Poprzednia
          </button>
          <span class="text-sm font-medium text-slate-600">
            Strona {{ overdueInvoicesPage }} z {{ totalOverdueInvoicesPages }}
          </span>
          <button 
            @click="nextOverdueInvoicesPage" 
            :disabled="overdueInvoicesPage === totalOverdueInvoicesPages"
            class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
          >
            Następna
          </button>
        </div>
  
    </div>
  </div>

    <!-- News Modal -->
    <Teleport to="body">
      <div v-if="isNewsModalOpen && selectedNews" class="fixed inset-0 z-9999 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm" @click.self="closeNewsModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-all p-8 relative">
          <button @click="closeNewsModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full hover:bg-slate-100 transition">
            <AppIcon name="xmark" class="w-6 h-6" />
            <span class="sr-only">Zamknij</span>
          </button>
          
          <div class="flex items-center gap-4 mb-6">
             <span class="text-xs font-bold px-3 py-1 rounded text-white shadow-sm" :class="selectedNews.color">
                {{ selectedNews.tag }}
             </span>
             <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">{{ selectedNews.date }}</span>
          </div>
          
          <h2 class="font-serif font-bold text-3xl text-slate-900 mb-6 leading-tight">{{ selectedNews.title }}</h2>
          
          <div class="prose prose-slate max-w-none text-slate-700 leading-relaxed max-h-[60vh] overflow-y-auto mb-6" v-html="selectedNews.content || '<i>Brak treści</i>'"></div>
          
          <div class="mt-8 border-t border-slate-100 pt-6 flex justify-between items-end">
            <div v-if="selectedNews.attachment" class="flex flex-col gap-2">
              <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-1">Pliki do pobrania:</span>
              <a :href="selectedNews.attachment" target="_blank" class="inline-flex items-center gap-2 text-stratton-gold font-bold hover:text-amber-700 transition group p-2 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-100">
                <AppIcon name="paperclip" class="w-4 h-4 bg-amber-200 text-amber-800 rounded p-0.5" />
                <span class="text-xs uppercase tracking-wide">{{ selectedNews.attachmentName || 'Pobierz załącznik' }}</span>
              </a>
            </div>
            <div v-else></div> <!-- Spacer -->
            
            <button @click="closeNewsModal" class="px-6 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Zamknij</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* News Carousel Animation */
.news-slide-enter-active,
.news-slide-leave-active {
  transition: all 0.5s ease-in-out;
  position: absolute;
  width: 100%;
}

.news-slide-enter-from {
  transform: translateX(100%);
  opacity: 0;
}

.news-slide-leave-to {
  transform: translateX(-100%);
  opacity: 0;
}

.news-content-area :deep(p) {
  margin-bottom: 0.75rem;
}
.news-content-area :deep(p:last-child) {
  margin-bottom: 0;
}
.news-content-area :deep(strong) {
  font-weight: 800;
}
.news-content-area :deep(em) {
  font-style: italic;
}
.news-content-area :deep(u) {
  text-decoration: underline;
}
</style>
