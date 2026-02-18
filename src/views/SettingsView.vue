<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { api, apiBaseUrl, getApiBaseOverride, getDefaultApiBaseUrl, setApiBaseUrl } from '@/api/client'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import { useCalculatorStore } from '@/components/calculator/store/useCalculatorStore'
import { useSessionStore } from '@/stores/session'
import { useClientStore } from '@/stores/client'
import { DEFAULT_CONFIG } from '@/components/calculator/tax-engine/constants'
import type { UserRole } from '@/types/models'

const clientStore = useClientStore()
const defaultApiBase = getDefaultApiBaseUrl()
const apiBaseOverride = ref(getApiBaseOverride() || '')
const apiBaseCurrent = ref(apiBaseUrl)
const apiBaseInput = ref(apiBaseOverride.value || apiBaseCurrent.value)
const apiBaseTesting = ref(false)
const apiBaseSaving = ref(false)
const apiBaseTestMessage = ref<string | null>(null)
const apiBaseError = ref<string | null>(null)

const token = localStorage.getItem('crm_token')
const auth = useAuthStore()
const toast = useToastStore()
const viewPermissions = useViewPermissionsStore()
const session = useSessionStore()

const modeLabel = computed(() => auth.enabled ? 'Keycloak (PROD)' : 'DEV (auth wyłączony)')
const currentUser = computed(() => auth.user || null)
const currentRole = computed(() => session.currentUser?.role || auth.user?.roles?.find((r) => typeof r === 'string') || null)
const kcUrl = import.meta.env.VITE_KEYCLOAK_URL || ''
const kcRealm = import.meta.env.VITE_KEYCLOAK_REALM || ''
const kcClientId = import.meta.env.VITE_KEYCLOAK_CLIENT_ID || ''
const appOrigin = window.location.origin
const activeTab = ref<'backend' | 'auth' | 'mail' | 'consents' | 'crm-permissions' | 'calculator' | 'statuses' | 'broadcasts'>('backend')
const settingsTabs = [
  { key: 'backend', label: 'Backend API', permissionKey: 'settings-backend' },
  { key: 'auth', label: 'Autoryzacja', permissionKey: 'settings-auth' },
  { key: 'mail', label: 'Poczta', permissionKey: 'settings-mail' },
  { key: 'consents', label: 'Zgody', permissionKey: 'settings-consents' },
  { key: 'crm-permissions', label: 'Uprawnienia CRM', permissionKey: 'settings-crm-permissions' },
  { key: 'calculator', label: 'Kalkulator', permissionKey: 'settings-calculator' },
  { key: 'statuses', label: 'Statusy', permissionKey: 'settings-statuses' },
  { key: 'broadcasts', label: 'Broadcasty', permissionKey: 'settings-broadcasts' },
] as const
const canAccessSettingsTab = (permissionKey?: string) => {
  if (!permissionKey) return false
  if (permissionKey === 'settings-crm-permissions') return isSuperAdmin.value
  return viewPermissions.isViewAllowed(permissionKey, currentRole.value)
}
const firstAllowedSettingsTab = computed(() => {
  const allowed = settingsTabs.find((tab) => tab && canAccessSettingsTab(tab.permissionKey))
  return allowed ? allowed.key : 'backend'
})
const visibleSettingsTabs = computed(() => settingsTabs.filter((tab) => tab && canAccessSettingsTab(tab.permissionKey)))
const isSuperAdmin = computed(() => currentRole.value === 'ADMIN')
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
  file_url?: string | null
  file_name?: string | null
  file_type?: string | null
  file_size?: number | null
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
const newConsentFile = ref<File | null>(null)
const editingConsentId = ref<string | null>(null)
const editingConsent = ref({
  code: '',
  title: '',
  description: '',
  required: true,
})
const editingConsentFile = ref<File | null>(null)
const editingConsentRemoveFile = ref(false)

type MailSettings = {
  from_name: string
  from_email: string
  imap_host: string
  imap_port: number
  imap_secure: boolean
  imap_username: string
  imap_password: string
  imap_inbox_folder: string
  imap_sent_folder: string
  imap_trash_folder: string
  smtp_host: string
  smtp_port: number
  smtp_secure: boolean
  smtp_username: string
  smtp_password: string
}

const defaultMailSettings = (): MailSettings => ({
  from_name: '',
  from_email: '',
  imap_host: '',
  imap_port: 993,
  imap_secure: true,
  imap_username: '',
  imap_password: '',
  imap_inbox_folder: 'INBOX',
  imap_sent_folder: 'Sent',
  imap_trash_folder: 'Trash',
  smtp_host: '',
  smtp_port: 465,
  smtp_secure: true,
  smtp_username: '',
  smtp_password: '',
})

const mailSettings = ref<MailSettings>(defaultMailSettings())
const mailSettingsLoaded = ref(false)
const mailSettingsSaving = ref(false)
const mailSettingsError = ref<string | null>(null)
const imapPasswordSet = ref(false)
const smtpPasswordSet = ref(false)
const mailFolders = ref<Array<{ path: string; name: string; specialUse?: string | null }>>([])
const mailFoldersLoading = ref(false)
const mailFoldersError = ref<string | null>(null)
const mailTabLoaded = ref(false)
const mailTestLoading = ref(false)
const mailTestResult = ref<any | null>(null)
const mailTestError = ref<string | null>(null)
const imapAdminLoading = ref(false)
const imapAdminError = ref<string | null>(null)
const imapHealth = ref<any | null>(null)
const imapMetrics = ref<any | null>(null)
const imapStats = ref<any[]>([])
const imapLogs = ref<any[]>([])
const imapJobs = ref<any[]>([])
const imapDebugUserId = ref<string>('')

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

const clamp = (val: number, min: number, max: number) => Math.min(Math.max(val, min), max)

const normalizeApiBaseInput = (value: string) => {
  let v = value.trim()
  while (v.length > 0 && v.charAt(v.length - 1) === '/') {
    v = v.substring(0, v.length - 1)
  }
  return v
}

const testApiConnection = async () => {
  const target = normalizeApiBaseInput(apiBaseInput.value)
  if (!target) {
    apiBaseError.value = 'Podaj poprawny adres API (np. https://example.com/api).'
    apiBaseTestMessage.value = null
    return
  }
  apiBaseTesting.value = true
  apiBaseError.value = null
  apiBaseTestMessage.value = null
  const controller = new AbortController()
  const timeoutId = setTimeout(() => controller.abort(), 10000)
  try {
    const healthUrl = `${target}/health`
    const response = await fetch(healthUrl, { method: 'GET', signal: controller.signal })
    if (response.status >= 500) {
      throw new Error(`Serwer zwrócił status ${response.status}`)
    }
    apiBaseTestMessage.value = response.ok
      ? `Połączenie OK (status ${response.status}).`
      : `Host odpowiada (status ${response.status}).` 
  } catch (error: any) {
    apiBaseError.value = error?.name === 'AbortError'
      ? 'Test przekroczył limit czasu (10s).'
      : (error?.message || 'Nie udało się nawiązać połączenia z API.')
  } finally {
    clearTimeout(timeoutId)
    apiBaseTesting.value = false
  }
}

const applyApiBase = async (value: string | null, successMessage: string) => {
  apiBaseSaving.value = true
  apiBaseError.value = null
  apiBaseTestMessage.value = null
  try {
    setApiBaseUrl(value)
    apiBaseCurrent.value = apiBaseUrl
    apiBaseOverride.value = value || ''
    apiBaseInput.value = value ? value : apiBaseCurrent.value
    toast.success(successMessage)
    if (auth.enabled) {
      const ok = await clientStore.refreshApiData()
      if (!ok) {
        toast.error('Połączenie z API nie zwróciło listy klientów. Sprawdź adres.')
      }
    }
  } catch (error: any) {
    apiBaseError.value = error?.message || 'Nie udało się zapisać adresu API.'
  } finally {
    apiBaseSaving.value = false
  }
}

const saveApiConnection = () => {
  const target = normalizeApiBaseInput(apiBaseInput.value)
  if (!target) {
    apiBaseError.value = 'Podaj poprawny adres API (np. https://example.com/api).'
    return
  }
  void applyApiBase(target, 'Zapisano nowy adres API.')
}

const resetApiConnection = () => {
  void applyApiBase(null, 'Przywrócono domyślny adres API.')
}

const sanitizeCalculatorValue = (path: string, value: number) => {
  if (Number.isNaN(value)) return 0

  const percentFields = new Set([
    'pit.prog1Stawka',
    'pit.prog2Stawka',
    'pit.uzKupProc',
    'pit.uzKupAutorskie',
    'swiadczenie.stawkaPit',
    'swiadczenie.odplatnosc',
    'prowizja.standard',
    'prowizja.plus',
    'zus.zdrowotna',
    'zus.uop.pracownik.emerytalna',
    'zus.uop.pracownik.rentowa',
    'zus.uop.pracownik.chorobowa',
    'zus.uop.pracodawca.emerytalna',
    'zus.uop.pracodawca.rentowa',
    'zus.uop.pracodawca.wypadkowa',
    'zus.uop.pracodawca.fp',
    'zus.uop.pracodawca.fgsp',
    'zus.uz.pracownik.emerytalna',
    'zus.uz.pracownik.rentowa',
    'zus.uz.pracownik.chorobowa',
    'zus.uz.pracodawca.emerytalna',
    'zus.uz.pracodawca.rentowa',
    'zus.uz.pracodawca.wypadkowa',
    'zus.uz.pracodawca.fp',
    'zus.uz.pracodawca.fgsp',
  ])

  const nonNegativeFields = new Set([
    'placaMinimalna.brutto',
    'placaMinimalna.netto',
    'minimalnaKwotaUZ.zasadniczaNetto',
    'pit.prog1Limit',
    'pit.kwotaWolnaRoczna',
    'pit.kwotaZmniejszajacaMies',
    'pit.kupStandard',
    'pit.kupPodwyzszone',
    'pit.ulgaMlodziLimitRoczny',
  ])

  if (percentFields.has(path)) {
    const clamped = clamp(value, 0, 100)
    if (clamped !== value) toast.warning('Wartość % została ograniczona do 0–100.')
    return clamped
  }

  if (path === 'pit.ulgaMlodziMaxWiek') {
    const clamped = clamp(value, 0, 30)
    if (clamped !== value) toast.warning('Maksymalny wiek ulgi młodych ustawiono w zakresie 0–30.')
    return clamped
  }

  if (path === 'pit.fpZwolnienieWiekKobieta' || path === 'pit.fpZwolnienieWiekMezczyzna') {
    const clamped = clamp(value, 0, 120)
    if (clamped !== value) toast.warning('Wiek zwolnienia FP ustawiono w zakresie 0–120.')
    return clamped
  }

  if (path === 'offerValidDays') {
    const clamped = clamp(Math.round(value), 1, 365)
    if (clamped !== value) toast.warning('Ważność oferty ustawiona w zakresie 1–365 dni.')
    return clamped
  }

  if (nonNegativeFields.has(path)) {
    const clamped = Math.max(0, value)
    if (clamped !== value) toast.warning('Wartość nie może być ujemna.')
    return clamped
  }

  return value
}

const updateCalculatorConfig = (path: string, value: number) => {
  const newConfig = JSON.parse(JSON.stringify(calculatorStore.config))
  const keys = path.split('.')
  let current = newConfig
  for (let i = 0; i < keys.length - 1; i++) current = current[keys[i]]
  current[keys[keys.length - 1]] = sanitizeCalculatorValue(path, value)
  calculatorStore.config = newConfig
}

const updateCalculatorConfigText = (path: string, value: string) => {
  const newConfig = JSON.parse(JSON.stringify(calculatorStore.config))
  const keys = path.split('.')
  let current = newConfig
  for (let i = 0; i < keys.length - 1; i++) {
    if (current[keys[i]] === undefined) current[keys[i]] = {}
    current = current[keys[i]]
  }
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
    const form = new FormData()
    form.append('code', newConsent.value.code.trim())
    form.append('title', newConsent.value.title.trim())
    form.append('description', newConsent.value.description.trim())
    form.append('required', newConsent.value.required ? '1' : '0')
    if (newConsentFile.value) form.append('file', newConsentFile.value)
    const { data } = await api.post('/v1/consents', form, { headers: { 'Content-Type': 'multipart/form-data' } })
    consents.value = [data, ...consents.value]
    newConsent.value = { code: '', title: '', description: '', required: true }
    newConsentFile.value = null
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
  editingConsentFile.value = null
  editingConsentRemoveFile.value = false
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
    const needsMultipart = Boolean(editingConsentFile.value) || editingConsentRemoveFile.value
    let data: any
    if (needsMultipart) {
      const form = new FormData()
      form.append('code', editingConsent.value.code.trim())
      form.append('title', editingConsent.value.title.trim())
      form.append('description', editingConsent.value.description.trim())
      form.append('required', editingConsent.value.required ? '1' : '0')
      if (editingConsentFile.value) form.append('file', editingConsentFile.value)
      if (editingConsentRemoveFile.value) form.append('file_remove', '1')
      form.append('_method', 'PATCH')
      const resp = await api.post(`/v1/consents/${editingConsentId.value}`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
      data = resp.data
    } else {
      const payload = {
        code: editingConsent.value.code.trim(),
        title: editingConsent.value.title.trim(),
        description: editingConsent.value.description.trim(),
        required: editingConsent.value.required,
      }
      const resp = await api.patch(`/v1/consents/${editingConsentId.value}`, payload)
      data = resp.data
    }
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

const openConsentFile = async (consent: ConsentItem, download = false) => {
  if (!consent?.id) return
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

const applyMailSettings = (payload: any) => {
  const defaults = defaultMailSettings()
  mailSettings.value = {
    ...defaults,
    ...payload,
    imap_port: Number(payload?.imap_port ?? defaults.imap_port),
    smtp_port: Number(payload?.smtp_port ?? defaults.smtp_port),
    imap_secure: payload?.imap_secure ?? defaults.imap_secure,
    smtp_secure: payload?.smtp_secure ?? defaults.smtp_secure,
    imap_password: '',
    smtp_password: '',
  }
  imapPasswordSet.value = Boolean(payload?.imap_password_set)
  smtpPasswordSet.value = Boolean(payload?.smtp_password_set)
}

const fetchMailSettings = async () => {
  if (!auth.enabled) return
  mailSettingsError.value = null
  try {
    const { data } = await api.get('/v1/crm-mail-settings')
    const payload = data?.data ?? null
    if (payload) applyMailSettings(payload)
    else {
      applyMailSettings(defaultMailSettings())
      imapPasswordSet.value = false
      smtpPasswordSet.value = false
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać konfiguracji poczty.'
    mailSettingsError.value = message
  } finally {
    mailSettingsLoaded.value = true
  }
}

const saveMailSettings = async () => {
  if (!auth.enabled) return
  mailSettingsSaving.value = true
  mailSettingsError.value = null
  try {
    const payload = { ...mailSettings.value }
    const { data } = await api.put('/v1/crm-mail-settings', payload)
    const saved = data?.data ?? null
    if (saved) applyMailSettings(saved)
    toast.success('Zapisano konfigurację poczty.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać konfiguracji poczty.'
    mailSettingsError.value = message
    toast.error(message)
  } finally {
    mailSettingsSaving.value = false
  }
}

const fetchMailFolders = async () => {
  if (!auth.enabled) return
  mailFoldersLoading.value = true
  mailFoldersError.value = null
  try {
    const { data } = await api.get('/v1/crm-mailbox/folders')
    const payload = data?.data ?? []
    mailFolders.value = Array.isArray(payload) ? payload : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać folderów.'
    mailFoldersError.value = message
    toast.error(message)
  } finally {
    mailFoldersLoading.value = false
  }
}

const runMailTest = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  mailTestLoading.value = true
  mailTestError.value = null
  mailTestResult.value = null
  try {
    const { data } = await api.get('/v1/crm-mailbox/test', {
      params: { diagnostics: 1 },
    })
    mailTestResult.value = data?.data ?? data ?? null
    toast.success('Test IMAP zakończony.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Test IMAP nieudany.'
    mailTestError.value = message
    toast.error(message)
  } finally {
    mailTestLoading.value = false
  }
}

const fetchImapAdmin = async () => {
  if (!auth.enabled || !isSuperAdmin.value) return
  imapAdminLoading.value = true
  imapAdminError.value = null
  try {
    const userIdParam = imapDebugUserId.value ? Number(imapDebugUserId.value) : undefined
    const [health, metrics, stats, logs, jobs] = await Promise.all([
      api.get('/v1/admin/imap/health'),
      api.get('/v1/admin/imap/metrics'),
      api.get('/v1/admin/imap/stats', { params: { user_id: userIdParam } }),
      api.get('/v1/admin/imap/logs', { params: { limit: 200, user_id: userIdParam } }),
      api.get('/v1/admin/imap/jobs', { params: { limit: 200, user_id: userIdParam } }),
    ])
    imapHealth.value = health.data?.data ?? null
    imapMetrics.value = metrics.data?.data ?? null
    imapStats.value = Array.isArray(stats.data?.data) ? stats.data.data : []
    imapLogs.value = Array.isArray(logs.data?.data) ? logs.data.data : []
    imapJobs.value = Array.isArray(jobs.data?.data) ? jobs.data.data : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych IMAP.'
    imapAdminError.value = message
    toast.error(message)
  } finally {
    imapAdminLoading.value = false
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

watch(
  () => [viewPermissions.resolvedPermissions, currentRole.value],
  () => {
    const allowedKey = firstAllowedSettingsTab.value
    if (!canAccessSettingsTab(`settings-${activeTab.value}`)) {
      activeTab.value = allowedKey as typeof activeTab.value
    }
  },
  { immediate: true }
)

watch(
  () => activeTab.value,
  (tab) => {
    if (tab !== 'mail' || mailTabLoaded.value) return
    mailTabLoaded.value = true
    fetchMailSettings()
    fetchMailFolders()
  }
)

watch(
  () => apiBaseInput.value,
  () => {
    apiBaseError.value = null
    apiBaseTestMessage.value = null
  }
)
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-2xl font-semibold textstratton700">Ustawienia</h2>

    <div class="bg-white rounded shadow p-4">
      <div class="flex items-center gap-4 border-b border-gray-200 pb-3">
        <button
          v-for="tab in visibleSettingsTabs"
          :key="tab.key"
          type="button"
          class="text-sm font-semibold pb-2 -mb-3 border-b-2 transition"
          :class="activeTab === tab.key ? 'border-sky-600 text-sky-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="activeTab === 'backend' && canAccessSettingsTab('settings-backend')" class="space-y-4 pt-4">
        <h3 class="font-semibold">Backend API</h3>

        <div>
          <div class="text-sm text-gray-600">Aktywny adres</div>
          <div class="font-mono break-all">{{ apiBaseCurrent }}</div>
          <p v-if="apiBaseOverride" class="text-xs text-gray-500 mt-1">
            Nadpisany lokalnie (zapisywany w pamięci urządzenia).
          </p>
          <p v-else class="text-xs text-gray-500 mt-1">
            Wartość z pliku <code>.env</code>: <span class="font-mono">{{ defaultApiBase }}</span>
          </p>
        </div>

        <div class="space-y-2">
          <label for="api-base-input" class="text-sm font-semibold text-gray-700">Nowy adres API</label>
          <input
            id="api-base-input"
            v-model="apiBaseInput"
            type="text"
            class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-sky-500"
            placeholder="https://api.twoja-domena.pl/api"
            autocomplete="off"
          />
          <p class="text-xs text-gray-500">
            Adres powinien wskazywać główny endpoint REST (np. <span class="font-mono">https://host/api</span>). Zmiana jest zapisywana lokalnie,
            więc możesz ją dostosować bez przebudowy aplikacji.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm rounded border border-sky-600 text-sky-600 hover:bg-sky-50 disabled:opacity-60"
            :disabled="apiBaseTesting || apiBaseSaving"
            @click="testApiConnection"
          >
            {{ apiBaseTesting ? 'Testuję…' : 'Testuj połączenie' }}
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm rounded border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-60"
            :disabled="apiBaseSaving"
            @click="saveApiConnection"
          >
            {{ apiBaseSaving ? 'Zapisuję…' : 'Zapisz' }}
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm rounded border border-gray-300 hover:bg-gray-50 disabled:opacity-60"
            :disabled="apiBaseSaving || !apiBaseOverride"
            @click="resetApiConnection"
          >
            Przywróć domyślny
          </button>
        </div>

        <div v-if="apiBaseTestMessage" class="text-xs text-emerald-600">{{ apiBaseTestMessage }}</div>
        <div v-if="apiBaseError" class="text-xs text-red-600">{{ apiBaseError }}</div>
        <p class="text-xs text-gray-500">
          Domyślny adres nadal można ustawić w pliku <code>.env</code> poprzez zmienną <code>VITE_API_BASE_URL</code>.
        </p>
      </div>

      <div v-else-if="activeTab === 'auth' && canAccessSettingsTab('settings-auth')" class="space-y-3 pt-4">
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

      <div v-else-if="activeTab === 'mail' && canAccessSettingsTab('settings-mail')" class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Poczta (IMAP/SMTP)</h3>
          <div class="flex items-center gap-2">
            <button
              v-if="isSuperAdmin"
              type="button"
              class="text-xs px-3 py-1.5 rounded border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-60"
              :disabled="!auth.enabled || mailTestLoading"
              @click="runMailTest"
            >
              Test IMAP
            </button>
            <button
              type="button"
              class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-60"
              :disabled="!auth.enabled || mailFoldersLoading"
              @click="fetchMailFolders"
            >
              Pobierz foldery
            </button>
            <button
              type="button"
              class="text-xs px-3 py-1.5 rounded border border-sky-600 bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-60"
              :disabled="!auth.enabled || mailSettingsSaving"
              @click="saveMailSettings"
            >
              Zapisz
            </button>
          </div>
        </div>

        <div v-if="!auth.enabled" class="text-sm text-gray-500">Tryb DEV: konfiguracja poczty jest dostępna tylko w trybie API.</div>
        <div v-else class="space-y-5">
          <div v-if="mailSettingsError" class="text-sm text-red-600">{{ mailSettingsError }}</div>
          <div v-if="isSuperAdmin" class="space-y-2">
            <div v-if="mailTestError" class="text-xs text-red-600">{{ mailTestError }}</div>
            <pre v-if="mailTestResult" class="text-[11px] bg-gray-50 border border-gray-200 rounded p-3 whitespace-pre-wrap">{{ JSON.stringify(mailTestResult, null, 2) }}</pre>
          </div>
          <div v-if="isSuperAdmin" class="border rounded p-4 space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="font-semibold text-sm">IMAP Debug (Node)</h4>
              <div class="flex items-center gap-2">
                <input
                  v-model="imapDebugUserId"
                  type="number"
                  min="1"
                  class="w-28 text-xs border border-gray-300 rounded px-2 py-1"
                  placeholder="user_id"
                />
                <button
                  type="button"
                  class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-60"
                  :disabled="imapAdminLoading"
                  @click="fetchImapAdmin"
                >
                  Odśwież debug
                </button>
              </div>
            </div>
            <div v-if="imapAdminError" class="text-xs text-red-600">{{ imapAdminError }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
              <div class="border rounded p-2 bg-gray-50">
                <div class="font-semibold mb-1">Health</div>
                <pre v-if="imapHealth" class="whitespace-pre-wrap">{{ JSON.stringify(imapHealth, null, 2) }}</pre>
                <div v-else class="text-gray-500">Brak danych</div>
              </div>
              <div class="border rounded p-2 bg-gray-50">
                <div class="font-semibold mb-1">Metrics</div>
                <pre v-if="imapMetrics" class="whitespace-pre-wrap">{{ JSON.stringify(imapMetrics, null, 2) }}</pre>
                <div v-else class="text-gray-500">Brak danych</div>
              </div>
            </div>
            <div class="text-xs">
              <div class="font-semibold mb-1">Sync stats</div>
              <pre v-if="imapStats.length" class="whitespace-pre-wrap bg-gray-50 border rounded p-2">{{ JSON.stringify(imapStats, null, 2) }}</pre>
              <div v-else class="text-gray-500">Brak danych</div>
            </div>
            <div class="text-xs">
              <div class="font-semibold mb-1">Queue</div>
              <pre v-if="imapJobs.length" class="whitespace-pre-wrap bg-gray-50 border rounded p-2">{{ JSON.stringify(imapJobs, null, 2) }}</pre>
              <div v-else class="text-gray-500">Brak danych</div>
            </div>
            <div class="text-xs">
              <div class="font-semibold mb-1">Logs (last 200)</div>
              <pre v-if="imapLogs.length" class="whitespace-pre-wrap bg-gray-50 border rounded p-2">{{ JSON.stringify(imapLogs, null, 2) }}</pre>
              <div v-else class="text-gray-500">Brak danych</div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-semibold text-gray-500">Nazwa nadawcy</label>
              <input v-model="mailSettings.from_name" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="np. Jan Kowalski" />
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500">Email nadawcy</label>
              <input v-model="mailSettings.from_email" type="email" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="jan.kowalski@firma.pl" />
            </div>
          </div>

          <div class="border rounded p-4 space-y-4">
            <h4 class="font-semibold text-sm">IMAP (pobieranie)</h4>
            <div v-if="mailFoldersError" class="text-xs text-red-600">{{ mailFoldersError }}</div>
            <datalist v-if="mailFolders.length" id="imap-folder-options">
              <option v-for="folder in mailFolders" :key="folder.path" :value="folder.path" />
            </datalist>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-500">Host</label>
                <input v-model="mailSettings.imap_host" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="imap.example.com" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Port</label>
                <input v-model.number="mailSettings.imap_port" type="number" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
              <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                  <input v-model="mailSettings.imap_secure" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                  SSL/TLS
                </label>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-500">Użytkownik</label>
                <input v-model="mailSettings.imap_username" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Hasło</label>
                <input v-model="mailSettings.imap_password" type="password" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="••••••••" />
                <p v-if="imapPasswordSet" class="mt-1 text-[11px] text-gray-500">Hasło zapisane. Zostaw puste, aby nie zmieniać.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-500">Folder INBOX</label>
                <input v-model="mailSettings.imap_inbox_folder" list="imap-folder-options" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Folder SENT</label>
                <input v-model="mailSettings.imap_sent_folder" list="imap-folder-options" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Folder TRASH</label>
                <input v-model="mailSettings.imap_trash_folder" list="imap-folder-options" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
            </div>
          </div>

          <div class="border rounded p-4 space-y-4">
            <h4 class="font-semibold text-sm">SMTP (wysyłka)</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-500">Host</label>
                <input v-model="mailSettings.smtp_host" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="smtp.example.com" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Port</label>
                <input v-model.number="mailSettings.smtp_port" type="number" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
              <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                  <input v-model="mailSettings.smtp_secure" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                  SSL/TLS
                </label>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-500">Użytkownik</label>
                <input v-model="mailSettings.smtp_username" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" />
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-500">Hasło</label>
                <input v-model="mailSettings.smtp_password" type="password" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="••••••••" />
                <p v-if="smtpPasswordSet" class="mt-1 text-[11px] text-gray-500">Hasło zapisane. Zostaw puste, aby nie zmieniać.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'consents' && canAccessSettingsTab('settings-consents')" class="space-y-4 pt-4">
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
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
            <div>
              <label class="text-xs font-semibold text-gray-500">Plik (PDF)</label>
              <input type="file" accept="application/pdf" class="mt-1 w-full border border-gray-300 rounded text-sm" @change="(e) => { const f = (e.target as HTMLInputElement).files?.[0] || null; newConsentFile = f }" />
            </div>
          </div>

          <div class="border rounded">
            <div class="grid grid-cols-7 gap-2 px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50">
              <div>Kod</div>
              <div>Tytuł</div>
              <div class="col-span-2">Opis</div>
              <div>Plik</div>
              <div>Wymagana</div>
              <div class="text-right">Akcje</div>
            </div>
            <div v-if="consentsLoading" class="px-3 py-3 text-sm text-gray-500">Ładowanie...</div>
            <div v-else-if="consentsError" class="px-3 py-3 text-sm text-red-600">{{ consentsError }}</div>
            <div v-else-if="consents.length === 0" class="px-3 py-3 text-sm text-gray-500">Brak zgód w bazie.</div>
            <div v-else class="divide-y">
              <div v-for="consent in consents" :key="consent.id" class="grid grid-cols-7 gap-2 px-3 py-2 text-sm items-center">
                <template v-if="editingConsentId === String(consent.id)">
                  <input v-model="editingConsent.code" type="text" class="border-gray-300 rounded text-xs font-mono" />
                  <input v-model="editingConsent.title" type="text" class="border-gray-300 rounded text-sm" />
                  <input v-model="editingConsent.description" type="text" class="border-gray-300 rounded text-sm col-span-2" />
                  <div class="flex flex-col gap-1">
                    <input type="file" accept="application/pdf" class="border border-gray-300 rounded text-xs" @change="(e) => { const f = (e.target as HTMLInputElement).files?.[0] || null; editingConsentFile = f }" />
                    <label class="flex items-center gap-2 text-[10px] text-gray-600">
                      <input v-model="editingConsentRemoveFile" type="checkbox" class="h-3 w-3 rounded border-gray-300" />
                      Usuń plik
                    </label>
                  </div>
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
                  <div class="text-xs text-gray-600">
                    <template v-if="consent.file_url">
                      <div class="font-mono truncate" :title="consent.file_name || ''">{{ consent.file_name || 'plik.pdf' }}</div>
                      <div class="flex items-center gap-2 mt-1">
                        <button type="button" class="text-xs text-sky-600 hover:underline" @click="openConsentFile(consent, false)">Podgląd</button>
                        <button type="button" class="text-xs text-sky-600 hover:underline" @click="openConsentFile(consent, true)">Pobierz</button>
                      </div>
                    </template>
                    <template v-else>
                      Brak
                    </template>
                  </div>
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

      <div v-else-if="activeTab === 'crm-permissions' && canAccessSettingsTab('settings-crm-permissions')" class="space-y-4 pt-4">
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

      <div v-else-if="activeTab === 'calculator' && isSuperAdmin && canAccessSettingsTab('settings-calculator')" class="space-y-6 pt-4">
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Kwota wolna roczna</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.kwotaWolnaRoczna"
              @input="updateCalculatorConfig('pit.kwotaWolnaRoczna', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Kwota zmniejszająca (mies.)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.kwotaZmniejszajacaMies"
              @input="updateCalculatorConfig('pit.kwotaZmniejszajacaMies', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Ulga młodych max wiek</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.ulgaMlodziMaxWiek"
              @input="updateCalculatorConfig('pit.ulgaMlodziMaxWiek', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Ulga młodych limit roczny</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.ulgaMlodziLimitRoczny"
              @input="updateCalculatorConfig('pit.ulgaMlodziLimitRoczny', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">KUP standard</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.kupStandard"
              @input="updateCalculatorConfig('pit.kupStandard', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">KUP podwyższone</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.kupPodwyzszone"
              @input="updateCalculatorConfig('pit.kupPodwyzszone', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">KUP UZ (proc)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.uzKupProc"
              @input="updateCalculatorConfig('pit.uzKupProc', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">KUP UZ autorskie</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.uzKupAutorskie"
              @input="updateCalculatorConfig('pit.uzKupAutorskie', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Zdrowotna (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.zus.zdrowotna"
              @input="updateCalculatorConfig('zus.zdrowotna', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="border border-slate-200 rounded-lg p-4 space-y-3">
            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">ZUS UOP - pracownik (%)</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracownik.emerytalna" @input="updateCalculatorConfig('zus.uop.pracownik.emerytalna', Number(($event.target as HTMLInputElement).value))" placeholder="Emerytalna" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracownik.rentowa" @input="updateCalculatorConfig('zus.uop.pracownik.rentowa', Number(($event.target as HTMLInputElement).value))" placeholder="Rentowa" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracownik.chorobowa" @input="updateCalculatorConfig('zus.uop.pracownik.chorobowa', Number(($event.target as HTMLInputElement).value))" placeholder="Chorobowa" />
            </div>
            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">ZUS UOP - pracodawca (%)</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracodawca.emerytalna" @input="updateCalculatorConfig('zus.uop.pracodawca.emerytalna', Number(($event.target as HTMLInputElement).value))" placeholder="Emerytalna" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracodawca.rentowa" @input="updateCalculatorConfig('zus.uop.pracodawca.rentowa', Number(($event.target as HTMLInputElement).value))" placeholder="Rentowa" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracodawca.wypadkowa" @input="updateCalculatorConfig('zus.uop.pracodawca.wypadkowa', Number(($event.target as HTMLInputElement).value))" placeholder="Wypadkowa" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracodawca.fp" @input="updateCalculatorConfig('zus.uop.pracodawca.fp', Number(($event.target as HTMLInputElement).value))" placeholder="FP" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uop.pracodawca.fgsp" @input="updateCalculatorConfig('zus.uop.pracodawca.fgsp', Number(($event.target as HTMLInputElement).value))" placeholder="FGŚP" />
            </div>
          </div>
          <div class="border border-slate-200 rounded-lg p-4 space-y-3">
            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">ZUS UZ - pracownik (%)</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracownik.emerytalna" @input="updateCalculatorConfig('zus.uz.pracownik.emerytalna', Number(($event.target as HTMLInputElement).value))" placeholder="Emerytalna" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracownik.rentowa" @input="updateCalculatorConfig('zus.uz.pracownik.rentowa', Number(($event.target as HTMLInputElement).value))" placeholder="Rentowa" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracownik.chorobowa" @input="updateCalculatorConfig('zus.uz.pracownik.chorobowa', Number(($event.target as HTMLInputElement).value))" placeholder="Chorobowa" />
            </div>
            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">ZUS UZ - pracodawca (%)</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracodawca.emerytalna" @input="updateCalculatorConfig('zus.uz.pracodawca.emerytalna', Number(($event.target as HTMLInputElement).value))" placeholder="Emerytalna" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracodawca.rentowa" @input="updateCalculatorConfig('zus.uz.pracodawca.rentowa', Number(($event.target as HTMLInputElement).value))" placeholder="Rentowa" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracodawca.wypadkowa" @input="updateCalculatorConfig('zus.uz.pracodawca.wypadkowa', Number(($event.target as HTMLInputElement).value))" placeholder="Wypadkowa" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracodawca.fp" @input="updateCalculatorConfig('zus.uz.pracodawca.fp', Number(($event.target as HTMLInputElement).value))" placeholder="FP" />
              <input type="number" class="border border-slate-200 rounded px-3 py-2" :value="calculatorStore.config.zus.uz.pracodawca.fgsp" @input="updateCalculatorConfig('zus.uz.pracodawca.fgsp', Number(($event.target as HTMLInputElement).value))" placeholder="FGŚP" />
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">FP zwolnienie wiek (K)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.fpZwolnienieWiekKobieta"
              @input="updateCalculatorConfig('pit.fpZwolnienieWiekKobieta', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">FP zwolnienie wiek (M)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.pit.fpZwolnienieWiekMezczyzna"
              @input="updateCalculatorConfig('pit.fpZwolnienieWiekMezczyzna', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Świadczenie PIT (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.swiadczenie.stawkaPit"
              @input="updateCalculatorConfig('swiadczenie.stawkaPit', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Świadczenie odpłatność (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.swiadczenie.odplatnosc"
              @input="updateCalculatorConfig('swiadczenie.odplatnosc', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Prowizja standard (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.prowizja.standard"
              @input="updateCalculatorConfig('prowizja.standard', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Prowizja plus (%)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.prowizja.plus"
              @input="updateCalculatorConfig('prowizja.plus', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Ważność oferty (dni)</label>
            <input
              type="number"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.offerValidDays"
              @input="updateCalculatorConfig('offerValidDays', Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Stopka (linia 1)</label>
            <input
              type="text"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.branding?.footerLine1 || ''"
              @input="updateCalculatorConfigText('branding.footerLine1', ($event.target as HTMLInputElement).value)"
            />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Logo stopki (URL)</label>
            <input
              type="text"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.branding?.footerLogoUrl || ''"
              @input="updateCalculatorConfigText('branding.footerLogoUrl', ($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>

        <div class="grid grid-cols-1">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Stopka (linia 2)</label>
            <input
              type="text"
              class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2"
              :value="calculatorStore.config.branding?.footerLine2 || ''"
              @input="updateCalculatorConfigText('branding.footerLine2', ($event.target as HTMLInputElement).value)"
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

      <div v-else-if="activeTab === 'statuses' && isSuperAdmin && canAccessSettingsTab('settings-statuses')" class="space-y-6 pt-4">
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

      <div v-else-if="activeTab === 'broadcasts' && isSuperAdmin && canAccessSettingsTab('settings-broadcasts')" class="space-y-6 pt-4">
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
