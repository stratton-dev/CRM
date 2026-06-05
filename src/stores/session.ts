import { defineStore, storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDataStore } from '@/stores/data'
import { api } from '@/api/client'
import type { User, UserRole } from '@/types/models'

const CURRENT_USER_KEY = 'stratton_session_user'
const ORIGINAL_USER_KEY = 'stratton_session_original'

export const useSessionStore = defineStore('session', () => {
  const auth = useAuthStore()
  const data = useDataStore()
  const { users } = storeToRefs(data)

  const currentUserId = ref<string | null>(localStorage.getItem(CURRENT_USER_KEY))
  const originalUserId = ref<string | null>(localStorage.getItem(ORIGINAL_USER_KEY))

  const apiUser = ref<User | null>(null)

  const localCurrentUser = computed<User | null>(() => {
    if (!currentUserId.value) return null
    const list = Array.isArray(users.value) ? users.value : []
    return list.find((user) => user.id === currentUserId.value) || null
  })

  const currentUser = computed<User | null>(() => (auth.enabled ? apiUser.value : localCurrentUser.value))

  const originalUser = computed<User | null>(() => {
    if (!originalUserId.value) return null
    const list = Array.isArray(users.value) ? users.value : []
    return list.find((user) => user.id === originalUserId.value) || null
  })

  const isImpersonating = computed(() => originalUserId.value !== null)
  const isReadOnly = computed(() => isImpersonating.value)

  let inflightMe: Promise<void> | null = null

  const resolveUserFromAuth = async (options?: { force?: boolean }) => {
    if (!auth.isAuthenticated) return

    if (auth.enabled) {
      // Dedup: profil już pobrany i nie wymuszamy odświeżenia (np. impersonacja) → pomiń.
      if (!options?.force && apiUser.value) return
      // Dedup: scal równoległe wywołania w jeden request /v1/me.
      // (5 triggerów: 2 watchery + guard + App.vue + login → wcześniej 5× /v1/me
      //  do wolnego backendu Railway; teraz 1.)
      if (inflightMe) return inflightMe
      inflightMe = (async () => {
        try {
          currentUserId.value = null
          const impersonationId = localStorage.getItem('x_impersonate_user')
          originalUserId.value = impersonationId ? 'ADMIN_MARKER' : null

          const { data } = await api.get('/v1/me')
          apiUser.value = data
        } catch (error) {
          apiUser.value = null
        } finally {
          inflightMe = null
        }
      })()
      return inflightMe
    }

    const list = Array.isArray(users.value) ? users.value : []
    if (list.length === 0) return
    const known = list.find((user) => user.id === currentUserId.value)
    if (known) return

    const email = auth.user?.email || ''
    const username = auth.user?.username || ''
    const match = list.find((user) => user.email === email || user.email === username)
    currentUserId.value = match?.id || list[0]?.id || null
  }

  const setCurrentUser = (userId: string | null) => {
    if (auth.enabled) return
    currentUserId.value = userId
  }

  const clearSession = () => {
    currentUserId.value = null
    originalUserId.value = null
    apiUser.value = null
    localStorage.removeItem('x_impersonate_user')
  }

  const impersonate = async (targetUserId: string) => {
    if (auth.enabled) {
      localStorage.setItem('x_impersonate_user', targetUserId)
      await resolveUserFromAuth({ force: true })
      return
    }

    const actor = currentUser.value
    const list = Array.isArray(users.value) ? users.value : []
    const target = list.find((user) => user.id === targetUserId)
    if (!actor || !target) return

    data.logAction(actor.id, 'IMPERSONATE', `Rozpoczęto podgląd konta: ${target.name}`, target.id)
    originalUserId.value = actor.id
    currentUserId.value = target.id
  }

  const stopImpersonation = async () => {
    if (auth.enabled) {
      localStorage.removeItem('x_impersonate_user')
      await resolveUserFromAuth({ force: true })
      return
    }

    if (!originalUser.value) return
    data.logAction(originalUser.value.id, 'STOP_IMPERSONATE', 'Zakończono podgląd konta', currentUser.value?.id)
    currentUserId.value = originalUser.value.id
    originalUserId.value = null
  }

  const isRole = (roles: UserRole[]) => {
    const role = currentUser.value?.role
    return role ? roles.includes(role) : false
  }

  const isLeadowiec = computed(() => currentUser.value?.role === 'LEADOWIEC')

  const isOpiekunOrAbove = computed(() =>
    ['SALES', 'MANAGER', 'DIRECTOR', 'ADMIN'].includes(currentUser.value?.role ?? '')
  )

  watch(currentUserId, (value) => {
    if (value) localStorage.setItem(CURRENT_USER_KEY, value)
    else localStorage.removeItem(CURRENT_USER_KEY)
  })

  watch(originalUserId, (value) => {
    if (value) localStorage.setItem(ORIGINAL_USER_KEY, value)
    else localStorage.removeItem(ORIGINAL_USER_KEY)
  })

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (!isAuthed && auth.enabled) {
        clearSession()
      }
      if (isAuthed) resolveUserFromAuth()
    },
    { immediate: true }
  )

  watch(
    () => auth.user,
    () => resolveUserFromAuth(),
    { immediate: true }
  )

  return {
    currentUserId,
    currentUser,
    originalUser,
    isImpersonating,
    isReadOnly,
    setCurrentUser,
    clearSession,
    impersonate,
    stopImpersonation,
    resolveUserFromAuth,
    impersonatedUser: computed(() => (auth.enabled && originalUserId.value ? apiUser.value : null)),
    isRole,
    isLeadowiec,
    isOpiekunOrAbove,
  }
})
