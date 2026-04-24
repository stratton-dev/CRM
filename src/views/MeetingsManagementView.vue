<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useClientStore } from '@/stores/client'
import { useToastStore } from '@/stores/toast'
import { useStructureStore } from '@/stores/structure'
import { useMailboxStore } from '@/stores/mailbox'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import ClientCardModal from '@/components/ClientCardModal.vue'
import type { Client, User } from '@/types/models'

const props = defineProps<{
  embedded?: boolean
  viewScope?: string
}>()

const router = useRouter()
const clientStore = useClientStore()
const toast = useToastStore()
const structureStore = useStructureStore()
const mailboxStore = useMailboxStore()
const notifyStore = useNotificationStore()
const authStore = useAuthStore()
const sessionStore = useSessionStore()

const { prospects: clients } = storeToRefs(clientStore)
const { users: structureUsers } = storeToRefs(structureStore)
const { currentUser } = storeToRefs(sessionStore)

const expandedMeetingId = ref<string | null>(null)

// --- Notification Logic ---
const showMsgModal = ref(false)
const selectedUserForMsg = ref<User | null>(null)
const msgData = ref({
  type: 'TASK' as 'TASK' | 'NOTE' | 'INFO' | 'WARNING',
  text: '',
})

const canNotify = (user: User) => {
  if (!authStore.enabled || !currentUser.value) return false
  if (currentUser.value.role === 'ADMIN') return true
  
  const childIds = structureStore.getSubtreeUserIds(currentUser.value.id)
  return childIds.includes(user.id) && user.id !== currentUser.value.id
}

const openMsgModal = (user: User) => {
  selectedUserForMsg.value = user
  msgData.value.type = 'TASK'
  msgData.value.text = ''
  showMsgModal.value = true
}

const closeMsgModal = () => {
  showMsgModal.value = false
  selectedUserForMsg.value = null
}

const sendMsg = () => {
  const recipient = selectedUserForMsg.value
  if (!recipient || !currentUser.value) return
  if (!msgData.value.text.trim()) {
    toast.warning('Wpisz treść wiadomości.')
    return
  }

  notifyStore.add({
    userId: recipient.id,
    type: msgData.value.type,
    message: msgData.value.text.trim(),
  })
  toast.success(`Wiadomość została wysłana do ${recipient.name}.`)
  closeMsgModal()
}
// --------------------------

const promoteToClient = (client: any) => {
  if (!client.nip || !client.name) {
    toast.error('Uzupełnij NIP i nazwę firmy przed utworzeniem kalkulacji.')
    openClientModal(client)
    return
  }
  // Navigate to Calculator with this client pre-selected
  router.push({ name: 'calculator', query: { clientId: client.id } })
}

const toggleOwnerDetails = (meetingId: string) => {
  if (expandedMeetingId.value === meetingId) {
    expandedMeetingId.value = null
  } else {
    expandedMeetingId.value = meetingId
  }
}

const getMeetingOwner = (client: any) => {
  if (!client.ownerId) return null
  const userList = Array.isArray(structureUsers.value) ? structureUsers.value : []
  return userList.find((u) => u.id === client.ownerId) || null
}

const emailMeetingOwner = (client: any) => {
  const email = getMeetingOwner(client)?.email
  if (!email) return
  mailboxStore.initiateEmailTo(email)
}

const getOwnerRoleLabel = (role: string) => {
  const roleMap: Record<string, string> = {
    'SALES': 'DORADCA BIZNESOWY',
    'ADMIN': 'ADMINISTRATOR',
    'MANAGER': 'MANAGER',
    'DIRECTOR': 'DYREKTOR',
  }
  return roleMap[role] || role
}

const canImpersonate = (user: any) => {
  if (!currentUser.value) return false
  return structureStore.canImpersonate(currentUser.value, user)
}

const canRemove = (user: any) => {
  if (!currentUser.value) return false
  return structureStore.canRemove(currentUser.value, user)
}

const impersonateUser = async (user: any) => {
  // Allow ADMIN to bypass "impersonate self" check if needed for testing/preview,
  // but usually impersonating self is a reload. 
  if (currentUser.value?.id === user.id) {
    if (!confirm('Czy na pewno chcesz odświeżyć własną sesję (zalogować się ponownie)?')) return
    window.location.reload()
    return
  }

  if (!confirm(`Czy na pewno chcesz zalogować się jako ${user.name}?`)) return
  try {
    await sessionStore.impersonate(user.id)
    router.push('/app/dashboard')
    toast.success(`Zalogowano jako ${user.name}`)
  } catch (error) {
    toast.error('Nie udało się zalogować jako użytkownik.')
  }
}

const removeUser = async (user: any) => {
  if (!currentUser.value) return
  
  if (currentUser.value.id === user.id) {
     if(!confirm('UWAGA: Próbujesz usunąć własne konto administratorskie. Czy na pewno chcesz to zrobić?')) return
  } else {
     if (!confirm(`Czy na pewno chcesz usunąć ${user.name} ze struktury? Tej operacji nie można cofnąć.`)) return
  }
  
  try {
    await structureStore.removeUserFromStructure(user, currentUser.value)
    await structureStore.fetchStructure()
    // Optionally refresh meetings list if needed, though structure changes might reflect via store
    toast.success(`Usunięto ${user.name} ze struktury.`)
  } catch (error: any) {
    const message = error?.response?.data?.message
    if (message) toast.error(message)
    else toast.error('Nie udało się usunąć użytkownika.')
  }
}

const editRedirect = (user: any) => {
   router.push({ path: '/app/structure', query: { focus: user.id } })
}


const selectedClientForModal = ref<Client | null>(null)
const showClientModal = ref(false)

const openClientModal = (client: Client) => {
  selectedClientForModal.value = client
  showClientModal.value = true
}

const openAddModal = () => {
  selectedClientForModal.value = null
  showClientModal.value = true
}

const searchQuery = ref('')
const sortKey = ref('name')
const sortOrder = ref<'asc' | 'desc'>('asc')
const meetingCurrentPage = ref(1)
const meetingItemsPerPage = 10

const filteredClients = computed(() => {
  let list = Array.isArray(clients.value) ? [...clients.value] : []
  
  // Exclude clients that are already in sales process (OFFER_GENERATED, CALCULATION_SENT, SIGNED etc.)
  list = list.filter(c => 
    !['OFFER_GENERATED', 'CALCULATION_SENT', 'SIGNED', 'TERMINATED', 'RESIGNED'].includes(c.status)
  )

  if (props.embedded && props.viewScope && props.viewScope !== 'all') {
    const scope = props.viewScope
    const myId = currentUser.value?.id
    if (scope === 'mine') {
      list = list.filter(c => c.ownerId === myId)
    } else if (scope === 'structure' || scope === 'team') {
      if (myId && currentUser.value?.role !== 'ADMIN') {
        const subtreeIds = [myId, ...structureStore.getSubtreeUserIds(myId)]
        list = list.filter(c => !c.ownerId || subtreeIds.includes(c.ownerId))
      }
      // ADMIN with 'structure' sees all — no extra filter
    } else if (scope.startsWith('role:')) {
      const targetRole = scope.slice(5)
      list = list.filter(c => {
        const owner = structureUsers.value.find((u: any) => u.id === c.ownerId)
        return owner?.role === targetRole
      })
    }
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(c => 
      (c.name || '').toLowerCase().includes(q) || 
      (c.contactName || '').toLowerCase().includes(q) ||
      (c.nip || '').includes(q)
    )
  }
  
  list.sort((a, b) => {
    let valA = (a as any)[sortKey.value] || ''
    let valB = (b as any)[sortKey.value] || ''
    if (sortKey.value === 'lastMeeting') {
      const actA = (a.activityHistory || []).find(ah => ah.type === 'MEETING')
      const actB = (b.activityHistory || []).find(ah => ah.type === 'MEETING')
      valA = actA ? new Date(actA.date).getTime() : 0
      valB = actB ? new Date(actB.date).getTime() : 0
    }
    
    if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1
    if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })
  
  return list
})

const slicedMeetings = computed(() => {
  const start = (meetingCurrentPage.value - 1) * meetingItemsPerPage
  const end = start + meetingItemsPerPage
  return filteredClients.value.slice(start, end)
})

const totalMeetingPages = computed(() => Math.ceil(filteredClients.value.length / meetingItemsPerPage))

const nextMeetingPage = () => {
    if (meetingCurrentPage.value < totalMeetingPages.value) meetingCurrentPage.value++
}

const prevMeetingPage = () => {
    if (meetingCurrentPage.value > 1) meetingCurrentPage.value--
}

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

const handleDeleteClient = async (client: Client) => {
    if (!confirm(`Czy na pewno chcesz usunąć klienta ${client.contactName} oraz powiązane z nim dane?`)) {
        return
    }

    try {
        await api.delete(`/v1/clients/${client.id}`)
        await clientStore.refreshApiData()
        toast.success('Klient został usunięty')
    } catch (error: any) {
        toast.error('Błąd podczas usuwania: ' + (error.response?.data?.message || error.message))
    }
}

const getLastActivityDate = (client: Client) => {
  if (!client.activityHistory || !Array.isArray(client.activityHistory) || client.activityHistory.length === 0) return 'Brak'
  const activity = client.activityHistory[0] // history is sorted desc
  return activity ? new Date(activity.date).toLocaleString('pl-PL') : 'Brak'
}

const toggleActivityCompletion = async (client: Client, event: Event) => {
    const activity = client.activityHistory?.[0]
    if (!activity) return

    const checkbox = event.target as HTMLInputElement
    const newCompleted = checkbox.checked
    // Optimistic update
    activity.isCompleted = newCompleted

    try {
        // @ts-ignore
        await clientStore.updateActivity(client.id, {
            ...activity,
            isCompleted: newCompleted
        })

        if (newCompleted && client.ownerId) {
            const owner = Array.isArray(structureUsers.value) ? structureUsers.value.find((u) => u.id === client.ownerId) : null
            if (owner && owner.parentKeycloakId) {
                const supervisor = Array.isArray(structureUsers.value) ? structureUsers.value.find((u) => u.id === owner.parentKeycloakId) : null
                if (supervisor) {
                    notifyStore.add({
                        userId: supervisor.id,
                        type: 'INFO',
                        message: `Pracownik ${owner.name} odbył spotkanie z firmą ${client.name}. Możesz skontaktować się w sprawie wyników.`,
                    })
                    toast.success(`Powiadomiono przełożonego (${supervisor.name}).`)
                }
            }
        }
    } catch (e) {
        activity.isCompleted = !newCompleted
        toast.error('Błąd aktualizacji statusu')
    }
}

onMounted(() => {
    if (clients.value && clients.value.length === 0) {
        clientStore.fetchClients()
    }
})

const exportToCsv = () => {
  const rows = filteredClients.value
  if (rows.length === 0) {
    toast.warning('Brak danych do eksportu.')
    return
  }

  const header = ['Nazwa Firmy', 'NIP', 'Osoba Kontaktowa', 'Telefon', 'Email', 'Stanowisko', 'Branża', 'Źródło', 'Opiekun', 'Data Ost. Aktywności']
  const csvRows = rows.map((r) => [
    `"${(r.name || '').replace(/"/g, '""')}"`,
    r.nip || '',
    `"${(r.contactName || '').replace(/"/g, '""')}"`,
    r.contactPhone || '',
    r.contactEmail || '',
    `"${(r.contactPosition || '').replace(/"/g, '""')}"`,
    `"${(r.industry || '').replace(/"/g, '""')}"`,
    `"${(r.source || '').replace(/"/g, '""')}"`,
    `"${(r.ownerId || '').replace(/"/g, '""')}"`,
    getLastActivityDate(r),
  ].join(','))

  const csvContent = '\uFEFF' + [header.join(','), ...csvRows].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', 'spotkania_stratton.csv')
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  toast.success('Rozpoczęto pobieranie pliku CSV.')
}
</script>

<template>
  <div class="p-6 max-w-[1600px] mx-auto space-y-8" :class="{ 'p-0! max-w-none! space-y-0!': embedded }">
    <!-- Header -->
    <div v-if="!embedded" class="bg-linear-to-br from-slate-950 via-slate-900 to-slate-800 text-white rounded-card p-8 shadow-card-hover border border-slate-800 flex justify-between items-center relative overflow-hidden mb-6">
      
      <div class="relative z-10 flex items-center gap-6">
          <button 
            @click="router.push('/app/dashboard')"
             class="w-12 h-12 rounded-md bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-all shadow-sm group"
            title="Powrót do Dashboardu"
          >
            <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
          </button>
          
          <div>
            <h1 class="text-3xl md:text-4xl font-serif font-bold text-white tracking-tight">Zarządzanie Spotkaniami</h1>
            <p class="text-stratton-100 mt-1 font-medium opacity-90">Planuj kontakty i zarządzaj bazą klientów</p>
          </div>
        </div>

        <button 
          type="button"
          @click="openAddModal"
          class="bg-linear-to-r from-[#D4AF37] to-stratton-gold hover:brightness-110 text-white px-6 py-3 rounded-md font-bold transition-all flex items-center gap-3 shadow-md group hover:-translate-y-0.5 relative z-20"
        >
          <div class="w-5 h-5 rounded bg-white/20 flex items-center justify-center group-hover:bg-white/30 transition-colors">
            <AppIcon name="plus" class="w-3.5 h-3.5" />
          </div>
          Dodaj Spotkanie
        </button>
      </div>

    <!-- Filters & Table -->
    <div 
      class="flex flex-col min-h-0 bg-surface"
      :class="embedded ? 'h-auto overflow-visible rounded-t-card' : 'rounded-card shadow-card border border-slate-200 overflow-hidden'"
    >
      <div 
        class="bg-slate-50 border-b border-slate-200 p-2 flex items-center shadow-sm shrink-0"
        :class="embedded ? 'rounded-t-card' : ''"
      >
        <div class="flex items-center gap-3 ml-4">
          <AppIcon name="calendar" class="w-5 h-5 text-primary" />
          <h3 class="font-black text-slate-800 text-xl tracking-tight">Spotkania w obsłudze</h3>
        </div>

        <div class="flex-1 flex items-center justify-end px-4 gap-4">
          <div class="flex items-center space-x-2">
            <button type="button" class="flex items-center px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 rounded-lg border border-slate-200 bg-white font-medium transition-colors" @click="openAddModal">
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              <span>Nowy</span>
            </button>
            <button type="button" class="flex items-center px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 rounded-lg border border-slate-200 bg-white font-medium transition-colors" @click="exportToCsv">
              <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              <span>Eksportuj</span>
            </button>
          </div>

          <div class="w-96 relative">
            <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5 pointer-events-none" />
            <input 
              v-model="searchQuery"
              type="text" 
              placeholder="Szukaj klienta, firmy lub NIP..."
              class="w-full border-slate-200 rounded-lg text-sm pl-10 pr-4 py-2.5 focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold bg-white text-slate-800 shadow-sm text-right font-bold transition-all placeholder-slate-400"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 relative bg-surface" :class="embedded ? 'overflow-visible' : 'overflow-hidden'">
        <div :class="embedded ? 'h-auto overflow-auto' : 'h-full overflow-auto'">
          <table class="w-full divide-y divide-slate-100">
            <thead class="bg-slate-50 sticky top-0 z-10 shadow-sm">
              <tr>
                <th
                  @click="toggleSort('name')"
                  class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-primary transition-colors"
                >
                  Firma
                  <span v-if="sortKey === 'name'" class="ml-1 text-[11px]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Opiekun</th>
                <th
                  @click="toggleSort('lastMeeting')"
                  class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-primary transition-colors"
                >
                  Ostatnia Aktywność
                  <span v-if="sortKey === 'lastMeeting'" class="ml-1 text-[11px]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Źródło</th>
                <th class="px-2 py-3 text-center text-[10px] font-bold text-slate-500 uppercase tracking-wider leading-tight">
                  Ilość<br>Pracowników
                </th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider pr-6">Akcje</th>
                <th class="px-2 py-3 text-center text-[10px] font-bold text-slate-500 uppercase tracking-wider leading-tight">
                  Spotkanie<br>odbyło się
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 bg-white">
              <template v-for="client in slicedMeetings" :key="client.id">
              <tr
                class="hover:bg-slate-50 cursor-pointer transition-colors group"
                @click="openClientModal(client)"
              >
                <td class="px-4 py-3 whitespace-nowrap">
                  <div class="w-fit rounded-lg border border-dashed border-slate-200 px-3 py-1 bg-slate-50 hover:bg-white hover:border-slate-300 transition-colors">
                    <div class="text-sm font-bold text-slate-700 group-hover:text-primary transition-colors leading-tight truncate max-w-[220px]" :title="client.name">
                      {{ client.name || 'Nieznana firma' }}
                    </div>
                    <div class="text-xs text-slate-400 font-mono tracking-wide">NIP: {{ client.nip || 'brak' }}</div>
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600" @click.stop="toggleOwnerDetails(client.id)">
                  <div 
                    class="w-fit rounded-lg border border-dashed border-slate-200 px-3 py-1 bg-slate-50/50 hover:bg-white hover:border-primary/30 transition-colors cursor-pointer group/owner"
                    :class="{'bg-slate-100 border-slate-300': expandedMeetingId === client.id}"
                  >
                    <div v-if="getMeetingOwner(client)" class="text-[9px] font-black text-primary uppercase tracking-tighter leading-none mb-1">
                      {{ getOwnerRoleLabel(getMeetingOwner(client)?.role || '') }}
                    </div>
                    <div class="text-sm font-semibold text-slate-700 group-hover/owner:text-primary transition-colors">{{ client.ownerName || 'Nieprzypisany' }}</div>
                    <div v-if="getMeetingOwner(client)" class="text-[11px] text-slate-400 font-mono tracking-wide">
                      ID: {{ getMeetingOwner(client)?.hierarchicalId || '?' }}
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">
                  <div class="flex items-center text-sm text-slate-500 gap-2">
                    <span class="w-2 h-2 rounded-full" :class="getLastActivityDate(client) === 'Brak' ? 'bg-slate-300' : 'bg-emerald-500'"></span>
                    <span>{{ getLastActivityDate(client) }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-xs">
                  <div class="inline-flex flex-col items-center justify-center px-3 py-1 rounded-lg text-[11px] font-bold uppercase tracking-wide leading-tight"
                    :class="client.source ? 'bg-slate-100 text-slate-600 border border-slate-200' : 'bg-slate-50 text-slate-400 border border-dashed border-slate-200'"
                    :title="client.source || 'Brak'"
                  >
                    <span v-for="(word, i) in (client.source || 'Brak').split(' ')" :key="i">{{ word }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center text-sm font-bold text-slate-700">{{ client.companySize || '?' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click.stop="promoteToClient(client)"
                      class="p-2 rounded-lg border border-transparent text-slate-400 hover:text-emerald-600 hover:border-emerald-100 hover:bg-emerald-50 transition"
                      title="Utwórz klienta i kalkulację"
                    >
                      <AppIcon name="calculator" class="w-4 h-4" />
                    </button>
                    <button
                      @click.stop="openClientModal(client)"
                      class="p-2 rounded-lg border border-transparent text-slate-400 hover:text-blue-600 hover:border-blue-100 hover:bg-blue-50 transition"
                      title="Edytuj"
                    >
                      <AppIcon name="pencil-square" class="w-4 h-4" />
                    </button>
                    <button
                      @click.stop="handleDeleteClient(client)"
                      class="p-2 rounded-lg border border-transparent text-slate-400 hover:text-red-600 hover:border-red-100 hover:bg-red-50 transition"
                      title="Usuń"
                    >
                      <AppIcon name="trash" class="w-4 h-4" />
                    </button>
                  </div>
                </td>
                <td class="px-4 py-3 text-center" @click.stop>
                   <input 
                      type="checkbox" 
                      :checked="client.activityHistory && client.activityHistory.length > 0 && !!client.activityHistory[0].isCompleted"
                      @change="(e) => toggleActivityCompletion(client, e)"
                      class="w-5 h-5 rounded border-2 border-slate-300 text-emerald-500 focus:ring-emerald-500 cursor-pointer transition-all hover:scale-110"
                   />
                </td>
              </tr>
              <tr v-if="expandedMeetingId === client.id" class="bg-slate-50 border-y border-slate-200 shadow-inner animate-fade-in">
                <td colspan="8" class="p-0 cursor-default" @click.stop>
                  <div class="p-4 flex justify-between items-center bg-slate-50/50">
                    <div v-if="getMeetingOwner(client)" class="flex justify-between items-center w-full">
                        <div class="flex items-center gap-8 text-xs text-slate-600">
                            <div>
                                <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Telefon</span>
                                <span class="font-medium text-slate-800">{{ getMeetingOwner(client)?.phone || 'Brak' }}</span>
                            </div>
                            <div>
                                <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Email</span>
                                <button type="button" class="text-primary hover:text-primary-dark hover:underline flex items-center gap-1 font-medium" @click="emailMeetingOwner(client)">
                                    <AppIcon name="envelope" class="w-3 h-3" />
                                    <span>{{ getMeetingOwner(client)?.email }}</span>
                                </button>
                            </div>
                            <div>
                                <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Rola</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-bold text-[10px] uppercase bg-white text-slate-600 border border-slate-200 shadow-sm">
                                    {{ getOwnerRoleLabel(getMeetingOwner(client)?.role || '') || 'Brak' }}
                                </span>
                            </div>
                            <div>
                                 <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Kod Struktury</span>
                                 <span class="font-mono bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-600 shadow-sm">{{ getMeetingOwner(client)?.hierarchicalId || 'Brak' }}</span>
                            </div>
                        </div>
                    
                        <div class="flex items-center gap-2">
                           <button type="button" class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm" title="Wyślij wiadomość" @click="emailMeetingOwner(client)">
                               <AppIcon name="chat-bubble-left-ellipsis" class="w-5 h-5" />
                           </button>

                           <!-- Added: Bell Notification Button -->
                           <button 
                             v-if="getMeetingOwner(client) && canNotify(getMeetingOwner(client)!)" 
                             type="button" 
                             class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm" 
                             title="Wyślij powiadomienie wewnętrzne"
                             @click="openMsgModal(getMeetingOwner(client)!)"
                           >
                             <AppIcon name="bell" class="w-5 h-5" />
                           </button>
                           
                           <!-- Added: Impersonate Button -->
                           <button 
                             v-if="getMeetingOwner(client) && canImpersonate(getMeetingOwner(client)!)" 
                             type="button" 
                             class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm" 
                             :title="`Podgląd konta: ${getMeetingOwner(client)?.name}`"
                             @click="impersonateUser(getMeetingOwner(client)!)"
                           >
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                             </svg>
                           </button>

                           <!-- Added: Edit Button (Redirect) -->
                           <button
                             v-if="getMeetingOwner(client) && currentUser?.role === 'ADMIN'"
                             type="button"
                             class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm"
                             :title="`Przejdź do struktury aby edytować: ${getMeetingOwner(client)?.name}`"
                             @click="editRedirect(getMeetingOwner(client)!)"
                           >
                              <AppIcon name="pencil-square" class="w-5 h-5" />
                           </button>
                           
                           <!-- Added: Remove Button -->
                           <button 
                             v-if="getMeetingOwner(client) && canRemove(getMeetingOwner(client)!)" 
                             type="button" 
                             class="p-2 bg-white text-red-600 border border-slate-200 rounded-lg hover:bg-red-50 hover:border-red-200 transition shadow-sm" 
                             :title="`Usuń ze struktury: ${getMeetingOwner(client)?.name}`"
                             @click="removeUser(getMeetingOwner(client)!)"
                           >
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                           </button>
                        </div>
                    </div>
                    <div v-else class="text-center text-xs text-slate-500 py-2 w-full">
                        Brak danych szczegółowych opiekuna w strukturze.
                    </div>
                  </div>
                </td>
              </tr>
              </template>
              <tr v-if="filteredClients.length === 0">
                <td colspan="8" class="p-8 text-center text-slate-500 text-sm">Nie znaleziono rekordów spełniających kryteria.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination Meetings -->
      <div 
        class="bg-surface border-t border-slate-200 shrink-0 flex justify-between items-center"
        :class="embedded ? 'px-4 py-2' : 'px-6 py-4'"
      >
        <div class="text-[10px] text-slate-400 font-medium uppercase tracking-widest">
          Strona {{ meetingCurrentPage }} z {{ totalMeetingPages || 1 }} ({{ filteredClients.length }} rekordów)
        </div>
        <div class="flex gap-2">
            <button 
                type="button" 
                @click="prevMeetingPage"
                :disabled="meetingCurrentPage === 1"
                class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
                Poprzednia
            </button>
            <button 
                type="button" 
                @click="nextMeetingPage"
                :disabled="meetingCurrentPage >= totalMeetingPages"
                class="px-4 py-2 bg-primary text-primary-foreground rounded-lg text-xs font-bold hover:bg-primary-dark transition shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                Następna
                <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
            </button>
        </div>
      </div>
    </div>

    <ClientCardModal
      :open="showClientModal"
      :client="selectedClientForModal ?? undefined"
      @close="showClientModal = false"
      @saved="clientStore.refreshApiData()"
    />

    <!-- Notification Modal -->
    <div v-if="showMsgModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeMsgModal"></div>
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md z-10 relative">
        <h3 class="text-lg font-bold mb-4 text-gray-900">Wyślij powiadomienie</h3>
        <div class="mb-4">
          <span class="text-sm text-gray-500">Do:</span> <span class="font-bold text-gray-900">{{ selectedUserForMsg?.name }}</span>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Typ</label>
            <select v-model="msgData.type" class="w-full border p-2 rounded bg-white text-gray-900">
              <option value="TASK">Zadanie / Działanie</option>
              <option value="NOTE">Notatka służbowa</option>
              <option value="INFO">Informacja</option>
              <option value="WARNING">Ostrzeżenie / Przypomnienie</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Treść wiadomości</label>
            <textarea v-model="msgData.text" rows="4" class="w-full border p-2 rounded bg-white text-gray-900" placeholder="Wpisz treść..."></textarea>
          </div>
          <div class="flex justify-end space-x-2">
            <button type="button" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded" @click="closeMsgModal">Anuluj</button>
            <button type="button" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700" @click="sendMsg">Wyślij</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
@reference "../assets/tailwind.css";

.form-input {
  @apply w-full border border-slate-300 rounded-input py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all text-sm placeholder:text-slate-400 bg-white;
}

/* Custom scrollbar for modal */
.overflow-y-auto::-webkit-scrollbar {
  width: 8px;
}
.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
