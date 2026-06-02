import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types/models'

export type CrmViewKey =
  | 'dashboard'
  | 'sales-start'
  | 'sales-email-compose'
  | 'analytics'
  | 'clients'
  | 'offer-tool'
  | 'sales-contract-preview'
  | 'contract-preview'
  | 'invoice-preview'
  | 'structure'
  | 'hr-panel'
  | 'user-management'
  | 'admin-analytics'
  | 'admin-logs'
  | 'admin'
  | 'autenti-panel'
  | 'commission-thresholds'
  | 'commission-distributions'
  | 'settlements'
  | 'notifications'
  | 'calendar'
  | 'mailbox'
  | 'knowledge-base'
  | 'recruitment'
  | 'quick-calculator'
  | 'calculator'
  | 'leaderboard'
  | 'meetings'
  | 'settings'
  | 'settings-backend'
  | 'settings-auth'
  | 'settings-mail'
  | 'settings-consents'
  | 'settings-crm-permissions'
  | 'settings-calculator'
  | 'settings-statuses'
  | 'settings-broadcasts'
  | 'news-management'
  | 'leads'
  | 'payroll'
  | 'leadowiec-calendar'
  | 'leadowiec-settlements'

export type ViewPermissionEntry = {
  view_key: CrmViewKey
  roles: UserRole[]
}

const ALL_ROLES: UserRole[] = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR', 'LEADOWIEC']

const VIEW_OPTIONS: Array<{ key: CrmViewKey; label: string }> = [
  { key: 'dashboard', label: 'Centrum Zarządzania' },
  { key: 'sales-start', label: 'Proces Sprzedaży (Start)' },
  { key: 'sales-email-compose', label: 'Email (Composer)' },
  { key: 'analytics', label: 'Analityka' },
  { key: 'clients', label: 'Klienci' },
  { key: 'offer-tool', label: 'Generator Ofert' },
  { key: 'sales-contract-preview', label: 'Podgląd Umowy (Sprzedaż)' },
  { key: 'contract-preview', label: 'Podgląd Umowy' },
  { key: 'invoice-preview', label: 'Podgląd Faktury' },
  { key: 'structure', label: 'Struktura' },
  { key: 'hr-panel', label: 'HR / Kadry' },
  { key: 'admin', label: 'Panel Admina' },
  { key: 'user-management', label: 'Użytkownicy' },
  { key: 'admin-analytics', label: 'Analityka Finansowa' },
  { key: 'admin-logs', label: 'Logi Systemowe' },
  { key: 'autenti-panel', label: 'Autenti' },
  { key: 'commission-thresholds', label: 'Progi Prowizyjne' },
  { key: 'commission-distributions', label: 'Prowizje Override' },
  { key: 'settlements', label: 'Rozliczenia' },
  { key: 'notifications', label: 'Powiadomienia' },
  { key: 'calendar', label: 'Kalendarz' },
  { key: 'mailbox', label: 'Poczta' },
  { key: 'knowledge-base', label: 'Baza Wiedzy' },
  { key: 'recruitment', label: 'Rekrutacja' },
  { key: 'quick-calculator', label: 'Szybka Oferta' },
  { key: 'calculator', label: 'Kalkulator' },
  { key: 'leaderboard', label: 'Rankingi' },
  { key: 'meetings', label: 'Zarządzanie Spotkaniami' },
  { key: 'settings', label: 'Ustawienia' },
  { key: 'settings-backend', label: 'Ustawienia: Backend API' },
  { key: 'settings-auth', label: 'Ustawienia: Auth' },
  { key: 'settings-mail', label: 'Ustawienia: Poczta' },
  { key: 'settings-consents', label: 'Ustawienia: Zgody' },
  { key: 'settings-crm-permissions', label: 'Ustawienia: Uprawnienia CRM' },
  { key: 'settings-calculator', label: 'Ustawienia: Kalkulator' },
  { key: 'settings-statuses', label: 'Ustawienia: Statusy' },
  { key: 'settings-broadcasts', label: 'Ustawienia: Broadcasty' },
  { key: 'news-management', label: 'Zarządzanie Aktualnościami' },
  { key: 'leads', label: 'Zarządzanie Leadami' },
  { key: 'payroll', label: 'Lista Płac' },
  { key: 'leadowiec-calendar', label: 'Leadowiec: Kalendarz' },
  { key: 'leadowiec-settlements', label: 'Leadowiec: Rozliczenia' },
]

const SETTINGS_TAB_KEYS: CrmViewKey[] = [
  'settings-backend',
  'settings-auth',
  'settings-mail',
  'settings-consents',
  'settings-crm-permissions',
  'settings-calculator',
  'settings-statuses',
  'settings-broadcasts',
]

const DEFAULT_PERMISSIONS: Record<CrmViewKey, UserRole[]> = {
  dashboard: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR', 'LEADOWIEC'],
  'sales-start': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  'sales-email-compose': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  analytics: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  clients: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'LEADOWIEC'],
  'offer-tool': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  'sales-contract-preview': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  'contract-preview': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  'invoice-preview': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  structure: ['ADMIN', 'DIRECTOR', 'MANAGER', 'LEADOWIEC'],
  'hr-panel': ['ADMIN'],
  admin: ['ADMIN'],
  'autenti-panel': ['ADMIN'],
  'commission-thresholds': ['ADMIN'],
  'commission-distributions': ['ADMIN'],
  'user-management': ['ADMIN'],
  'admin-analytics': ['ADMIN'],
  'admin-logs': ['ADMIN'],
  settlements: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  notifications: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  calendar: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  mailbox: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  'knowledge-base': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  recruitment: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  'quick-calculator': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'LEADOWIEC'],
  calculator: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  meetings: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  leaderboard: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  settings: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  'settings-backend': ['ADMIN'],
  'settings-auth': ['ADMIN'],
  'settings-mail': ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  'settings-consents': ['ADMIN'],
  'settings-crm-permissions': ['ADMIN'],
  'settings-calculator': ['ADMIN'],
  'settings-statuses': ['ADMIN'],
  'settings-broadcasts': ['ADMIN'],
  'news-management': ['ADMIN'],
  leads: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'],
  payroll: ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR'],
  'leadowiec-calendar': ['LEADOWIEC'],
  'leadowiec-settlements': ['LEADOWIEC'],
}

const toMap = (entries: ViewPermissionEntry[]) => {
  const map: Record<string, UserRole[]> = {}
  entries.forEach((entry) => {
    map[entry.view_key] = entry.roles || []
  })
  return map
}

export const useViewPermissionsStore = defineStore('view-permissions', () => {
  const auth = useAuthStore()
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref<string | null>(null)
  const permissions = ref<Record<string, UserRole[]>>({})

  const resolvedPermissions = computed<Record<string, UserRole[]>>(() => ({
    ...DEFAULT_PERMISSIONS,
    ...permissions.value,
  }))

  const ensureLoaded = async () => {
    if (loaded.value || loading.value) return
    if (!auth.enabled) {
      loaded.value = true
      return
    }
    if (!auth.isAuthenticated) return
    await fetchPermissions()
  }

  const fetchPermissions = async () => {
    if (!auth.enabled || !auth.isAuthenticated) {
      loaded.value = true
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/v1/crm-view-permissions')
      const list = Array.isArray(data) ? (data as ViewPermissionEntry[]) : []
      permissions.value = toMap(list)
      loaded.value = true
    } catch (err: any) {
      error.value = err?.response?.data?.message || err?.message || 'Nie udało się pobrać uprawnień.'
      loaded.value = true // fallback to DEFAULT_PERMISSIONS on error
    } finally {
      loading.value = false
    }
  }

  const savePermissions = async (entries: ViewPermissionEntry[]) => {
    if (!auth.enabled) return null
    loading.value = true
    error.value = null
    try {
      const payload = {
        permissions: entries.map((entry) => ({
          view_key: entry.view_key,
          roles: entry.roles,
        })),
      }
      const { data } = await api.post('/v1/crm-view-permissions', payload)
      const list = Array.isArray(data) ? (data as ViewPermissionEntry[]) : []
      permissions.value = toMap(list)
      loaded.value = true
      return permissions.value
    } catch (err: any) {
      error.value = err?.response?.data?.message || err?.message || 'Nie udało się zapisać uprawnień.'
      throw err
    } finally {
      loading.value = false
    }
  }

  const isViewAllowed = (viewKey: string | undefined, role?: string | null) => {
    if (!viewKey) return true
    if (!role) return true
    
    const normalizedRole = String(role).toUpperCase()
    if (normalizedRole === 'ADMIN') return true
    
    // Safety fallback for new 'meetings' view during deployment transition
    if (viewKey === 'meetings') {
      const baseRoles = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR']
      if (baseRoles.includes(normalizedRole)) return true
    }
    if (viewKey === 'settings-mail') {
      const baseRoles = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR']
      if (baseRoles.includes(normalizedRole)) return true
    }

    // Hardcoded bypass for LEADOWIEC — API permissions may override defaults with empty arrays
    if (normalizedRole === 'LEADOWIEC') {
      const leadowiecBaseViews = ['clients', 'leadowiec-calendar', 'leadowiec-settlements', 'structure', 'quick-calculator', 'dashboard']
      if (leadowiecBaseViews.includes(viewKey)) return true
    }

    const roles = resolvedPermissions.value[viewKey] || []
    return roles.some(r => String(r).toUpperCase() === normalizedRole)
  }

  const isSettingsAllowed = (role?: string | null) => {
    if (isViewAllowed('settings', role)) return true
    return SETTINGS_TAB_KEYS.some((key) => isViewAllowed(key, role))
  }

  return {
    loading,
    loaded,
    error,
    permissions,
    resolvedPermissions,
    roles: ALL_ROLES,
    viewOptions: VIEW_OPTIONS,
    ensureLoaded,
    fetchPermissions,
    savePermissions,
    isViewAllowed,
    isSettingsAllowed,
  }
})
