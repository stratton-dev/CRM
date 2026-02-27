import { defineStore, storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDataStore } from '@/stores/data'
import { useToastStore } from '@/stores/toast'
import { api } from '@/api/client'
import type { User, UserRole, AutentiDocument } from '@/types/models'

export const useStructureStore = defineStore('structure', () => {
  const auth = useAuthStore()
  const data = useDataStore()
  const toast = useToastStore()

  const { users: localUsers } = storeToRefs(data)
  const apiUsers = ref<User[]>([])
  const normalizedLocalUsers = computed<User[]>(() =>
    (Array.isArray(localUsers.value) ? localUsers.value : []).map((user) => ({
      ...user,
      parentKeycloakId: user.parentKeycloakId ?? user.parentId,
      hierarchicalId: user.hierarchicalId ?? user.hierarchicalCode,
    }))
  )
  const users = computed<User[]>(() => (auth.enabled ? apiUsers.value : normalizedLocalUsers.value))
  const teamGroups = ref<string[]>([])

  const normalizeApiUser = (user: User) => ({
    ...user,
    parentKeycloakId: user.parentKeycloakId ?? user.parentId,
    hierarchicalId: user.hierarchicalId ?? user.hierarchicalCode,
  })

  const fetchStructure = async (options?: { sync?: boolean }) => {
    if (!auth.enabled) return
    try {
      const params = options?.sync ? { sync: 1 } : undefined
      const { data } = await api.get('/v1/structure', { params })
      const normalized = Array.isArray(data) ? data.map((item) => normalizeApiUser(item)) : []
      apiUsers.value = normalized
      if (teamGroups.value.length === 0) {
        const paths = normalized.map((item) => item.teamGroupPath).filter((path): path is string => typeof path === 'string' && path.length > 0)
        teamGroups.value = Array.from(new Set(paths))
      }
    } catch (error) {
      apiUsers.value = []
      if (options?.sync) {
        throw error
      }
    }
  }

  const syncKeycloak = async () => {
    if (!auth.enabled) return
    const { data } = await api.post('/v1/admin/keycloak/sync')
    return data as { status: string; created: number; updated: number; skipped: number; errors: number; messages: string[] }
  }

  const regenerateCodes = async (resetCounters = true) => {
    if (!auth.enabled) return
    const { data } = await api.post('/v1/structure/regenerate-codes', {
      reset_counters: resetCounters,
    })
    return data as { status: string; exitCode: number; output: string }
  }

  const fetchTeams = async (options?: { refresh?: boolean }) => {
    if (!auth.enabled) return
    const params = options?.refresh ? { refresh: 1 } : undefined
    const { data } = await api.get('/v1/admin/keycloak/teams', { params })
    const paths = Array.isArray(data?.paths) ? data.paths : []
    teamGroups.value = Array.from(new Set(paths))
    return paths as string[]
  }

  const createTeam = async (code: string, displayName?: string) => {
    if (!auth.enabled) return
    const { data } = await api.post('/v1/admin/keycloak/teams', {
      code,
      display_name: displayName || undefined,
    })
    const path = (data as { path?: string }).path
    if (typeof path === 'string' && path.length > 0) {
      teamGroups.value = Array.from(new Set([...teamGroups.value, path]))
    }
    return data as { id: string; code: string; path: string; displayName?: string | null }
  }

  const deleteTeam = async (path: string) => {
    if (!auth.enabled) return
    const { data } = await api.delete('/v1/admin/keycloak/teams', { data: { path } })
    teamGroups.value = teamGroups.value.filter((item) => item !== path)
    return data as { id: string; path: string }
  }

  const restoreTeamUsers = async (teamGroupPath: string) => {
    if (!auth.enabled) return
    const { data } = await api.post('/v1/structure/teams/restore', {
      team_group_path: teamGroupPath,
    })
    return data as { total: number; restored: number; errors: { keycloak_id: string; email?: string | null; message: string }[] }
  }

  const deleteRemovedTeamUsersFromDb = async (teamGroupPath: string) => {
    if (!auth.enabled) return
    const { data } = await api.post('/v1/structure/teams/delete', {
      team_group_path: teamGroupPath,
    })
    return data as { deleted: number }
  }

  const upsertApiUser = (user: User) => {
    const normalized = normalizeApiUser(user)
    const idx = apiUsers.value.findIndex((u) => u.id === normalized.id)
    if (idx === -1) apiUsers.value = [...apiUsers.value, normalized]
    else apiUsers.value = apiUsers.value.map((u) => (u.id === normalized.id ? normalized : u))
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && isAuthed) fetchStructure()
      if (auth.enabled && !isAuthed) apiUsers.value = []
    },
    { immediate: true }
  )

  const getSubtreeUserIds = (rootUserId: string): string[] => {
    const directReports = users.value.filter((user) => user.parentKeycloakId === rootUserId && !user.isRemovedFromStructure)
    let ids = directReports.map((user) => user.id)
    directReports.forEach((child) => {
      ids = [...ids, ...getSubtreeUserIds(child.id)]
    })
    return ids
  }

  const getRoleLevel = (role: UserRole) => {
    switch (role) {
      case 'ADMIN':
        return 0
      case 'DIRECTOR':
        return 1
      case 'MANAGER':
        return 2
      case 'SALES':
        return 3
      default:
        return 99
    }
  }

  const createIdempotencyKey = () => {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
      return crypto.randomUUID()
    }
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`
  }

  const addUser = async (userData: Omit<User, 'id'> & { id?: string }, initiatorId: string, idempotencyKey?: string) => {
    if (auth.enabled) {
      const key = idempotencyKey || createIdempotencyKey()
      const payload = {
        name: userData.name,
        email: userData.email,
        phone: userData.phone || null,
        role: userData.role,
        parent_keycloak_id: userData.parentKeycloakId || null,
        team_group_path: userData.teamGroupPath || null,
        contract_status: userData.contractStatus || 'DRAFT',
        type: userData.type || null,
        address_json: userData.addressData || null,
        documents_json: userData.documents || null,
        hierarchical_preview: userData.hierarchicalId || null,
        keycloak_id: userData.id,
      }
      try {
        const { data: created } = await api.post('/v1/users', payload, {
          headers: { 'Idempotency-Key': key },
        })
        upsertApiUser(created)
        return created as User
      } catch (error) {
        const status = (error as any)?.response?.status
        if (!idempotencyKey && (!status || status >= 500)) {
          return addUser(userData, initiatorId, key)
        }
        throw error
      }
    }

    const newUser: User = {
      ...userData,
      parentId: userData.parentKeycloakId ?? userData.parentId,
      id: Math.random().toString(36).substr(2, 9),
      contractStatus: 'DRAFT',
      rank: 'JUNIOR',
      points: 0,
      isRemovedFromStructure: false,
    }

    data.rawAddUser(newUser)
    data.logAction(initiatorId, 'ADD_USER', `Dodano użytkownika ${newUser.name} (${newUser.role})`, newUser.id)

    initiateAutentiProcess(newUser, initiatorId)
    return newUser
  }

  const getInitials = (name?: string) => {
    if (!name) return '??'
    const parts = name.trim().split(' ')
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
    return parts.map((part) => part[0]).join('').toUpperCase().substring(0, 2)
  }

  const moveUser = async (userId: string, newParentId: string | null, initiatorId: string, newTeamGroupPath?: string | null) => {
    const userToMove = users.value.find((user) => user.id === userId)
    const newParent = newParentId ? users.value.find((user) => user.id === newParentId) : null

    if (!userToMove || (newParentId && !newParent)) {
      toast.error('Nie można przenieść użytkownika: nie znaleziono danych.')
      return
    }

    if (auth.enabled) {
      const { data: updated } = await api.post('/v1/structure/move', {
        user_keycloak_id: userId,
        new_parent_keycloak_id: newParentId,
        new_team_group_path: newTeamGroupPath ?? null,
      })
      upsertApiUser(updated)
      return
    }

    if (!newParent) {
      toast.error('W trybie lokalnym nie można przenieść użytkownika do zespołu bez przełożonego.')
      return
    }
    const oldParent = users.value.find((user) => user.id === userToMove.parentKeycloakId)
    const initials = getInitials(userToMove.name)
    const newHierarchicalId = newParent.hierarchicalId
      ? `${newParent.hierarchicalId}/${initials}`
      : `${(newParent.addressData?.city?.substring(0, 2) || 'XX').toUpperCase()}${initials}`

    const updateChildrenIds = async (parentId: string, parentHierarchicalId: string) => {
      const children = users.value.filter((user) => user.parentKeycloakId === parentId)
      for (const child of children) {
        const childInitials = getInitials(child.name)
        const childNewHierarchicalId = `${parentHierarchicalId}/${childInitials}`
        data.rawUpdateUser(child.id, { hierarchicalId: childNewHierarchicalId })
        await updateChildrenIds(child.id, childNewHierarchicalId)
      }
    }

    data.rawUpdateUser(userId, { parentId: newParentId, hierarchicalId: newHierarchicalId })
    await updateChildrenIds(userToMove.id, newHierarchicalId)

    data.logAction(initiatorId, 'MOVE_USER', `Przeniesiono ${userToMove.name} od ${oldParent?.name || 'Root'} do ${newParent.name}`)
  }

  const restoreUser = async (user: User) => {
    if (!auth.enabled) return user
    const { data: restored } = await api.post('/v1/structure/restore', {
      user_keycloak_id: user.id,
    })

    if (restored.id && restored.id !== user.id) {
      apiUsers.value = apiUsers.value.filter((item) => item.id !== user.id)
    }
    upsertApiUser(restored)
    return restored as User
  }

  const fetchGusData = async (nip: string) => {
    if (!auth.enabled) {
      throw new Error('GUS lookup requires API mode.')
    }
    const { data } = await api.get('/v1/gus', {
      params: { nip },
    })
    return data as {
      name: string
      street: string
      houseNr: string
      aptNr?: string
      zipCode: string
      city: string
      regon: string
      krs?: string | null
      email?: string | null
      phone?: string | null
      fax?: string | null
      website?: string | null
    }
  }

  const initiateAutentiProcess = (user: User, initiatorId: string) => {
    updateUserContractStatus(user.id, 'SENT_TO_AUTENTI', initiatorId)

    const docs = []
    if (user.documents?.nda) docs.push('NDA')
    if (user.documents?.cooperationAgreement) docs.push('Umowa Współpracy')
    if (user.documents?.careerPath) docs.push('Ścieżka Kariery')
    if (user.documents?.otherFileName) docs.push(user.documents.otherFileName)

    const docString = docs.join(', ')

    const autentiDoc: AutentiDocument = {
      id: `aut-${user.id}`,
      recipientName: user.name,
      recipientEmail: user.email,
      documentList: docString,
      status: 'SENT',
      sentDate: new Date().toISOString(),
      userId: user.id,
      initiatorId,
    }
    data.rawAddAutentiDocument(autentiDoc)

    toast.info(`Autenti: Wysłano dokumenty (${docString}) do ${user.email}.`)
    data.logAction(initiatorId, 'AUTENTI_SENT', `Wysłano dokumenty do ${user.name}`, user.id)
  }

  const updateUserAdmin = async (userId: string, partial: Partial<User>, initiatorId: string) => {
    const oldUser = users.value.find((user) => user.id === userId)
    if (!oldUser) return

    if (auth.enabled) {
      const payload: Record<string, unknown> = {
        name: partial.name,
        email: partial.email,
        phone: partial.phone,
        role: partial.role,
        parent_id: partial.parentId,
        hierarchical_id: partial.hierarchicalId,
        crm_number: partial.crmNumber,
        rank: partial.rank,
        contract_status: partial.contractStatus,
        type: partial.type,
        address_json: partial.addressData,
        documents_json: partial.documents,
        is_removed_from_structure: partial.isRemovedFromStructure,
        is_blocked: partial.isBlocked,
        points: partial.points,
        active: partial.active,
      }
      if (partial.renewalCommissionRate !== undefined) {
        payload.renewal_commission_rate = partial.renewalCommissionRate
      }
      if (partial.overrideCommissionRate !== undefined) {
        payload.override_commission_rate = partial.overrideCommissionRate
      }
      const { data: updated } = await api.patch(`/v1/users/${userId}`, payload)
      upsertApiUser(updated)
      return
    }

    data.rawUpdateUser(userId, partial)
    data.logAction(initiatorId, 'UPDATE_USER', `Zaktualizowano dane użytkownika ${oldUser.name}`, userId)
  }

  const updateUserProfile = async (userId: string, partial: Pick<User, 'name' | 'phone'>) => {
    if (auth.enabled) {
      const { data: updated } = await api.patch(`/v1/users/${userId}`, {
        name: partial.name,
        phone: partial.phone,
      })
      upsertApiUser(updated)
      return
    }
    data.rawUpdateUser(userId, partial)
  }

  const changePassword = async (userId: string, newPass: string) => {
    if (auth.enabled) return
    data.rawUpdateUser(userId, { password: newPass })
  }

  const updateUserContractStatus = async (userId: string, status: User['contractStatus'], initiatorId?: string) => {
    if (auth.enabled) {
      const { data: updated } = await api.patch(`/v1/users/${userId}`, {
        contract_status: status,
      })
      upsertApiUser(updated)
      return
    }
    data.rawUpdateUser(userId, { contractStatus: status })
    if (initiatorId) {
      return
    }
  }

  const removeUserFromStructure = async (userToRemove: User, initiator: User) => {
    if (auth.enabled) {
      const { data: updated } = await api.post('/v1/structure/remove', {
        user_keycloak_id: userToRemove.id,
      })
      upsertApiUser(updated)
    } else {
      data.rawUpdateUser(userToRemove.id, { isRemovedFromStructure: true })
      data.logAction(initiator.id, 'REMOVE_USER', `Usunięto ze struktury: ${userToRemove.name}`, userToRemove.id)
    }

    return true
  }

  const toggleBlockUser = async (user: User, initiatorId: string) => {
    const newState = !user.isBlocked
    if (auth.enabled) {
      const { data: updated } = await api.patch(`/v1/users/${user.id}`, {
        is_blocked: newState,
      })
      upsertApiUser(updated)
      return
    }
    data.rawUpdateUser(user.id, { isBlocked: newState })
    data.logAction(initiatorId, newState ? 'BLOCK_USER' : 'UNBLOCK_USER', `Zmieniono blokadę dla ${user.name}`, user.id)
  }

  const canAddUnder = (currentUser: User, targetNode: User) => {
    if (targetNode.role === 'SALES') return false
    if (currentUser.role === 'ADMIN') {
      if (targetNode.role === 'CLIENT_HR') {
        return typeof targetNode.id === 'string' && targetNode.id.startsWith('team:')
      }
      return true
    }
    if (currentUser.role === 'DIRECTOR') {
      return targetNode.id === currentUser.id || targetNode.role === 'MANAGER'
    }
    if (currentUser.role === 'MANAGER') return targetNode.id === currentUser.id
    return false
  }

  const canImpersonate = (currentUser: User, targetNode: User) => {
    // Super Admin / Admin has full access, can impersonate anyone including self/other admins
    if (currentUser.role === 'ADMIN') return true

    if (targetNode.id === currentUser.id) return false
    if (targetNode.role === 'CLIENT_HR') return false

    const myLevel = getRoleLevel(currentUser.role)
    const targetLevel = getRoleLevel(targetNode.role)
    return myLevel < targetLevel
  }

  const canRemove = (currentUser: User, targetNode: User) => {
    // Super Admin / Admin has full access
    if (currentUser.role === 'ADMIN') return true
    
    if (currentUser.role === 'DIRECTOR') {
      const descendants = getSubtreeUserIds(currentUser.id)
      return descendants.includes(targetNode.id)
    }
    return false
  }

  return {
    users,
    teamGroups,
    fetchStructure,
    syncKeycloak,
    fetchTeams,
    createTeam,
    deleteTeam,
    restoreTeamUsers,
    deleteRemovedTeamUsersFromDb,
    regenerateCodes,
    getSubtreeUserIds,
    getRoleLevel,
    getInitials,
    addUser,
    moveUser,
    restoreUser,
    fetchGusData,
    initiateAutentiProcess,
    updateUserAdmin,
    updateUserProfile,
    changePassword,
    updateUserContractStatus,
    removeUserFromStructure,
    toggleBlockUser,
    canAddUnder,
    canImpersonate,
    canRemove,
  }
})
