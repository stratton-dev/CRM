<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
// apexcharts (~508 KB) ładowany leniwie tylko tu — to jedyny widok z wykresami.
// Wcześniej był rejestrowany globalnie w main.ts → wisiał w głównym bundlu na każdej stronie.
const apexchart = defineAsyncComponent(() => import('vue3-apexcharts'))
import { useSessionStore } from '@/stores/session'
import { useNotificationStore } from '@/stores/notification'
import { useClientStore } from '@/stores/client'
import { useGamificationStore } from '@/stores/gamification'
import { useStructureStore } from '@/stores/structure'
import { useAuditLogStore } from '@/stores/auditLog'
import AppIcon from '@/components/AppIcon.vue'
import type { Rank } from '@/types/models'

const session = useSessionStore()
const { currentUser } = storeToRefs(session)
const notificationsStore = useNotificationStore()
const clientStore = useClientStore()
const { notifications } = storeToRefs(notificationsStore)
const { clients } = storeToRefs(clientStore)
const gamification = useGamificationStore()
const structure = useStructureStore()
const auditLogStore = useAuditLogStore()
const { users } = storeToRefs(structure)
const { logs: auditLogs } = storeToRefs(auditLogStore)

const chartMetric = ref<'SALES' | 'MEETINGS'>('SALES')

const user = computed(() => currentUser.value)

/**
 * Scope rules:
 *  ADMIN    → widzi wszystkich użytkowników w systemie
 *  DIRECTOR → widzi swój pion — wszystkich poniżej swojego hierarchicalId (np. "PL" → cały kraj)
 *  MANAGER  → widzi swoją grupę — bezpośrednie podzespoły pod własnym hierarchicalId
 *  SALES    → widzi tylko siebie
 *
 * Używamy prefiksu hierarchicalId (np. "PL/WM/") zamiast rekurencji przez parentKeycloakId,
 * żeby zakres był spójny z widokiem struktury i nie zależał od kompletności drzewa parentów.
 * Fallback na getSubtreeUserIds gdy hierarchicalId nie jest ustawione.
 */
const getScopeIds = (
  u: { id: string; role: string; hierarchicalId?: string | null },
  allUsers: { id: string; role?: string; hierarchicalId?: string | null; isRemovedFromStructure?: boolean }[],
): Set<string> => {
  // SALES — tylko własne ID
  if (u.role === 'SALES') return new Set([u.id])

  // ADMIN — wszyscy
  if (u.role === 'ADMIN') return new Set(allUsers.filter((m) => !m.isRemovedFromStructure).map((m) => m.id))

  // DIRECTOR / MANAGER — pion lub grupa wyznaczona przez hierarchicalId
  const hid = u.hierarchicalId
  if (hid) {
    const prefix = hid + '/'
    const subtree = allUsers
      .filter((m) => !m.isRemovedFromStructure && m.hierarchicalId?.startsWith(prefix))
      .map((m) => m.id)
    // Includie własne ID (manager może też mieć własnych klientów)
    return new Set([u.id, ...subtree])
  }
  // Fallback gdy brak hierarchicalId
  return new Set([u.id, ...structure.getSubtreeUserIds(u.id)])
}

const criticalNotifications = computed(() => {
  const u = user.value
  if (!u) return []
  const items = Array.isArray(notifications.value) ? notifications.value : []
  const allUsers = Array.isArray(users.value) ? users.value : []
  const scopeIds = getScopeIds(u, allUsers)

  const seen = new Set<string>()
  return items.filter((n) => {
    if (!scopeIds.has(n.userId)) return false
    if (n.type !== 'CRITICAL' || n.read) return false
    // Filtruj błędy API zapisane przypadkowo jako powiadomienia
    if (/field is required|the body/i.test(n.message)) return false
    // Deduplikacja po treści
    if (seen.has(n.message)) return false
    seen.add(n.message)
    return true
  })
})

const nextRankData = computed(() => {
  const points = user.value?.points || 0
  return gamification.getNextRankTarget(points)
})

const myClientsCount = computed(() => {
  const u = user.value
  if (!u) return 0
  // For managers/directors/admins show full team count, for sales — own
  if (u.role !== 'SALES') return funnelClients.value.length
  const list = Array.isArray(clients.value) ? clients.value : []
  return list.filter((client) => client.ownerId === u.id).length
})

const signedCount = computed(() => {
  const u = user.value
  if (!u) return 0
  // For managers/directors/admins show full team signed count
  if (u.role !== 'SALES') return funnelClients.value.filter((c) => c.status === 'SIGNED').length
  const list = Array.isArray(clients.value) ? clients.value : []
  return list.filter((client) => client.ownerId === u.id && client.status === 'SIGNED').length
})

const funnelClients = computed(() => {
  const u = user.value
  if (!u) return []
  const list = Array.isArray(clients.value) ? clients.value : []
  const allUsers = Array.isArray(users.value) ? users.value : []
  const scopeIds = getScopeIds(u, allUsers)
  return list.filter((client) => scopeIds.has(client.ownerId))
})

const funnelEstimate = computed(() => {
  const weights: Record<string, number> = {
    NEW: 0.1,
    IN_TALKS: 0.5,
    SIGNED: 1,
    RESIGNED: 0,
    TERMINATED: 0,
  }
  const total = funnelClients.value.reduce((sum, client: any) => {
    const baseValue = (client.employeesTotal || 0) * 1000
    const weight = weights[client.status] ?? 0.1
    return sum + baseValue * weight
  }, 0)
  return total
})

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN', maximumFractionDigits: 0 }).format(value)
}

const recentLogs = computed(() => {
  const logs = Array.isArray(auditLogs.value) ? auditLogs.value : []
  return logs.slice(0, 5)
})

const chartTitle = computed(() => {
  const role = user.value?.role
  if (role === 'SALES') return 'Lejek Sprzedaży'
  const metricLabel = chartMetric.value === 'SALES' ? 'Podpisane Umowy' : 'Spotkania (30 dni)'
  if (role && ['MANAGER', 'DIRECTOR', 'ADMIN'].includes(role)) return `Ranking Zespołu - ${metricLabel}`
  return 'Statystyki'
})

const chartData = computed(() => {
  const role = user.value?.role
  const clientList = Array.isArray(clients.value) ? clients.value : []
  const allUsers = Array.isArray(users.value) ? users.value : []

  if (!role) return []

  if (role === 'SALES') {
    const myClients = clientList.filter((client) => client.ownerId === user.value?.id)
    return [
      { label: 'Nowy', value: myClients.filter((c) => c.status === 'NEW').length },
      { label: 'Rozmowy', value: myClients.filter((c) => c.status === 'IN_TALKS').length },
      { label: 'Umowa', value: myClients.filter((c) => c.status === 'SIGNED').length },
    ]
  }

  if (['MANAGER', 'DIRECTOR', 'ADMIN'].includes(role)) {
    const scopeIds = getScopeIds(user.value!, allUsers)
    // Ranking tylko po SALES i MANAGER (nie liczymy samego siebie jako row jeśli DIRECTOR/ADMIN)
    const teamIds = [...scopeIds].filter((id) => {
      if (id === user.value?.id) return false
      const m = allUsers.find((item) => item.id === id)
      return m && ['SALES', 'MANAGER'].includes(m.role ?? '')
    })

    const rows = teamIds.map((id) => {
      const u = allUsers.find((item) => item.id === id)
      let value = 0
      if (chartMetric.value === 'SALES') {
        value = clientList.filter((client) => client.ownerId === id && client.status === 'SIGNED').length
      } else {
        const now = new Date()
        const thirtyDaysAgo = new Date()
        thirtyDaysAgo.setDate(now.getDate() - 30)
        const userClients = clientList.filter((client) => client.ownerId === id)
        userClients.forEach((client) => {
          if (client.activityHistory) {
            value += client.activityHistory.filter((act) => act.type === 'MEETING' && new Date(act.date) >= thirtyDaysAgo).length
          }
        })
      }
      return { label: u ? u.name : '??', value }
    })

    return rows.sort((a, b) => b.value - a.value).slice(0, 10)
  }

  return []
})

const getRankIconName = (rank?: Rank | null) => {
  switch (rank) {
    case 'JUNIOR':
      return 'medal'
    case 'REGULAR':
      return 'award'
    case 'SENIOR':
      return 'trophy'
    case 'MASTER':
      return 'sparkles'
    case 'LEGEND':
      return 'trophy'
    default:
      return 'bolt'
  }
}

const getRankIconClass = (rank?: Rank | null) => {
  switch (rank) {
    case 'JUNIOR':
      return 'text-amber-200'
    case 'REGULAR':
      return 'text-slate-200'
    case 'SENIOR':
      return 'text-yellow-200'
    case 'MASTER':
      return 'text-indigo-200'
    case 'LEGEND':
      return 'text-yellow-100'
    default:
      return 'text-emerald-200'
  }
}

// ── ApexCharts options ──────────────────────────────────────────────────────

const apexFunnelOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: {
    bar: { horizontal: true, borderRadius: 6, distributed: true, dataLabels: { position: 'right' } },
  },
  colors: ['#94a3b8', '#f59e0b', '#3b82f6', '#10b981'],
  dataLabels: { enabled: true, formatter: (val: number) => `${val} klientów` },
  xaxis: { categories: chartData.value.map((d) => d.label) },
  legend: { show: false },
  grid: { borderColor: '#f1f5f9', xaxis: { lines: { show: false } } },
  tooltip: { y: { formatter: (val: number) => `${val} klientów` } },
}))

const apexBarOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  colors: ['#C5A059'],
  dataLabels: { enabled: false },
  xaxis: {
    categories: chartData.value.map((d) => d.label),
    labels: { rotate: -40, style: { fontSize: '11px' } },
  },
  yaxis: { title: { text: chartMetric.value === 'SALES' ? 'Podpisane umowy' : 'Spotkania' }, min: 0, forceNiceScale: true },
  grid: { borderColor: '#f1f5f9' },
  tooltip: { y: { formatter: (val: number) => `${val}` } },
}))

const apexChartSeries = computed(() => [
  { name: chartMetric.value === 'SALES' ? 'Podpisane' : 'Spotkania', data: chartData.value.map((d) => d.value) },
])

// Monthly new-client trend — last 6 months (scoped to funnelClients)
const monthlyTrendLabels = computed(() => {
  const now = new Date()
  return Array.from({ length: 6 }, (_, i) => {
    const d = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1)
    return d.toLocaleString('pl-PL', { month: 'short', year: '2-digit' })
  })
})

const monthlyTrendSeries = computed(() => {
  const now = new Date()
  const list = funnelClients.value
  const data = Array.from({ length: 6 }, (_, i) => {
    const d = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1)
    const start = new Date(d.getFullYear(), d.getMonth(), 1)
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59)
    return list.filter((c: any) => {
      const created = c.createdAt ? new Date(c.createdAt) : null
      return created && created >= start && created <= end
    }).length
  })
  return [{ name: 'Nowi klienci', data }]
})

const apexLineOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
  stroke: { curve: 'smooth', width: 3 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 90, 100] } },
  colors: ['#C5A059'],
  xaxis: { categories: monthlyTrendLabels.value },
  yaxis: { title: { text: 'Klienci' }, min: 0, forceNiceScale: true },
  dataLabels: { enabled: false },
  markers: { size: 4 },
  grid: { borderColor: '#f1f5f9' },
  tooltip: { y: { formatter: (val: number) => `${val} klientów` } },
}))

onMounted(() => {
  auditLogStore.fetchLogs()
  if (clientStore.clients.length === 0) clientStore.fetchClients({ perPage: 200 })
  if (structure.users.length === 0) structure.fetchStructure()
})
</script>

<template>
  <div class="space-y-3 md:space-y-6">
    <div class="flex justify-between items-center mb-1 md:mb-2">
      <h1 class="text-lg md:text-xl font-semibold text-gray-800">Dashboard - Analityka</h1>
      <span class="text-xs text-gray-500">Ostatnia aktualizacja: Dzisiaj</span>
    </div>

    <div v-if="criticalNotifications.length > 0" class="bg-red-50 border border-red-200 p-3 rounded flex items-start space-x-3 mb-4">
      <AppIcon name="exclamation-triangle" class="w-5 h-5 text-red-600 mt-0.5" />
      <div>
        <h4 class="text-sm font-semibold text-red-800">Wymagane działanie</h4>
        <ul class="list-disc pl-4 mt-1 text-xs text-red-700">
          <li v-for="notif in criticalNotifications" :key="notif.id">{{ notif.message }}</li>
        </ul>
      </div>
    </div>

    <div v-if="user && ['SALES', 'MANAGER'].includes(user.role)" class="bg-white border border-gray-200 rounded shadow-sm p-4 relative overflow-hidden">
      <div class="flex flex-col md:flex-row justify-between items-center relative z-10">
        <div class="flex items-center">
          <div class="w-12 h-12 rounded-full bg-brand-main text-white flex items-center justify-center text-2xl">
            <AppIcon :name="getRankIconName(user.rank)" class="w-6 h-6" :class="getRankIconClass(user.rank)" />
          </div>
          <div class="ml-4">
            <p class="text-xs text-gray-500 uppercase font-bold">Twoja Ranga</p>
            <h3 class="text-xl font-bold text-gray-800">{{ user.rank || 'JUNIOR' }}</h3>
            <p class="text-xs text-gray-500">Punkty: <span class="text-brand-main font-bold">{{ user.points || 0 }} XP</span></p>
          </div>
        </div>

        <div v-if="nextRankData" class="w-full md:w-1/2 mt-4 md:mt-0 pl-0 md:pl-10">
          <div class="flex justify-between text-xs mb-1 text-gray-600">
            <span>Następny cel: <strong>{{ nextRankData.nextRank }}</strong></span>
            <span>{{ user.points || 0 }} / {{ nextRankData.required }}</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-brand-main h-2 rounded-full transition-all duration-1000" :style="{ width: `${((user.points || 0) / nextRankData.required) * 100}%` }"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-if="user?.role !== 'CLIENT_HR'" class="bg-white border border-gray-200 rounded shadow-sm p-4 hover:border-brand-main transition-colors cursor-default">
        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
          {{ user?.role === 'SALES' ? 'Moi Klienci' : 'Klienci Zespołu' }}
        </dt>
        <dd class="mt-2 text-2xl md:text-3xl font-light text-gray-900">{{ myClientsCount }}</dd>
      </div>
      <div v-if="user?.role !== 'CLIENT_HR'" class="bg-white border border-gray-200 rounded shadow-sm p-4 hover:border-brand-main transition-colors cursor-default">
        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
          {{ user?.role === 'SALES' ? 'Podpisane Umowy' : 'Umowy Zespołu' }}
        </dt>
        <dd class="mt-2 text-2xl md:text-3xl font-light text-gray-900">{{ signedCount }}</dd>
      </div>
      <div v-if="user?.role !== 'CLIENT_HR'" class="bg-white border border-gray-200 rounded shadow-sm p-4 hover:border-brand-main transition-colors cursor-default">
        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Estymacja (Lejek)</dt>
        <dd class="mt-2 text-2xl md:text-3xl font-light text-gray-900">{{ formatCurrency(funnelEstimate) }}</dd>
        <p class="text-xs text-gray-400 mt-1">Ważone wg etapu i wielkości zespołu klienta.</p>
      </div>
      <div v-if="user?.role !== 'CLIENT_HR'" class="bg-white border border-gray-200 rounded shadow-sm p-4 hover:border-brand-main transition-colors cursor-default">
        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Wymaga uwagi</dt>
        <dd class="mt-2 text-2xl md:text-3xl font-light text-amber-600">{{ criticalNotifications.length }}</dd>
      </div>
      <div v-else class="bg-white border border-gray-200 shadow-sm p-4 col-span-4 rounded">
        <h3 class="text-lg font-semibold text-gray-900">Portal Klienta HR</h3>
        <p class="text-sm text-gray-500 mt-1">Zarządzaj benefitami pracowników w jednym miejscu.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div v-if="user?.role !== 'CLIENT_HR'" class="bg-white border border-gray-200 rounded shadow-sm p-4 flex flex-col">
        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
          <h3 class="text-sm font-bold text-gray-700 uppercase">{{ chartTitle }}</h3>
          <div v-if="user && ['MANAGER', 'DIRECTOR', 'ADMIN'].includes(user.role)" class="flex space-x-2">
            <button type="button" class="text-xs px-2 py-1 rounded" :class="chartMetric === 'SALES' ? 'bg-brand-main text-white' : 'text-gray-600'" @click="chartMetric = 'SALES'">Sprzedaż</button>
            <button type="button" class="text-xs px-2 py-1 rounded" :class="chartMetric === 'MEETINGS' ? 'bg-brand-main text-white' : 'text-gray-600'" @click="chartMetric = 'MEETINGS'">Spotkania</button>
          </div>
        </div>
        <div class="space-y-3">
          <div v-if="chartData.length === 0" class="text-xs text-gray-400 text-center py-8">Brak danych.</div>
          <template v-else>
            <apexchart
              v-if="user?.role === 'SALES'"
              type="bar"
              height="220"
              :options="apexFunnelOptions"
              :series="apexChartSeries"
            />
            <apexchart
              v-else
              type="bar"
              height="220"
              :options="apexBarOptions"
              :series="apexChartSeries"
            />
          </template>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded shadow-sm">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
          <h3 class="text-sm font-bold text-gray-700 uppercase">Oś czasu aktywności</h3>
        </div>
        <div class="p-4">
          <div class="relative border-l border-gray-200 ml-3 space-y-6">
            <div v-for="log in recentLogs" :key="log.id" class="relative pl-6">
              <span class="absolute -left-1.5 top-1 h-3 w-3 rounded-full bg-gray-200 border-2 border-white ring-1 ring-gray-300"></span>
              <div class="text-xs text-gray-500">{{ new Date(log.date).toLocaleString() }}</div>
              <div class="text-sm text-gray-800 font-medium">{{ log.details }}</div>
            </div>
            <p v-if="recentLogs.length === 0" class="text-gray-500 text-sm pl-6">Brak danych.</p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="user?.role !== 'CLIENT_HR'" class="bg-white border border-gray-200 rounded shadow-sm p-4">
      <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
        <h3 class="text-sm font-bold text-gray-700 uppercase">Trend — Nowi Klienci (6 miesięcy)</h3>
      </div>
      <apexchart type="area" height="200" :options="apexLineOptions" :series="monthlyTrendSeries" />
    </div>
  </div>
</template>
