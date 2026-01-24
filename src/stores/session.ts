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

  const resolveUserFromAuth = async () => {
    if (!auth.isAuthenticated) return

    if (auth.enabled) {
      try {
        currentUserId.value = null
        originalUserId.value = null
        const { data } = await api.get('/v1/me')
        apiUser.value = data
      } catch (error) {
        apiUser.value = null
      }
      return
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
  }

  const impersonate = (targetUserId: string) => {
    if (auth.enabled) return
    const actor = currentUser.value
    const list = Array.isArray(users.value) ? users.value : []
    const target = list.find((user) => user.id === targetUserId)
    if (!actor || !target) return

    data.logAction(actor.id, 'IMPERSONATE', `Rozpoczęto podgląd konta: ${target.name}`, target.id)
    originalUserId.value = actor.id
    currentUserId.value = target.id
  }

  const stopImpersonation = () => {
    if (auth.enabled) return
    if (!originalUser.value) return
    data.logAction(originalUser.value.id, 'STOP_IMPERSONATE', 'Zakończono podgląd konta', currentUser.value?.id)
    currentUserId.value = originalUser.value.id
    originalUserId.value = null
  }

  const isRole = (roles: UserRole[]) => {
    const role = currentUser.value?.role
    return role ? roles.includes(role) : false
  }

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
    isRole,
  }
})
