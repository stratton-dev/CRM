<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
import { api } from '@/api/client'
import type { Client } from '@/types/models'

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

const auth = useAuthStore()
const finance = useFinanceStore()
const data = useDataStore()
const clientStore = useClientStore()
const session = useSessionStore()
const structure = useStructureStore()
const toast = useToastStore()
const mailboxStore = useMailboxStore()
const router = useRouter()
const route = useRoute()

const { clients, clientPagination } = storeToRefs(clientStore)
const { invoices } = storeToRefs(finance)
const { users: dataUsers } = storeToRefs(structure)
const { users: structureUsers } = storeToRefs(structure)
const { currentUser, isReadOnly } = storeToRefs(session)

const viewMode = ref<'list' | 'kanban'>('list')
const selectedClient = ref<Client | null>(null)
const activePanelTab = ref<'details' | 'contacts' | 'activity' | 'finance' | 'offers'>('details')

const filterText = ref('')
const sortField = ref<keyof Client | 'opiekunDisplay' | ''>('lastActionDate')
const sortDir = ref<'asc' | 'desc'>('desc')

const draggedClientId = ref<string | null>(null)
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
const clientCalculations = ref<Array<{ id: string; meetingId: string; status: string; employeeCount: number; savingsAmount: number; validUntil: string; createdAt?: string | null }>>([])
const calculationsLoading = ref(false)
const calculationStatuses = ref<Array<{ key: string; label: string }>>([])
const calculationsError = ref<string | null>(null)

const kanbanStages: Array<{ status: Client['status']; title: string }> = [
  { status: 'NEW', title: 'Nowy' },
  { status: 'IN_TALKS', title: 'W Rozmowach' },
  { status: 'OFFER_PREPARING', title: 'Przygotowanie Oferty' },
  { status: 'OFFER_GENERATED', title: 'Oferta Wygenerowana' },
  { status: 'CALCULATION_SENT', title: 'Wysłano Ofertę' },
  { status: 'SIGNED', title: 'Podpisany' },
  { status: 'TERMINATED', title: 'Umowa Rozwiązana' },
  { status: 'RESIGNED', title: 'Rezygnacja' },
]

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

  let list = clients.map((client) => {
    const owner = userList.find((user) => user.id === client.ownerId)
    const opiekunDisplay = owner ? `${owner.name} (${owner.hierarchicalId || 'Brak ID'})` : 'Nieprzypisany'
    return { ...client, opiekunDisplay }
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

const kanbanData = computed(() =>
  kanbanStages.map((stage) => ({
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
    OFFER_PREPARING: 'Przygotowanie oferty',
    OFFER_GENERATED: 'Oferta wygenerowana',
    CALCULATION_SENT: 'Wysłano ofertę',
    SPECIAL_OFFER: 'Oferta specjalna',
    SIGNED: 'Podpisany',
    TERMINATED: 'Umowa rozwiązana',
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

const goToPage = async (page: number) => {
  if (!auth.enabled) return
  if (page < 1 || page > clientPagination.value.lastPage) return
  await clientStore.fetchClients({ page })
}

const setViewMode = (mode: 'list' | 'kanban') => {
  viewMode.value = mode
}

const selectClient = (client: Client) => {
  selectedClient.value = client
  activePanelTab.value = 'details'
  if (auth.enabled) {
    void fetchClientConsents(client.id)
    void fetchClientContacts(client.id)
    void fetchClientCalculations(client.id)
  }
}

const closePanel = () => {
  selectedClient.value = null
}

const getClientSlaStatus = (client: Client) => {
  if (!['NEW', 'IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT'].includes(client.status)) return 'OK'
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
    }))
  } catch (error: any) {
    calculationsError.value = error?.response?.data?.message || error?.message || 'Nie udało się pobrać kalkulacji.'
  } finally {
    calculationsLoading.value = false
  }
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
      mode: 'continue',
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
  toast.success('Dodano notatkę.')
  activityDescription.value = ''
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
  if (event.dataTransfer && !isReadOnly.value) {
    event.dataTransfer.effectAllowed = 'move'
    draggedClientId.value = client.id
  }
}

const onDragOver = (event: DragEvent) => {
  event.preventDefault()
}

const onDrop = (event: DragEvent, newStatus: Client['status']) => {
  event.preventDefault()
  const clientId = draggedClientId.value
  if (!clientId) return
  const client = (Array.isArray(clients.value) ? clients.value : []).find((c) => c.id === clientId)
  if (!client || client.status === newStatus) {
    draggedClientId.value = null
    return
  }

  if (client.status === 'SIGNED') {
    toast.warning('Zmiana statusu podpisanego klienta wymaga weryfikacji.')
  } else if (newStatus === 'SIGNED' && ['OFFER_GENERATED', 'CALCULATION_SENT'].includes(client.status)) {
    router.push(`/app/contract-preview/${clientId}`)
  } else if (['RESIGNED', 'TERMINATED'].includes(client.status)) {
    toast.warning(`Nie można przenieść klienta ze statusu '${client.status}'.`)
  } else {
    clientStore.updateClient(clientId, { status: newStatus })
    toast.success(`Przeniesiono '${client.name}' do etapu: ${newStatus}`)
  }

  draggedClientId.value = null
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
  <div class="flex flex-col h-[calc(100vh-112px)]">
    <div class="bg-gray-50 border-b border-gray-200 p-2 flex items-center space-x-2 shadow-sm flex-shrink-0">
      <button type="button" class="flex items-center px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 rounded border border-gray-300 bg-white" @click="router.push('/app/sales/start')">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Nowy</span>
      </button>
      <button type="button" class="flex items-center px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 rounded border border-gray-300 bg-white" @click="exportToCsv">
        <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span>Eksportuj</span>
      </button>
      <div class="h-5 w-px bg-gray-300 mx-2"></div>
      <div class="flex items-center bg-gray-200 rounded p-0.5">
        <button type="button" class="px-2 py-1 rounded text-sm flex items-center" :class="viewMode === 'list' ? 'bg-white shadow-sm' : 'text-gray-500'" @click="setViewMode('list')">
          <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
          Lista
        </button>
        <button type="button" class="px-2 py-1 rounded text-sm flex items-center" :class="viewMode === 'kanban' ? 'bg-white shadow-sm' : 'text-gray-500'" @click="setViewMode('kanban')">
          <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
          Kanban
        </button>
      </div>

      <div class="flex-1 max-w-xs relative ml-4">
        <input v-model="filterText" type="text" placeholder="Filtruj listę..." class="w-full border-gray-300 rounded text-sm pl-8 py-1.5 focus:ring-brand-main focus:border-brand-main bg-white text-gray-900" />
        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
      </div>
      <div class="flex-1"></div>
      <span class="text-xs text-gray-500 px-4">Liczba: {{ displayedClients.length }}</span>
    </div>

    <div v-if="viewMode === 'list'" class="flex-1 relative overflow-hidden bg-white">
      <div class="h-full overflow-y-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 sticky top-0 z-10">
            <tr>
              <th class="px-4 py-2 w-10"></th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer" @click="sort('name')">Nazwa Klienta</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer" @click="sort('opiekunDisplay')">Opiekun</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer" @click="sort('status')">Status</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer" @click="sort('lastActionDate')">Ostatnia Aktywność</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer" @click="sort('city')">Miasto</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rezerwacja</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            <tr
              v-for="client in displayedClients"
              :key="client.id"
              :id="`client-${client.id}`"
              class="hover:bg-sky-50 cursor-pointer transition-colors"
              :class="selectedClient?.id === client.id ? 'bg-sky-100' : ''"
              @click="selectClient(client)"
            >
              <td class="px-4 py-2 text-center">
                <input type="checkbox" class="h-4 w-4 text-brand-main border-gray-300 rounded focus:ring-brand-main" @click.stop />
              </td>
              <td class="px-4 py-2 whitespace-nowrap relative">
                <div>
                  <div class="text-sm font-semibold text-brand-main truncate max-w-[260px]" :title="client.name">{{ client.name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ client.nip }}</div>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="text-sm text-gray-800">{{ client.opiekunDisplay }}</div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': client.status === 'IN_TALKS',
                    'bg-yellow-100 text-yellow-800': client.status === 'NEW',
                    'bg-amber-100 text-amber-800': client.status === 'OFFER_PREPARING',
                    'bg-indigo-100 text-indigo-800': client.status === 'OFFER_GENERATED',
                    'bg-blue-100 text-blue-800': client.status === 'CALCULATION_SENT',
                    'bg-red-100 text-red-800': client.status === 'RESIGNED',
                    'bg-emerald-100 text-emerald-800': client.status === 'SIGNED',
                    'bg-purple-100 text-purple-800': client.status === 'SPECIAL_OFFER',
                    'bg-gray-200 text-gray-800': client.status === 'TERMINATED',
                  }"
                >
                  {{ statusLabel(client.status) }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">
                <div class="flex items-center">
                  <span v-if="getClientSlaStatus(client) === 'CRITICAL'" class="w-2.5 h-2.5 rounded-full bg-red-500 mr-2 flex-shrink-0" title="Brak kontaktu od ponad 3 dni!"></span>
                  <span>{{ new Date(client.lastActionDate).toLocaleDateString() }}</span>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">{{ client.city }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-600">
                <span v-if="getRemainingReservationDays(client) !== null && ['IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT'].includes(client.status) && (getRemainingReservationDays(client) || 0) > 0" class="inline-flex flex-col items-start rounded bg-sky-100 text-sky-700 font-semibold px-2 py-1 leading-tight">
                  <span>rezerwacja do</span>
                  <span>{{ formatReservationDate(client) }}</span>
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
            <tr v-if="displayedClients.length === 0">
              <td colspan="8" class="p-8 text-center text-gray-500 text-sm">Brak rekordów spełniających kryteria.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="auth.enabled && clientPagination.total > 0" class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-600">
        <div>
          Strona {{ clientPagination.currentPage }} z {{ clientPagination.lastPage }}
          <span class="ml-2 text-gray-400">({{ clientPagination.total }} rekordów)</span>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="clientPagination.currentPage <= 1"
            @click="goToPage(clientPagination.currentPage - 1)"
          >
            Poprzednia
          </button>
          <button
            type="button"
            class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="clientPagination.currentPage >= clientPagination.lastPage"
            @click="goToPage(clientPagination.currentPage + 1)"
          >
            Następna
          </button>
        </div>
      </div>
    </div>

    <div v-if="viewMode === 'kanban'" class="flex-1 overflow-x-auto p-4 bg-gray-100">
      <div class="flex space-x-4 h-full">
        <div v-for="stage in kanbanData" :key="stage.status" class="w-80 bg-gray-50 rounded-lg shadow-sm border border-gray-200 flex flex-col flex-shrink-0">
          <div class="p-3 border-b border-gray-200">
            <h3 class="font-semibold text-sm text-gray-700">{{ stage.title }} <span class="text-xs text-gray-400 font-normal">({{ stage.clients.length }})</span></h3>
          </div>
          <div class="flex-1 p-2 overflow-y-auto space-y-2" @dragover="onDragOver" @drop="onDrop($event, stage.status)">
            <div
              v-for="client in stage.clients"
              :key="client.id"
              draggable="true"
              class="bg-white p-3 rounded border border-gray-200 shadow-sm cursor-move hover:border-brand-main relative"
              @dragstart="onDragStart($event, client)"
            >
              <div v-if="getRemainingReservationDays(client) && ['IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT'].includes(client.status)" class="absolute top-2 right-2 text-[10px] bg-sky-600 text-white font-bold rounded px-1.5 py-0.5 shadow leading-tight text-right">
                <span class="block">rezerwacja do</span>
                <span class="block">{{ formatReservationDate(client) }}</span>
              </div>
              <div class="flex justify-between items-start">
                <p class="font-bold text-sm text-brand-main pr-2 break-words">{{ client.name }}</p>
                <span v-if="getClientSlaStatus(client) === 'CRITICAL'" class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0 mt-1" title="Brak kontaktu od ponad 3 dni!"></span>
              </div>
              <p class="text-[11px] text-gray-400 font-mono mt-1">{{ client.nip }}</p>
              <div class="mt-2 pt-2 border-t border-gray-100">
                <p class="text-[10px] text-gray-400">Opiekun:</p>
                <p class="text-xs text-gray-600 font-medium">{{ client.opiekunDisplay }}</p>
              </div>
              <div class="text-xs text-gray-400 mt-3 flex justify-between items-center">
                <span>{{ client.city }}</span>
                <button type="button" class="p-1 hover:bg-gray-100 rounded" title="Pokaż szczegóły" @click.stop="selectClient(client)">
                  <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
              </div>
            </div>
            <div v-if="stage.clients.length === 0" class="h-full border-2 border-dashed border-gray-200 rounded-md flex items-center justify-center text-xs text-gray-400 p-4">
              Przeciągnij klienta tutaj
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedClient" class="fixed inset-0 z-40">
      <div class="absolute inset-0 bg-black/30" @click="closePanel"></div>
      <div class="absolute top-0 right-0 h-full w-full max-w-2xl bg-gray-50 z-50 shadow-2xl flex flex-col animate-slide-in-right">
        <div class="p-4 bg-white border-b border-gray-200 flex-shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-lg font-bold text-gray-900">{{ selectedClient.name }}</h3>
              <p class="text-xs text-gray-500">NIP: {{ selectedClient.nip }}</p>
            </div>
            <button type="button" class="p-2 text-gray-400 hover:bg-gray-100 rounded-full" @click="closePanel">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div class="flex items-center space-x-2 mt-4">
            <div class="flex items-center space-x-2">
              <label class="text-xs font-bold text-gray-500">STATUS:</label>
            <select :value="selectedClient.status" class="py-1 text-sm rounded border-gray-300 focus:ring-brand-main focus:border-brand-main bg-white" :disabled="isReadOnly" @change="changeStatus($event, selectedClient.id)">
                <option value="NEW">Nowy</option>
                <option value="IN_TALKS">W rozmowach</option>
                <option value="OFFER_PREPARING">Przygotowanie oferty</option>
                <option value="OFFER_GENERATED">Oferta wygenerowana</option>
                <option value="CALCULATION_SENT">Wysłano ofertę</option>
                <option value="SIGNED">Podpisany</option>
                <option value="TERMINATED">Umowa rozwiązana</option>
                <option value="RESIGNED">Rezygnacja</option>
              </select>
            </div>
            <div class="flex-1"></div>
            <button type="button" class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs hover:bg-gray-100" @click="continueProcess(selectedClient)">
              Kontynuuj proces
            </button>
            <button
              v-if="selectedClient.meetingStatus === 'open'"
              type="button"
              class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs hover:bg-gray-100"
              @click="openRescheduleModal(selectedClient)"
            >
              Wstrzymaj / przełóż
            </button>
            <button type="button" class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs hover:bg-gray-100" @click="generateContract(selectedClient.id)">
              Generuj Umowę
            </button>
          </div>
        </div>

        <div class="border-b border-gray-200 bg-white flex-shrink-0">
          <nav class="flex space-x-4 px-4">
            <button type="button" class="px-1 py-3 text-sm font-medium" :class="activePanelTab === 'details' ? 'border-b-2 border-brand-main text-brand-main' : 'text-gray-500'" @click="activePanelTab = 'details'">Szczegóły</button>
            <button type="button" class="px-1 py-3 text-sm font-medium" :class="activePanelTab === 'contacts' ? 'border-b-2 border-brand-main text-brand-main' : 'text-gray-500'" @click="activePanelTab = 'contacts'">Kontakt</button>
            <button type="button" class="px-1 py-3 text-sm font-medium" :class="activePanelTab === 'activity' ? 'border-b-2 border-brand-main text-brand-main' : 'text-gray-500'" @click="activePanelTab = 'activity'">Aktywności</button>
            <button type="button" class="px-1 py-3 text-sm font-medium" :class="activePanelTab === 'finance' ? 'border-b-2 border-brand-main text-brand-main' : 'text-gray-500'" @click="activePanelTab = 'finance'">Finanse</button>
            <button type="button" class="px-1 py-3 text-sm font-medium" :class="activePanelTab === 'offers' ? 'border-b-2 border-brand-main text-brand-main' : 'text-gray-500'" @click="activePanelTab = 'offers'">Oferty</button>
          </nav>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="activePanelTab === 'details'" class="space-y-6">
            <div class="bg-white p-4 border border-gray-200 rounded">
              <h4 class="font-bold text-gray-700 uppercase text-xs mb-3">Dane Firmy</h4>
              <dl class="grid grid-cols-3 gap-4 text-sm">
                <div class="col-span-2">
                  <dt class="text-gray-500">Nazwa</dt>
                  <dd class="text-gray-900 font-medium">{{ selectedClient.name }}</dd>
                </div>
                <div class="col-span-1">
                  <dt class="text-gray-500">NIP</dt>
                  <dd class="text-gray-900 font-medium font-mono">{{ selectedClient.nip }}</dd>
                </div>
                <div class="col-span-2">
                  <dt class="text-gray-500">Adres</dt>
                  <dd class="text-gray-900 font-medium">
                    {{ [selectedClient.street, selectedClient.buildingNr].filter(Boolean).join(' ') || '—' }}
                  </dd>
                </div>
                <div class="col-span-1">
                  <dt class="text-gray-500">Miasto</dt>
                  <dd class="text-gray-900 font-medium">
                    {{ [selectedClient.zip, selectedClient.city].filter(Boolean).join(' ') || '—' }}
                  </dd>
                </div>
              </dl>
            </div>
            <div class="bg-white p-4 border border-gray-200 rounded">
              <h4 class="font-bold text-gray-700 uppercase text-xs mb-3">Zgody</h4>
              <div v-if="consentsLoading" class="text-sm text-gray-400">Ładowanie zgód...</div>
              <div v-else-if="!consentCatalog.length" class="text-sm text-gray-400">Brak zdefiniowanych zgód.</div>
              <div v-else class="space-y-3">
                <label v-for="consent in consentCatalog" :key="consent.id" class="flex items-start gap-3 border border-gray-200 rounded-lg p-3 hover:border-brand-main/30">
                  <input
                    type="checkbox"
                    class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-main focus:ring-brand-main"
                    :checked="isConsentAccepted(consent.id)"
                    :disabled="isReadOnly"
                    @change="toggleClientConsent(consent.id, ($event.target as HTMLInputElement).checked)"
                  />
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-gray-800">{{ consent.title }}</span>
                      <span v-if="consent.required" class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Wymagana</span>
                      <span v-if="needsConsentUpdate(consent.id)" class="text-[10px] font-bold text-rose-600 uppercase tracking-wider">Wymaga aktualizacji</span>
                    </div>
                    <p v-if="consent.description" class="text-xs text-gray-500">{{ consent.description }}</p>
                    <div v-if="consent.file_url" class="mt-2 flex items-center gap-3 text-xs">
                      <button type="button" class="text-sky-600 hover:underline" @click.stop="openConsentFile(consent, false)">Podgląd</button>
                      <button type="button" class="text-sky-600 hover:underline" @click.stop="openConsentFile(consent, true)">Pobierz</button>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-400 flex flex-wrap gap-x-4 gap-y-1">
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
            <div class="bg-white p-4 border border-gray-200 rounded">
              <div class="flex items-center justify-between mb-3">
                <h4 class="font-bold text-gray-700 uppercase text-xs">Osoby Kontaktowe</h4>
                <span class="text-xs text-gray-400">Liczba: {{ filteredContacts.length }}</span>
              </div>
              <div class="mb-4">
                <input
                  v-model="contactSearch"
                  type="text"
                  placeholder="Szukaj kontaktu..."
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none"
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
                    <div class="text-xs text-gray-400">Status</div>
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
  </div>
</template>
