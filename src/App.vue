<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useNotificationStore } from '@/stores/notification'
import { useStructureStore } from '@/stores/structure'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import ToastContainer from '@/components/ToastContainer.vue'
import AppIcon from '@/components/AppIcon.vue'
import logoUrl from '@/assets/logo.svg'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const session = useSessionStore()
const data = useDataStore()
const clientStore = useClientStore()
const notifStore = useNotificationStore()
const structure = useStructureStore()
const viewPermissions = useViewPermissionsStore()

const isSidebarOpen = ref(true)
const showNotifications = ref(false)
const showCommandPalette = ref(false)
const commandQuery = ref('')
const commandInput = ref<HTMLInputElement | null>(null)

const { currentUser } = storeToRefs(session)
const { users: dataUsers } = storeToRefs(data)
const { clients } = storeToRefs(clientStore)
const { notifications } = storeToRefs(notifStore)
const { users: structureUsers } = storeToRefs(structure)
const shouldShowSidebar = computed(() => !route.path.includes('/sales/start'))
const showShell = computed(() => route.path.startsWith('/app'))
const authRole = computed(() => {
  const roles = auth.user?.roles || []
  const allowed = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR']
  return roles.find((role) => allowed.includes(role))
})
const sidebarUser = computed(() => {
  const user = currentUser.value
  if (user) return { name: user.name, role: user.role, crmNumber: user.crmNumber }
  return {
    name: auth.fullName || auth.user?.username || auth.user?.email || '',
    role: authRole.value,
    crmNumber: undefined,
  }
})
const topbarUser = computed(() => {
  const baseUser = currentUser.value
  const list = auth.enabled ? structureUsers.value : dataUsers.value
  if (baseUser?.hierarchicalId || baseUser?.hierarchicalCode || baseUser?.crmNumber) return baseUser
  if (!Array.isArray(list) || list.length === 0) return baseUser || null

  if (baseUser?.id) {
    const byId = list.find((user) => user.id === baseUser.id)
    if (byId) return byId
  }

  const authEmail = auth.user?.email || auth.user?.username
  const baseEmail = baseUser?.email || authEmail
  if (baseEmail) {
    const byEmail = list.find((user) => user.email === baseEmail)
    if (byEmail) return byEmail
  }

  const baseName = baseUser?.name || auth.fullName
  if (baseName) {
    const normalized = baseName.trim().toLowerCase()
    const byName = list.find((user) => user.name?.trim().toLowerCase() === normalized)
    if (byName) return byName
  }

  return baseUser || null
})
const topbarId = computed(() => {
  const user = topbarUser.value
  if (user?.hierarchicalId) return user.hierarchicalId
  if (user?.hierarchicalCode) return user.hierarchicalCode
  if (user?.crmNumber) return user.crmNumber
  if (user?.id) return user.id
  return auth.user?.hierarchicalId || auth.user?.crmNumber || auth.user?.id || ''
})
const topbarRole = computed(() => {
  const roleName = (topbarUser.value as { role_name?: string; roleName?: string } | null)?.role_name || (topbarUser.value as { roleName?: string } | null)?.roleName
  if (roleName) return roleName
  const role = topbarUser.value?.role || authRole.value || ''
  const roleLabels: Record<string, string> = {
    ADMIN: 'Super Admin',
    DIRECTOR: 'Dyrektor',
    MANAGER: 'Manager',
    SALES: 'Handlowiec',
    CLIENT_HR: 'Kadry',
  }
  return roleLabels[role] || role
})
const sidebarInitials = computed(() => {
  const name = sidebarUser.value.name?.trim() || ''
  if (!name) return '--'
  const parts = name.split(/\s+/).filter(Boolean)
  const initials = parts.length >= 2 ? parts[0][0] + parts[1][0] : name.slice(0, 2)
  return initials.toUpperCase()
})

const navLinks = computed(() => {
  const fallbackRole = !auth.enabled ? (localStorage.getItem('stratton_dev_role') || undefined) : undefined
  const role = currentUser.value?.role || authRole.value || fallbackRole
  const links: Array<{ label: string; path: string; icon: string; viewKey: string }> = []

  links.push({ label: 'Kokpit', path: '/app/dashboard', icon: 'dashboard', viewKey: 'dashboard' })

  if (role === 'ADMIN') {
    links.push(
      { label: 'Analityka', path: '/app/analytics', icon: 'chart-line', viewKey: 'analytics' },
      { label: 'Klienci', path: '/app/clients', icon: 'users', viewKey: 'clients' },
      { label: 'Struktura', path: '/app/structure', icon: 'sitemap', viewKey: 'structure' },
      { label: 'HR / Kadry', path: '/app/hr-panel', icon: 'briefcase', viewKey: 'hr-panel' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'invoice', viewKey: 'settlements' },
      { label: 'Faktury', path: '/app/admin-invoices', icon: 'file-invoice-dollar', viewKey: 'admin-invoices' },
      { label: 'Progi Prowizyjne', path: '/app/commission-thresholds', icon: 'sliders', viewKey: 'commission-thresholds' },
      { label: 'Autenti', path: '/app/autenti-panel', icon: 'signature', viewKey: 'autenti-panel' },
      { label: 'Rankingi', path: '/app/leaderboard', icon: 'trophy', viewKey: 'leaderboard' },
      { label: 'Ustawienia', path: '/app/settings', icon: 'gear', viewKey: 'settings' }
    )
  } else if (role === 'DIRECTOR') {
    links.push(
      { label: 'Analityka', path: '/app/analytics', icon: 'chart-pie', viewKey: 'analytics' },
      { label: 'Mój Zespół', path: '/app/structure', icon: 'people-roof', viewKey: 'structure' },
      { label: 'Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'wallet', viewKey: 'settlements' },
      { label: 'Rankingi', path: '/app/leaderboard', icon: 'medal', viewKey: 'leaderboard' }
    )
  } else if (role === 'MANAGER') {
    links.push(
      { label: 'Mój Zespół', path: '/app/structure', icon: 'people-group', viewKey: 'structure' },
      { label: 'Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'wallet', viewKey: 'settlements' },
      { label: 'Rankingi', path: '/app/leaderboard', icon: 'medal', viewKey: 'leaderboard' }
    )
  } else if (role === 'SALES') {
    links.push(
      { label: 'Moi Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Szybka Oferta', path: '/app/quick-calculator', icon: 'calculator', viewKey: 'quick-calculator' },
      { label: 'Moje Prowizje', path: '/app/settlements', icon: 'hand-holding-dollar', viewKey: 'settlements' },
      { label: 'Ranking', path: '/app/leaderboard', icon: 'award', viewKey: 'leaderboard' }
    )
  }

  links.push(
    { label: 'Kalendarz', path: '/app/calendar', icon: 'calendar', viewKey: 'calendar' },
    { label: 'Poczta', path: '/app/mailbox', icon: 'envelope', viewKey: 'mailbox' },
    { label: 'Baza Wiedzy', path: '/app/knowledge-base', icon: 'book-open', viewKey: 'knowledge-base' }
  )

  return links.filter((link) => viewPermissions.isViewAllowed(link.viewKey, role))
})

const myNotifications = computed(() => {
  const user = currentUser.value
  if (!user) return []
  return notifications.value
    .filter((notif) => notif.userId === user.id)
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const unreadCount = computed(() => myNotifications.value.filter((notif) => !notif.read).length)

const predefinedActions = [
  { label: 'Pulpit', category: 'Akcje', execute: () => router.push('/app/dashboard') },
  { label: 'Dodaj nowego klienta', category: 'Akcje', execute: () => router.push({ path: '/app/clients', query: { action: 'new' } }) },
  { label: 'Struktura zespołu', category: 'Akcje', execute: () => router.push('/app/structure') },
  { label: 'Kalendarz', category: 'Akcje', execute: () => router.push('/app/calendar') },
  { label: 'Baza Wiedzy', category: 'Akcje', execute: () => router.push('/app/knowledge-base') },
  { label: 'Ustawienia', category: 'Akcje', execute: () => router.push('/app/settings') },
]

const commandResults = computed(() => {
  const query = commandQuery.value.toLowerCase()
  if (!query) return { actions: [], clients: [], users: [] }

  const actions = predefinedActions.filter((action) => action.label.toLowerCase().includes(query))
  const user = currentUser.value

  let visibleClients = clients.value
  if (user?.role === 'SALES') visibleClients = visibleClients.filter((client) => client.ownerId === user.id)

  const matchedClients = visibleClients.filter((client) => client.name.toLowerCase().includes(query)).slice(0, 5)
  const userList = auth.enabled ? structureUsers.value : dataUsers.value
  const matchedUsers = userList
    .filter((u) => !u.isRemovedFromStructure && u.name.toLowerCase().includes(query))
    .slice(0, 5)

  return { actions, clients: matchedClients, users: matchedUsers }
})

const getPageTitle = () => {
  const path = route.path
  if (path.includes('dashboard')) return 'Kokpit Zarządczy'
  if (path.includes('clients')) return 'Baza Klientów'
  if (path.includes('structure')) return 'Struktura Organizacyjna'
  if (path.includes('settings')) return 'Ustawienia Systemu'
  if (path.includes('analytics')) return 'Analityka i Raporty'
  if (path.includes('calendar')) return 'Kalendarz Spotkań'
  return 'Stratton CRM'
}

const toggleCommandPalette = () => {
  showCommandPalette.value = !showCommandPalette.value
  if (!showCommandPalette.value) commandQuery.value = ''
}

const executeCommand = (item: any, type: 'action' | 'client' | 'user') => {
  if (type === 'action') {
    item.execute()
  } else if (type === 'client') {
    router.push({ path: '/app/clients', query: { expand: item.id } })
  } else if (type === 'user') {
    router.push({ path: '/app/structure', query: { search: item.name } })
  }
  toggleCommandPalette()
}

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
}

const markAsRead = (id: string) => {
  notifStore.markAsRead(id)
}

const logout = async () => {
  await auth.logout()
  session.clearSession()
  isSidebarOpen.value = false
  router.push('/login')
}

const handleGlobalKeyDown = (event: KeyboardEvent) => {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'f') {
    event.preventDefault()
    toggleCommandPalette()
  }
  if (event.key === 'Escape' && showCommandPalette.value) {
    showCommandPalette.value = false
  }
}

watch(showCommandPalette, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    commandInput.value?.focus()
  }
})

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeyDown)
  clientStore.checkSla()
  clientStore.checkReservations()
  viewPermissions.ensureLoaded()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeyDown)
})
</script>

<template>
  <div v-if="showShell" class="flex h-screen bg-slate-50 transition-all duration-300" :class="session.isImpersonating ? 'border-[6px] border-amber-400' : ''">
    <ToastContainer />

    <aside
      v-if="shouldShowSidebar"
      class="flex-shrink-0 flex flex-col transition-all duration-300 bg-slate-900 border-r border-slate-800"
      :class="isSidebarOpen ? 'w-72' : 'w-20'"
    >
      <div class="h-20 flex items-center justify-center border-b border-slate-800 transition-colors hover:bg-slate-800/50 cursor-pointer" @click="router.push('/app/dashboard')">
        <div v-if="isSidebarOpen" class="flex items-center space-x-3 text-stratton-gold font-serif font-bold text-xl tracking-widest animate-fade-in">
          <img :src="logoUrl" alt="Stratton logo" class="w-9 h-9 rounded bg-stratton-gold p-1 ring-1 ring-white/20" />
          <span>STRATTON</span>
        </div>
        <img v-else :src="logoUrl" alt="Stratton logo" class="w-10 h-10 rounded-lg bg-stratton-gold p-1 ring-1 ring-white/20" />
      </div>

      <nav class="flex-1 overflow-y-auto py-6 space-y-1 px-3">
        <RouterLink
          v-for="link in navLinks"
          :key="link.path"
          :to="link.path"
          class="group flex items-center px-3 py-3 rounded-xl transition-all duration-200 mb-1"
          :class="route.path.startsWith(link.path) ? 'bg-stratton-gold text-slate-900 shadow-md font-bold' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
          :title="link.label"
        >
          <div
            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
            :class="route.path.startsWith(link.path) ? 'bg-transparent' : 'bg-white/10 group-hover:bg-white/20'"
          >
            <AppIcon :name="link.icon" class="w-5 h-5" />
          </div>
          <span v-if="isSidebarOpen" class="ml-3 text-sm font-medium whitespace-nowrap animate-fade-in transition-opacity">
            {{ link.label }}
          </span>
        </RouterLink>
      </nav>

      <div class="p-4 border-t border-slate-800 bg-slate-900">
        <div class="flex items-center" :class="isSidebarOpen ? 'justify-between' : 'justify-center'">
          <div v-if="isSidebarOpen" class="flex items-center min-w-0 mr-2">
            <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-white font-bold text-xs ring-2 ring-slate-600">
              {{ sidebarInitials }}
            </div>
            <div class="ml-3 min-w-0">
              <p class="text-sm font-medium text-white truncate">{{ sidebarUser?.name }}</p>
              <div class="flex flex-col">
                <span class="text-[10px] text-slate-400">{{ sidebarUser?.role }}</span>
                <span v-if="sidebarUser?.crmNumber" class="text-[10px] text-stratton-gold font-mono">{{ sidebarUser?.crmNumber }}</span>
              </div>
            </div>
          </div>
          <button type="button" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" @click="toggleSidebar">
            <AppIcon :name="isSidebarOpen ? 'chevron-left' : 'chevron-right'" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 relative bg-slate-50">
      <header v-if="shouldShowSidebar" class="bg-white h-20 border-b border-slate-200 sticky top-0 z-40 px-8 flex items-center justify-between shadow-sm">
        <div class="flex items-center">
          <h1 class="text-xl font-bold text-slate-800 hidden sm:block">{{ getPageTitle() }}</h1>
        </div>
        <div class="hidden md:block flex-1 max-w-xl mx-8 relative">
          <div
            class="w-full bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl px-4 py-2.5 flex items-center cursor-text transition group shadow-sm hover:shadow"
            @click="toggleCommandPalette"
          >
            <AppIcon name="search" class="w-5 h-5 text-slate-400 mr-3 group-hover:text-stratton-gold transition" />
            <span class="text-slate-500 text-sm font-medium">Szukaj (Ctrl + K)</span>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <div class="relative cursor-pointer group" @click="toggleNotifications">
            <div class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-50 rounded-full transition">
              <AppIcon name="bell" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition" />
              <span v-if="unreadCount > 0" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500 border border-white"></span>
            </div>
          </div>
          <button type="button" class="flex items-center gap-2 text-slate-400 hover:text-slate-800 transition text-xs font-bold uppercase tracking-wide group" @click="logout">
            <span class="group-hover:underline">Wyloguj</span>
            <AppIcon name="logout" class="w-4 h-4" />
          </button>
        </div>
      </header>
      <header v-else class="bg-white h-20 border-b border-slate-100 sticky top-0 z-40 px-8 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-6">
          <div class="flex items-center space-x-3 text-slate-900 font-serif font-bold text-xl tracking-widest cursor-pointer" @click="router.push('/app/dashboard')">
            <img :src="logoUrl" alt="Stratton logo" class="w-9 h-9 rounded bg-stratton-gold p-1 ring-1 ring-slate-200" />
            <span>STRATTON</span>
          </div>
          <div class="h-8 w-px bg-slate-200"></div>
          <div class="border border-slate-200 rounded px-2 py-1 text-[10px] font-mono font-bold text-slate-500 bg-slate-50">
            {{ topbarId || 'BRAK ID' }}
          </div>
          <div class="flex flex-col justify-center">
            <span class="text-xs font-bold text-slate-900 leading-tight">{{ sidebarUser.name }}</span>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-tight">{{ topbarRole || '---' }}</span>
          </div>
        </div>
        <div class="flex items-center space-x-6">
          <div class="relative cursor-pointer group" @click="toggleNotifications">
            <div class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-50 rounded-full transition">
              <AppIcon name="bell" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition" />
              <span v-if="unreadCount > 0" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500 border border-white"></span>
            </div>
          </div>
          <button type="button" class="flex items-center gap-2 text-slate-400 hover:text-slate-800 transition text-xs font-bold uppercase tracking-wide group" @click="logout">
            <span class="group-hover:underline">Wyloguj</span>
            <AppIcon name="logout" class="w-4 h-4" />
          </button>
        </div>
      </header>

      <div v-if="session.isImpersonating" class="bg-amber-400 text-amber-900 text-sm py-2 px-4 text-center font-bold flex justify-center items-center shadow-md z-30 animate-pulse">
        <AppIcon name="mask" class="w-4 h-4 mr-2" />
        <span class="mr-4 uppercase tracking-wider">TRYB PODGLĄDU: {{ currentUser?.name }}</span>
        <button type="button" class="bg-white text-amber-900 px-3 py-1 rounded text-xs hover:bg-amber-50 shadow-sm border border-amber-500 uppercase font-bold" @click="session.stopImpersonation">
          Zakończ
        </button>
      </div>

      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 scroll-smooth crm-form">
        <RouterView v-slot="{ Component }">
          <Transition name="route" mode="out-in" appear>
            <component :is="Component" v-if="Component" />
          </Transition>
        </RouterView>
        <footer class="mt-12 border-t border-slate-200 py-8 text-center">
          <p class="text-xs text-slate-400 font-medium">&copy; 2026 Stratton Financial Services. System Version 3.1</p>
        </footer>
      </main>
    </div>

    <div v-if="showNotifications" class="absolute right-8 top-24 w-96 bg-white rounded-xl shadow-xl border border-slate-100 py-0 z-50 text-sm overflow-hidden animate-fade-in-up" @click.stop>
      <div class="bg-slate-50 px-5 py-4 border-b border-slate-100 font-bold text-slate-700 flex justify-between items-center">
        <span>Powiadomienia</span>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="toggleNotifications">
          <AppIcon name="xmark" class="w-4 h-4" />
        </button>
      </div>
      <div class="max-h-80 overflow-y-auto">
        <div
          v-for="notif in myNotifications"
          :key="notif.id"
          class="px-5 py-4 hover:bg-slate-50 cursor-pointer border-b border-slate-50 transition group"
          :class="!notif.read ? 'bg-blue-50' : ''"
          @click="markAsRead(notif.id)"
        >
          <p class="text-stratton-blue font-bold text-xs mb-1 group-hover:underline">
            <AppIcon name="info" class="w-4 h-4 mr-1" />
            {{ notif.type }}
          </p>
          <p class="text-slate-800 text-sm font-medium">{{ notif.message }}</p>
          <p class="text-slate-500 text-xs mt-1">{{ new Date(notif.date).toLocaleString() }}</p>
        </div>
        <div v-if="myNotifications.length === 0" class="p-8 text-center text-slate-400 text-sm">Brak nowych powiadomień.</div>
      </div>
    </div>
  </div>

  <div v-else class="min-h-screen">
    <ToastContainer />
    <RouterView v-slot="{ Component }">
      <Transition name="route" mode="out-in" appear>
        <component :is="Component" v-if="Component" />
      </Transition>
    </RouterView>
  </div>

  <div v-if="showCommandPalette" class="fixed inset-0 z-[99] flex justify-center pt-[15vh]" @click="toggleCommandPalette">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[600px] flex flex-col transition-all overflow-hidden" @click.stop>
      <div class="p-4 border-b border-slate-100 flex items-center bg-slate-50">
        <AppIcon name="search" class="w-5 h-5 text-slate-400 mx-3" />
        <input
          ref="commandInput"
          v-model="commandQuery"
          type="text"
          placeholder="Wpisz komendę, klienta lub pracownika..."
          class="w-full text-lg bg-transparent border-none focus:ring-0 !p-1 !shadow-none text-slate-800 placeholder-slate-400 font-medium"
        />
        <span class="text-xs text-slate-400 border border-slate-200 rounded px-2 py-1 bg-white">ESC</span>
      </div>
      <div class="overflow-y-auto">
        <div v-if="commandQuery && commandResults.actions.length === 0 && commandResults.clients.length === 0 && commandResults.users.length === 0" class="text-center py-16">
          <AppIcon name="ghost" class="w-10 h-10 text-slate-200 mb-4" />
          <p class="text-slate-500 font-medium">Brak wyników wyszukiwania.</p>
        </div>
        <div v-else>
          <div v-if="commandResults.actions.length > 0" class="p-2">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase px-4 py-2 tracking-wider">Szybkie Akcje</h3>
            <ul>
              <li
                v-for="action in commandResults.actions"
                :key="action.label"
                class="group px-4 py-3 mx-2 text-sm text-slate-700 hover:bg-stratton-blue hover:text-white rounded-lg cursor-pointer flex items-center justify-between transition-colors"
                @click="executeCommand(action, 'action')"
              >
                <span class="flex items-center gap-3 font-medium">
                  <AppIcon name="bolt" class="w-4 h-4 text-slate-400 group-hover:text-white/70" />
                  {{ action.label }}
                </span>
                <span class="text-[10px] text-slate-400 group-hover:text-white/70 opacity-0 group-hover:opacity-100 transition-opacity">Enter</span>
              </li>
            </ul>
          </div>
          <div v-if="commandResults.clients.length > 0" class="p-2 border-t border-slate-100">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase px-4 py-2 tracking-wider">Klienci</h3>
            <ul>
              <li
                v-for="client in commandResults.clients"
                :key="client.id"
                class="group px-4 py-3 mx-2 text-sm text-slate-700 hover:bg-emerald-600 hover:text-white rounded-lg cursor-pointer flex items-center justify-between transition-colors"
                @click="executeCommand(client, 'client')"
              >
                <span class="flex items-center gap-3 font-medium">
                  <AppIcon name="user" class="w-4 h-4 text-emerald-500 group-hover:text-white/70" />
                  {{ client.name }}
                  <span class="text-xs opacity-50">({{ client.nip }})</span>
                </span>
              </li>
            </ul>
          </div>
          <div v-if="commandResults.users.length > 0" class="p-2 border-t border-slate-100">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase px-4 py-2 tracking-wider">Pracownicy</h3>
            <ul>
              <li
              v-for="u in commandResults.users"
                :key="u.id"
                class="group px-4 py-3 mx-2 text-sm text-slate-700 hover:bg-violet-600 hover:text-white rounded-lg cursor-pointer flex items-center justify-between transition-colors"
                @click="executeCommand(u, 'user')"
              >
                <span class="flex items-center gap-3 font-medium">
                  <AppIcon name="user-tie" class="w-4 h-4 text-violet-500 group-hover:text-white/70" />
                  {{ u.name }}
                  <span class="text-xs opacity-50">({{ u.role }})</span>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-400 flex justify-between px-6">
        <span><b>↑↓</b> nawigacja</span>
        <span><b>enter</b> wybierz</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.route-enter-from,
.route-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.route-enter-active,
.route-leave-active {
  transition: opacity 220ms ease, transform 220ms ease;
}
</style>
