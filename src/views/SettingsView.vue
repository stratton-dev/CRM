<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { api } from '@/api/client'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import { useCalculatorStore } from '@/components/calculator/store/useCalculatorStore'
import { DEFAULT_CONFIG } from '@/components/calculator/tax-engine/constants'
import type { UserRole } from '@/types/models'

const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
const token = localStorage.getItem('crm_token')
const auth = useAuthStore()
const toast = useToastStore()
const viewPermissions = useViewPermissionsStore()

const modeLabel = computed(() => auth.enabled ? 'Keycloak (PROD)' : 'DEV (auth wyłączony)')
const currentUser = computed(() => auth.user || null)
const kcUrl = import.meta.env.VITE_KEYCLOAK_URL || ''
const kcRealm = import.meta.env.VITE_KEYCLOAK_REALM || ''
const kcClientId = import.meta.env.VITE_KEYCLOAK_CLIENT_ID || ''
const appOrigin = window.location.origin
const activeTab = ref<'backend' | 'auth' | 'consents' | 'crm-permissions' | 'calculator' | 'statuses' | 'broadcasts'>('backend')
const isSuperAdmin = computed(() => (auth.user?.roles || []).includes('ADMIN'))
const calculatorStore = useCalculatorStore()
const calculatorSaving = ref(false)
const calculatorError = ref<string | null>(null)
const calculationStatuses = ref<Array<{ key: string; label: string }>>([])
const calculationStatusesLoading = ref(false)
const calculationStatusesError = ref<string | null>(null)
const newCalculationStatus = ref({ key: '', label: '' })

type ConsentItem = {
  id: number | string
  code: string
  title: string
  description: string
  required: boolean
}

const consents = ref<ConsentItem[]>([])
const consentsLoading = ref(false)
const consentsError = ref<string | null>(null)
const newConsent = ref({
  code: '',
  title: '',
  description: '',
  required: true,
})
const editingConsentId = ref<string | null>(null)
const editingConsent = ref({
  code: '',
  title: '',
  description: '',
  required: true,
})

const permissionsDraft = ref<Record<string, UserRole[]>>({})

type StatusItem = {
  id: number | string
  key: string
  label: string
  description?: string | null
  sort_order?: number | null
  active: boolean
  events?: Array<{ id: number | string; key: string; label: string }>
}

type EventItem = {
  id: number | string
  key: string
  label: string
  description?: string | null
  active: boolean
}

type BroadcastItem = {
  id: number | string
  name: string
  event_key: string
  description?: string | null
  enabled: boolean
  targets?: Array<{ id: number | string; target_type: 'ROLE' | 'TEAM'; target_value: string }>
}

const statuses = ref<StatusItem[]>([])
const events = ref<EventItem[]>([])
const broadcasts = ref<BroadcastItem[]>([])
const statusLoading = ref(false)
const eventLoading = ref(false)
const broadcastLoading = ref(false)
const teams = ref<string[]>([])
const teamsLoading = ref(false)

const newStatus = ref({
  key: '',
  label: '',
  description: '',
  sort_order: 0,
  active: true,
  eventIds: [] as Array<number | string>,
})
const editingStatusId = ref<string | null>(null)
const editingStatus = ref({
  key: '',
  label: '',
  description: '',
  sort_order: 0,
  active: true,
  eventIds: [] as Array<number | string>,
})

const newEvent = ref({
  key: '',
  label: '',
  description: '',
  active: true,
})
const editingEventId = ref<string | null>(null)
const editingEvent = ref({
  key: '',
  label: '',
  description: '',
  active: true,
})

const newBroadcast = ref({
  name: '',
  event_key: '',
  description: '',
  enabled: true,
  targets: [] as Array<{ target_type: 'ROLE' | 'TEAM'; target_value: string }>,
})
const editingBroadcastId = ref<string | null>(null)
const editingBroadcast = ref({
  name: '',
  event_key: '',
  description: '',
  enabled: true,
  targets: [] as Array<{ target_type: 'ROLE' | 'TEAM'; target_value: string }>,
})

const roles: UserRole[] = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR']

const updateCalculatorConfig = (path: string, value: number) => {
  const newConfig = JSON.parse(JSON.stringify(calculatorStore.config))
  const keys = path.split('.')
  let current = newConfig
  for (let i = 0; i < keys.length - 1; i++) current = current[keys[i]]
  current[keys[keys.length - 1]] = value
  calculatorStore.config = newConfig
}

const saveCalculatorConfig = async () => {
  if (!auth.enabled) {
    toast.warning('Tryb API jest wyłączony.')
    return
  }
  calculatorSaving.value = true
  calculatorError.value = null
  try {
    await calculatorStore.saveConfigToApi()
    toast.success('Zapisano globalną konfigurację kalkulatora.')
  } catch (error: any) {
    calculatorError.value = calculatorStore.configError || error?.message || 'Nie udało się zapisać konfiguracji.'
    toast.error(calculatorError.value || 'Nie udało się zapisać konfiguracji.')
  } finally {
    calculatorSaving.value = false
  }
}

const fetchCalculationStatuses = async () => {
  if (!auth.enabled) return
  calculationStatusesLoading.value = true
  calculationStatusesError.value = null
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
    calculationStatuses.value = statuses.map((item: any) => ({
      key: String(item.key || ''),
      label: String(item.label || item.key || ''),
    }))
  } catch (error: any) {
    calculationStatusesError.value = error?.response?.data?.message || error?.message || 'Nie udało się pobrać statusów.'
  } finally {
    calculationStatusesLoading.value = false
  }
}

const saveCalculationStatuses = async () => {
  if (!auth.enabled) return
  calculationStatusesLoading.value = true
  calculationStatusesError.value = null
  try {
    await api.post('/v1/calculator-configs', {
      scope: 'global',
      key: 'crm_calculation_statuses',
      value_json: calculationStatuses.value,
      is_active: true,
    })
    toast.success('Zapisano statusy kalkulacji.')
  } catch (error: any) {
    calculationStatusesError.value = error?.response?.data?.message || error?.message || 'Nie udało się zapisać statusów.'
    toast.error(calculationStatusesError.value || 'Nie udało się zapisać statusów.')
  } finally {
    calculationStatusesLoading.value = false
  }
}

const addCalculationStatus = () => {
  const key = newCalculationStatus.value.key.trim().toUpperCase()
  const label = newCalculationStatus.value.label.trim()
  if (!key || !label) {
    toast.warning('Uzupełnij kod i nazwę statusu.')
    return
  }
  if (calculationStatuses.value.some((item) => item.key === key)) {
    toast.warning('Status o takim kodzie już istnieje.')
    return
  }
  calculationStatuses.value = [...calculationStatuses.value, { key, label }]
  newCalculationStatus.value = { key: '', label: '' }
}

const removeCalculationStatus = (key: string) => {
  calculationStatuses.value = calculationStatuses.value.filter((item) => item.key !== key)
}

const resetPermissionsDraft = () => {
  const resolved = viewPermissions.resolvedPermissions
  const next: Record<string, UserRole[]> = {}
  viewPermissions.viewOptions.forEach((view) => {
    next[view.key] = [...(resolved[view.key] || [])]
  })
  permissionsDraft.value = next
}

const toggleViewRole = (viewKey: string, role: UserRole) => {
  const current = permissionsDraft.value[viewKey] || []
  if (current.includes(role)) {
    permissionsDraft.value[viewKey] = current.filter((item) => item !== role)
  } else {
    permissionsDraft.value[viewKey] = [...current, role]
  }
}

const saveCrmPermissions = async () => {
  if (!auth.enabled) return
  try {
    const payload = viewPermissions.viewOptions.map((view) => ({
      view_key: view.key,
      roles: permissionsDraft.value[view.key] || [],
    }))
    await viewPermissions.savePermissions(payload)
    toast.success('Zapisano uprawnienia widoków.')
  } catch (error: any) {
    const message = viewPermissions.error || error?.message || 'Nie udało się zapisać uprawnień.'
    toast.error(message)
  }
}

const fetchConsents = async () => {
  if (!auth.enabled) return
  consentsLoading.value = true
  consentsError.value = null
  try {
    const { data } = await api.get('/v1/consents', { params: { per_page: 200 } })
    consents.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  } catch (error: any) {
    consentsError.value = error?.response?.data?.message || error?.message || 'Nie udało się pobrać zgód.'
  } finally {
    consentsLoading.value = false
  }
}

const createConsent = async () => {
  if (!auth.enabled) return
  if (!newConsent.value.code || !newConsent.value.title || !newConsent.value.description) {
    toast.warning('Uzupełnij kod, tytuł i opis zgody.')
    return
  }
  try {
    const payload = {
      code: newConsent.value.code.trim(),
      title: newConsent.value.title.trim(),
      description: newConsent.value.description.trim(),
      required: newConsent.value.required,
    }
    const { data } = await api.post('/v1/consents', payload)
    consents.value = [data, ...consents.value]
    newConsent.value = { code: '', title: '', description: '', required: true }
    toast.success('Dodano zgodę.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się dodać zgody.'
    toast.error(message)
  }
}

const startEditConsent = (consent: ConsentItem) => {
  editingConsentId.value = String(consent.id)
  editingConsent.value = {
    code: consent.code,
    title: consent.title,
    description: consent.description,
    required: consent.required,
  }
}

const cancelEditConsent = () => {
  editingConsentId.value = null
}

const saveConsent = async () => {
  if (!auth.enabled || !editingConsentId.value) return
  if (!editingConsent.value.code || !editingConsent.value.title || !editingConsent.value.description) {
    toast.warning('Uzupełnij kod, tytuł i opis zgody.')
    return
  }
  try {
    const payload = {
      code: editingConsent.value.code.trim(),
      title: editingConsent.value.title.trim(),
      description: editingConsent.value.description.trim(),
      required: editingConsent.value.required,
    }
    const { data } = await api.patch(`/v1/consents/${editingConsentId.value}`, payload)
    consents.value = consents.value.map((item) => (String(item.id) === editingConsentId.value ? data : item))
    editingConsentId.value = null
    toast.success('Zapisano zgodę.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać zgody.'
    toast.error(message)
  }
}

const deleteConsent = async (consentId: string) => {
  if (!auth.enabled) return
  if (!window.confirm('Usunąć tę zgodę?')) return
  try {
    await api.delete(`/v1/consents/${consentId}`)
    consents.value = consents.value.filter((item) => String(item.id) !== consentId)
    if (editingConsentId.value === consentId) editingConsentId.value = null
    toast.success('Usunięto zgodę.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się usunąć zgody.'
    toast.error(message)
  }
}

const fetchStatuses = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  statusLoading.value = true
  try {
    const { data } = await api.get('/v1/crm-statuses')
    statuses.value = Array.isArray(data) ? data : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać statusów.'
    toast.error(message)
  } finally {
    statusLoading.value = false
  }
}

const fetchEvents = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  eventLoading.value = true
  try {
    const { data } = await api.get('/v1/crm-events')
    events.value = Array.isArray(data) ? data : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać eventów.'
    toast.error(message)
  } finally {
    eventLoading.value = false
  }
}

const fetchBroadcasts = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  broadcastLoading.value = true
  try {
    const { data } = await api.get('/v1/crm-broadcasts')
    broadcasts.value = Array.isArray(data) ? data : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać broadcastów.'
    toast.error(message)
  } finally {
    broadcastLoading.value = false
  }
}

const fetchTeams = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  teamsLoading.value = true
  try {
    const { data } = await api.get('/v1/admin/keycloak/teams')
    const paths = Array.isArray(data?.paths) ? data.paths : []
    teams.value = paths
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać zespołów.'
    toast.error(message)
  } finally {
    teamsLoading.value = false
  }
}

const createStatus = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  if (!newStatus.value.key || !newStatus.value.label) {
    toast.warning('Uzupełnij klucz i nazwę statusu.')
    return
  }
  try {
    const payload = {
      key: newStatus.value.key.trim(),
      label: newStatus.value.label.trim(),
      description: newStatus.value.description.trim() || undefined,
      sort_order: newStatus.value.sort_order,
      active: newStatus.value.active,
      event_ids: newStatus.value.eventIds.map(Number),
    }
    const { data } = await api.post('/v1/crm-statuses', payload)
    statuses.value = [data, ...statuses.value]
    newStatus.value = { key: '', label: '', description: '', sort_order: 0, active: true, eventIds: [] }
    toast.success('Dodano status.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się dodać statusu.'
    toast.error(message)
  }
}

const startEditStatus = (status: StatusItem) => {
  editingStatusId.value = String(status.id)
  editingStatus.value = {
    key: status.key,
    label: status.label,
    description: status.description || '',
    sort_order: status.sort_order || 0,
    active: status.active,
    eventIds: (status.events || []).map((e) => e.id),
  }
}

const cancelEditStatus = () => {
  editingStatusId.value = null
}

const saveStatus = async () => {
  if (!auth.enabled || !isSuperAdmin.value || !editingStatusId.value) return
  if (!editingStatus.value.key || !editingStatus.value.label) {
    toast.warning('Uzupełnij klucz i nazwę statusu.')
    return
  }
  try {
    const payload = {
      key: editingStatus.value.key.trim(),
      label: editingStatus.value.label.trim(),
      description: editingStatus.value.description.trim() || undefined,
      sort_order: editingStatus.value.sort_order,
      active: editingStatus.value.active,
      event_ids: editingStatus.value.eventIds.map(Number),
    }
    const { data } = await api.patch(`/v1/crm-statuses/${editingStatusId.value}`, payload)
    statuses.value = statuses.value.map((item) => (String(item.id) === editingStatusId.value ? data : item))
    editingStatusId.value = null
    toast.success('Zapisano status.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać statusu.'
    toast.error(message)
  }
}

const deleteStatus = async (statusId: string) => {
  if (!auth.enabled || !isSuperAdmin.value) return
  if (!window.confirm('Usunąć ten status?')) return
  try {
    await api.delete(`/v1/crm-statuses/${statusId}`)
    statuses.value = statuses.value.filter((item) => String(item.id) !== statusId)
    if (editingStatusId.value === statusId) editingStatusId.value = null
    toast.success('Usunięto status.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się usunąć statusu.'
    toast.error(message)
  }
}

const createEvent = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  if (!newEvent.value.key || !newEvent.value.label) {
    toast.warning('Uzupełnij klucz i nazwę eventu.')
    return
  }
  try {
    const payload = {
      key: newEvent.value.key.trim(),
      label: newEvent.value.label.trim(),
      description: newEvent.value.description.trim() || undefined,
      active: newEvent.value.active,
    }
    const { data } = await api.post('/v1/crm-events', payload)
    events.value = [data, ...events.value]
    newEvent.value = { key: '', label: '', description: '', active: true }
    toast.success('Dodano event.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się dodać eventu.'
    toast.error(message)
  }
}

const startEditEvent = (event: EventItem) => {
  editingEventId.value = String(event.id)
  editingEvent.value = {
    key: event.key,
    label: event.label,
    description: event.description || '',
    active: event.active,
  }
}

const cancelEditEvent = () => {
  editingEventId.value = null
}

const saveEvent = async () => {
  if (!auth.enabled || !isSuperAdmin.value || !editingEventId.value) return
  if (!editingEvent.value.key || !editingEvent.value.label) {
    toast.warning('Uzupełnij klucz i nazwę eventu.')
    return
  }
  try {
    const payload = {
      key: editingEvent.value.key.trim(),
      label: editingEvent.value.label.trim(),
      description: editingEvent.value.description.trim() || undefined,
      active: editingEvent.value.active,
    }
    const { data } = await api.patch(`/v1/crm-events/${editingEventId.value}`, payload)
    events.value = events.value.map((item) => (String(item.id) === editingEventId.value ? data : item))
    editingEventId.value = null
    toast.success('Zapisano event.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać eventu.'
    toast.error(message)
  }
}

const deleteEvent = async (eventId: string) => {
  if (!auth.enabled || !isSuperAdmin.value) return
  if (!window.confirm('Usunąć ten event?')) return
  try {
    await api.delete(`/v1/crm-events/${eventId}`)
    events.value = events.value.filter((item) => String(item.id) !== eventId)
    if (editingEventId.value === eventId) editingEventId.value = null
    toast.success('Usunięto event.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się usunąć eventu.'
    toast.error(message)
  }
}

const addBroadcastTarget = (target: { target_type: 'ROLE' | 'TEAM'; target_value: string }) => {
  if (editingBroadcastId.value) {
    editingBroadcast.value.targets = [...editingBroadcast.value.targets, target]
    return
  }
  newBroadcast.value.targets = [...newBroadcast.value.targets, target]
}

const removeBroadcastTarget = (index: number) => {
  if (editingBroadcastId.value) {
    editingBroadcast.value.targets = editingBroadcast.value.targets.filter((_, idx) => idx !== index)
    return
  }
  newBroadcast.value.targets = newBroadcast.value.targets.filter((_, idx) => idx !== index)
}

const createBroadcast = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  if (!newBroadcast.value.name || !newBroadcast.value.event_key) {
    toast.warning('Uzupełnij nazwę i event key.')
    return
  }
  try {
    const payload = {
      name: newBroadcast.value.name.trim(),
      event_key: newBroadcast.value.event_key.trim(),
      description: newBroadcast.value.description.trim() || undefined,
      enabled: newBroadcast.value.enabled,
      targets: newBroadcast.value.targets,
    }
    const { data } = await api.post('/v1/crm-broadcasts', payload)
    broadcasts.value = [data, ...broadcasts.value]
    newBroadcast.value = { name: '', event_key: '', description: '', enabled: true, targets: [] }
    toast.success('Dodano broadcast.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się dodać broadcastu.'
    toast.error(message)
  }
}

const startEditBroadcast = (broadcast: BroadcastItem) => {
  editingBroadcastId.value = String(broadcast.id)
  editingBroadcast.value = {
    name: broadcast.name,
    event_key: broadcast.event_key,
    description: broadcast.description || '',
    enabled: broadcast.enabled,
    targets: (broadcast.targets || []).map((t) => ({ target_type: t.target_type, target_value: t.target_value })),
  }
}

const cancelEditBroadcast = () => {
  editingBroadcastId.value = null
}

const saveBroadcast = async () => {
  if (!auth.enabled || !isSuperAdmin.value || !editingBroadcastId.value) return
  if (!editingBroadcast.value.name || !editingBroadcast.value.event_key) {
    toast.warning('Uzupełnij nazwę i event key.')
    return
  }
  try {
    const payload = {
      name: editingBroadcast.value.name.trim(),
      event_key: editingBroadcast.value.event_key.trim(),
      description: editingBroadcast.value.description.trim() || undefined,
      enabled: editingBroadcast.value.enabled,
      targets: editingBroadcast.value.targets,
    }
    const { data } = await api.patch(`/v1/crm-broadcasts/${editingBroadcastId.value}`, payload)
    broadcasts.value = broadcasts.value.map((item) => (String(item.id) === editingBroadcastId.value ? data : item))
    editingBroadcastId.value = null
    toast.success('Zapisano broadcast.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać broadcastu.'
    toast.error(message)
  }
}

const deleteBroadcast = async (broadcastId: string) => {
  if (!auth.enabled || !isSuperAdmin.value) return
  if (!window.confirm('Usunąć ten broadcast?')) return
  try {
    await api.delete(`/v1/crm-broadcasts/${broadcastId}`)
    broadcasts.value = broadcasts.value.filter((item) => String(item.id) !== broadcastId)
    if (editingBroadcastId.value === broadcastId) editingBroadcastId.value = null
    toast.success('Usunięto broadcast.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się usunąć broadcastu.'
    toast.error(message)
  }
}

onMounted(() => {
  fetchConsents()
  viewPermissions.fetchPermissions().then(() => {
    resetPermissionsDraft()
  })
  fetchEvents()
  fetchStatuses()
  fetchBroadcasts()
  fetchTeams()
  fetchCalculationStatuses()
})

watch(
  () => viewPermissions.loaded,
  (loaded) => {
    if (loaded) resetPermissionsDraft()
  }
)
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-2xl font-semibold textstratton700">Ustawienia</h2>

    <div class="bg-white rounded shadow p-4">
      <div class="flex items-center gap-4 border-b border-gray-200 pb-3">
        <button
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'backend' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'backend'"
        >
          Backend API
        </button>
        <button
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'auth' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'auth'"
        >
          Autoryzacja
        </button>
        <button
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'consents' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'consents'"
        >
          Zgody
        </button>
        <button
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'crm-permissions' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'crm-permissions'"
        >
          Uprawnienia CRM
        </button>
        <button
          v-if="isSuperAdmin"
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'calculator' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'calculator'"
        >
          Kalkulator
        </button>
        <button
          v-if="isSuperAdmin"
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'statuses' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'statuses'"
        >
          Statusy
        </button>
        <button
          v-if="isSuperAdmin"
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === 'broadcasts' ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'broadcasts'"
        >
          Broadcasty
        </button>
      </div>

      <div v-if="activeTab === 'backend'" class="space-y-3 pt-4">
        <h3 class="font-semibold">Backend API</h3>
        <div class="text-sm text-gray-600">Base URL</div>
        <div class="font-mono">{{ apiBase }}</div>
        <p class="text-sm text-gray-500 mt-2">
          Configure via <code>.env</code> file in <code>crm</code> folder with <code>VITE_API_BASE_URL</code>.
        </p>
      </div>

      <div v-else-if="activeTab === 'auth'" class="space-y-3 pt-4">
        <h3 class="font-semibold">Auth</h3>
      <div>
        <div class="text-sm text-gray-600">Tryb</div>
        <span class="inline-block px-2 py-0.5 text-xs rounded border"
              :class="auth.enabled ? 'bg-green-50 border-green-300 text-green-800' : 'bg-yellow-50 border-yellow-300 text-yellow-800'">
          {{ modeLabel }}
        </span>
      </div>

      <div v-if="auth.enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <div class="text-sm text-gray-600">Keycloak URL</div>
          <div class="text-sm font-mono break-all">{{ kcUrl || '—' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">Realm</div>
          <div class="text-sm font-mono">{{ kcRealm || '—' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">Client ID</div>
          <div class="text-sm font-mono">{{ kcClientId || '—' }}</div>
        </div>
      </div>

      <div>
        <div class="text-sm text-gray-600">Token obecny</div>
        <div class="mb-1">{{ token ? 'Yes' : 'No' }}</div>
      </div>

      <div>
        <div class="text-sm text-gray-600">Aktualny użytkownik</div>
        <div>
          <template v-if="currentUser">
            <div class="text-sm">{{ currentUser.firstName || '' }} {{ currentUser.lastName || '' }}
              <span v-if="currentUser.email" class="text-gray-500"> — {{ currentUser.email }}</span>
            </div>
          </template>
          <template v-else>
            <div class="text-sm text-gray-500">Brak danych</div>
          </template>
        </div>
      </div>

      <div class="text-xs text-gray-500 border-t pt-3">
        Aby włączyć logowanie przez Keycloak ustaw w <code>.env</code>:
        <pre class="whitespace-pre-wrap mt-1">VITE_AUTH_ENABLED=true
VITE_KEYCLOAK_URL=https://keycloak.example.com
VITE_KEYCLOAK_REALM=your-realm
VITE_KEYCLOAK_CLIENT_ID=crm-frontend</pre>
        Upewnij się, że w kliencie Keycloak dozwolony jest redirect na Twój origin: {{ appOrigin }}
      </div>
      </div>

      <div v-else-if="activeTab === 'consents'" class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Zgody</h3>
          <button type="button" class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50" @click="fetchConsents">
            Odśwież
          </button>
        </div>
        <div v-if="!auth.enabled" class="text-sm text-gray-500">Tryb DEV: lista zgód jest dostępna tylko w trybie API.</div>
        <div v-else class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div>
              <label class="text-xs font-semibold text-gray-500">Kod</label>
              <input v-model="newConsent.code" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="np. gdpr" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Tytuł</label>
              <input v-model="newConsent.title" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Tytuł zgody" />
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold text-gray-500">Opis</label>
              <input v-model="newConsent.description" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Opis zgody" />
            </div>
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="newConsent.required" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                Wymagana
              </label>
              <button type="button" class="px-3 py-2 rounded bg-sky-600 text-white text-sm hover:bg-sky-700" @click="createConsent">
                Dodaj
              </button>
            </div>
          </div>

          <div class="border rounded">
            <div class="grid grid-cols-6 gap-2 px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50">
              <div>Kod</div>
              <div>Tytuł</div>
              <div class="col-span-2">Opis</div>
              <div>Wymagana</div>
              <div class="text-right">Akcje</div>
            </div>
            <div v-if="consentsLoading" class="px-3 py-3 text-sm text-gray-500">Ładowanie...</div>
            <div v-else-if="consentsError" class="px-3 py-3 text-sm text-red-600">{{ consentsError }}</div>
            <div v-else-if="consents.length === 0" class="px-3 py-3 text-sm text-gray-500">Brak zgód w bazie.</div>
            <div v-else class="divide-y">
              <div v-for="consent in consents" :key="consent.id" class="grid grid-cols-6 gap-2 px-3 py-2 text-sm items-center">
                <template v-if="editingConsentId === String(consent.id)">
                  <input v-model="editingConsent.code" type="text" class="border-gray-300 rounded text-xs font-mono" />
                  <input v-model="editingConsent.title" type="text" class="border-gray-300 rounded text-sm" />
                  <input v-model="editingConsent.description" type="text" class="border-gray-300 rounded text-sm col-span-2" />
                  <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input v-model="editingConsent.required" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                    Wymagana
                  </label>
                  <div class="flex items-center gap-2">
                    <button type="button" class="px-2 py-1 text-xs rounded bg-sky-600 text-white hover:bg-sky-700" @click="saveConsent">
                      Zapisz
                    </button>
                    <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="cancelEditConsent">
                      Anuluj
                    </button>
                  </div>
                </template>
                <template v-else>
                  <div class="font-mono text-xs">{{ consent.code }}</div>
                  <div class="font-semibold text-gray-800">{{ consent.title }}</div>
                  <div class="col-span-2">{{ consent.description }}</div>
                  <div>
                    <span :class="consent.required ? 'text-emerald-700' : 'text-gray-400'">
                      {{ consent.required ? 'TAK' : 'NIE' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-2 justify-end">
                    <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="startEditConsent(consent)">
                      Edytuj
                    </button>
                    <button type="button" class="px-2 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50" @click="deleteConsent(String(consent.id))">
                      Usuń
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'crm-permissions'" class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Uprawnienia widoków CRM</h3>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50"
              @click="resetPermissionsDraft"
            >
              Resetuj
            </button>
            <button
              type="button"
              class="text-xs px-3 py-1.5 rounded border border-sky-600 bg-sky-600 text-white hover:bg-sky-700"
              :disabled="!auth.enabled || viewPermissions.loading"
              @click="saveCrmPermissions"
            >
              Zapisz
            </button>
          </div>
        </div>

        <div v-if="!auth.enabled" class="text-sm text-gray-500">
          Tryb DEV: zarządzanie uprawnieniami dostępne tylko w trybie API.
        </div>

        <div v-else class="border border-gray-200 rounded-lg overflow-hidden">
          <div class="grid grid-cols-[minmax(140px,1fr)_repeat(5,120px)] bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
            <div class="px-3 py-2">Widok</div>
            <div v-for="role in viewPermissions.roles" :key="role" class="px-3 py-2 text-center">{{ role }}</div>
          </div>
          <div v-for="view in viewPermissions.viewOptions" :key="view.key" class="grid grid-cols-[minmax(140px,1fr)_repeat(5,120px)] border-t border-gray-200 text-sm">
            <div class="px-3 py-2 font-medium text-gray-700">{{ view.label }}</div>
            <label v-for="role in viewPermissions.roles" :key="`${view.key}-${role}`" class="flex items-center justify-center px-3 py-2">
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                :checked="(permissionsDraft[view.key] || []).includes(role)"
                @change="toggleViewRole(view.key, role)"
              />
            </label>
          </div>
        </div>

        <div v-if="viewPermissions.error" class="text-xs text-red-600">
          {{ viewPermissions.error }}
        </div>
      </div>

      <div v-else-if="activeTab === 'calculator' && isSuperAdmin" class="space-y-6 pt-4">
        <div>
          <h3 class="font-semibold">Parametry kalkulatora</h3>
          <p class="text-sm text-gray-500">Ustawienia zapisywane są globalnie w API i obowiązują wszystkich użytkowników.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="px-4 py-2 bg-slate-900 text-white rounded text-sm font-semibold hover:bg-slate-800 disabled:opacity-60"
            :disabled="!auth.enabled || calculatorSaving || calculatorStore.configLoading"
            @click="saveCalculatorConfig"
          >
            Zapisz konfigurację
          </button>
          <button
            type="button"
            class="px-4 py-2 border border-slate-200 rounded text-sm font-semibold text-slate-600 hover:border-slate-400"
            :disabled="!auth.enabled || calculatorStore.configLoading"
            @click="calculatorStore.fetchConfigFromApi()"
          >
            Odśwież z API
          </button>
          <span v-if="calculatorStore.configLoading" class="text-xs text-slate-500">Ładowanie konfiguracji...</span>
        </div>
        <div v-if="calculatorError || calculatorStore.configError" class="text-xs text-red-600">
          {{ calculatorError || calculatorStore.configError }}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Płaca minimalna (brutto)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.placaMinimalna.brutto"
              @input="updateCalculatorConfig('placaMinimalna.brutto', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Płaca minimalna (netto)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.placaMinimalna.netto"
              @input="updateCalculatorConfig('placaMinimalna.netto', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Minimalna UZ (netto)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.minimalnaKwotaUZ.zasadniczaNetto"
              @input="updateCalculatorConfig('minimalnaKwotaUZ.zasadniczaNetto', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">PIT próg 1 (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.prog1Stawka"
              @input="updateCalculatorConfig('pit.prog1Stawka', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">PIT próg 2 (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.prog2Stawka"
              @input="updateCalculatorConfig('pit.prog2Stawka', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">PIT próg 1 limit</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.prog1Limit"
              @input="updateCalculatorConfig('pit.prog1Limit', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div>
          <button type="button" class="text-xs font-bold text-slate-500" @click="calculatorStore.config = DEFAULT_CONFIG">
            Przywróć domyślne
          </button>
        </div>

        <div class="bg-white border border-gray-200 rounded p-4 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-bold text-gray-700 uppercase text-xs">Statusy kalkulacji</h4>
              <p class="text-xs text-gray-500">Definiują etapy przygotowania oferty w kalkulatorze.</p>
            </div>
            <button
              type="button"
              class="text-xs text-sky-600 hover:text-sky-700"
              :disabled="calculationStatusesLoading"
              @click="fetchCalculationStatuses"
            >
              Odśwież
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
              <label class="text-xs font-semibold text-gray-500">Kod</label>
              <input v-model="newCalculationStatus.key" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="PREPARING" />
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold text-gray-500">Nazwa</label>
              <input v-model="newCalculationStatus.label" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="W trakcie przygotowania" />
            </div>
            <div class="md:col-span-3 flex justify-end">
              <button type="button" class="px-3 py-2 rounded bg-sky-600 text-white text-sm hover:bg-sky-700" @click="addCalculationStatus">
                Dodaj status
              </button>
            </div>
          </div>

          <div v-if="calculationStatusesLoading" class="text-xs text-gray-500">Ładowanie statusów...</div>
          <div v-else-if="calculationStatusesError" class="text-xs text-red-600">{{ calculationStatusesError }}</div>
          <div v-else-if="calculationStatuses.length === 0" class="text-xs text-gray-400">Brak zdefiniowanych statusów.</div>
          <div v-else class="divide-y border border-gray-200 rounded">
            <div v-for="status in calculationStatuses" :key="status.key" class="flex items-center justify-between px-3 py-2 text-sm">
              <div>
                <div class="font-mono text-xs text-gray-500">{{ status.key }}</div>
                <div class="font-semibold text-gray-800">{{ status.label }}</div>
              </div>
              <button type="button" class="text-xs text-red-600 hover:text-red-700" @click="removeCalculationStatus(status.key)">
                Usuń
              </button>
            </div>
          </div>

          <div class="flex justify-end">
            <button
              type="button"
              class="px-4 py-2 bg-slate-900 text-white rounded text-sm font-semibold hover:bg-slate-800"
              :disabled="calculationStatusesLoading || !auth.enabled"
              @click="saveCalculationStatuses"
            >
              Zapisz statusy
            </button>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'statuses' && isSuperAdmin" class="space-y-6 pt-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Statusy CRM</h3>
          <button type="button" class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50" @click="fetchStatuses">
            Odśwież
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-slate-50 border border-slate-200 rounded p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800 mb-1">Czym są statusy</div>
            Status opisuje etap klienta/relacji (np. wygenerowana oferta). Statusy są widoczne w CRM i filtrach.
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800 mb-1">Czym są eventy</div>
            Event to zdarzenie w systemie (np. utworzenie spotkania). Eventy mogą wyzwalać broadcasty i logi.
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800 mb-1">Jak przypisać status do eventu</div>
            Wybierz eventy w sekcji „Eventy powiązane” podczas dodawania/edycji statusu.
          </div>
        </div>

        <div class="bg-white border border-gray-200 rounded p-4 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div>
              <label class="text-xs font-semibold text-gray-500">Key</label>
              <input v-model="newStatus.key" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="OFFER_GENERATED" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Nazwa</label>
              <input v-model="newStatus.label" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Oferta wygenerowana" />
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold text-gray-500">Opis</label>
              <input v-model="newStatus.description" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Opis statusu" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Kolejność</label>
              <input v-model.number="newStatus.sort_order" type="number" class="mt-1 w-full border-gray-300 rounded text-sm" />
            </div>
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="newStatus.active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                Aktywny
              </label>
              <button type="button" class="px-3 py-2 rounded bg-sky-600 text-white text-sm hover:bg-sky-700" @click="createStatus">
                Dodaj
              </button>
            </div>
          </div>

          <div>
            <label class="text-xs font-semibold text-gray-500">Eventy powiązane</label>
            <div class="mt-2 flex flex-wrap gap-2">
              <label v-for="event in events" :key="event.id" class="flex items-center gap-2 text-xs text-gray-600 border border-gray-200 rounded px-2 py-1">
                <input v-model="newStatus.eventIds" type="checkbox" :value="event.id" />
                {{ event.label }}
              </label>
            </div>
          </div>
        </div>

        <div class="border rounded">
          <div class="grid grid-cols-6 gap-2 px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50">
            <div>Key</div>
            <div>Nazwa</div>
            <div class="col-span-2">Opis</div>
            <div>Aktywny</div>
            <div class="text-right">Akcje</div>
          </div>
          <div v-if="statusLoading" class="px-3 py-3 text-sm text-gray-500">Ładowanie...</div>
          <div v-else-if="statuses.length === 0" class="px-3 py-3 text-sm text-gray-500">Brak statusów.</div>
          <div v-else class="divide-y">
            <div v-for="status in statuses" :key="status.id" class="grid grid-cols-6 gap-2 px-3 py-2 text-sm items-center">
              <template v-if="editingStatusId === String(status.id)">
                <input v-model="editingStatus.key" type="text" class="border-gray-300 rounded text-xs font-mono" />
                <input v-model="editingStatus.label" type="text" class="border-gray-300 rounded text-sm" />
                <input v-model="editingStatus.description" type="text" class="border-gray-300 rounded text-sm col-span-2" />
                <label class="flex items-center gap-2 text-xs text-gray-600">
                  <input v-model="editingStatus.active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                  Aktywny
                </label>
                <div class="flex items-center gap-2 justify-end">
                  <button type="button" class="px-2 py-1 text-xs rounded bg-sky-600 text-white hover:bg-sky-700" @click="saveStatus">
                    Zapisz
                  </button>
                  <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="cancelEditStatus">
                    Anuluj
                  </button>
                </div>
                <div class="col-span-6">
                  <div class="text-xs text-gray-500 mt-2">Eventy powiązane</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <label v-for="event in events" :key="event.id" class="flex items-center gap-2 text-xs text-gray-600 border border-gray-200 rounded px-2 py-1">
                      <input v-model="editingStatus.eventIds" type="checkbox" :value="event.id" />
                      {{ event.label }}
                    </label>
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="font-mono text-xs">{{ status.key }}</div>
                <div class="font-semibold text-gray-800">{{ status.label }}</div>
                <div class="col-span-2">{{ status.description || '—' }}</div>
                <div>
                  <span :class="status.active ? 'text-emerald-700' : 'text-gray-400'">
                    {{ status.active ? 'TAK' : 'NIE' }}
                  </span>
                </div>
                <div class="flex items-center gap-2 justify-end">
                  <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="startEditStatus(status)">
                    Edytuj
                  </button>
                  <button type="button" class="px-2 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50" @click="deleteStatus(String(status.id))">
                    Usuń
                  </button>
                </div>
              </template>
            </div>
          </div>
        </div>

        <div class="border rounded">
          <div class="grid grid-cols-5 gap-2 px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50">
            <div>Event Key</div>
            <div>Nazwa</div>
            <div class="col-span-2">Opis</div>
            <div class="text-right">Akcje</div>
          </div>
          <div v-if="eventLoading" class="px-3 py-3 text-sm text-gray-500">Ładowanie...</div>
          <div v-else-if="events.length === 0" class="px-3 py-3 text-sm text-gray-500">Brak eventów.</div>
          <div v-else class="divide-y">
            <div v-for="event in events" :key="event.id" class="grid grid-cols-5 gap-2 px-3 py-2 text-sm items-center">
              <template v-if="editingEventId === String(event.id)">
                <input v-model="editingEvent.key" type="text" class="border-gray-300 rounded text-xs font-mono" />
                <input v-model="editingEvent.label" type="text" class="border-gray-300 rounded text-sm" />
                <input v-model="editingEvent.description" type="text" class="border-gray-300 rounded text-sm col-span-2" />
                <div class="flex items-center gap-2 justify-end">
                  <button type="button" class="px-2 py-1 text-xs rounded bg-sky-600 text-white hover:bg-sky-700" @click="saveEvent">
                    Zapisz
                  </button>
                  <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="cancelEditEvent">
                    Anuluj
                  </button>
                </div>
              </template>
              <template v-else>
                <div class="font-mono text-xs">{{ event.key }}</div>
                <div class="font-semibold text-gray-800">{{ event.label }}</div>
                <div class="col-span-2">{{ event.description || '—' }}</div>
                <div class="flex items-center gap-2 justify-end">
                  <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="startEditEvent(event)">
                    Edytuj
                  </button>
                  <button type="button" class="px-2 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50" @click="deleteEvent(String(event.id))">
                    Usuń
                  </button>
                </div>
              </template>
            </div>
          </div>

          <div class="px-3 py-3 border-t bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
              <div>
                <label class="text-xs font-semibold text-gray-500">Event Key</label>
                <input v-model="newEvent.key" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="meeting.created" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Nazwa</label>
                <input v-model="newEvent.label" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Nowe spotkanie" />
              </div>
              <div class="md:col-span-2">
                <label class="text-xs font-semibold text-gray-500">Opis</label>
                <input v-model="newEvent.description" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Opis eventu" />
              </div>
              <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                  <input v-model="newEvent.active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                  Aktywny
                </label>
                <button type="button" class="px-3 py-2 rounded bg-sky-600 text-white text-sm hover:bg-sky-700" @click="createEvent">
                  Dodaj
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'broadcasts' && isSuperAdmin" class="space-y-6 pt-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Broadcasty CRM</h3>
          <button type="button" class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50" @click="fetchBroadcasts">
            Odśwież
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-slate-50 border border-slate-200 rounded p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800 mb-1">Powiadomienie vs aktualność</div>
            Powiadomienie to komunikat 1:1 lub do roli/zespołu. Aktualność to ogłoszenie masowe (news), widoczne w całym CRM.
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800 mb-1">Kiedy odbiorcy dostają?</div>
            W momencie wystąpienia eventu z pola „Event Key” (np. `notifications.created`).
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800 mb-1">Odbiorcy</div>
            Dodaj role lub zespoły. `ALL_TEAMS` oznacza wszystkie zespoły Keycloak.
          </div>
        </div>

        <div class="bg-white border border-gray-200 rounded p-4 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div>
              <label class="text-xs font-semibold text-gray-500">Nazwa</label>
              <input v-model="newBroadcast.name" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Powiadomienie o spotkaniu" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Event Key</label>
              <input v-model="newBroadcast.event_key" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="meeting.created" />
            </div>
            <div class="md:col-span-2">
              <label class="text-xs font-semibold text-gray-500">Opis</label>
              <input v-model="newBroadcast.description" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Opis broadcastu" />
            </div>
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="newBroadcast.enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                Włączony
              </label>
              <button type="button" class="px-3 py-2 rounded bg-sky-600 text-white text-sm hover:bg-sky-700" @click="createBroadcast">
                Dodaj
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-semibold text-gray-500">Role docelowe</label>
              <div class="mt-2 flex flex-wrap gap-2">
                <button v-for="role in roles" :key="role" type="button" class="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50" @click="addBroadcastTarget({ target_type: 'ROLE', target_value: role })">
                  {{ role }}
                </button>
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Zespoły (Keycloak, tag ALL_TEAMS)</label>
              <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" class="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50" @click="addBroadcastTarget({ target_type: 'TEAM', target_value: 'ALL_TEAMS' })">
                  ALL_TEAMS
                </button>
                <button v-for="team in teams" :key="team" type="button" class="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50" @click="addBroadcastTarget({ target_type: 'TEAM', target_value: team })">
                  {{ team }}
                </button>
                <span v-if="teamsLoading" class="text-xs text-gray-400">Ładowanie zespołów...</span>
              </div>
            </div>
          </div>

          <div>
            <div class="text-xs text-gray-500 mb-2">Odbiorcy (podgląd)</div>
            <div class="flex flex-wrap gap-2">
              <span v-for="(target, idx) in newBroadcast.targets" :key="`${target.target_type}-${target.target_value}-${idx}`" class="text-xs px-2 py-1 rounded border border-slate-200 bg-slate-50 flex items-center gap-2">
                {{ target.target_type }}: {{ target.target_value }}
                <button type="button" class="text-slate-400 hover:text-slate-700" @click="removeBroadcastTarget(idx)">✕</button>
              </span>
              <span v-if="newBroadcast.targets.length === 0" class="text-xs text-gray-400">Brak odbiorców.</span>
            </div>
          </div>
        </div>

        <div class="border rounded">
          <div class="grid grid-cols-5 gap-2 px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50">
            <div>Nazwa</div>
            <div>Event Key</div>
            <div class="col-span-2">Targety</div>
            <div class="text-right">Akcje</div>
          </div>
          <div v-if="broadcastLoading" class="px-3 py-3 text-sm text-gray-500">Ładowanie...</div>
          <div v-else-if="broadcasts.length === 0" class="px-3 py-3 text-sm text-gray-500">Brak broadcastów.</div>
          <div v-else class="divide-y">
            <div v-for="broadcast in broadcasts" :key="broadcast.id" class="grid grid-cols-5 gap-2 px-3 py-2 text-sm items-center">
              <template v-if="editingBroadcastId === String(broadcast.id)">
                <input v-model="editingBroadcast.name" type="text" class="border-gray-300 rounded text-sm" />
                <input v-model="editingBroadcast.event_key" type="text" class="border-gray-300 rounded text-xs font-mono" />
                <div class="col-span-2">
                  <div class="text-xs text-gray-500 mb-2">Targety</div>
                  <div class="flex flex-wrap gap-2 mb-2">
                    <button v-for="role in roles" :key="role" type="button" class="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50" @click="addBroadcastTarget({ target_type: 'ROLE', target_value: role })">
                      {{ role }}
                    </button>
                    <button type="button" class="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50" @click="addBroadcastTarget({ target_type: 'TEAM', target_value: 'ALL_TEAMS' })">
                      ALL_TEAMS
                    </button>
                    <button v-for="team in teams" :key="team" type="button" class="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50" @click="addBroadcastTarget({ target_type: 'TEAM', target_value: team })">
                      {{ team }}
                    </button>
                  </div>
                  <div class="text-xs text-gray-500 mb-2">Odbiorcy (podgląd)</div>
                  <div class="flex flex-wrap gap-2">
                    <span v-for="(target, idx) in editingBroadcast.targets" :key="`${target.target_type}-${target.target_value}-${idx}`" class="text-xs px-2 py-1 rounded border border-slate-200 bg-slate-50 flex items-center gap-2">
                      {{ target.target_type }}: {{ target.target_value }}
                      <button type="button" class="text-slate-400 hover:text-slate-700" @click="removeBroadcastTarget(idx)">✕</button>
                    </span>
                    <span v-if="editingBroadcast.targets.length === 0" class="text-xs text-gray-400">Brak odbiorców.</span>
                  </div>
                  <div class="flex items-center gap-2 mt-2">
                    <label class="flex items-center gap-2 text-xs text-gray-600">
                      <input v-model="editingBroadcast.enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                      Włączony
                    </label>
                  </div>
                </div>
                <div class="flex items-center gap-2 justify-end">
                  <button type="button" class="px-2 py-1 text-xs rounded bg-sky-600 text-white hover:bg-sky-700" @click="saveBroadcast">
                    Zapisz
                  </button>
                  <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="cancelEditBroadcast">
                    Anuluj
                  </button>
                </div>
              </template>
              <template v-else>
                <div class="font-semibold text-gray-800">
                  {{ broadcast.name }}
                  <span v-if="!broadcast.enabled" class="ml-2 text-[10px] uppercase text-gray-400">Wyłączony</span>
                </div>
                <div class="text-xs font-mono">{{ broadcast.event_key }}</div>
                <div class="col-span-2 text-xs text-gray-500">
                  <span v-if="broadcast.targets?.length">{{ broadcast.targets.map((t) => `${t.target_type}:${t.target_value}`).join(', ') }}</span>
                  <span v-else>Brak targetów</span>
                </div>
                <div class="flex items-center gap-2 justify-end">
                  <button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50" @click="startEditBroadcast(broadcast)">
                    Edytuj
                  </button>
                  <button type="button" class="px-2 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50" @click="deleteBroadcast(String(broadcast.id))">
                    Usuń
                  </button>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
