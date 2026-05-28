<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFinanceStore } from '@/stores/finance'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useSessionStore } from '@/stores/session'
import { useStructureStore } from '@/stores/structure'
import { useToastStore } from '@/stores/toast'
import { useMailboxStore } from '@/stores/mailbox'
import { useNotificationStore } from '@/stores/notification'
import { api } from '@/api/client'
import type { Client } from '@/types/models'
import AppIcon from '@/components/AppIcon.vue'

type ClientContact = {
  id: string
  name: string
  position?: string | null
  phone?: string | null
  email?: string | null
  is_decision_maker?: boolean | null
  created_at?: string | null
  updated_at?: string | null
}

const emit = defineEmits(['select'])

const props = defineProps<{
  embedded?: boolean
  dateFrom?: string
  dateTo?: string
}>()

const auth = useAuthStore()
const finance = useFinanceStore()
const data = useDataStore()
const clientStore = useClientStore()
const session = useSessionStore()
const structure = useStructureStore()
const toast = useToastStore()
const mailboxStore = useMailboxStore()
const notifyStore = useNotificationStore()
const router = useRouter()
const route = useRoute()

const { clients } = storeToRefs(clientStore)
const { invoices } = storeToRefs(finance)
const { users: dataUsers } = storeToRefs(structure)
const { users: structureUsers } = storeToRefs(structure)
const { currentUser, isReadOnly } = storeToRefs(session)

const VIEW_MODE_STORAGE_KEY = 'clients-view-mode'
const isAdmin = computed(() => currentUser.value?.role === 'ADMIN')
const initialViewMode = ((): 'list' | 'kanban' => {
  try {
    const saved = localStorage.getItem(VIEW_MODE_STORAGE_KEY)
    if (saved === 'list' || saved === 'kanban') return saved
  } catch {}
  return 'kanban'
})()
const viewMode = ref<'list' | 'kanban'>(initialViewMode)
const selectedClient = ref<Client | null>(null)
const activePanelTab = ref<'details' | 'contacts' | 'activity' | 'finance' | 'offers'>('details')

const filterText = ref('')
const sortField = ref<keyof Client | 'opiekunDisplay' | ''>('lastActionDate')
const sortDir = ref<'asc' | 'desc'>('desc')

// Leady od moich leadowców — widoczne dla SALES/MANAGER/DIRECTOR/ADMIN
const fromMyLeadowcy = ref(false)
const leadowcyClients = ref<any[]>([])
const loadingLeadowcy = ref(false)

const isOpiekunOrAbove = computed(() =>
  ['SALES', 'MANAGER', 'DIRECTOR', 'ADMIN'].includes(currentUser.value?.role ?? '')
)

const fetchLeadowcyClients = async () => {
  if (!auth.enabled) return
  loadingLeadowcy.value = true
  try {
    const { data } = await api.get('/v1/clients', { params: { from_my_leadowcy: 1, per_page: 100 } })
    leadowcyClients.value = Array.isArray(data) ? data : data.data ?? []
  } catch {
    leadowcyClients.value = []
  } finally {
    loadingLeadowcy.value = false
  }
}

const toggleLeadowcy = () => {
  fromMyLeadowcy.value = !fromMyLeadowcy.value
  if (fromMyLeadowcy.value) fetchLeadowcyClients()
}

const draggedClientId = ref<string | null>(null)
const expandedClientId = ref<string | null>(null)
const selectedActivityType = ref('CALL')
const activityDescription = ref('')
const consentCatalog = ref<Array<{ id: string; code: string; title: string; description: string; required: boolean; updated_at?: string | null; file_url?: string | null; file_name?: string | null }>>([])
const clientConsentEntries = ref<Array<{ id: string; consent_id: string; accepted_at?: string | null; denied_at?: string | null; consent?: { id: string; updated_at?: string | null } }>>([])
const consentsLoading = ref(false)
const clientContacts = ref<ClientContact[]>([])
const contactsLoading = ref(false)
const contactSearch = ref('')
const isContactEditOpen = ref(false)
const contactEdit = ref({
  id: '',
  name: '',
  position: '',
  phone: '',
  email: '',
  is_decision_maker: false,
})
const newContact = ref({
  name: '',
  position: '',
  phone: '',
  email: '',
  is_decision_maker: false,
})
const isRescheduleOpen = ref(false)
const rescheduleDateTime = ref('')
const clientCalculations = ref<Array<{ id: string; meetingId: string; status: string; employeeCount: number; savingsAmount: number; validUntil: string; createdAt?: string | null; valueJson?: any | null }>>([])
const calculationsLoading = ref(false)
const calculationStatuses = ref<Array<{ key: string; label: string }>>([])
const calculationsError = ref<string | null>(null)

const ALL_KANBAN_STAGES: Array<{ status: Client['status']; title: string }> = [
  { status: 'NEW', title: 'Nowy' },
  { status: 'IN_TALKS', title: 'W rozmowach' },
  { status: 'SIGNED', title: 'Podpisany (Stratton Prime)' },
  { status: 'TERMINATED', title: 'Umowa Rozwiązana' },
  { status: 'RESIGNED', title: 'Rezygnacja' },
]

// Non-admin users don't see the "Umowa Rozwiązana" column — TERMINATED is admin-only.
const kanbanStages = computed(() =>
  isAdmin.value ? ALL_KANBAN_STAGES : ALL_KANBAN_STAGES.filter((stage) => stage.status !== 'TERMINATED')
)

const filteredContacts = computed(() => {
  const term = contactSearch.value.trim().toLowerCase()
  if (!term) return clientContacts.value
  return clientContacts.value.filter((contact) => {
    const hay = [
      contact.name,
      contact.position,
      contact.phone,
      contact.email,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return hay.includes(term)
  })
})

const baseClients = computed(() => {
  const u = currentUser.value
  if (!u) return []
  const base = Array.isArray(clients.value) ? clients.value : []
  if (auth.enabled) {
    if (u.role === 'ADMIN') return base
    if (u.role === 'MANAGER' || u.role === 'DIRECTOR') {
      const teamIds = [u.id, ...structure.getSubtreeUserIds(u.id)]
      return base.filter((client) => !client.ownerId || teamIds.includes(client.ownerId))
    }
    return base.filter((client) => !client.ownerId || client.ownerId === u.id)
  }
  if (u.role === 'ADMIN') return base
  if (u.role === 'MANAGER' || u.role === 'DIRECTOR') {
    const teamIds = [u.id, ...data.getSubtreeUserIds(u.id)]
    return base.filter((client) => teamIds.includes(client.ownerId))
  }
  return base.filter((client) => client.ownerId === u.id)
})

const displayedClients = computed(() => {
  const clients = baseClients.value
  const userList = Array.isArray(auth.enabled ? structureUsers.value : dataUsers.value)
    ? (auth.enabled ? structureUsers.value : dataUsers.value)
    : []
  const term = filterText.value.toLowerCase()
  const sField = sortField.value
  const sDir = sortDir.value

  const roleMap: Record<string, string> = {
    'SALES': 'DORADCA BIZNESOWY',
    'ADMIN': 'ADMINISTRATOR',
    'MANAGER': 'MANAGER',
    'DIRECTOR': 'DYREKTOR',
  }

  let list = clients.map((client) => {
    const owner = userList.find((user) => user.id === client.ownerId)
    const opiekunRole = owner ? (roleMap[owner.role] || owner.role) : ''
    const opiekunName = owner ? owner.name : 'Nieprzypisany'
    const opiekunHierarchy = owner?.hierarchicalId || 'Brak'
    const opiekunDisplay = owner ? `${opiekunRole} ${opiekunName} (${opiekunHierarchy})` : 'Nieprzypisany'
    return { ...client, opiekunDisplay, opiekunName, opiekunHierarchy, opiekunRole }
  })

  if (term) {
    list = list.filter((client) =>
      client.name.toLowerCase().includes(term) ||
      client.nip.includes(term) ||
      client.city.toLowerCase().includes(term) ||
      client.contactName.toLowerCase().includes(term) ||
      (client.opiekunDisplay || '').toLowerCase().includes(term)
    )
  }

  if (props.embedded && (props.dateFrom || props.dateTo)) {
    list = list.filter((client) => {
      const activeDate = client.lastActionDate ? new Date(client.lastActionDate) : null
      if (!activeDate) return false
      
      // Reset filtering time to compare only dates
      activeDate.setHours(0, 0, 0, 0)
      
      if (props.dateFrom) {
        const from = new Date(props.dateFrom)
        from.setHours(0, 0, 0, 0)
        if (activeDate < from) return false
      }

      if (props.dateTo) {
        const to = new Date(props.dateTo)
        to.setHours(0, 0, 0, 0)
        if (activeDate > to) return false
      }

      return true
    })
  }

  if (sField) {
    list.sort((a: any, b: any) => {
      const valA = (a[sField] || '').toString().toLowerCase()
      const valB = (b[sField] || '').toString().toLowerCase()
      if (valA < valB) return sDir === 'asc' ? -1 : 1
      if (valA > valB) return sDir === 'asc' ? 1 : -1
      return 0
    })
  }

  return list
})

const clientsPerPage = 10
const clientsPage = ref(1)
const totalClientPages = computed(() => Math.max(1, Math.ceil(displayedClients.value.length / clientsPerPage)))
const paginatedClients = computed(() => {
  const start = (clientsPage.value - 1) * clientsPerPage
  return displayedClients.value.slice(start, start + clientsPerPage)
})

watch(displayedClients, () => {
  clientsPage.value = 1
})

watch(totalClientPages, (newTotal) => {
  if (clientsPage.value > newTotal) {
    clientsPage.value = newTotal || 1
  }
})

const nextClientsPage = () => {
  if (clientsPage.value < totalClientPages.value) clientsPage.value += 1
}

const prevClientsPage = () => {
  if (clientsPage.value > 1) clientsPage.value -= 1
}

const kanbanData = computed(() =>
  kanbanStages.value.map((stage) => ({
    ...stage,
    clients: displayedClients.value.filter((client) => client.status === stage.status),
  }))
)

const clientInvoices = computed(() => {
  const client = selectedClient.value
  if (!client) return []
  const invoiceList = Array.isArray(invoices.value) ? invoices.value : []
  return invoiceList
    .filter((inv) => inv.clientId === client.id)
    .sort((a, b) => new Date(b.issueDate).getTime() - new Date(a.issueDate).getTime())
})

const clientFinancials = computed(() => {
  const invoices = clientInvoices.value
  const totalBilled = invoices.reduce((sum, inv) => sum + inv.amountNet, 0)
  const totalPaid = invoices.filter((inv) => inv.status === 'PAID').reduce((sum, inv) => sum + inv.amountNet, 0)
  const outstandingBalance = totalBilled - totalPaid
  return { totalBilled, totalPaid, outstandingBalance }
})

const sort = (field: keyof Client | 'opiekunDisplay') => {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}

const statusLabel = (status: Client['status']) => {
  const labels: Record<Client['status'], string> = {
    NEW: 'Nowy',
    IN_TALKS: 'W rozmowach',
    SIGNED: 'Podpisany (Stratton Prime)',
    TERMINATED: 'Umowa Rozwiązana',
    RESIGNED: 'Rezygnacja',
  }
  return labels[status] || status
}

onMounted(() => {
  if (auth.enabled) {
    clientStore.refreshApiData()
    fetchConsentCatalog()
    fetchCalculationStatuses()
  }
})

const setViewMode = (mode: 'list' | 'kanban') => {
  if (!isAdmin.value && mode === 'list') return
  viewMode.value = mode
  if (isAdmin.value) {
    try { localStorage.setItem(VIEW_MODE_STORAGE_KEY, mode) } catch {}
  }
}

watch(isAdmin, (admin) => {
  if (!admin && viewMode.value === 'list') viewMode.value = 'kanban'
}, { immediate: true })

const selectClient = (client: Client) => {
  selectedClient.value = client
  activePanelTab.value = 'details'
  if (auth.enabled) {
    void fetchClientConsents(client.id)
    void fetchClientContacts(client.id)
    void fetchClientCalculations(client.id)
  }
}

const toggleOwnerDetails = (clientId: string) => {
  if (expandedClientId.value === clientId) {
    expandedClientId.value = null
  } else {
    expandedClientId.value = clientId
  }
}

const getClientOwner = (client: any) => {
  if (!client.ownerId) return null
  const userList = Array.isArray(auth.enabled ? structureUsers.value : dataUsers.value)
    ? (auth.enabled ? structureUsers.value : dataUsers.value)
    : []
  return userList.find((u) => u.id === client.ownerId) || null
}

const showMsgModal = ref(false)
const selectedUserForMsg = ref<any | null>(null)
const msgData = ref({
  type: 'TASK' as 'TASK' | 'NOTE' | 'INFO' | 'WARNING',
  text: '',
})

const canNotify = (user: any) => {
  if (!auth.enabled || !currentUser.value) return false
  if (currentUser.value.role === 'ADMIN') return true
  
  const childIds = structure.getSubtreeUserIds(currentUser.value.id)
  return childIds.includes(user.id) && user.id !== currentUser.value.id
}

const openMsgModal = (user: any) => {
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

const canImpersonate = (user: any) => {
  if (!auth.enabled || !currentUser.value) return false
  return structure.canImpersonate(currentUser.value, user)
}

const canRemove = (user: any) => {
  if (!auth.enabled || !currentUser.value) return false
  return structure.canRemove(currentUser.value, user)
}

const impersonateUser = async (user: any) => {
  if (currentUser.value?.id === user.id) {
    if (!confirm('Czy na pewno chcesz odświeżyć własną sesję?')) return
    window.location.reload()
    return
  }
  if (!confirm(`Czy na pewno chcesz zalogować się jako ${user.name}?`)) return
  try {
    await session.impersonate(user.id)
    router.push('/app/dashboard')
    toast.success(`Zalogowano jako ${user.name}`)
  } catch (error) {
    toast.error('Nie udało się zalogować jako użytkownik.')
  }
}

const removeUser = async (user: any) => {
  if (!auth.enabled || !currentUser.value) return
  
  if (currentUser.value.id === user.id) {
     if(!confirm('UWAGA: Usuwasz własne konto. Kontynuować?')) return
  } else {
     if (!confirm(`Czy na pewno chcesz usunąć ${user.name} ze struktury? Tej operacji nie można cofnąć.`)) return
  }

  try {
    await structure.removeUserFromStructure(user, currentUser.value)
    await structure.fetchStructure()
    await clientStore.refreshApiData()
    toast.success(`Usunięto ${user.name} ze struktury.`)
  } catch (error: any) {
    const message = error?.response?.data?.message
    if (message) toast.error(message)
    else toast.error('Nie udało się usunąć użytkownika.')
  }
}

const editRedirect = (user: any) => {
   router.push({ path: '/app/structure', query: { focus: user.id } })
   toast.info('Przeniesiono do widoku struktury. Znajdź użytkownika na liście, aby edytować.')
}

const emailClientOwner = (client: any) => {
  const email = getClientOwner(client)?.email
  if (!email) return
  mailboxStore.initiateEmailTo(email)
}

const closePanel = () => {
  selectedClient.value = null
}

const getClientSlaStatus = (client: Client) => {
  if (!['NEW', 'IN_TALKS'].includes(client.status)) return 'OK'
  const lastAction = new Date(client.lastActionDate).getTime()
  const now = new Date().getTime()
  const diffDays = (now - lastAction) / (1000 * 60 * 60 * 24)
  return diffDays > 3 ? 'CRITICAL' : 'OK'
}

const getRemainingReservationDays = (client: Client) => {
  if (!client.reservationEndDate) return null
  const endDate = new Date(client.reservationEndDate)
  const now = new Date()
  if (endDate < now) return 0
  const diffTime = endDate.getTime() - now.getTime()
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

const formatReservationDate = (client: Client) => {
  if (!client.reservationEndDate) return ''
  return new Date(client.reservationEndDate).toLocaleDateString()
}

const isClientEditOpen = ref(false)
const isSubmitting = ref(false)
const clientEditForm = ref({
  id: '',
  contactName: '',
  contactPosition: '',
  contactPhone: '',
  contactEmail: '',
  isDecisionMaker: false,
  companyName: '',
  nip: '',
  address: '',
  industry: '',
  companySize: '',
})

const industries = [
  "Uprawy rolne, chów i hodowla zwierząt, łowiectwo, włączając działalność usługową",
  "Leśnictwo i pozyskiwanie drewna",
  "Rybactwo",
  "Wydobywanie węgla kamiennego i węgla brunatnego (lignitu)",
  "Górnictwo ropy naftowej i gazu ziemnego",
  "Górnictwo rud metali",
  "Pozostałe górnictwo i wydobywanie",
  "Usługi wspomagające górnictwo i wydobywanie",
  "Produkcja art. spożywczych",
  "Produkcja napojów",
  "Produkcja wyrobów tytoniowych",
  "Produkcja wyrobów tekstylnych",
  "Produkcja odzieży",
  "Produkcja skór i wyrobów ze skór wyprawionych",
  "Produkcja wyrobów z drewna oraz korka, z wyłączeniem mebli; Produkcja wyrobów ze słomy i materiałów używanych do wyplatania",
  "Produkcja papieru i wyrobów z papieru",
  "Poligrafia i reprodukcja zapisanych nośników informacji",
  "Wytwarzanie i przetwarzanie koksu i produktów rafinacji ropy naftowej",
  "Produkcja chemikaliów i wyrobów chemicznych",
  "Produkcja podstawowych substancji farmaceutycznych oraz leków i pozostałych wyrobów farmaceutycznych",
  "Produkcja wyrobów z gumy i tworzyw sztucznych",
  "Produkcja wyrobów z pozostałych mineralnych surowców niemetalicznych",
  "Produkcja metali",
  "Produkcja metalowych wyrobów gotowych, z wyłączeniem maszyn i urządzeń",
  "Produkcja komputerów, wyrobów elektronicznych i optycznych",
  "Produkcja urządzeń elektrycznych",
  "Produkcja maszyn i urządzeń, gdzie indziej niesklasyfikowana",
  "Produkcja pojazdów samochodowych, przyczep i naczep, z wyłączeniem motocykli",
  "Produkcja pozostałego sprzętu transportowego",
  "Produkcja mebli",
  "Pozostała produkcja wyrobów",
  "Naprawa, konserwacja i instalowanie maszyn i urządzeń",
  "Wytwarzanie i zaopatrywanie w energię elektryczną, gaz, parę wodną, gorącą wodę i powietrze do układów klimatyzacyjnych",
  "Pobór, uzdatnianie i dostarczanie wody",
  "Odprowadzanie i oczyszczanie ścieków",
  "Zbieranie, przetwarzanie i unieszkodliwianie odpadów oraz odzysk surowców",
  "Rekultywacją i pozostałe usługi związane z gospodarką odpadami",
  "Roboty budowlane związane ze wznoszeniem budynków",
  "Roboty związane z budową obiektów inżynierii lądowej i wodnej",
  "Roboty budowlane specjalistyczne",
  "Handel hurtowy i detaliczny pojazdami samochodowymi; Naprawa pojazdów samochodowych",
  "Handel hurtowy (bez pojazdów samochodowych)",
  "Handel detaliczny (bez pojazdów samochodowych)",
  "Transport lądowy oraz rurociągowy",
  "Transport wodny",
  "Transport lotniczy",
  "Magazynowanie i usługi wspomagające transport",
  "Działalność pocztowa i kurierska",
  "Zakwaterowanie",
  "Wyżywienie",
  "Działalność wydawnicza",
  "Działalność filmowa, telewizyjna, dźwiękowa i muzyczna",
  "Nadawanie programów telewizyjnych i radiowych",
  "Telekomunikacja",
  "Oprogramowanie i doradztwo w zakresie informatyki",
  "Zarządzanie stronami WWW, przetwarzanie danych i hosting",
  "Usługi finansowe z wyłączeniem ubezpieczeń i funduszów emerytalnych",
  "Ubezpieczenia, reasekuracja i fundusze emerytalne, z wyłączeniem obowiązkowego ubezpieczenia społecznego",
  "Usługi objęte pośrednictwem finansowym",
  "Obsługa rynku nieruchomości",
  "Usługi prawnicze, rachunkowo-księgowe i doradztwo podatkowe",
  "Działalność firm centralnych i doradztwo związane z zarządzaniem",
  "Architektura, inżynieria, badania i analizy techniczne",
  "Badania naukowe i prace rozwojowe",
  "Reklama, badanie rynku i opinii publicznej",
  "Projektowanie, fotografia, tłumaczenia, działalność profesjonalna",
  "Weterynaria",
  "Wynajem i dzierżawa",
  "Zatrudnienie",
  "Turystyka",
  "Usługi detektywistyczne i ochroniarskie",
  "Sprzątanie budynków i gospodarowanie terenami zieleni",
  "Administracja biurowa i wspomaganie prowadzenia działalności gospodarczej",
  "Administracja publiczna, obrona narodowa i obowiązkowe zabezpieczenia społeczne",
  "Edukacja",
  "Opieka zdrowotna",
  "Pomoc społeczna (z zakwaterowaniem)",
  "Pomoc społeczna (bez zakwaterowania)",
  "Kultura i rozrywka",
  "Biblioteki, archiwa, muzea, zoo oraz inne obiekty kulturalne",
  "Gry losowe i zakłady wzajemne",
  "Sport, rozrywka i rekreacja",
  "Działalność organizacji członkowskich",
  "Naprawa komputerów i artykułów osobistych oraz domowych",
  "Pozostała indywidualna działalność usługowa"
]

const openClientEditModal = (client: Client) => {
  clientEditForm.value = {
    id: client.id,
    contactName: client.contactName || '',
    contactPosition: client.contactPosition || '',
    contactPhone: client.contactPhone || '',
    contactEmail: client.contactEmail || '',
    isDecisionMaker: client.isDecisionMaker || false,
    companyName: client.name || '',
    nip: client.nip || '',
    address: `${client.street || ''} ${client.buildingNr || ''}, ${client.zip || ''} ${client.city || ''}`.trim(),
    industry: client.industry || '',
    companySize: client.companySize || '',
  }
  isClientEditOpen.value = true
}

const handleUpdateClient = async () => {
    if (!clientEditForm.value.id) return
    isSubmitting.value = true
    try {
        await api.patch(`/v1/clients/${clientEditForm.value.id}`, {
            name: clientEditForm.value.companyName,
            nip: clientEditForm.value.nip,
            contact_name: clientEditForm.value.contactName,
            contact_phone: clientEditForm.value.contactPhone,
            contact_email: clientEditForm.value.contactEmail,
            contact_position: clientEditForm.value.contactPosition,
            is_decision_maker: clientEditForm.value.isDecisionMaker,
            address: clientEditForm.value.address,
            industry: clientEditForm.value.industry,
            company_size: clientEditForm.value.companySize,
        })
        await clientStore.refreshApiData()
        toast.success('Dane klienta zaktualizowane')
        isClientEditOpen.value = false
    } catch (error: any) {
        toast.error('Błąd aktualizacji: ' + (error.response?.data?.message || error.message))
    } finally {
        isSubmitting.value = false
    }
}

const handleDeleteClient = async (client: Client) => {
    if (!confirm(`Czy na pewno chcesz usunąć klienta ${client.name} oraz powiązane z nim dane?`)) {
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

const formatDateTime = (value?: string | null) => (value ? new Date(value).toLocaleString() : '—')

const fetchCalculationStatuses = async () => {
  if (!auth.enabled) return
  const fallback = [
    { key: 'PREPARING', label: 'W trakcie przygotowania' },
    { key: 'READY', label: 'Gotowa' },
    { key: 'SENT', label: 'Wysłana' },
  ]
  try {
    const { data } = await api.get('/v1/calculator-configs', {
      params: {
        scope: 'global',
        key: 'crm_calculation_statuses',
        per_page: 1,
      },
    })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    const latest = list[0]
    const statuses = Array.isArray(latest?.value_json) ? latest.value_json : []
    const normalized = statuses
      .map((item: any) => ({
        key: String(item.key || '').toUpperCase(),
        label: String(item.label || item.key || ''),
      }))
      .filter((item: any) => item.key)
    const merged = [...normalized]
    fallback.forEach((item) => {
      if (!merged.some((entry) => entry.key === item.key)) merged.push(item)
    })
    calculationStatuses.value = merged
  } catch (error: any) {
    calculationStatuses.value = fallback
  }
}

const fetchClientCalculations = async (clientId: string) => {
  if (!auth.enabled) return
  calculationsLoading.value = true
  calculationsError.value = null
  try {
    const pageSize = 200
    let page = 1
    let all: any[] = []
    for (;;) {
      const { data } = await api.get('/v1/calculations', {
        params: { per_page: pageSize, page, client_id: clientId },
      })
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      all = all.concat(list)
      if (list.length < pageSize) break
      page += 1
    }
    clientCalculations.value = all.map((item: any) => ({
      id: String(item.id),
      meetingId: String(item.meeting_id || item.meeting?.id || ''),
      status: String(item.status || 'PREPARING').toUpperCase(),
      employeeCount: Number(item.employee_count || 0),
      savingsAmount: Number(item.savings_amount || 0),
      validUntil: item.valid_until,
      createdAt: item.created_at || null,
      valueJson: item.value_json || null,
    }))
  } catch (error: any) {
    calculationsError.value = error?.response?.data?.message || error?.message || 'Nie udało się pobrać kalkulacji.'
  } finally {
    calculationsLoading.value = false
  }
}

const downloadCalculationExcel = (calc: { id: string; valueJson?: any | null }) => {
  const payload = calc.valueJson
  const base64 =
    typeof payload === 'string'
      ? payload
      : payload?.excelBase64 || payload?.excel_base64 || payload?.excel || null

  if (!base64) {
    toast.warning('Brak danych do pobrania pliku Excel.')
    return
  }

  const fileName =
    (payload?.excelFileName || payload?.excel_file_name || `Kalkulacja_${calc.id}.xlsx`) as string

  const href = String(base64).startsWith('data:')
    ? String(base64)
    : `data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,${base64}`

  const link = document.createElement('a')
  link.href = href
  link.download = fileName.endsWith('.xlsx') ? fileName : `${fileName}.xlsx`
  document.body.appendChild(link)
  link.click()
  link.remove()
}

const updateCalculationStatus = async (calcId: string, status: string) => {
  if (!auth.enabled) return
  try {
    await api.patch(`/v1/calculations/${calcId}`, { status })
    clientCalculations.value = clientCalculations.value.map((calc) => (calc.id === calcId ? { ...calc, status } : calc))
    toast.success('Zaktualizowano status kalkulacji.')
  } catch (error: any) {
    toast.error(error?.response?.data?.message || error?.message || 'Nie udało się zaktualizować statusu.')
  }
}

const getDecisionMakerContact = () => {
  if (!clientContacts.value.length) return null
  const decision = clientContacts.value.find((contact) => contact.is_decision_maker && contact.email)
  if (decision) return decision
  const fallback = clientContacts.value.find((contact) => contact.email)
  return fallback || null
}

const openOfferEmail = (calc: { meetingId: string }) => {
  if (!selectedClient.value) return
  const decision = getDecisionMakerContact()
  const fallbackEmail = selectedClient.value.contactEmail || ''
  const targetEmail = decision?.email || fallbackEmail

  if (!targetEmail) {
    toast.warning('Brak adresu e-mail osoby decyzyjnej.')
  }

  router.push({
    path: '/app/sales/email-compose',
    query: {
      clientId: selectedClient.value.id,
      meetingId: calc.meetingId || selectedClient.value.meetingId || '',
      template: 'offer-calculator',
      decisionEmail: targetEmail,
      decisionName: decision?.name || selectedClient.value.contactName || '',
      companyName: selectedClient.value.name || '',
    },
  })
}

const fetchConsentCatalog = async () => {
  if (!auth.enabled) return
  try {
    const { data } = await api.get('/v1/consents', { params: { per_page: 200 } })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    consentCatalog.value = list.map((item: any) => ({
      id: String(item.id),
      code: String(item.code || ''),
      title: String(item.title || item.code || ''),
      description: String(item.description || ''),
      required: Boolean(item.required),
      file_url: item.file_url || null,
      file_name: item.file_name || null,
      updated_at: item.updated_at || item.updatedAt || null,
    }))
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać zgód.'
    toast.error(message)
  }
}

const fetchClientConsents = async (clientId: string) => {
  if (!auth.enabled) return
  consentsLoading.value = true
  try {
    const { data } = await api.get(`/v1/clients/${clientId}/consents`)
    clientConsentEntries.value = Array.isArray(data)
      ? data.map((item: any) => ({
          id: String(item.id),
          consent_id: String(item.consent_id || item.consent?.id || ''),
          accepted_at: item.accepted_at || item.acceptedAt || null,
          denied_at: item.denied_at || item.deniedAt || null,
          consent: item.consent ? { id: String(item.consent.id || ''), updated_at: item.consent.updated_at || null } : undefined,
        }))
      : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać zgód klienta.'
    toast.error(message)
  } finally {
    consentsLoading.value = false
  }
}

const fetchClientContacts = async (clientId: string) => {
  if (!auth.enabled) return
  contactsLoading.value = true
  try {
    const { data } = await api.get(`/v1/clients/${clientId}/contacts`)
    clientContacts.value = Array.isArray(data)
      ? data.map((item: any) => ({
          id: String(item.id),
          name: String(item.name || ''),
          position: item.position || null,
          phone: item.phone || null,
          email: item.email || null,
          is_decision_maker: item.is_decision_maker ?? item.isDecisionMaker ?? null,
          created_at: item.created_at || null,
          updated_at: item.updated_at || null,
        }))
      : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać kontaktów.'
    toast.error(message)
  } finally {
    contactsLoading.value = false
  }
}

const resetContactForm = () => {
  newContact.value = {
    name: '',
    position: '',
    phone: '',
    email: '',
    is_decision_maker: false,
  }
}

const openContactEdit = (contact: ClientContact) => {
  contactEdit.value = {
    id: contact.id,
    name: contact.name || '',
    position: contact.position || '',
    phone: contact.phone || '',
    email: contact.email || '',
    is_decision_maker: Boolean(contact.is_decision_maker),
  }
  isContactEditOpen.value = true
}

const closeContactEdit = () => {
  isContactEditOpen.value = false
}

const saveContactEdit = async () => {
  if (!auth.enabled || !contactEdit.value.id) return
  try {
    await api.patch(`/v1/contacts/${contactEdit.value.id}`, {
      name: contactEdit.value.name.trim(),
      position: contactEdit.value.position || null,
      phone: contactEdit.value.phone || null,
      email: contactEdit.value.email || null,
      is_decision_maker: contactEdit.value.is_decision_maker || false,
    })
    toast.success('Zaktualizowano kontakt.')
    closeContactEdit()
    if (selectedClient.value) {
      await fetchClientContacts(selectedClient.value.id)
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać kontaktu.'
    toast.error(message)
  }
}

const addClientContact = async () => {
  if (!auth.enabled || !selectedClient.value) return
  if (!newContact.value.name.trim()) {
    toast.warning('Uzupełnij imię i nazwisko kontaktu.')
    return
  }
  try {
    await api.post(`/v1/clients/${selectedClient.value.id}/contacts`, {
      name: newContact.value.name.trim(),
      position: newContact.value.position || null,
      phone: newContact.value.phone || null,
      email: newContact.value.email || null,
      is_decision_maker: newContact.value.is_decision_maker || false,
    })
    toast.success('Dodano osobę kontaktową.')
    resetContactForm()
    await fetchClientContacts(selectedClient.value.id)
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się dodać kontaktu.'
    toast.error(message)
  }
}

const getConsentEntry = (consentId: string) =>
  clientConsentEntries.value.find((entry) => entry.consent_id === consentId)

const isConsentAccepted = (consentId: string) => {
  const entry = getConsentEntry(consentId)
  if (!entry?.accepted_at) return false
  if (entry.denied_at && new Date(entry.denied_at) >= new Date(entry.accepted_at)) return false
  return true
}

const needsConsentUpdate = (consentId: string) => {
  const entry = getConsentEntry(consentId)
  if (!entry?.accepted_at) return false
  const catalog = consentCatalog.value.find((c) => c.id === consentId)
  if (!catalog?.updated_at) return false
  return new Date(catalog.updated_at) > new Date(entry.accepted_at)
}

const openConsentFile = async (consent: { id: string; file_name?: string | null }, download = false) => {
  try {
    const { data } = await api.get(`/v1/consents/${consent.id}/file`, {
      params: download ? { download: 1 } : undefined,
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(data)
    if (download) {
      const a = document.createElement('a')
      a.href = url
      a.download = consent.file_name || 'consent.pdf'
      a.click()
      window.URL.revokeObjectURL(url)
      return
    }
    window.open(url, '_blank')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać pliku.'
    toast.error(message)
  }
}

const toggleClientConsent = async (consentId: string, checked: boolean) => {
  if (!auth.enabled || !selectedClient.value) return
  const now = new Date().toISOString()
  const entry = getConsentEntry(consentId)
  try {
    if (checked) {
      if (entry?.id) {
        await api.patch(`/v1/client-consents/${entry.id}`, { accepted_at: now, denied_at: null, source: 'crm' })
      } else {
        await api.post(`/v1/clients/${selectedClient.value.id}/consents`, {
          consent_id: consentId,
          accepted_at: now,
          source: 'crm',
        })
      }
    } else if (entry?.id) {
      await api.patch(`/v1/client-consents/${entry.id}`, { denied_at: now, source: 'crm' })
    }
    await fetchClientConsents(selectedClient.value.id)
    toast.success('Zaktualizowano zgody.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zaktualizować zgody.'
    toast.error(message)
  }
}

const changeStatus = (event: Event, clientId: string) => {
  if (isReadOnly.value) return
  const newStatus = (event.target as HTMLSelectElement).value as Client['status']
  const client = (Array.isArray(clients.value) ? clients.value : []).find((c) => c.id === clientId)
  if (!client || newStatus === client.status) return
  if (client.status === 'SIGNED') {
    toast.warning('Zmiana statusu podpisanego klienta wymaga weryfikacji w panelu administratora.')
    ;(event.target as HTMLSelectElement).value = client.status
    return
  }

  clientStore.updateClient(clientId, { status: newStatus })
  toast.success('Zmieniono status klienta.')
}

const continueProcess = (client: Client) => {
  router.push({
    path: '/app/sales/start',
    query: {
      clientId: client.id,
      meetingId: client.meetingId || undefined,
    },
  })
}

const openRescheduleModal = (client: Client) => {
  if (!auth.enabled) {
    toast.warning('Tryb API jest wyłączony.')
    return
  }
  if (!client.meetingId) {
    toast.warning('Brak aktywnego spotkania do przełożenia.')
    return
  }
  if (client.meetingResumeAt) {
    rescheduleDateTime.value = new Date(client.meetingResumeAt).toISOString().slice(0, 16)
  } else if (client.meetingValidUntil) {
    rescheduleDateTime.value = `${client.meetingValidUntil}T09:00`
  } else {
    rescheduleDateTime.value = ''
  }
  isRescheduleOpen.value = true
}

const closeRescheduleModal = () => {
  isRescheduleOpen.value = false
}

const confirmReschedule = async () => {
  const client = selectedClient.value
  if (!client || !client.meetingId) return
  if (!rescheduleDateTime.value) {
    toast.warning('Podaj nowy termin i godzinę spotkania.')
    return
  }
  try {
    const resumeAt = new Date(rescheduleDateTime.value)
    if (Number.isNaN(resumeAt.getTime())) {
      toast.warning('Podaj poprawną datę i godzinę.')
      return
    }
    await api.patch(`/v1/meetings/${client.meetingId}`, {
      valid_until: resumeAt.toISOString().slice(0, 10),
      paused_at: new Date().toISOString(),
      resume_at: resumeAt.toISOString(),
    })
    toast.success('Spotkanie wstrzymane i zaplanowano wznowienie.')
    closeRescheduleModal()
    await clientStore.refreshApiData()
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zmienić terminu.'
    toast.error(message)
  }
}

const addActivity = (clientId: string, type: string, description: string) => {
  const u = currentUser.value
  if (!description || !u) return

  clientStore.addActivity(clientId, { type: type as any, description, authorId: u.id })
  toast.success('Dodano aktywność.')
  activityDescription.value = ''
}

const quickNote = ref('')
const clientNotes = computed(() => {
  if (!selectedClient.value?.activityHistory) return []
  return selectedClient.value.activityHistory
    .filter((act) => act.type === 'NOTE')
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const saveQuickNote = async () => {
  if (!selectedClient.value || !quickNote.value.trim()) return
  // Use the local wrapper but await the store action indirectly? 
  // actually local wrapper is not async right now.
  const description = quickNote.value
  const u = currentUser.value
  const clientId = selectedClient.value.id
  
  if (!description || !u) return

  try {
    await clientStore.addActivity(clientId, { type: 'NOTE', description, authorId: u.id })
    // Remove toast from here because store already shows it on success?
    // Store shows "Dodano aktywność" on success.
    // The wrapper 'addActivity' also shows 'Dodano aktywność.'. Double toast.
    
    // reset form
    quickNote.value = ''
    activityDescription.value = ''
  } catch (e) {
    // If store throws, we catch here? 
    // Store catches internally and shows toast.error. 
    // It does not re-throw error in the catch block unless we change it.
    // Currently store catches and returns.
  }
}

const exportToCsv = () => {
  const rows = displayedClients.value
  if (rows.length === 0) {
    toast.warning('Brak danych do eksportu.')
    return
  }

  const header = ['Nazwa Firmy', 'NIP', 'Miasto', 'Osoba Kontaktowa', 'Telefon', 'Status', 'Opiekun', 'Data Ost. Aktywności']
  const csvRows = rows.map((r) => [
    `"${r.name.replace(/"/g, '""')}"`,
    r.nip,
    r.city,
    `"${r.contactName}"`,
    r.contactPhone,
    r.status,
    `"${r.opiekunDisplay}"`,
    new Date(r.lastActionDate).toLocaleDateString(),
  ].join(','))

  const csvContent = '\uFEFF' + [header.join(','), ...csvRows].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', 'klienci_stratton.csv')
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  toast.success('Rozpoczęto pobieranie pliku CSV.')
}

const onDragStart = (event: DragEvent, client: Client) => {
  if (!event.dataTransfer || isReadOnly.value) return
  event.dataTransfer.effectAllowed = 'move'
  // Set a dummy payload so Firefox actually starts the drag.
  try { event.dataTransfer.setData('text/plain', client.id) } catch {}
  draggedClientId.value = client.id
}

const onDragOver = (event: DragEvent) => {
  if (!draggedClientId.value) return
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
}

const onDrop = (event: DragEvent, newStatus: Client['status']) => {
  event.preventDefault()
  const clientId = draggedClientId.value
  // Clear drag state synchronously so the browser can finalize the drag
  // before any Vue re-render kicks in.
  draggedClientId.value = null
  if (!clientId) return

  const list = Array.isArray(clients.value) ? clients.value : []
  const client = list.find((c) => c.id === clientId)
  if (!client || client.status === newStatus) return

  // Defer mutation + native confirm() to the next macrotask. Calling
  // confirm() or mutating the dragged element from inside the drop
  // handler can leave Chrome with a frozen invisible dialog and a
  // half-cleaned-up drag layer, blocking the whole tab.
  setTimeout(() => {
    if (client.status === 'SIGNED' && !window.confirm('Czy na pewno chcesz zmienić status podpisanego klienta?')) return
    clientStore.updateClient(clientId, { status: newStatus })
    toast.success(`Przeniesiono '${client.name}' do etapu: ${statusLabel(newStatus)}`)
  }, 0)
}

const generateContract = (clientId: string) => {
  router.push(`/app/contract-preview/${clientId}`)
}

if (route.query.expand) {
  const expandId = Array.isArray(route.query.expand) ? route.query.expand[0] : route.query.expand
  const target = (Array.isArray(clients.value) ? clients.value : []).find((client) => client.id === expandId)
  if (target) selectClient(target)
}
</script>

<template>
  <div class="flex flex-col" :class="embedded ? 'h-auto min-h-[600px]' : 'h-[calc(100vh-112px)]'">

    <div class="px-4 pt-4 pb-2 md:px-6 md:pt-6" v-if="!embedded">
       <div class="text-white rounded-card p-4 md:p-8 shadow-card-hover flex justify-between items-center relative overflow-hidden border" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%); border-color: #003366;">
          <div class="relative z-10 flex items-center gap-4 md:gap-6">
              <RouterLink to="/app/sales/start" class="hidden md:flex w-12 h-12 rounded-md bg-slate-800 border border-slate-700 items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-all shadow-sm group">
                  <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
              </RouterLink>
              <div>
                  <h1 class="font-serif font-bold text-2xl md:text-4xl text-white tracking-tight">Klienci</h1>
                  <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Twoja baza kontaktów</p>
              </div>
          </div>
          <div class="relative z-10 flex items-center gap-4">
              <!-- Actions -->
          </div>
       </div>
    </div>
    
    <div class="bg-slate-50 border-b border-slate-200 p-2 flex flex-wrap items-center gap-y-2 shadow-sm flex-shrink-0" :class="embedded ? 'rounded-t-card' : ''">
      <div class="flex items-center gap-3 ml-4">
        <AppIcon name="users" class="w-5 h-5 text-primary" />
        <h3 class="font-black text-slate-800 text-xl tracking-tight">Klienci w obsłudze</h3>
      </div>
      <div class="flex-1 flex flex-wrap items-center justify-end px-2 md:px-4 gap-2 md:gap-4">
        <div class="flex items-center space-x-2">
          <button type="button" class="flex items-center px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 rounded-lg border border-slate-200 bg-white font-medium transition-colors" @click="router.push('/app/sales/start')">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Nowy</span>
          </button>
          <button type="button" class="flex items-center px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 rounded-lg border border-slate-200 bg-white font-medium transition-colors" @click="exportToCsv">
            <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span>Eksportuj</span>
          </button>
          <template v-if="isAdmin">
            <div class="h-5 w-px bg-slate-200 mx-2"></div>
            <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200">
              <button type="button" class="px-2 py-1 rounded-md text-sm flex items-center transition-all" :class="viewMode === 'list' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 hover:text-slate-700'" @click="setViewMode('list')">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                Lista
              </button>
              <button type="button" class="px-2 py-1 rounded-md text-sm flex items-center transition-all" :class="viewMode === 'kanban' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 hover:text-slate-700'" @click="setViewMode('kanban')">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                Kanban
              </button>
            </div>
          </template>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
          <!-- Leady od moich leadowców toggle — only for opiekun roles -->
          <button
            v-if="isOpiekunOrAbove && auth.enabled"
            type="button"
            @click="toggleLeadowcy"
            class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border transition-colors whitespace-nowrap"
            :class="fromMyLeadowcy ? 'bg-[#001f3d] text-white border-[#001f3d]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
          >
            <AppIcon name="users" class="w-3.5 h-3.5" />
            Leady od moich leadowców
          </button>
          <div class="w-full md:w-96 relative">
            <input v-model="filterText" type="text" placeholder="Szukaj klienta, firmy lub NIP..." class="w-full border-slate-200 rounded-lg text-sm pl-10 pr-4 py-2.5 focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold bg-white text-slate-800 shadow-sm text-right font-bold transition-all placeholder-slate-400" />
            <AppIcon name="search" class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
        </div>
      </div>
    </div>

    <!-- Leady od moich leadowców — panel -->
    <div v-if="fromMyLeadowcy && isOpiekunOrAbove" class="mx-4 mb-4 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700">Leady od moich leadowców</h3>
        <span class="text-xs text-slate-400">{{ leadowcyClients.length }} klientów</span>
      </div>
      <div v-if="loadingLeadowcy" class="py-8 flex items-center justify-center">
        <div class="w-6 h-6 border-2 border-slate-300 border-t-[#C5A059] rounded-full animate-spin" />
      </div>
      <div v-else-if="!leadowcyClients.length" class="py-6 text-center text-sm text-slate-400">
        Brak leadów od Twoich leadowców.
      </div>
      <div v-else class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
        <div v-for="c in leadowcyClients" :key="c.id" class="px-4 py-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
          <div>
            <p class="text-sm font-semibold text-slate-800">{{ c.name }}</p>
            <p class="text-xs text-slate-400">{{ [c.nip ? 'NIP: ' + c.nip : null, c.city].filter(Boolean).join(' · ') }}</p>
          </div>
          <span class="text-[10px] px-2 py-1 bg-green-100 text-green-700 rounded-full font-medium">Nowy lead</span>
        </div>
      </div>
    </div>

    <div v-if="viewMode === 'list'" class="flex-1 relative overflow-hidden bg-surface flex flex-col">
      <!-- Mobile card list -->
      <div class="md:hidden flex-1 overflow-y-auto divide-y divide-slate-100 bg-white">
        <div
          v-for="client in paginatedClients"
          :key="client.id"
          class="p-4 cursor-pointer active:bg-slate-50 transition-colors"
          @click="selectClient(client)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="font-bold text-slate-800 truncate">{{ client.name }}</div>
              <div class="text-xs text-slate-400 font-mono mt-0.5">{{ client.nip }}</div>
            </div>
            <span
              class="px-2 py-0.5 inline-flex shrink-0 text-xs leading-4 font-semibold rounded-full"
              :class="{
                'bg-yellow-100 text-yellow-800': client.status === 'NEW',
                'bg-indigo-100 text-indigo-800': client.status === 'IN_TALKS',
                'bg-emerald-100 text-emerald-800': client.status === 'SIGNED',
                'bg-gray-200 text-gray-800': client.status === 'TERMINATED',
                'bg-red-100 text-red-800': client.status === 'RESIGNED',
              }"
            >{{ statusLabel(client.status) }}</span>
          </div>
          <div class="mt-2 flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500">{{ client.opiekunName }}</span>
            <div class="flex items-center gap-1 text-xs text-slate-400">
              <span v-if="getClientSlaStatus(client) === 'CRITICAL'" class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
              {{ new Date(client.lastActionDate).toLocaleDateString() }}
            </div>
          </div>
          <div class="mt-2 flex items-center justify-between">
            <span
              v-if="getRemainingReservationDays(client) !== null && client.status === 'IN_TALKS' && (getRemainingReservationDays(client) || 0) > 0"
              class="text-[10px] rounded bg-sky-100 text-sky-700 font-semibold px-2 py-0.5"
            >do {{ formatReservationDate(client) }}</span>
            <span v-else></span>
            <div class="flex items-center gap-1">
              <button @click.stop="openClientEditModal(client)" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 min-w-[36px] min-h-[36px] flex items-center justify-center">
                <AppIcon name="pencil-square" class="w-4 h-4" />
              </button>
              <button @click.stop="handleDeleteClient(client)" class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 min-w-[36px] min-h-[36px] flex items-center justify-center">
                <AppIcon name="trash" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-if="displayedClients.length === 0" class="p-8 text-center text-slate-500 text-sm">
          Brak rekordów spełniających kryteria.
        </div>
      </div>
      <!-- Desktop table -->
      <div class="flex-1 overflow-auto min-h-0 hidden md:block">
        <table class="w-full divide-y divide-slate-100">
          <thead class="bg-slate-50 sticky top-0 z-10 shadow-sm">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-primary transition-colors" @click="sort('name')">Nazwa Klienta</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-primary transition-colors" @click="sort('opiekunDisplay')">Opiekun</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-primary transition-colors" @click="sort('status')">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-primary transition-colors" @click="sort('lastActionDate')">Ostatnia Aktywność</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Rezerwacja</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider pr-6">Akcje</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 bg-white">
            <template v-for="client in paginatedClients" :key="client.id">
            <tr
              :id="`client-${client.id}`"
              class="hover:bg-slate-50 cursor-pointer transition-colors group"
              :class="selectedClient?.id === client.id ? 'bg-indigo-50/30' : ''"
              @click="selectClient(client)"
            >
              <td class="px-4 py-3 whitespace-nowrap relative">
                <div>
                  <div class="text-sm font-bold text-slate-700 group-hover:text-primary transition-colors truncate max-w-[260px]" :title="client.name">{{ client.name }}</div>
                  <div class="text-xs text-slate-400 font-mono">{{ client.nip }}</div>
                </div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap" @click.stop="toggleOwnerDetails(client.id)">
                <div class="w-fit rounded-lg border border-dashed border-slate-200 px-3 py-1 bg-slate-50 hover:bg-white hover:border-primary/30 transition-colors group/owner">
                  <div v-if="client.opiekunRole" class="text-[9px] font-black text-primary uppercase tracking-tighter leading-none mb-1">{{ client.opiekunRole }}</div>
                  <div class="text-sm font-semibold text-slate-700 leading-tight group-hover/owner:text-primary transition-colors">{{ client.opiekunName }}</div>
                  <div class="text-[11px] text-slate-400 font-mono tracking-wide">ID: {{ client.opiekunHierarchy }}</div>
                </div>
              </td>
              <td class="px-4 py-1.5 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full"
                  :class="{
                    'bg-yellow-100 text-yellow-800': client.status === 'NEW',
                    'bg-indigo-100 text-indigo-800': client.status === 'IN_TALKS',
                    'bg-emerald-100 text-emerald-800': client.status === 'SIGNED',
                    'bg-gray-200 text-gray-800': client.status === 'TERMINATED',
                    'bg-red-100 text-red-800': client.status === 'RESIGNED',
                  }"
                >
                  {{ statusLabel(client.status) }}
                </span>
              </td>
              <td class="px-4 py-1.5 whitespace-nowrap text-xs text-gray-600">
                <div class="flex items-center">
                  <span v-if="getClientSlaStatus(client) === 'CRITICAL'" class="w-2 h-2 rounded-full bg-red-500 mr-2 flex-shrink-0" title="Brak kontaktu od ponad 3 dni!"></span>
                  <span>{{ new Date(client.lastActionDate).toLocaleDateString() }}</span>
                </div>
              </td>
              <td class="px-4 py-1.5 whitespace-nowrap text-[11px] text-gray-600">
                <span v-if="getRemainingReservationDays(client) !== null && client.status === 'IN_TALKS' && (getRemainingReservationDays(client) || 0) > 0" class="inline-flex flex-col items-start rounded bg-sky-100 text-sky-700 font-semibold px-2 py-0.5 leading-tight">
                  <span>rezerwacja do</span>
                  <span>{{ formatReservationDate(client) }}</span>
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-1.5 whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-2 text-right">
                  <button
                    @click.stop="openClientEditModal(client)"
                    class="p-1 px-2 rounded-lg border border-transparent text-slate-400 hover:text-blue-600 hover:border-blue-100 hover:bg-blue-50 transition"
                    title="Edytuj"
                  >
                    <AppIcon name="pencil-square" class="w-4 h-4" />
                  </button>
                  <button
                    @click.stop="handleDeleteClient(client)"
                    class="p-1 px-2 rounded-lg border border-transparent text-slate-400 hover:text-red-600 hover:border-red-100 hover:bg-red-50 transition"
                    title="Usuń"
                  >
                    <AppIcon name="trash" class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="expandedClientId === client.id" class="bg-slate-50 border-y border-slate-200 shadow-inner animate-fade-in">
              <td colspan="6" class="p-0 cursor-default" @click.stop>
                <div class="p-4 flex justify-between items-center bg-slate-50/50">
                <div v-if="getClientOwner(client)" class="flex justify-between items-center w-full">
                    <div class="flex items-center gap-8 text-xs text-slate-600">
                        <div>
                            <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Telefon</span>
                            <span class="font-medium text-slate-800">{{ getClientOwner(client)?.phone || 'Brak' }}</span>
                        </div>
                        <div>
                            <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Email</span>
                            <button type="button" class="text-primary hover:text-primary-dark hover:underline flex items-center gap-1 font-medium" @click="emailClientOwner(client)">
                                <AppIcon name="envelope" class="w-3 h-3" />
                                <span>{{ getClientOwner(client)?.email }}</span>
                            </button>
                        </div>
                        <div>
                            <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Rola</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-bold text-[10px] uppercase bg-white text-slate-600 border border-slate-200 shadow-sm">
                                {{ getClientOwner(client)?.role || 'Brak' }}
                            </span>
                        </div>
                        <div>
                             <span class="font-bold block text-slate-400 uppercase text-[10px] mb-1">Kod Struktury</span>
                             <span class="font-mono bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-600 shadow-sm">{{ getClientOwner(client)?.hierarchicalId || 'Brak' }}</span>
                        </div>
                    </div>
                
                    <div class="flex items-center gap-2">
                       <button type="button" class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm" title="Wyślij wiadomość" @click="emailClientOwner(client)">
                           <AppIcon name="chat-bubble-left-ellipsis" class="w-5 h-5" />
                       </button>

                       <!-- Bell Notification Button -->
                       <button 
                         v-if="getClientOwner(client) && canNotify(getClientOwner(client))" 
                         type="button" 
                         class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm" 
                         title="Wyślij powiadomienie wewnętrzne"
                         @click="openMsgModal(getClientOwner(client))"
                       >
                         <AppIcon name="bell" class="w-5 h-5" />
                       </button>

                       <button 
                         v-if="getClientOwner(client) && canImpersonate(getClientOwner(client))" 
                         type="button" 
                         class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm" 
                         :title="`Podgląd konta: ${getClientOwner(client)?.name}`"
                         @click="impersonateUser(getClientOwner(client))"
                       >
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                         </svg>
                       </button>

                       <button
                         v-if="getClientOwner(client) && auth.enabled && currentUser?.role === 'ADMIN'"
                         type="button"
                         class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-primary hover:border-primary/30 transition shadow-sm"
                         :title="`Przejdź do struktury aby edytować: ${getClientOwner(client)?.name}`"
                         @click="editRedirect(getClientOwner(client))"
                       >
                          <AppIcon name="pencil-square" class="w-5 h-5" />
                       </button>
                       
                       <button 
                         v-if="getClientOwner(client) && canRemove(getClientOwner(client))" 
                         type="button" 
                         class="p-2 bg-white text-red-600 border border-slate-200 rounded-lg hover:bg-red-50 hover:border-red-200 transition shadow-sm" 
                         :title="`Usuń ze struktury: ${getClientOwner(client)?.name}`"
                         @click="removeUser(getClientOwner(client))"
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
            <tr v-if="displayedClients.length === 0">
              <td colspan="6" class="p-8 text-center text-slate-500 text-sm">Brak rekordów spełniających kryteria.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div 
        class="bg-surface border-t border-slate-200 flex justify-between items-center shrink-0"
        :class="embedded ? 'px-4 py-2' : 'px-6 py-4'"
      >
        <div class="uppercase tracking-widest text-[10px] text-slate-400 font-medium">
          Strona {{ clientsPage }} z {{ totalClientPages }}
          <span class="ml-2 text-slate-400">({{ displayedClients.length }} rekordów)</span>
        </div>
        <div class="flex gap-2">
          <button
            type="button"
            @click="prevClientsPage"
            :disabled="clientsPage === 1"
            class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
            Poprzednia
          </button>
          <button
            type="button"
            @click="nextClientsPage"
            :disabled="clientsPage >= totalClientPages"
            class="px-4 py-2 bg-primary text-primary-foreground rounded-lg text-xs font-bold hover:bg-primary-dark transition shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            Następna
            <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <div v-if="viewMode === 'kanban'" class="flex-1 overflow-x-auto p-4 bg-surface">
      <div class="flex space-x-4 h-full">
        <div v-for="stage in kanbanData" :key="stage.status" class="w-80 bg-slate-50/50 rounded-card shadow-sm border border-slate-200 flex flex-col flex-shrink-0">
          <div class="p-3 border-b border-slate-200 bg-white/50 rounded-t-card">
            <h3 class="font-bold text-sm text-slate-700">{{ stage.title }} <span class="text-xs text-slate-400 font-normal">({{ stage.clients.length }})</span></h3>
          </div>
          <div class="flex-1 p-2 overflow-y-auto space-y-2" @dragover="onDragOver" @drop="onDrop($event, stage.status)">
            <div
              v-for="client in stage.clients"
              :key="client.id"
              draggable="true"
              class="bg-white p-3 rounded-card border border-slate-200 shadow-sm cursor-move hover:border-primary hover:shadow-md transition-all relative group"
              @dragstart="onDragStart($event, client)"
            >
              <div v-if="getRemainingReservationDays(client) && client.status === 'IN_TALKS'" class="absolute top-2 right-2 text-[10px] bg-primary text-primary-foreground font-bold rounded px-1.5 py-0.5 shadow leading-tight text-right">
                <span class="block">rezerwacja do</span>
                <span class="block">{{ formatReservationDate(client) }}</span>
              </div>
              <div class="flex justify-between items-start">
                <p class="font-bold text-sm text-slate-800 group-hover:text-primary transition-colors pr-2 break-words">{{ client.name }}</p>
                <span v-if="getClientSlaStatus(client) === 'CRITICAL'" class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0 mt-1" title="Brak kontaktu od ponad 3 dni!"></span>
              </div>
              <p class="text-[11px] text-slate-400 font-mono mt-1">{{ client.nip }}</p>
              <div class="mt-2 pt-2 border-t border-slate-100">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Opiekun</p>
                <div v-if="client.opiekunRole" class="text-[8px] font-black text-primary uppercase tracking-tight">{{ client.opiekunRole }}</div>
                <p class="text-xs text-slate-700 font-bold leading-tight">{{ client.opiekunName }}</p>
                <p class="text-[10px] text-slate-400 font-mono">ID: {{ client.opiekunHierarchy }}</p>
              </div>
              <div class="text-xs text-slate-400 mt-3 flex justify-between items-center">
                <span>{{ client.city }}</span>
                <button type="button" class="p-1 hover:bg-slate-100 rounded group relative" title="Pokaż szczegóły" @click.stop="selectClient(client)">
                  <span class="absolute inset-0 m-1 bg-emerald-400 rounded-full animate-ping opacity-50"></span>
                  <svg class="relative w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                </button>
              </div>
            </div>
            <div v-if="stage.clients.length === 0" class="h-full border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center text-xs text-slate-400 p-4">
              Przeciągnij klienta tutaj
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedClient" class="fixed inset-0 z-40">
      <div class="absolute inset-0 bg-black/30 backdrop-blur-[1px]" @click="closePanel"></div>
      <div class="absolute top-0 right-0 h-full w-full max-w-2xl bg-surface z-50 shadow-2xl flex flex-col animate-slide-in-right">
        <div class="p-6 bg-white border-b border-slate-200 flex-shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-xl font-bold text-slate-900">{{ selectedClient.name }}</h3>
              <p class="text-sm text-slate-500 font-mono mt-0.5">NIP: {{ selectedClient.nip }}</p>
            </div>
            <button type="button" class="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition-colors" @click="closePanel">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div class="flex items-center gap-4 mt-6">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status</label>
            <select :value="selectedClient.status" class="py-1.5 pl-3 pr-8 text-sm font-medium rounded-lg border-slate-300 focus:ring-primary focus:border-primary bg-white shadow-sm" :disabled="isReadOnly" @change="changeStatus($event, selectedClient.id)">
                <option value="NEW">Nowy</option>
                <option value="IN_TALKS">W rozmowach</option>
                <option value="SIGNED">Podpisany</option>
                <option v-if="isAdmin" value="TERMINATED">Umowa rozwiązana</option>
                <option value="RESIGNED">Rezygnacja</option>
              </select>
            </div>
            <div class="flex-1"></div>
            <button type="button" class="bg-white border border-slate-300 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-slate-50 transition-colors" @click="continueProcess(selectedClient)">
              Kontynuuj proces
            </button>
            <button
              v-if="selectedClient.meetingStatus === 'open'"
              type="button"
              class="bg-white border border-slate-300 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-slate-50 transition-colors"
              @click="openRescheduleModal(selectedClient)"
            >
              Wstrzymaj / przełóż
            </button>
            <button type="button" class="bg-primary text-primary-foreground border border-transparent px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-primary-dark transition-colors" @click="generateContract(selectedClient.id)">
              Generuj Umowę
            </button>
          </div>
        </div>

        <div class="border-b border-slate-200 bg-white flex-shrink-0">
          <nav class="flex space-x-6 px-6">
            <button type="button" class="px-1 py-4 text-sm font-bold border-b-2 transition-colors duration-200" :class="activePanelTab === 'details' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700'" @click="activePanelTab = 'details'">Szczegóły</button>
            <button type="button" class="px-1 py-4 text-sm font-bold border-b-2 transition-colors duration-200" :class="activePanelTab === 'contacts' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700'" @click="activePanelTab = 'contacts'">Kontakt</button>
            <button type="button" class="px-1 py-4 text-sm font-bold border-b-2 transition-colors duration-200" :class="activePanelTab === 'activity' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700'" @click="activePanelTab = 'activity'">Aktywności</button>
            <button type="button" class="px-1 py-4 text-sm font-bold border-b-2 transition-colors duration-200" :class="activePanelTab === 'finance' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700'" @click="activePanelTab = 'finance'">Finanse</button>
            <button type="button" class="px-1 py-4 text-sm font-bold border-b-2 transition-colors duration-200" :class="activePanelTab === 'offers' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700'" @click="activePanelTab = 'offers'">Oferty</button>
          </nav>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-surface">
          <div v-if="activePanelTab === 'details'" class="space-y-6">
            <div class="bg-white p-6 border border-slate-200 rounded-card shadow-sm">
              <h4 class="font-bold text-slate-400 uppercase text-xs tracking-wider mb-4">Dane Firmy</h4>
              <dl class="grid grid-cols-3 gap-6 text-sm">
                <div class="col-span-2">
                  <dt class="text-slate-500 text-xs uppercase font-semibold mb-1">Nazwa</dt>
                  <dd class="text-slate-900 font-medium text-base">{{ selectedClient.name }}</dd>
                </div>
                <div class="col-span-1">
                  <dt class="text-slate-500 text-xs uppercase font-semibold mb-1">NIP</dt>
                  <dd class="text-slate-900 font-medium font-mono text-base">{{ selectedClient.nip }}</dd>
                </div>
                <div v-if="selectedClient.regon" class="col-span-1">
                  <dt class="text-slate-500 text-xs uppercase font-semibold mb-1">REGON</dt>
                  <dd class="text-slate-900 font-medium font-mono">{{ selectedClient.regon }}</dd>
                </div>
                <div v-if="selectedClient.krs" class="col-span-1">
                  <dt class="text-slate-500 text-xs uppercase font-semibold mb-1">KRS</dt>
                  <dd class="text-slate-900 font-medium font-mono">{{ selectedClient.krs }}</dd>
                </div>
                <div class="col-span-2">
                  <dt class="text-slate-500 text-xs uppercase font-semibold mb-1">Adres</dt>
                  <dd class="text-slate-900 font-medium">
                    {{ [selectedClient.street, selectedClient.buildingNr].filter(Boolean).join(' ') || '—' }}
                  </dd>
                </div>
                <div class="col-span-1">
                  <dt class="text-slate-500 text-xs uppercase font-semibold mb-1">Miasto</dt>
                  <dd class="text-slate-900 font-medium">
                    {{ [selectedClient.zip, selectedClient.city].filter(Boolean).join(' ') || '—' }}
                  </dd>
                </div>
              </dl>
            </div>
            <div class="bg-white p-6 border border-slate-200 rounded-card shadow-sm">
              <h4 class="font-bold text-slate-400 uppercase text-xs tracking-wider mb-4">Zgody</h4>
              <div v-if="consentsLoading" class="text-sm text-slate-400">Ładowanie zgód...</div>
              <div v-else-if="!consentCatalog.length" class="text-sm text-slate-400">Brak zdefiniowanych zgód.</div>
              <div v-else class="space-y-3">
                <label v-for="consent in consentCatalog" :key="consent.id" class="flex items-start gap-3 border border-slate-200 rounded-lg p-3 hover:border-primary/50 transition-colors bg-slate-50/50 cursor-pointer">
                  <input
                    type="checkbox"
                    class="mt-1 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary transition-all"
                    :checked="isConsentAccepted(consent.id)"
                    :disabled="isReadOnly"
                    @change="toggleClientConsent(consent.id, ($event.target as HTMLInputElement).checked)"
                  />
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-slate-800">{{ consent.title }}</span>
                      <span v-if="consent.required" class="text-[10px] font-bold text-amber-600 uppercase tracking-wider bg-amber-50 px-1.5 py-0.5 rounded">Wymagana</span>
                      <span v-if="needsConsentUpdate(consent.id)" class="text-[10px] font-bold text-rose-600 uppercase tracking-wider bg-rose-50 px-1.5 py-0.5 rounded">Wymaga aktualizacji</span>
                    </div>
                    <p v-if="consent.description" class="text-xs text-slate-500 mt-1">{{ consent.description }}</p>
                    <div v-if="consent.file_url" class="mt-2 flex items-center gap-3 text-xs">
                      <button type="button" class="text-primary hover:underline font-medium" @click.stop="openConsentFile(consent, false)">Podgląd</button>
                      <button type="button" class="text-primary hover:underline font-medium" @click.stop="openConsentFile(consent, true)">Pobierz</button>
                    </div>
                    <div class="mt-2 text-[10px] text-slate-400 flex flex-wrap gap-x-4 gap-y-1 font-mono">
                      <span>Akceptacja: {{ formatDateTime(getConsentEntry(consent.id)?.accepted_at) }}</span>
                      <span>Odmowa: {{ formatDateTime(getConsentEntry(consent.id)?.denied_at) }}</span>
                      <span>Aktualizacja zgody: {{ formatDateTime(consent.updated_at) }}</span>
                    </div>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <div v-else-if="activePanelTab === 'contacts'" class="space-y-6">
            <div class="bg-white p-6 border border-slate-200 rounded-card shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-slate-400 uppercase text-xs tracking-wider">Osoby Kontaktowe</h4>
                <span class="text-xs text-slate-400 font-mono bg-slate-100 px-2 py-1 rounded">Liczba: {{ filteredContacts.length }}</span>
              </div>
              <div class="mb-4 relative">
                <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input
                  v-model="contactSearch"
                  type="text"
                  placeholder="Szukaj kontaktu..."
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-10 pr-4 py-2 text-sm text-gray-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none text-right font-bold"
                />
              </div>
              <div v-if="contactsLoading" class="text-sm text-gray-400">Ładowanie kontaktów...</div>
              <div v-else-if="filteredContacts.length === 0" class="text-sm text-gray-400">Brak kontaktów dla tego klienta.</div>
              <div v-else class="space-y-3">
                <button
                  v-for="contact in filteredContacts"
                  :key="contact.id"
                  type="button"
                  class="w-full text-left border border-gray-200 rounded-lg p-3 hover:border-sky-300 hover:bg-sky-50/50 transition"
                  @click="openContactEdit(contact)"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="flex items-center gap-2">
                        <p class="font-semibold text-gray-900">{{ contact.name }}</p>
                        <span v-if="contact.is_decision_maker" class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Decyzyjna</span>
                      </div>
                      <p v-if="contact.position" class="text-xs text-gray-500">{{ contact.position }}</p>
                    </div>
                    <div class="text-xs text-gray-500 text-right">
                      <div v-if="contact.phone">{{ contact.phone }}</div>
                      <button
                        v-if="contact.email"
                        type="button"
                        class="text-sky-600 hover:underline"
                        @click="mailboxStore.initiateEmailTo(contact.email)"
                      >
                        {{ contact.email }}
                      </button>
                    </div>
                  </div>
                </button>
              </div>

              <div class="mt-5 border-t border-gray-100 pt-5">
                <h5 class="text-xs font-bold text-gray-500 uppercase mb-4">Dodaj kontakt</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Imię i nazwisko</label>
                    <input v-model="newContact.name" type="text" placeholder="Jan Kowalski" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Stanowisko</label>
                    <input v-model="newContact.position" type="text" placeholder="Dyrektor finansowy" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Telefon</label>
                    <input v-model="newContact.phone" type="text" placeholder="+48 600 100 200" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Email</label>
                    <input v-model="newContact.email" type="email" placeholder="jan.kowalski@firma.pl" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
                  </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                  <label class="flex items-center gap-2 text-xs font-medium text-gray-600">
                    <input v-model="newContact.is_decision_maker" type="checkbox" class="h-4 w-4 text-brand-main border-gray-300 rounded focus:ring-brand-main" />
                    Osoba decyzyjna
                  </label>
                  <button type="button" class="bg-sky-600 text-white text-xs font-semibold px-5 py-2.5 rounded-lg shadow-sm hover:bg-sky-700" @click="addClientContact">
                    Dodaj kontakt
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activePanelTab === 'activity'" class="space-y-4">
            <div class="bg-white p-4 border border-gray-200 rounded">
              <h4 class="text-xs font-bold text-gray-500 uppercase mb-3">Dodaj Aktywność</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <select v-model="selectedActivityType" class="text-sm border-gray-300 rounded">
                  <option value="CALL">Telefon</option>
                  <option value="MEETING">Spotkanie</option>
                  <option value="EMAIL">Email</option>
                  <option value="NOTE">Notatka</option>
                </select>
                <input v-model="activityDescription" type="text" placeholder="Opis" class="text-sm border-gray-300 rounded" />
                <button type="button" class="bg-sky-600 text-white text-sm rounded px-3 py-2" @click="addActivity(selectedClient.id, selectedActivityType, activityDescription)">
                  Dodaj
                </button>
              </div>
            </div>
            <div class="bg-white p-4 border border-gray-200 rounded">
              <h4 class="text-xs font-bold text-gray-500 uppercase mb-3">Historia</h4>
              <div v-if="selectedClient.activityHistory?.length" class="space-y-2">
                <div v-for="act in selectedClient.activityHistory" :key="act.id" class="text-sm text-gray-700">
                  <span class="font-semibold">{{ act.type }}</span> — {{ act.description }}
                  <div class="text-xs text-gray-400">{{ new Date(act.date).toLocaleString() }}</div>
                </div>
              </div>
              <p v-else class="text-sm text-gray-400">Brak aktywności.</p>
            </div>
            
            <div class="bg-white p-4 border border-gray-200 rounded shadow-sm">
              <h4 class="text-xs font-bold text-slate-500 uppercase mb-3 flex items-center gap-2">
                <AppIcon name="document-text" class="w-4 h-4" />
                Notatki
              </h4>
              <div class="mb-4">
                 <textarea
                   v-model="quickNote"
                   placeholder="Wpisz treść notatki..."
                   class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[100px] resize-y"
                 ></textarea>
                 <div class="flex justify-end mt-2">
                   <button 
                     type="button" 
                     class="bg-slate-800 text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-slate-700 transition shadow-sm flex items-center gap-2"
                     @click="saveQuickNote"
                   >
                     <AppIcon name="plus" class="w-3 h-3" />
                     Zapisz notatkę
                   </button>
                 </div>
              </div>
              
              <div class="space-y-3 mt-6 pt-4 border-t border-slate-100">
                <div v-if="clientNotes.length === 0" class="text-center py-4 text-slate-400 text-xs italic">
                  Brak notatek dla tego klienta.
                </div>
                <div v-else v-for="note in clientNotes" :key="note.id" class="bg-yellow-50/50 border border-yellow-100 rounded-lg p-3 relative group">
                  <div class="text-xs text-slate-400 font-mono mb-1 flex justify-between">
                     <span>{{ new Date(note.date).toLocaleString() }}</span>
                  </div>
                  <p class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{{ note.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activePanelTab === 'finance'" class="space-y-4">
            <div class="bg-white p-4 border border-gray-200 rounded">
              <h4 class="text-xs font-bold text-gray-500 uppercase mb-3">Podsumowanie</h4>
              <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                  <div class="text-gray-500">Wystawiono</div>
                  <div class="font-bold">{{ clientFinancials.totalBilled.toFixed(2) }} PLN</div>
                </div>
                <div>
                  <div class="text-gray-500">Opłacono</div>
                  <div class="font-bold">{{ clientFinancials.totalPaid.toFixed(2) }} PLN</div>
                </div>
                <div>
                  <div class="text-gray-500">Saldo</div>
                  <div class="font-bold">{{ clientFinancials.outstandingBalance.toFixed(2) }} PLN</div>
                </div>
              </div>
            </div>
            <div class="bg-white p-4 border border-gray-200 rounded">
              <h4 class="text-xs font-bold text-gray-500 uppercase mb-3">Faktury</h4>
              <div v-if="clientInvoices.length" class="space-y-2">
                <div v-for="invoice in clientInvoices" :key="invoice.id" class="text-sm text-gray-700">
                  <div class="font-semibold">{{ invoice.number }}</div>
                  <div class="text-xs text-gray-400">{{ new Date(invoice.issueDate).toLocaleDateString() }} · {{ invoice.amountGross.toFixed(2) }} PLN</div>
                </div>
              </div>
              <p v-else class="text-sm text-gray-400">Brak faktur.</p>
            </div>
          </div>
          <div v-else-if="activePanelTab === 'offers'" class="space-y-4">
            <div class="bg-white p-4 border border-gray-200 rounded">
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold text-gray-500 uppercase">Kalkulacje z kalkulatora</h4>
                <button type="button" class="text-xs text-sky-600 hover:text-sky-700" @click="selectedClient && fetchClientCalculations(selectedClient.id)">
                  Odśwież
                </button>
              </div>
              <div v-if="calculationsLoading" class="text-sm text-gray-500">Ładowanie kalkulacji...</div>
              <div v-else-if="calculationsError" class="text-sm text-red-600">{{ calculationsError }}</div>
              <div v-else-if="clientCalculations.length === 0" class="text-sm text-gray-400">Brak kalkulacji.</div>
              <div v-else class="space-y-3">
                <div v-for="calc in clientCalculations" :key="calc.id" class="border border-gray-200 rounded p-3 text-sm bg-gray-50">
                  <div class="flex items-center justify-between">
                    <div class="font-semibold text-gray-800">Kalkulacja #{{ calc.id }}</div>
                    <div class="text-xs text-gray-400">{{ formatDateTime(calc.createdAt) }}</div>
                  </div>
                  <div class="mt-2 grid grid-cols-3 gap-3 text-xs text-gray-600">
                    <div>
                      <div class="text-gray-400">Liczba pracowników</div>
                      <div class="font-semibold text-gray-700">{{ calc.employeeCount }}</div>
                    </div>
                    <div>
                      <div class="text-gray-400">Oszczędność (netto)</div>
                      <div class="font-semibold text-gray-700">{{ calc.savingsAmount.toLocaleString() }} PLN</div>
                    </div>
                    <div>
                      <div class="text-gray-400">Ważna do</div>
                      <div class="font-semibold text-gray-700">{{ formatDateTime(calc.validUntil) }}</div>
                    </div>
                  </div>
                  <div class="mt-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                      <select
                        class="text-xs border border-gray-300 rounded px-2 py-1 bg-white"
                        :value="calc.status"
                        :disabled="isReadOnly"
                        @change="updateCalculationStatus(calc.id, ($event.target as HTMLSelectElement).value)"
                      >
                        <option v-for="status in calculationStatuses" :key="status.key" :value="status.key">{{ status.label }}</option>
                        <option v-if="calculationStatuses.length === 0" value="PREPARING">W trakcie przygotowania</option>
                        <option v-if="calculationStatuses.length === 0" value="READY">Gotowa</option>
                        <option v-if="calculationStatuses.length === 0" value="SENT">Wysłana</option>
                      </select>
                      <button
                        v-if="calc.valueJson"
                        @click="downloadCalculationExcel(calc)"
                        class="text-[10px] text-brand hover:text-brand/80 flex items-center bg-white border border-brand/20 rounded px-2 py-1 transition-colors"
                      >
                        <AppIcon name="DocumentArrowDownIcon" class="w-3 h-3 mr-1" />
                        Pobierz Excel
                      </button>
                    </div>
                  </div>
                  <div class="mt-3 flex items-center justify-end">
                    <button type="button" class="text-xs font-semibold px-3 py-1.5 rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50" @click="openOfferEmail(calc)">
                      Wyślij ofertę
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isRescheduleOpen" class="absolute inset-0 z-[60] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeRescheduleModal"></div>
        <div class="relative bg-white w-full max-w-md rounded-xl shadow-2xl border border-gray-200 p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-2">Wstrzymaj spotkanie</h3>
          <p class="text-sm text-gray-500 mb-4">Wybierz datę i godzinę wznowienia spotkania.</p>
          <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Wznowienie</label>
          <input v-model="rescheduleDateTime" type="datetime-local" class="w-full border-gray-300 rounded text-sm" />
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="px-4 py-2 text-sm rounded border border-gray-300 text-gray-700 hover:bg-gray-100" @click="closeRescheduleModal">
              Anuluj
            </button>
            <button type="button" class="px-4 py-2 text-sm rounded bg-sky-600 text-white hover:bg-sky-700" @click="confirmReschedule">
              Zapisz termin
            </button>
          </div>
        </div>
      </div>

      <div v-if="isContactEditOpen" class="absolute inset-0 z-[60] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeContactEdit"></div>
        <div class="relative bg-white w-full max-w-md rounded-xl shadow-2xl border border-gray-200 p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-2">Edytuj kontakt</h3>
          <p class="text-sm text-gray-500 mb-4">Zaktualizuj dane osoby kontaktowej.</p>
          <div class="space-y-3">
            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Imię i nazwisko</label>
              <input v-model="contactEdit.name" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Stanowisko</label>
              <input v-model="contactEdit.position" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Telefon</label>
              <input v-model="contactEdit.phone" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Email</label>
              <input v-model="contactEdit.email" type="email" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none" />
            </div>
          </div>
          <label class="mt-4 flex items-center gap-2 text-xs text-gray-600">
            <input v-model="contactEdit.is_decision_maker" type="checkbox" class="h-4 w-4 text-brand-main border-gray-300 rounded focus:ring-brand-main" />
            Osoba decyzyjna
          </label>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="px-4 py-2 text-sm rounded border border-gray-300 text-gray-700 hover:bg-gray-100" @click="closeContactEdit">
              Anuluj
            </button>
            <button type="button" class="px-4 py-2 text-sm rounded bg-sky-600 text-white hover:bg-sky-700" @click="saveContactEdit">
              Zapisz
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Client Edit Modal -->
    <div
      v-if="isClientEditOpen"
      class="fixed inset-0 z-[60] flex items-end md:items-center justify-center p-0 md:p-4 bg-black/20 backdrop-blur-[2px]"
      @click.self="isClientEditOpen = false"
    >
      <div class="bg-white rounded-t-2xl md:rounded-xl shadow-2xl w-full md:max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Edycja danych klienta</h3>
            <p class="text-xs text-gray-500 mt-0.5">Zaktualizuj informacje kontaktowe i branżowe</p>
          </div>
          <button
            @click="isClientEditOpen = false"
            class="p-2 -mr-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <AppIcon name="x-mark" class="w-5 h-5" />
          </button>
        </div>

        <!-- Form Content -->
        <div class="p-6 overflow-y-auto custom-scrollbar">
          <div class="grid grid-cols-2 gap-6">
            <!-- Basic Info -->
            <div class="col-span-2 space-y-4">
              <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Dane Postawowe</h4>
              
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-1">Nazwa Firmy</label>
                  <input
                    v-model="clientEditForm.companyName"
                    type="text"
                    class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                    placeholder="Wpisz nazwę firmy..."
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-1">Branża</label>
                  <div class="relative">
                    <select
                      v-model="clientEditForm.industry"
                      class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 appearance-none focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all pr-8"
                    >
                      <option value="" disabled>Wybierz branżę...</option>
                      <option v-for="ind in industries" :key="ind" :value="ind">{{ ind }}</option>
                    </select>
                    <AppIcon name="chevron-down" class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Contact Info -->
            <div class="col-span-2 space-y-4 pt-4 border-t border-gray-100">
              <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Kontakt</h4>
              
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-1">Osoba Kontaktowa</label>
                  <input
                    v-model="clientEditForm.contactName"
                    type="text"
                    class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                    placeholder="Imię i nazwisko"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-1">Telefon</label>
                  <input
                    v-model="clientEditForm.contactPhone"
                    type="text"
                    class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                    placeholder="+48..."
                  />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                  <input
                    v-model="clientEditForm.contactEmail"
                    type="email"
                    class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                    placeholder="adres@email.com"
                  />
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="col-span-2 space-y-4 pt-4 border-t border-gray-100">
              <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Notatki</h4>
              <div>
                <textarea
                  v-model="clientEditForm.contactPosition"
                  rows="3"
                  class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all resize-none"
                  placeholder="Dodatkowe informacje..."
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3">
          <button
            @click="isClientEditOpen = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all"
            :disabled="isSubmitting"
          >
            Anuluj
          </button>
          <button
            @click="handleUpdateClient"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            :disabled="isSubmitting"
          >
            <AppIcon v-if="isSubmitting" name="arrow-path" class="w-4 h-4 animate-spin" />
            <span>{{ isSubmitting ? 'Zapisywanie...' : 'Zapisz zmiany' }}</span>
          </button>
        </div>
      </div>
    </div>

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
