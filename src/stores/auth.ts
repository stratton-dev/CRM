import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { createClient, type SupabaseClient, type User as SupabaseUser } from '@supabase/supabase-js'

const SUPABASE_URL = import.meta.env.VITE_SUPABASE_URL as string
const SUPABASE_ANON_KEY = import.meta.env.VITE_SUPABASE_ANON_KEY as string

let supabaseInstance: SupabaseClient | null = null
const getSupabase = (): SupabaseClient => {
  if (!supabaseInstance) {
    supabaseInstance = createClient(SUPABASE_URL, SUPABASE_ANON_KEY)
  }
  return supabaseInstance
}

type UserProfile = {
  id?: string
  email?: string
  username?: string
  firstName?: string
  lastName?: string
  role?: string
  roles?: string[]
}

function mapSupabaseUser(u: SupabaseUser): UserProfile {
  const meta = u.user_metadata || {}
  const appMeta = (u as any).app_metadata || {}
  const fullName: string = meta.full_name || meta.name || ''
  const role: string | undefined = appMeta.role || meta.role || undefined
  return {
    id: u.id,
    email: u.email,
    username: u.email,
    firstName: meta.first_name || meta.firstName || fullName.split(' ')[0] || undefined,
    lastName: meta.last_name || meta.lastName || fullName.split(' ').slice(1).join(' ') || undefined,
    role,
    roles: role ? [role] : [],
  }
}

export const useAuthStore = defineStore('auth', () => {
  const enabled = computed(() => String(import.meta.env.VITE_AUTH_ENABLED || 'false') === 'true')

  const initializing = ref(false)
  const isAuthenticated = ref(false)
  const token = ref<string | null>(localStorage.getItem('crm_token'))
  const user = ref<UserProfile | null>(null)
  const error = ref<string | null>(null)

  const fullName = computed(() => {
    if (!user.value) return ''
    const fn = [user.value.firstName, user.value.lastName].filter(Boolean).join(' ')
    return fn || user.value.username || user.value.email || ''
  })

  let listenerSetUp = false

  function saveToken(t: string | null) {
    token.value = t
    if (t) localStorage.setItem('crm_token', t)
    else localStorage.removeItem('crm_token')
  }

  async function init() {
    if (!enabled.value) return
    if (initializing.value) return

    initializing.value = true
    error.value = null

    try {
      const supabase = getSupabase()

      if (!listenerSetUp) {
        listenerSetUp = true
        supabase.auth.onAuthStateChange((_event, session) => {
          isAuthenticated.value = !!session
          saveToken(session?.access_token ?? null)
          user.value = session?.user ? mapSupabaseUser(session.user) : null
        })
      }

      const { data: { session }, error: sessionError } = await supabase.auth.getSession()
      if (sessionError) throw sessionError

      isAuthenticated.value = !!session
      saveToken(session?.access_token ?? null)
      user.value = session?.user ? mapSupabaseUser(session.user) : null
    } catch (e: any) {
      console.error('[AUTH] init error:', e)
      error.value = e?.message || 'Błąd inicjalizacji autoryzacji'
    } finally {
      initializing.value = false
    }
  }

  async function ensureInitialized() {
    if (!enabled.value) return
    await init()
  }

  async function login(email: string, password: string) {
    if (!enabled.value) {
      isAuthenticated.value = true
      user.value = { username: 'dev', email: 'dev@example.com' }
      saveToken('dev-token')
      return
    }

    error.value = null
    initializing.value = true
    try {
      const supabase = getSupabase()
      const { data, error: loginError } = await supabase.auth.signInWithPassword({ email, password })
      if (loginError) throw loginError

      isAuthenticated.value = true
      saveToken(data.session?.access_token ?? null)
      user.value = data.user ? mapSupabaseUser(data.user) : null
    } catch (e: any) {
      error.value = e?.message || 'Błąd logowania'
      throw e
    } finally {
      initializing.value = false
    }
  }

  async function logout() {
    if (!enabled.value) {
      isAuthenticated.value = false
      user.value = null
      saveToken(null)
      return
    }

    const supabase = getSupabase()
    await supabase.auth.signOut()
    isAuthenticated.value = false
    user.value = null
    saveToken(null)
  }

  async function getToken(): Promise<string | null> {
    if (!enabled.value) return null
    const supabase = getSupabase()
    const { data: { session } } = await supabase.auth.getSession()
    if (session?.access_token) {
      saveToken(session.access_token)
      return session.access_token
    }
    // Try to refresh
    const { data: refreshData } = await supabase.auth.refreshSession()
    if (refreshData?.session?.access_token) {
      saveToken(refreshData.session.access_token)
      return refreshData.session.access_token
    }
    return null
  }

  return {
    enabled,
    initializing,
    isAuthenticated,
    token,
    user,
    error,
    fullName,
    init,
    ensureInitialized,
    login,
    logout,
    getToken,
  }
})
