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
import { useUiStore } from '@/stores/ui'
import ToastContainer from '@/components/ToastContainer.vue'
import AppIcon from '@/components/AppIcon.vue'
import MailComposeModal from '@/components/MailComposeModal.vue'
import ChatPanel from '@/components/ChatPanel.vue'
import AiChatWidget from '@/components/AiChatWidget.vue'
import MobileBottomNav from '@/components/MobileBottomNav.vue'
import { useChatStore } from '@/stores/chat'
import { webPush } from '@/services/webPush'
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
const ui = useUiStore()
const chatStore = useChatStore()
const { totalUnread: chatUnread } = storeToRefs(chatStore)

const isSidebarOpen = ref(true)
const { showNotifications, showCommandPalette, commandQuery } = storeToRefs(ui)
const commandInput = ref<HTMLInputElement | null>(null)

const { currentUser } = storeToRefs(session)
const { users: dataUsers } = storeToRefs(data)
const { clients } = storeToRefs(clientStore)
const { notifications } = storeToRefs(notifStore)
const { users: structureUsers } = storeToRefs(structure)
const shouldShowSidebar = computed(() => true)
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

  links.push({ label: 'Główny Pulpit', path: '/app/dashboard', icon: 'dashboard', viewKey: 'dashboard' })

  if (role === 'ADMIN') {
    links.push(
      { label: 'Użytkownicy', path: '/app/users', icon: 'user-tie', viewKey: 'user-management' },
      { label: 'Analityka Finansowa', path: '/app/admin-analytics', icon: 'presentation-chart-line', viewKey: 'admin-analytics' },
      { label: 'Logi Systemowe', path: '/app/admin-logs', icon: 'shield-check', viewKey: 'admin-logs' },
      { label: 'Analityka (Sprzedaż)', path: '/app/analytics', icon: 'chart-line', viewKey: 'analytics' },
      { label: 'Klienci', path: '/app/clients', icon: 'users', viewKey: 'clients' },
      { label: 'Struktura', path: '/app/structure', icon: 'sitemap', viewKey: 'structure' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'invoice', viewKey: 'settlements' },
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
      { label: 'Spotkania', path: '/app/meetings', icon: 'calendar', viewKey: 'meetings' },
      { label: 'Moi Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Szybka Oferta', path: '/app/quick-calculator', icon: 'calculator', viewKey: 'quick-calculator' },
      { label: 'Moje Prowizje', path: '/app/settlements', icon: 'hand-holding-dollar', viewKey: 'settlements' },
      { label: 'Ranking', path: '/app/leaderboard', icon: 'award', viewKey: 'leaderboard' }
    )
  } else if (role === 'LEADOWIEC') {
    // Pozycje odpowiadające aktywnym kafelkom w panelu leadowca.
    links.push(
      { label: 'Moje rozliczenia', path: '/app/leadowiec/settlements', icon: 'hand-holding-dollar', viewKey: 'leadowiec-settlements' },
      { label: 'Klienci w obsłudze', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Struktura', path: '/app/structure', icon: 'sitemap', viewKey: 'structure' },
      { label: 'Kalendarz', path: '/app/leadowiec/calendar', icon: 'calendar', viewKey: 'leadowiec-calendar' }
    )
  }

  links.push(
    { label: 'Kalendarz', path: '/app/calendar', icon: 'calendar', viewKey: 'calendar' },
    { label: 'Powiadomienia', path: '/app/notifications', icon: 'bell', viewKey: 'notifications' },
    { label: 'Poczta', path: '/app/mailbox', icon: 'envelope', viewKey: 'mailbox' },
    { label: 'Baza Wiedzy', path: '/app/knowledge-base', icon: 'book-open', viewKey: 'knowledge-base' }
  )

  if (role === 'ADMIN') {
    links.push(
      { label: 'Aktualności', path: '/app/news-management', icon: 'document-text', viewKey: 'news-management' },
      { label: 'Baza Wiedzy AI', path: '/app/admin/knowledge-base', icon: 'book-open', viewKey: 'admin-knowledge-base' }
    )
  }

  const filteredLinks = links.filter((link) => {
    // Force show for newly added permissions if viewPermissions might be lagging or configured strangely
    if (['user-management', 'admin-analytics', 'admin-logs', 'admin-knowledge-base'].includes(link.viewKey) && role === 'ADMIN') return true

    if (link.viewKey === 'settings') {
      return viewPermissions.isSettingsAllowed(role)
    }
    return viewPermissions.isViewAllowed(link.viewKey, role)
  })

  const dashboardLink = filteredLinks.find((link) => link.viewKey === 'dashboard')
  const otherLinks = filteredLinks.filter((link) => link.viewKey !== 'dashboard').sort((a, b) => {
    return a.label.localeCompare(b.label, 'pl')
  })

  return dashboardLink ? [dashboardLink, ...otherLinks] : otherLinks
})

const NOTIF_CLEARED_KEY = 'crm_notifications_cleared_at'
const _loadClearedAt = (): number | null => {
  try { const v = localStorage.getItem(NOTIF_CLEARED_KEY); return v ? Number(v) : null } catch { return null }
}
const notificationsClearedAt = ref<number | null>(_loadClearedAt())

const myNotifications = computed(() => {
  const user = currentUser.value
  if (!user) return []
  return notifications.value
    .filter((notif) => notif.userId === user.id)
    .filter((notif) => !notificationsClearedAt.value || new Date(notif.date).getTime() > notificationsClearedAt.value)
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const unreadCount = computed(() => myNotifications.value.filter((notif) => !notif.read).length)

const clearAllNotifications = () => {
  const now = Date.now()
  notificationsClearedAt.value = now
  try { localStorage.setItem(NOTIF_CLEARED_KEY, String(now)) } catch { /* ignore */ }
  const user = currentUser.value
  if (user) notifStore.markAllAsRead(user.id)
}


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
  const user = currentUser.value

  const actions = predefinedActions
    .filter((action) => action.label.toLowerCase().includes(query))
    .filter((action) => action.label !== 'Ustawienia' || viewPermissions.isSettingsAllowed(user?.role))

  let visibleClients = clients.value
  if (user?.role === 'SALES') visibleClients = visibleClients.filter((client) => client.ownerId === user.id)

  const matchedClients = visibleClients.filter((client) => client.name.toLowerCase().includes(query)).slice(0, 5)
  const userList = auth.enabled ? structureUsers.value : dataUsers.value
  const matchedUsers = userList
    .filter((u) => !u.isRemovedFromStructure && u.name.toLowerCase().includes(query))
    .slice(0, 5)

  return { actions, clients: matchedClients, users: matchedUsers }
})


const toggleCommandPalette = () => {
  ui.toggleCommandPalette()
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
  ui.toggleNotifications()
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

onMounted(async () => {
  window.addEventListener('keydown', handleGlobalKeyDown)
  clientStore.checkSla()
  clientStore.checkReservations()
  viewPermissions.ensureLoaded()
  if (auth.enabled && auth.isAuthenticated) {
    await session.resolveUserFromAuth()
    // Init Web Push (registers SW, subscribes if permission already granted)
    webPush.init().catch(() => {})
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeyDown)
})
</script>

<template>
  <div v-if="showShell" class="flex h-screen bg-slate-50 transition-all duration-300" :class="session.isImpersonating ? 'border-[6px] border-amber-400' : ''">
    <ToastContainer />
    <MailComposeModal />
    <ChatPanel />
    <AiChatWidget />

    <aside
      v-if="shouldShowSidebar"
      class="hidden md:flex flex-shrink-0 flex-col transition-all duration-300 bg-white border-r border-slate-200"
      :class="isSidebarOpen ? 'w-72' : 'w-20'"
    >
      <div class="h-20 flex items-center justify-center border-b border-slate-200 transition-colors hover:bg-slate-50 cursor-pointer px-4" @click="router.push('/app/dashboard')">
        <div v-if="isSidebarOpen" class="text-center animate-fade-in">
          <span class="text-stratton-gold font-bold text-xl uppercase tracking-wider block leading-tight">
            TAURI CRM
          </span>
          <span class="text-stratton-gold font-medium text-[10px] uppercase tracking-widest block leading-tight mt-0.5">
            System Zarządzania Zasobami Klienta
          </span>
        </div>
        <span v-else class="text-stratton-gold font-bold text-xl">T</span>
      </div>

      <nav class="flex-1 overflow-y-auto py-2 space-y-0.5 px-3">
        <RouterLink
          v-for="link in navLinks"
          :key="link.path"
          :to="link.path"
          class="group flex items-center px-3 py-1.5 rounded-xl transition-all duration-200"
          :class="route.path.startsWith(link.path) ? 'bg-stratton-gold text-slate-900 shadow-md font-bold' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'"
          :title="link.label"
        >
          <div
            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
            :class="route.path.startsWith(link.path) ? 'bg-transparent' : 'bg-slate-100 group-hover:bg-slate-200'"
          >
            <AppIcon :name="link.icon" class="w-5 h-5" />
          </div>
          <span v-if="isSidebarOpen" class="ml-3 text-sm font-medium whitespace-nowrap animate-fade-in transition-opacity">
            {{ link.label }}
          </span>
        </RouterLink>
      </nav>

      <div class="p-4 border-t border-slate-200 bg-white">
        <div class="flex items-center" :class="isSidebarOpen ? 'justify-between' : 'justify-center'">
          <div v-if="isSidebarOpen" class="flex items-center min-w-0 mr-2">
            <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-700 font-bold text-xs ring-2 ring-slate-300">
              {{ sidebarInitials }}
            </div>
            <div class="ml-3 min-w-0">
              <p class="text-sm font-medium text-slate-900 truncate">{{ sidebarUser?.name }}</p>
              <div class="flex flex-col">
                <span class="text-[10px] text-slate-500">{{ sidebarUser?.role }}</span>
                <span v-if="sidebarUser?.crmNumber" class="text-[10px] text-stratton-gold font-mono">{{ sidebarUser?.crmNumber }}</span>
              </div>
            </div>
          </div>
          <button type="button" class="p-2 rounded-lg text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition" @click="toggleSidebar">
            <AppIcon :name="isSidebarOpen ? 'chevron-left' : 'chevron-right'" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 relative bg-slate-50">
      <header v-if="shouldShowSidebar" class="relative w-full overflow-hidden pb-0 bg-slate-50 shrink-0 border-b border-gray-200">
        <div class="flex items-center justify-between w-full px-3 md:px-4 h-12 md:h-16">
          
          <!-- Left Wing -->
          <div class="flex-1 flex flex-col items-end relative h-full justify-center">
             <!-- Search Bar (Compact) -->
             <div
               class="absolute left-0 bottom-1 bg-white/90 hover:bg-white border border-slate-100 rounded-lg px-2 md:px-3 py-1.5 flex items-center cursor-text transition group shadow-sm z-30 w-32 sm:w-44 md:w-56 backdrop-blur-md overflow-hidden h-8 md:h-9"
               @click="toggleCommandPalette"
             >
               <AppIcon name="search" class="w-4 h-4 text-slate-400 mr-2 group-hover:text-stratton-gold transition shrink-0" />
               <span class="text-slate-500 text-[10px] font-bold truncate uppercase tracking-wider flex-1 text-right">Szukaj (Ctrl+F)</span>
             </div>

             <!-- Lines (desktop only) -->
             <div class="hidden lg:flex absolute right-0 top-2 md:top-3 w-[200%] flex-col gap-1 pointer-events-none">
               <div class="h-px bg-[#0f172a] w-full"></div>
               <div class="h-[2px] bg-[#0f172a] w-full"></div>
             </div>

             <!-- Text -->
             <span class="hidden lg:inline text-base font-bold text-[#0f172a] tracking-[0.25em] mr-2 mt-1 relative z-10 font-cinzel">
              STRATTON
             </span>
          </div>

          <!-- Center Logo -->
          <div class="px-1 md:px-2 relative z-20 shrink-0">
            <img :src="logoUrl" class="h-10 md:h-14 w-auto filter drop-shadow-md" alt="Stratton Prime" />
          </div>

          <!-- Right Wing -->
          <div class="flex-1 flex flex-col items-start relative h-full justify-center">
             <!-- Controls -->
             <div class="absolute right-0 bottom-1 flex items-center space-x-1 md:space-x-2 z-30 bg-white/90 backdrop-blur-md px-2 md:px-3 rounded-lg border border-slate-100 shadow-sm h-8 md:h-9">
               <!-- Chat icon -->
               <div class="relative cursor-pointer group" @click="chatStore.toggle()">
                 <div class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-full transition">
                   <AppIcon name="chat-bubble" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition" />
                   <span v-if="chatUnread > 0" class="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center border border-white z-10 leading-none">{{ chatUnread > 99 ? '99+' : chatUnread }}</span>
                 </div>
               </div>
               <div class="h-4 w-px bg-slate-200 mx-1"></div>
               <div class="relative cursor-pointer group" @click="toggleNotifications">
                  <div
                    class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-full transition"
                    :class="{ 'animate-bell': unreadCount > 0 }"
                  >
                    <AppIcon
                      name="bell"
                      class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition"
                    />
                    <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 border border-white z-10"></span>
                  </div>
               </div>
               <!-- Web Push permission button — shown only if not yet granted -->
               <template v-if="webPush.isSupported && webPush.permissionState !== 'granted'">
                 <div class="h-4 w-px bg-slate-200 mx-1"></div>
                 <button
                   type="button"
                   class="flex items-center gap-1 text-slate-400 hover:text-stratton-gold transition text-[11px] font-bold uppercase tracking-wide group"
                   title="Włącz powiadomienia push"
                   @click="webPush.requestPermissionAndSubscribe()"
                 >
                   <AppIcon name="bell" class="w-4 h-4" />
                   <span class="group-hover:underline hidden sm:inline">Push</span>
                 </button>
               </template>
               <div class="h-4 w-px bg-slate-200 mx-1"></div>
               <button type="button" class="flex items-center gap-1 text-slate-400 hover:text-slate-800 transition text-[11px] font-bold uppercase tracking-wide group" @click="logout">
                 <span class="hidden sm:inline group-hover:underline">Wyloguj</span>
                 <AppIcon name="logout" class="w-4 h-4" />
               </button>
             </div>

             <!-- Lines (desktop only) -->
             <div class="hidden lg:flex absolute left-0 top-2 md:top-3 w-[200%] flex-col gap-1 pointer-events-none">
               <div class="h-px bg-[#0f172a] w-full"></div>
               <div class="h-[2px] bg-[#0f172a] w-full"></div>
             </div>

             <!-- Text -->
             <span class="hidden lg:inline text-base font-bold text-[#0f172a] tracking-[0.25em] ml-2 mt-1 relative z-10 font-cinzel">
              PRIME
             </span>
          </div>
          
        </div>
      </header>
      <!-- Secondary Header Removed (Merged into main) -->

      <div v-if="session.isImpersonating" class="bg-amber-400 text-amber-900 text-sm py-2 px-4 text-center font-bold flex justify-center items-center shadow-md z-30 animate-pulse">
        <AppIcon name="mask" class="w-4 h-4 mr-2" />
        <span class="mr-4 uppercase tracking-wider">TRYB PODGLĄDU: {{ session.impersonatedUser?.name || 'Ładowanie...' }}</span>
        <button type="button" class="bg-white text-amber-900 px-3 py-1 rounded text-xs hover:bg-amber-50 shadow-sm border border-amber-500 uppercase font-bold" @click="session.stopImpersonation">
          Zakończ
        </button>
      </div>

      <main
        class="flex-1 scroll-smooth crm-form transition-all duration-300 min-h-0"
        :class="route.meta.fullHeight ? 'overflow-hidden p-0' : 'overflow-y-auto p-2 sm:p-4 lg:p-4 pb-20 md:pb-4'"
      >
        <RouterView v-slot="{ Component }">
          <Transition name="route" mode="out-in" appear>
            <component :is="Component" v-if="Component" />
          </Transition>
        </RouterView>
        <footer class="mt-4 border-t border-slate-200 py-3 text-center">
          <p class="text-xs text-slate-400 font-medium">&copy; 2026 CRM - System Zarządzania Zasobami Klienta. System Version 2.8</p>
        </footer>
      </main>
    </div>

    <div v-if="showNotifications" class="absolute right-8 top-24 w-96 bg-white rounded-xl shadow-xl border border-slate-100 py-0 z-50 text-sm overflow-hidden animate-fade-in-up" @click.stop>
      <div class="bg-slate-50 px-5 py-4 border-b border-slate-100 font-bold text-slate-700 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <span>Powiadomienia</span>
            <button v-if="myNotifications.length > 0" @click="clearAllNotifications" class="text-[10px] text-red-500 hover:text-red-700 uppercase tracking-wider font-bold bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition-colors" title="Wyczyść widok powiadomień (nie usuwa z historii)">
                Wyczyść
            </button>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="toggleNotifications">
          <AppIcon name="xmark" class="w-4 h-4" />
        </button>
      </div>
      <div class="max-h-80 overflow-y-auto">
        <div
          v-for="notif in myNotifications"
          :key="notif.id"
          class="px-5 py-4 hover:bg-slate-50 cursor-pointer border-b border-slate-50 transition group"
          :class="{ 'bg-blue-50': !notif.read }"
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

  <MobileBottomNav v-if="showShell" class="md:hidden" />

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
          class="w-full text-2xl bg-transparent border-none focus:ring-0 !p-1 !shadow-none text-slate-800 placeholder-slate-400 font-bold"
        />
        <span class="text-xs text-slate-400 border border-slate-200 rounded px-2 py-1 bg-white mr-3">ESC</span>
        <button type="button" @click.stop="toggleCommandPalette" class="text-slate-400 hover:text-red-500 transition-colors p-1 hover:bg-slate-200 rounded-full">
           <AppIcon name="xmark" class="w-6 h-6" />
        </button>
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

@keyframes bell-pulse {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); }
  70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(234, 179, 8, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
}

@keyframes bell-pulse {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); }
  70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(234, 179, 8, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
}

.animate-bell {
  animation: bell-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
