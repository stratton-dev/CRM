import { defineStore } from 'pinia'
import { ref, computed, shallowRef, markRaw } from 'vue'
import Keycloak from 'keycloak-js'

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

type AuthConfig = {
  enabled: boolean
  url: string | null
  realm: string | null
  clientId: string | null
}

function readConfig(): AuthConfig {
  const enabled = String(import.meta.env.VITE_AUTH_ENABLED || 'false') === 'true'
  return {
    enabled,
    url: import.meta.env.VITE_KEYCLOAK_URL ?? null,
    realm: import.meta.env.VITE_KEYCLOAK_REALM ?? null,
    clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID ?? null,
  }
}

export const useAuthStore = defineStore('auth', () => {
  const config = readConfig()

  const debugEnabled = String(import.meta.env.VITE_AUTH_DEBUG || 'false') === 'true'
  const dbg = (...args: any[]) => {
    if (debugEnabled) console.debug('[AUTH]', ...args)
  }

  const initializing = ref(false)
  const isAuthenticated = ref(false)
  const token = ref<string | null>(localStorage.getItem('crm_token'))
  const user = ref<UserProfile | null>(null)
  const error = ref<string | null>(null)

  const keycloak = shallowRef<Keycloak | null>(null)
  const initAttempted = ref(false)

  const fullName = computed(() => {
    if (!user.value) return ''
    const fn = [user.value.firstName, user.value.lastName].filter(Boolean).join(' ')
    return fn || user.value.username || user.value.email || ''
  })

  function validateConfig(): string | null {
    if (!config.enabled) return null
    if (!config.url) return 'Brak VITE_KEYCLOAK_URL'
    if (!config.realm) return 'Brak VITE_KEYCLOAK_REALM'
    if (!config.clientId) return 'Brak VITE_KEYCLOAK_CLIENT_ID'
    return null
  }

  function saveToken(t: string | undefined | null) {
    token.value = t || null
    if (t) localStorage.setItem('crm_token', t)
    else localStorage.removeItem('crm_token')
  }

  async function init() {
    if (!config.enabled) return
    if (initializing.value) return
    // Already initialized? Just return. login() will use existing instance.
    if (keycloak.value && initAttempted.value) return

    initializing.value = true
    error.value = null
    try {
      const cfgErr = validateConfig()
      if (cfgErr) {
        error.value = `Konfiguracja logowania niepełna: ${cfgErr}`
        return
      }
      const kc = new Keycloak({
        url: config.url!,
        realm: config.realm!,
        clientId: config.clientId!
      })
      keycloak.value = markRaw(kc)

            const initOptions = {
        onLoad: 'check-sso',
        pkceMethod: 'S256',
        checkLoginIframe: false,
        enableLogging: true
      }
      
      console.log('[AUTH] Starting Keycloak init with options:', initOptions)

      try {
        const authenticated = await kc.init(initOptions as any)
        dbg('kc.init authenticated =', authenticated)
        isAuthenticated.value = authenticated
        saveToken(kc.token)
        if (authenticated) {
            user.value = kc.tokenParsed as any
            setTokenRefresh()
        }
        kc.onTokenExpired = async () => {
             try {
                await kc.updateToken(30)
                saveToken(kc.token)
             } catch (e) {
                dbg('Token refresh failed, logging out', e)
                await logout()
             }
         }
      } catch (innerError) {
          console.error('[AUTH] INIT FAILED:', innerError)
          throw innerError
      }
    } catch (e: any) {
      console.error(e)
      error.value = e?.message || 'Nie udało się zainicjalizować logowania'
      // Ensure keycloak is reset if init failed so retry is possible
      keycloak.value = null
      initializing.value = false
    } finally {
      // Don't set initializing to false here if we want to allow retry,
      // but initAttempted should be true only if success? No, it means we tried.
      initializing.value = false
      initAttempted.value = true
    }
  }

  function setTokenRefresh() {
    const kc = keycloak.value
    if (!kc) return
    // co 20s spróbuj odświeżyć gdy krótszy niż 60s
    const interval = setInterval(async () => {
      if (!keycloak.value) return clearInterval(interval)
      try {
        const refreshed = await kc.updateToken(60)
        if (refreshed) saveToken(kc.token)
      } catch (e) {
        dbg('Periodic token refresh failed', e)
        await logout()
      }
    }, 20000)
  }

  async function ensureInitialized() {
    if (!config.enabled) return
    if (initAttempted.value) return
    await init()
  }

  async function login(redirectUri?: string) {
    if (!config.enabled) {
      // DEV: autoryzacja wyłączona – przepuść użytkownika
      isAuthenticated.value = true
      user.value = { username: 'dev', email: 'dev@example.com' }
      saveToken('dev-token')
      return
    }
    await init()
    const kc = keycloak.value
    if (!kc) throw new Error('Keycloak nie jest zainicjalizowany')
    await kc.login({ redirectUri })
  }

  async function logout(redirectUri?: string) {
    if (!config.enabled) {
      isAuthenticated.value = false
      user.value = null
      saveToken(null)
      return
    }
    const kc = keycloak.value
    isAuthenticated.value = false
    user.value = null
    saveToken(null)
    if (kc) await kc.logout({ redirectUri: redirectUri || window.location.origin + '/login' })
  }

  const enabled = computed(() => config.enabled)

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
