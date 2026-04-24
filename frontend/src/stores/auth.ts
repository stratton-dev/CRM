import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { createClient, type SupabaseClient, type User as SupabaseUser } from '@supabase/supabase-js'

type UserProfile = {
  username?: string
  firstName?: string
  lastName?: string
  email?: string
  roles?: string[]
  id?: string
  hierarchicalId?: string
  crmNumber?: string
}

function buildSupabaseClient(): SupabaseClient {
  const url = import.meta.env.VITE_SUPABASE_URL as string
  const key = import.meta.env.VITE_SUPABASE_ANON_KEY as string
  if (!url || !key) {
    console.warn('[AUTH] Brak VITE_SUPABASE_URL lub VITE_SUPABASE_ANON_KEY w .env')
  }
  return createClient(url || '', key || '')
}

const supabase = buildSupabaseClient()

function mapSupabaseUser(sbUser: SupabaseUser): UserProfile {
  const meta = (sbUser.user_metadata || {}) as Record<string, any>
  const appMeta = (sbUser.app_metadata || {}) as Record<string, any>
  const role = appMeta.role || meta.role
  return {
    id: sbUser.id,
    email: sbUser.email,
    username: meta.username || sbUser.email,
    firstName: meta.first_name || meta.firstName,
    lastName: meta.last_name || meta.lastName,
    roles: role ? [role] : [],
  }
}

export const useAuthStore = defineStore('auth', () => {
  const initializing = ref(false)
  const isAuthenticated = ref(false)
  const token = ref<string | null>(null)
  const user = ref<UserProfile | null>(null)
  const error = ref<string | null>(null)
  const initAttempted = ref(false)

  const fullName = computed(() => {
    if (!user.value) return ''
    const fn = [user.value.firstName, user.value.lastName].filter(Boolean).join(' ')
    return fn || user.value.username || user.value.email || ''
  })

  // Always enabled – Supabase doesn't require env toggle
  const enabled = computed(() => true)

  function saveToken(t: string | null) {
    token.value = t
    if (t) localStorage.setItem('crm_token', t)
    else localStorage.removeItem('crm_token')
  }

  async function init() {
    if (initializing.value) return
    if (initAttempted.value) return
    initializing.value = true
    error.value = null
    try {
      const { data, error: sbError } = await supabase.auth.getSession()
      if (sbError) throw sbError
      const session = data.session
      if (session) {
        isAuthenticated.value = true
        saveToken(session.access_token)
        user.value = mapSupabaseUser(session.user)
      }
      supabase.auth.onAuthStateChange((_event, session) => {
        if (session) {
          isAuthenticated.value = true
          saveToken(session.access_token)
          user.value = mapSupabaseUser(session.user)
        } else {
          isAuthenticated.value = false
          saveToken(null)
          user.value = null
        }
      })
    } catch (e: any) {
      error.value = e?.message || 'Nie udało się zainicjalizować logowania'
    } finally {
      initializing.value = false
      initAttempted.value = true
    }
  }

  async function ensureInitialized() {
    if (initAttempted.value) return
    await init()
  }

  async function login(email: string, password: string) {
    error.value = null
    const { data, error: sbError } = await supabase.auth.signInWithPassword({ email, password })
    if (sbError) throw new Error(sbError.message)
    if (data.session) {
      isAuthenticated.value = true
      saveToken(data.session.access_token)
      user.value = mapSupabaseUser(data.user!)
    }
  }

  async function logout() {
    await supabase.auth.signOut()
    isAuthenticated.value = false
    user.value = null
    saveToken(null)
  }

  return {
    // state
    enabled,
    initializing,
    isAuthenticated,
    token,
    user,
    error,
    fullName,
    // methods
    init,
    ensureInitialized,
    login,
    logout,
  }
})
