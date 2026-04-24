<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterView, useRoute, useRouter } from 'vue-router'
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
import AppSidebar from '@/components/AppSidebar.vue'
import AppHeader from '@/components/AppHeader.vue'
import NotificationsPanel from '@/components/NotificationsPanel.vue'

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

const isSidebarOpen = ref(localStorage.getItem('crm_sidebar') !== 'false')
const { showNotifications, showCommandPalette, commandQuery } = storeToRefs(ui)
const commandInput = ref<HTMLInputElement | null>(null)

const { currentUser } = storeToRefs(session)
const { users: dataUsers } = storeToRefs(data)
const { clients } = storeToRefs(clientStore)
const { notifications } = storeToRefs(notifStore)
const { users: structureUsers } = storeToRefs(structure)
const showShell = computed(() => route.path.startsWith('/app'))

const notificationsClearedAt = ref<number | null>(null)

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
    notificationsClearedAt.value = Date.now()
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
  localStorage.setItem('crm_sidebar', String(isSidebarOpen.value))
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
  localStorage.removeItem('crm_sidebar')
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

    <AppSidebar :is-open="isSidebarOpen" @toggle="toggleSidebar" />

    <div class="flex-1 flex flex-col min-w-0 relative bg-slate-50 lg:ml-14">
      <AppHeader
        :unread-count="unreadCount"
        @toggle-notifications="toggleNotifications"
        @toggle-command-palette="toggleCommandPalette"
        @toggle-sidebar="toggleSidebar"
        @logout="logout"
      />
      <div v-if="session.isImpersonating" class="bg-amber-400 text-amber-900 text-sm py-2 px-4 text-center font-bold flex justify-center items-center shadow-md z-30 animate-pulse">
        <AppIcon name="mask" class="w-4 h-4 mr-2" />
        <span class="mr-4 uppercase tracking-wider">TRYB PODGLĄDU: {{ session.impersonatedUser?.name || 'Ładowanie...' }}</span>
        <button type="button" class="bg-white text-amber-900 px-3 py-1 rounded text-xs hover:bg-amber-50 shadow-sm border border-amber-500 uppercase font-bold" @click="session.stopImpersonation">
          Zakończ
        </button>
      </div>

      <!-- MAIN CONTENT AREA -->
      <main class="flex-1 overflow-y-auto scroll-smooth crm-form transition-all duration-300 p-2 sm:p-4 lg:p-4">
        <RouterView v-slot="{ Component }">
          <Transition name="route" mode="out-in" appear>
            <component :is="Component" v-if="Component" />
          </Transition>
        </RouterView>
        <footer class="mt-12 border-t border-slate-200 py-8 text-center">
          <p class="text-xs text-slate-400 font-medium">&copy; 2026 CRM - System Zarządzania Zasobami Klienta. System Version 2.8</p>
        </footer>
      </main>
    </div>

    <!-- NOTIFICATIONS PANEL -->
    <NotificationsPanel
      v-if="showNotifications"
      :notifications="myNotifications"
      @close="toggleNotifications"
      @mark-as-read="markAsRead"
      @clear-all="clearAllNotifications"
    />
  </div>

  <div v-else class="min-h-screen">
    <ToastContainer />
    <RouterView v-slot="{ Component }">
      <Transition name="route" mode="out-in" appear>
        <component :is="Component" v-if="Component" />
      </Transition>
    </RouterView>
  </div>

  <div v-if="showCommandPalette" class="fixed inset-0 z-99 flex justify-center pt-[15vh]" @click="toggleCommandPalette">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[600px] flex flex-col transition-all overflow-hidden" @click.stop>
      <div class="p-4 border-b border-slate-100 flex items-center bg-slate-50">
        <AppIcon name="search" class="w-5 h-5 text-slate-400 mx-3" />
        <input
          ref="commandInput"
          v-model="commandQuery"
          type="text"
          placeholder="Wpisz komendę, klienta lub pracownika..."
          class="w-full text-2xl bg-transparent border-none focus:ring-0 p-1! shadow-none! text-slate-800 placeholder-slate-400 font-bold"
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
</style>
