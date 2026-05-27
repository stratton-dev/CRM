<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useStructureStore } from '@/stores/structure'
import { useSessionStore } from '@/stores/session'
import { useNotificationStore } from '@/stores/notification'
import { useMailboxStore } from '@/stores/mailbox'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'
import StructureChartView from '@/components/StructureChartView.vue'
import type { EntityType, User, UserRole } from '@/types/models'

type TreeNode = User & { level: number; hasChildren: boolean; isLast: boolean; parentChain: boolean[]; isTeamNode?: boolean }

defineProps<{
  embedded?: boolean
}>()

const router = useRouter()
const structure = useStructureStore()
const session = useSessionStore()
const notify = useNotificationStore()
const mailbox = useMailboxStore()
const toast = useToastStore()

const viewMode = ref<'list' | 'chart'>('list')
const searchQuery = ref('')
const selectedNodeId = ref<string | null>(null)
const draggedNode = ref<User | null>(null)
const dropTargetNodeId = ref<string | null>(null)
const expandedNodes = ref<Set<string>>(new Set())

const showAddModal = ref(false)
const showMsgModal = ref(false)
const showImpersonateModal = ref(false)
const showRemoveModal = ref(false)
const showTeamModal = ref(false)
const showRegenerateModal = ref(false)
const showDeleteTeamModal = ref(false)

const targetParent = ref<User | null>(null)
const selectedUser = ref<User | null>(null)
const userToImpersonate = ref<User | null>(null)
const userToRemove = ref<User | null>(null)
const teamToDelete = ref<User | null>(null)

const isSending = ref(false)
const isFetchingGus = ref(false)
const isCreatingTeam = ref(false)
const selectedDirectorTeam = ref('')
const isSyncing = ref(false)
const isRegenerating = ref(false)
const hasSynced = ref(false)
const isLoadingStructure = ref(false)
const showInactive = ref(false)
const resetCounters = ref(true)

const msgData = reactive({
  type: 'TASK' as 'TASK' | 'NOTE' | 'INFO' | 'WARNING',
  text: '',
})

const newTeamBase = ref('')
const computedTeamCode = computed(() => {
  const base = newTeamBase.value.trim()
  if (!base) return ''
  const cleanBase = base.replace(/[^a-zA-Z0-9]/g, '')
  const prefix = cleanBase.toUpperCase().slice(0, 3)
  if (!prefix) return ''
  const existingCodes = (Array.isArray(teamGroups.value) ? teamGroups.value : [])
    .map((path) => path.split('/').filter(Boolean).pop() || '')
    .filter(Boolean)

  let maxSuffix = 0

  existingCodes.forEach((code) => {
    const cleanCode = code.replace(/[^a-zA-Z0-9]/g, '')
    const codePrefix = cleanCode.toUpperCase().slice(0, 3)
    if (codePrefix !== prefix) {
      return
    }

    const match = code.match(/(\d+)$/)
    if (match) {
      maxSuffix = Math.max(maxSuffix, Number(match[1]))
    }
  })

  const next = maxSuffix + 1 || 1
  return `${prefix}${String(next).padStart(3, '0')}`
})

const newUserData = reactive({
  type: 'PRIVATE' as EntityType,
  role: 'DIRECTOR' as UserRole,
  email: '',
  phone: '',
  name: '',
  firstName: '',
  lastName: '',
  pesel: '',
  nip: '',
  regon: '',
  krs: '',
  address: {
    street: '',
    houseNr: '',
    aptNr: '',
    zipCode: '',
    city: '',
  },
})

const { users, teamGroups, isLoading: isStructureFetching, fetchError: structureFetchError } = storeToRefs(structure)
const { currentUser } = storeToRefs(session)

const isTeamNode = (node?: User | null) => Boolean(node && (node as TreeNode).isTeamNode)

const regenerateCodes = async () => {
  if (currentUser.value?.role !== 'ADMIN') return
  isRegenerating.value = true
  try {
    const result = await structure.regenerateCodes(resetCounters.value)
    if (result?.status === 'ok') {
      toast.success('Kody struktury zostały przeliczone.')
      await structure.fetchStructure()
    } else {
      toast.error('Nie udało się przeliczyć kodów struktury.')
    }
  } catch (error) {
    toast.error('Nie udało się przeliczyć kodów struktury.')
  } finally {
    isRegenerating.value = false
    showRegenerateModal.value = false
  }
}

const openRegenerateModal = () => {
  resetCounters.value = true
  showRegenerateModal.value = true
}

const openDeleteTeamModal = (node: User) => {
  if (!node.teamGroupPath) return
  teamToDelete.value = node
  showDeleteTeamModal.value = true
}

const confirmDeleteTeam = async () => {
  if (currentUser.value?.role !== 'ADMIN') return
  const path = teamToDelete.value?.teamGroupPath
  if (!path) return
  try {
    await structure.deleteTeam(path)
    toast.success('Zespół został usunięty.')
    await structure.fetchTeams({ refresh: true })
    await structure.fetchStructure()
  } catch (error: any) {
    const message = error?.response?.data?.message
    if (message) toast.error(message)
    else toast.error('Nie udało się usunąć zespołu.')
  } finally {
    showDeleteTeamModal.value = false
    teamToDelete.value = null
  }
}

const deleteRemovedUsersPermanently = async (node: User) => {
  if (currentUser.value?.role !== 'ADMIN') return
  if (!node.teamGroupPath) return
  if (!window.confirm(`Usunąć trwale wszystkich usuniętych w zespole ${node.name}?`)) return
  try {
    const result = await structure.deleteRemovedTeamUsersFromDb(node.teamGroupPath)
    const deleted = result?.deleted ?? 0
    if (deleted > 0) {
      toast.success(`Usunięto trwale: ${deleted} użytkowników.`)
    } else {
      toast.info('Brak użytkowników do trwałego usunięcia.')
    }
    await structure.fetchStructure()
  } catch {
    toast.error('Nie udało się usunąć użytkowników trwale.')
  }
}

const restoreTeamUsers = async (node: User) => {
  if (currentUser.value?.role !== 'ADMIN') return
  if (!node.teamGroupPath) return
  if (!window.confirm(`Przywrócić wszystkich usuniętych w zespole ${node.name}?`)) return
  try {
    const result = await structure.restoreTeamUsers(node.teamGroupPath)
    const restored = result?.restored ?? 0
    const total = result?.total ?? 0
    if (restored > 0) {
      toast.success(`Przywrócono ${restored}/${total} użytkowników.`)
    } else {
      toast.info('Brak użytkowników do przywrócenia.')
    }
    if (result?.errors?.length) {
      toast.warning(`Nie przywrócono: ${result.errors.length} użytkowników.`)
    }
    await structure.fetchStructure()
  } catch {
    toast.error('Nie udało się przywrócić użytkowników zespołu.')
  }
}

watch(
  () => users.value,
  (userList) => {
    const list = Array.isArray(userList) ? userList : []
    if (expandedNodes.value.size === 0) {
      list.forEach((user) => expandedNodes.value.add(user.id))
    }
  },
  { immediate: true }
)

watch(
  () => currentUser.value,
  async (user) => {
    if (!user || user.role !== 'ADMIN' || hasSynced.value) return
    hasSynced.value = true
    isLoadingStructure.value = true
    try {
      await structure.fetchStructure({ sync: true })
      await structure.fetchTeams({ refresh: true })
    } catch {
      // ignore initial sync errors
    } finally {
      isLoadingStructure.value = false
    }
  },
  { immediate: true }
)

const canAddGlobal = computed(() => currentUser.value?.role === 'ADMIN')
const roleOptions: UserRole[] = ['DIRECTOR', 'MANAGER', 'SALES']
const roleOverrides = ref<Record<string, UserRole>>({})
const parentOverrides = ref<Record<string, string | null | undefined>>({})
const movingNodeId = ref<string | null>(null)

const getParentValue = (node: User): string =>
  node.id in parentOverrides.value
    ? (parentOverrides.value[node.id] ?? '')
    : (node.parentSupabaseId ?? '')

const setParentValue = (node: User, val: string) => {
  parentOverrides.value = { ...parentOverrides.value, [node.id]: val || null }
}

const validParentsFor = (node: User): User[] => {
  const allUsers = Array.isArray(users.value) ? users.value : []
  const descendants = new Set(structure.getSubtreeUserIds(node.id))
  const roleMap: Record<string, UserRole[]> = {
    MANAGER: ['DIRECTOR'],
    SALES: ['MANAGER'],
    LEADOWIEC: ['SALES', 'MANAGER', 'DIRECTOR'],
  }
  const allowed = roleMap[node.role] ?? []
  return allUsers.filter(
    (u) => allowed.includes(u.role as UserRole) && u.id !== node.id && !descendants.has(u.id)
  )
}

const moveToParent = async (node: User) => {
  if (!currentUser.value || movingNodeId.value) return
  const newParentId = getParentValue(node) || null
  if (newParentId === (node.parentSupabaseId ?? null)) {
    toast.warning('Wybierz innego przełożonego.')
    return
  }
  movingNodeId.value = node.id
  try {
    await structure.moveUser(node.id, newParentId, currentUser.value.id)
    toast.success(`Przeniesiono ${node.name} pod nowego przełożonego.`)
    delete parentOverrides.value[node.id]
  } catch (e: any) {
    const msg = e?.response?.data?.message || ''
    if (msg.toLowerCase().includes('parent team does not match')) {
      toast.error('Przełożony jest z innego zespołu.')
    } else if (msg.toLowerCase().includes('403') || e?.response?.status === 403) {
      toast.error('Brak uprawnień do przeniesienia.')
    } else {
      toast.error('Nie udało się przenieść użytkownika.')
    }
  } finally {
    movingNodeId.value = null
  }
}

watch(
  () => newUserData.type,
  (type) => {
    if (type === 'LEADOWIEC') {
      newUserData.role = 'LEADOWIEC'
    } else if (newUserData.role === 'LEADOWIEC') {
      newUserData.role = availableRoles.value[0]?.val as UserRole || 'SALES'
    }
  }
)

const isExpanded = (id: string) => expandedNodes.value.has(id)

const toggleNode = (id: string) => {
  if (expandedNodes.value.has(id)) {
    expandedNodes.value.delete(id)
  } else {
    expandedNodes.value.add(id)
  }
}

const toggleDetails = (id: string) => {
  selectedNodeId.value = selectedNodeId.value === id ? null : id
}

const getRoleValue = (node: User) => roleOverrides.value[node.id] || node.role || 'SALES'

const setRoleValue = (node: User, value: UserRole) => {
  roleOverrides.value = { ...roleOverrides.value, [node.id]: value }
}

const saveRole = async (node: User) => {
  if (!currentUser.value) return
  const role = getRoleValue(node)
  try {
    await structure.updateUserAdmin(node.id, { role }, currentUser.value.id)
    toast.success(`Zmieniono rolę na ${role}.`)
  } catch {
    toast.error('Nie udało się zmienić roli.')
  }
}

const visibleNodes = computed<TreeNode[]>(() => {
  const allUsers = (Array.isArray(users.value) ? users.value : []).filter((user) => {
    if (user.role === 'CLIENT_HR') return false
    if (showInactive.value) return true
    return !user.isRemovedFromStructure && user.enabled !== false
  })
  const unassignedTeamId = 'team:unassigned'
  const teamNodeId = (path?: string | null) => (path ? `team:${path}` : unassignedTeamId)
  const teamLabel = (path?: string | null) => {
    if (!path) return 'Zespół bez przypisanego teamu'
    const parts = path.split('/').filter(Boolean)
    const code = parts[parts.length - 1] || path
    return `Zespół ${code}`
  }

  const teamNodesMap = new Map<string, User & { isTeamNode: true }>()
  const extraTeams = Array.isArray(teamGroups.value) ? teamGroups.value : []

  allUsers.forEach((user) => {
    if (user.role === 'ADMIN') return
    if (!user.teamGroupPath) return
    const teamKey = teamNodeId(user.teamGroupPath)
    if (!teamNodesMap.has(teamKey)) {
      teamNodesMap.set(teamKey, {
        id: teamKey,
        email: '',
        name: teamLabel(user.teamGroupPath),
        role: 'CLIENT_HR',
        type: 'PRIVATE',
        parentSupabaseId: null,
        hierarchicalId: null,
        hierarchicalCode: null,
        teamGroupPath: user.teamGroupPath,
        isRemovedFromStructure: false,
        contractStatus: null,
        rank: null,
        addressData: null,
        documents: null,
        phone: undefined,
        isTeamNode: true,
      })
    }
  })

  const normalizedUsers = allUsers.map((user) => {
    if (user.role !== 'ADMIN' && !user.parentSupabaseId) {
      const teamKey = teamNodeId(user.teamGroupPath)
      if (!teamNodesMap.has(teamKey)) {
        teamNodesMap.set(teamKey, {
          id: teamKey,
          email: '',
          name: teamLabel(user.teamGroupPath),
          role: 'CLIENT_HR',
          type: 'PRIVATE',
          parentSupabaseId: null,
          hierarchicalId: null,
          hierarchicalCode: null,
          teamGroupPath: user.teamGroupPath || null,
          isRemovedFromStructure: false,
          contractStatus: null,
          rank: null,
          addressData: null,
          documents: null,
          phone: undefined,
          isTeamNode: true,
        })
      }
      return { ...user, parentSupabaseId: teamKey }
    }
    return user
  })

  extraTeams.forEach((path) => {
    const teamKey = teamNodeId(path)
    if (!teamNodesMap.has(teamKey)) {
      teamNodesMap.set(teamKey, {
        id: teamKey,
        email: '',
        name: teamLabel(path),
        role: 'CLIENT_HR',
        type: 'PRIVATE',
        parentSupabaseId: null,
        hierarchicalId: null,
        hierarchicalCode: null,
        teamGroupPath: path,
        isRemovedFromStructure: false,
        contractStatus: null,
        rank: null,
        addressData: null,
        documents: null,
        phone: undefined,
        isTeamNode: true,
      })
    }
  })

  const treeUsers = [...teamNodesMap.values(), ...normalizedUsers]
  const treeIds = new Set(treeUsers.map((user) => user.id))
  const adjustedUsers = treeUsers.map((user) => {
    if (user.isTeamNode) return user
    if (!user.parentSupabaseId) return user
    if (treeIds.has(user.parentSupabaseId)) return user
    const fallbackTeamId = teamNodeId(user.teamGroupPath)
    return { ...user, parentSupabaseId: fallbackTeamId }
  })
  const childrenByParentId = new Map<string | null, User[]>()

  adjustedUsers.forEach((user) => {
    const parentKey = user.parentSupabaseId ?? null
    const bucket = childrenByParentId.get(parentKey) || []
    bucket.push(user)
    childrenByParentId.set(parentKey, bucket)
  })

  childrenByParentId.forEach((list) => list.sort((a, b) => a.name.localeCompare(b.name)))

  const roots = adjustedUsers.filter((user) => user.role === 'ADMIN' || !user.parentSupabaseId)
  roots.sort((a, b) => a.name.localeCompare(b.name))

  const nodes: TreeNode[] = []

  const processNode = (user: User, level: number, isLast: boolean, parentChain: boolean[]) => {
    const children = childrenByParentId.get(user.id) || []
    const hasChildren = children.length > 0
    nodes.push({ ...user, level, hasChildren, isLast, parentChain })

    if (hasChildren && expandedNodes.value.has(user.id)) {
      const nextParentChain = [...parentChain, !isLast]
      children.forEach((child, index) => {
        processNode(child, level + 1, index === children.length - 1, nextParentChain)
      })
    }
  }

  roots.forEach((root, index) => processNode(root, 0, index === roots.length - 1, []))

  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return nodes

  return nodes.filter((node) => {
    const code = node.isTeamNode ? node.teamGroupPath || '' : node.hierarchicalId || node.hierarchicalCode || ''
    return (
      node.name.toLowerCase().includes(query) ||
      node.email.toLowerCase().includes(query) ||
      node.role.toLowerCase().includes(query) ||
      code.toLowerCase().includes(query)
    )
  })
})

const getInitials = (name?: string) => structure.getInitials(name)

const canAddUnder = (node: User) => {
  if (!currentUser.value) return false
  return structure.canAddUnder(currentUser.value, node)
}

const canImpersonate = (node: User) => {
  if (!currentUser.value) return false
  return structure.canImpersonate(currentUser.value, node)
}

const canRemove = (node: User) => {
  if (!currentUser.value) return false
  return structure.canRemove(currentUser.value, node)
}

const handleDragStart = (event: DragEvent, node: TreeNode) => {
  if (node.role === 'ADMIN' || node.isTeamNode) return
  draggedNode.value = node
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', node.id)
  }
}

const handleDragEnd = () => {
  draggedNode.value = null
  dropTargetNodeId.value = null
}

const handleDragOver = (event: DragEvent, node: TreeNode) => {
  if (!draggedNode.value || draggedNode.value.id === node.id) return
  event.preventDefault()
  dropTargetNodeId.value = node.id
}

const handleDrop = async (event: DragEvent, node: TreeNode) => {
  event.preventDefault()
  const dragged = draggedNode.value
  if (!dragged || !currentUser.value) return
  if (dragged.id === node.id) return

  const descendants = structure.getSubtreeUserIds(dragged.id)
  if (descendants.includes(node.id)) {
    toast.error('Nie można przenieść użytkownika pod jego podwładnego.')
    handleDragEnd()
    return
  }

  try {
    if (node.isTeamNode) {
      await structure.moveUser(dragged.id, null, currentUser.value.id, node.teamGroupPath || null)
      toast.success(`Przeniesiono ${dragged.name} do zespołu ${node.name}.`)
    } else {
      await structure.moveUser(dragged.id, node.id, currentUser.value.id)
      toast.success(`Przeniesiono ${dragged.name} pod ${node.name}.`)
    }
  } catch (error: any) {
    const status = error?.response?.status
    const message = error?.response?.data?.message || ''
    if (status === 422) {
      if (message.toLowerCase().includes('parent team does not match')) {
        toast.error('Przełożony jest z innego zespołu.')
      } else if (message.toLowerCase().includes('cannot move user under itself')) {
        toast.error('Nie można przenieść użytkownika pod samego siebie.')
      } else if (message.toLowerCase().includes('cannot move user under a descendant')) {
        toast.error('Nie można przenieść użytkownika pod jego podwładnego.')
      } else if (message.toLowerCase().includes('missing team group path')) {
        toast.error('Brak docelowego zespołu do przeniesienia.')
      } else {
        toast.error('Nie udało się przenieść użytkownika (błąd walidacji).')
      }
    } else if (status === 403) {
      toast.error('Brak uprawnień do przeniesienia tego użytkownika.')
    } else {
      toast.error('Nie udało się przenieść użytkownika.')
    }
  } finally {
    handleDragEnd()
  }
}

const resetForm = () => {
  newUserData.type = 'PRIVATE'
  if (isTeamNode(targetParent.value)) {
    newUserData.role = 'DIRECTOR'
  } else if (targetParent.value?.role === 'ADMIN') {
    newUserData.role = 'ADMIN'
  } else {
    newUserData.role = targetParent.value?.role === 'DIRECTOR' ? 'MANAGER' : targetParent.value?.role === 'MANAGER' ? 'SALES' : 'DIRECTOR'
  }
  newUserData.email = ''
  newUserData.phone = ''
  newUserData.name = ''
  newUserData.firstName = ''
  newUserData.lastName = ''
  newUserData.pesel = ''
  newUserData.nip = ''
  newUserData.regon = ''
  newUserData.krs = ''
  newUserData.address.street = ''
  newUserData.address.houseNr = ''
  newUserData.address.aptNr = ''
  newUserData.address.zipCode = ''
  newUserData.address.city = ''
  selectedDirectorTeam.value = isTeamNode(targetParent.value) ? targetParent.value?.teamGroupPath ?? '' : ''
}

const openAddModal = (parent?: User) => {
  targetParent.value = parent || null
  void structure.fetchTeams()
  resetForm()
  showAddModal.value = true
}

const openMsgModal = (user: User) => {
  selectedUser.value = user
  msgData.type = 'TASK'
  msgData.text = ''
  showMsgModal.value = true
}

const closeMsgModal = () => {
  showMsgModal.value = false
  selectedUser.value = null
}

const sendMsg = () => {
  const recipient = selectedUser.value
  if (!recipient || !currentUser.value) return
  if (!msgData.text.trim()) {
    toast.warning('Wpisz treść wiadomości.')
    return
  }

  notify.add({
    userId: recipient.id,
    type: msgData.type,
    message: msgData.text.trim(),
  })
  toast.success(`Wiadomość została wysłana do ${recipient.name}.`)
  closeMsgModal()
}

const syncUsers = async () => {
  if (isSyncing.value) return
  isSyncing.value = true
  isLoadingStructure.value = true
  try {
    const report = await structure.syncUsers()
    await structure.fetchStructure({ sync: true })
    await structure.fetchTeams({ refresh: true })
    if (report?.status === 'locked') {
      toast.info('Synchronizacja już trwa. Spróbuj ponownie za chwilę.')
    } else {
      toast.success('Zsynchronizowano użytkowników.')
    }
  } catch (error) {
    toast.error('Nie udało się zsynchronizować użytkowników.')
  } finally {
    isSyncing.value = false
    isLoadingStructure.value = false
  }
}

const openTeamModal = async () => {
  newTeamBase.value = ''
  showTeamModal.value = true
  try {
    await structure.fetchTeams({ refresh: true })
  } catch {
    // optional: silent, list may come from current structure
  }
}

const createTeam = async () => {
  const code = computedTeamCode.value.trim()
  if (!code) {
    toast.warning('Podaj nazwę zespołu.')
    return
  }
  if (isCreatingTeam.value) return
  isCreatingTeam.value = true
  try {
    await structure.createTeam(code, newTeamBase.value.trim() || undefined)
    await structure.fetchTeams()
    toast.success('Utworzono zespół.')
    showTeamModal.value = false
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 409) {
      toast.error('Taki zespół już istnieje.')
    } else if (status === 422) {
      toast.error('Nieprawidłowy kod zespołu.')
    } else {
      toast.error('Nie udało się utworzyć zespołu.')
    }
  } finally {
    isCreatingTeam.value = false
  }
}

const initImpersonate = (user: User) => {
  userToImpersonate.value = user
  showImpersonateModal.value = true
}

const closeImpersonateModal = () => {
  showImpersonateModal.value = false
  userToImpersonate.value = null
}

const confirmImpersonate = async () => {
  if (!userToImpersonate.value) return
  await session.impersonate(userToImpersonate.value.id)
  closeImpersonateModal()
  router.push('/app/dashboard')
}

const initRemove = (user: User) => {
  userToRemove.value = user
  showRemoveModal.value = true
}

const closeRemoveModal = () => {
  showRemoveModal.value = false
  userToRemove.value = null
}

const confirmRemove = async () => {
  const target = userToRemove.value
  const actor = currentUser.value
  if (!target || !actor) return
  await structure.removeUserFromStructure(target, actor)
  await structure.fetchStructure()
  toast.success(`Usunięto ${target.name} ze struktury.`)
  closeRemoveModal()
}

const restoreUser = async (node: User) => {
  if (!currentUser.value) return
  try {
    const restored = await structure.restoreUser(node)
    toast.success('Przywrócono użytkownika.')
    if ((restored as any)?.inviteSent === false) {
      toast.warning('Użytkownik został przywrócony, ale zaproszenie email nie zostało wysłane.')
    }
  } catch {
    toast.error('Nie udało się przywrócić użytkownika.')
  }
}

const updateFullName = () => {
  if (newUserData.type !== 'PRIVATE' && newUserData.type !== 'LEADOWIEC') return
  newUserData.name = `${newUserData.firstName} ${newUserData.lastName}`.trim()
}

const generatedId = computed(() => {
  if (newUserData.role === 'ADMIN') {
    return 'ROOT'
  }
  if (newUserData.type === 'LEADOWIEC') {
    return null
  }
  const parentCode = targetParent.value?.hierarchicalCode || targetParent.value?.hierarchicalId
  const nameForInitials =
    newUserData.type === 'PRIVATE' || newUserData.type === 'LEADOWIEC'
      ? `${newUserData.firstName} ${newUserData.lastName}`.trim()
      : newUserData.name.trim()
  const initials = getInitials(nameForInitials) || 'XX'
  if (parentCode) {
    return `${parentCode}/${initials}???`
  }

  const teamPath = targetParent.value?.teamGroupPath || selectedDirectorTeam.value || currentUser.value?.teamGroupPath
  if (!teamPath) return `TEAM???/${initials}???`

  const parts = teamPath.split('/').filter(Boolean)
  const teamCode = parts[parts.length - 1] || 'TEAM'
  const clean = teamCode.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
  const prefix = clean.slice(0, 3) || 'TEAM'
  return `${prefix}???/${initials}???`
})

const availableRoles = computed(() => {
  if (newUserData.type === 'LEADOWIEC') return [{ val: 'LEADOWIEC', label: 'Leadowiec' }]
  if (!targetParent.value || isTeamNode(targetParent.value)) {
    return [{ val: 'DIRECTOR', label: 'Dyrektor' }]
  }
  if (targetParent.value.role === 'ADMIN') return [{ val: 'ADMIN', label: 'Super Admin' }]
  if (targetParent.value.role === 'DIRECTOR') return [{ val: 'MANAGER', label: 'Manager' }]
  if (targetParent.value.role === 'MANAGER') return [{ val: 'SALES', label: 'Handlowiec' }]
  if (targetParent.value.role === 'SALES') return [{ val: 'LEADOWIEC', label: 'Leadowiec' }]
  return []
})

const fetchGus = async () => {
  if (!newUserData.nip) return
  isFetchingGus.value = true
  try {
    const data = (await structure.fetchGusData(newUserData.nip)) as {
      name: string
      street: string
      houseNr: string
      aptNr?: string
      zipCode: string
      city: string
      regon: string
      krs: string
      email?: string | null
      phone?: string | null
    }
    newUserData.name = data.name
    newUserData.address.street = data.street
    newUserData.address.houseNr = data.houseNr
    newUserData.address.aptNr = data.aptNr || ''
    newUserData.address.zipCode = data.zipCode
    newUserData.address.city = data.city
    newUserData.regon = data.regon
    newUserData.krs = data.krs
    if (!newUserData.email && data.email) {
      newUserData.email = data.email
    }
    if (!newUserData.phone && data.phone) {
      newUserData.phone = data.phone
    }
    toast.info('Pobrano dane z GUS.')
  } catch (error) {
    toast.error(typeof error === 'string' ? error : 'Nie udało się pobrać danych z GUS.')
  } finally {
    isFetchingGus.value = false
  }
}

const addUser = async () => {
  const actor = currentUser.value
  if (!actor) return

  const inferredTeamPath = isTeamNode(targetParent.value)
    ? targetParent.value?.teamGroupPath
    : targetParent.value?.teamGroupPath || currentUser.value?.teamGroupPath
  const teamPath = inferredTeamPath || (selectedDirectorTeam.value || undefined)

  if (actor.role === 'ADMIN' && newUserData.role === 'DIRECTOR' && !teamPath) {
    toast.warning('Wybierz zespół dla nowego dyrektora.')
    return
  }

  const name = newUserData.type === 'PRIVATE' || newUserData.type === 'LEADOWIEC' ? `${newUserData.firstName} ${newUserData.lastName}`.trim() : newUserData.name.trim()
  const cityRequired = newUserData.type !== 'LEADOWIEC'
  if (!name || !newUserData.email || (cityRequired && !newUserData.address.city)) {
    toast.warning('Uzupełnij wymagane pola.')
    return
  }

  isSending.value = true
  try {
    const created = await structure.addUser(
      {
        name,
        email: newUserData.email,
        role: newUserData.role,
        phone: newUserData.phone || undefined,
        parentSupabaseId: newUserData.role === 'ADMIN' ? undefined : isTeamNode(targetParent.value) ? undefined : targetParent.value?.id,
        hierarchicalId: generatedId.value || undefined,
        teamGroupPath: newUserData.role === 'ADMIN' ? undefined : teamPath,
        type: newUserData.type,
        firstName: newUserData.firstName || undefined,
        lastName: newUserData.lastName || undefined,
        pesel: newUserData.pesel || undefined,
        nip: newUserData.nip || undefined,
        regon: newUserData.regon || undefined,
        krs: newUserData.krs || undefined,
        addressData: {
          street: newUserData.address.street,
          houseNr: newUserData.address.houseNr,
          aptNr: newUserData.address.aptNr || undefined,
          zipCode: newUserData.address.zipCode,
          city: newUserData.address.city,
        },
      },
      actor.id
    )
    toast.success('Dodano użytkownika.')
    if (created.inviteSent === false) {
      const detail = created.inviteError ? ` (${created.inviteError})` : ''
      toast.warning(`Użytkownik został utworzony, ale nie udało się wysłać zaproszenia email.${detail}`)
    } else if (created.inviteSent === null) {
      toast.info('Użytkownik utworzony bez zaproszenia email.')
    }
    showAddModal.value = false
    if (targetParent.value) {
      expandedNodes.value.add(targetParent.value.id)
    }
    await nextTick()
    const element = document.getElementById(`structure-node-${created.id}`)
    element?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 409) {
      toast.error('Konflikt zapisu (Idempotency-Key). Spróbuj ponownie za chwilę.')
    } else if (status === 422) {
      toast.error('Błąd walidacji podczas tworzenia użytkownika.')
    } else {
      toast.error('Nie udało się dodać użytkownika.')
    }
  } finally {
    isSending.value = false
  }
}
</script>

<template>
  <div class="space-y-3 md:space-y-6">
    <div class="flex flex-wrap justify-between items-start gap-2">
      <div v-if="!embedded">
        <h1 class="text-xl md:text-2xl font-bold text-slate-900">Struktura Organizacji</h1>
        <p class="text-xs md:text-sm text-slate-500">
          <span v-if="currentUser?.role === 'ADMIN'">Widok globalny (Super Admin) - Zarządzaj całą organizacją</span>
          <span v-else>Zarządzaj swoim zespołem i monitoruj strukturę.</span>
        </p>
      </div>
      <div v-else>
         <!-- Spacer if header is hidden -->
      </div>
      <div class="flex space-x-3 items-center">
        <button
          v-if="currentUser?.role === 'ADMIN'"
          type="button"
          class="px-3 py-2 text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition shadow-sm"
          @click="openTeamModal"
        >
          Dodaj zespół
        </button>

        <div class="flex items-center bg-slate-100 rounded-lg p-1 border border-slate-200">
          <button 
            @click="viewMode = 'list'" 
            class="px-3 py-1.5 text-xs font-bold rounded-md transition-all flex items-center"
            :class="viewMode === 'list' ? 'bg-white text-slate-900 shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-700'"
          >
            <AppIcon name="list" class="h-3.5 w-3.5 mr-1.5" />
            Lista
          </button>
          <button 
            @click="viewMode = 'chart'" 
            class="px-3 py-1.5 text-xs font-bold rounded-md transition-all flex items-center"
            :class="viewMode === 'chart' ? 'bg-white text-slate-900 shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-700'"
          >
            <AppIcon name="chart-network" class="h-3.5 w-3.5 mr-1.5" />
            Schemat
          </button>
        </div>

        <div class="relative w-64 lg:w-80">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Szukaj w strukturze..."
            class="w-full border border-slate-200 rounded-lg py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold bg-white text-slate-900 shadow-sm placeholder-slate-400 text-right font-bold"
          />
          <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
        </div>
        <button
          v-if="currentUser?.role === 'ADMIN'"
          type="button"
          class="px-3 py-2 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition shadow-sm"
          @click="showInactive = !showInactive"
        >
          <span class="flex items-center gap-2">
            <AppIcon :name="showInactive ? 'xmark' : 'eye'" class="h-3.5 w-3.5 text-slate-500" />
            {{ showInactive ? 'Ukryj usuniętych' : 'Pokaż usuniętych' }}
          </span>
        </button>
        <button
          v-if="currentUser?.role === 'ADMIN'"
          type="button"
          class="px-3 py-2 text-xs font-bold rounded-lg border border-slate-200 bg-white text-primary hover:bg-slate-50 transition shadow-sm disabled:opacity-60"
          :disabled="isSyncing"
          @click="syncUsers"
        >
          {{ isSyncing ? 'Synchronizuję...' : 'Synchronizuj' }}
        </button>
      </div>
    </div>

    <div class="bg-surface shadow-card rounded-card overflow-hidden border border-slate-200 relative">
      <div class="bg-slate-50/50 px-6 py-3 border-b border-slate-200 grid grid-cols-12 text-xs font-bold text-slate-500 uppercase tracking-wider">
        <div class="col-span-8">Struktura Organizacyjna</div>
        <div class="col-span-4">Rola / ID</div>
      </div>

      <div v-if="isLoadingStructure" class="absolute inset-0 bg-white/80 backdrop-blur-[1px] z-10 flex items-center justify-center">
        <div class="flex items-center gap-3 text-sm text-slate-600 font-medium">
          <AppIcon name="refresh" class="h-5 w-5 animate-spin text-primary" />
          <span>Synchronizuję strukturę...</span>
        </div>
      </div>

      <div v-if="viewMode === 'list'" class="divide-y divide-slate-100">
        <div
          v-for="node in visibleNodes"
          :key="node.id"
          class="group relative transition-all duration-200"
          :id="`structure-node-${node.id}`"
          :class="{
            'bg-sky-50/50': selectedNodeId === node.id,
            'opacity-50': draggedNode?.id === node.id,
            'outline-dashed outline-2 outline-green-500 outline-offset-2 z-10': dropTargetNodeId === node.id,
            'hover:bg-slate-50': !draggedNode && !node.isTeamNode && selectedNodeId !== node.id,
          }"
          :draggable="node.role !== 'ADMIN' && !node.isTeamNode"
          @click="
            node.isTeamNode
              ? (toggleNode(node.id), (selectedNodeId = selectedNodeId === node.id ? null : node.id))
              : toggleDetails(node.id)
          "
          @dragstart="handleDragStart($event, node)"
          @dragend="handleDragEnd"
          @dragover="handleDragOver($event, node)"
          @dragleave="dropTargetNodeId = null"
          @drop="handleDrop($event, node)"
        >
          <div class="px-6 py-3 grid grid-cols-12 items-center min-h-[56px]" :class="node.role !== 'ADMIN' && !node.isTeamNode ? 'cursor-move' : ''">
            <div class="col-span-8 flex items-center relative overflow-hidden">
              <div class="absolute left-0 top-0 h-full" :style="{ width: `${node.level * 28}px` }">
                <div v-for="(hasSibling, index) in node.parentChain" :key="index">
                  <div v-if="hasSibling" class="absolute top-0 w-px h-full bg-slate-200" :style="{ left: `${index * 28 + 14}px` }"></div>
                </div>
                <div v-if="node.level > 0">
                  <div
                    class="absolute top-0 w-px bg-slate-200"
                    :class="node.isLast ? 'h-1/2' : 'h-full'"
                    :style="{ left: `${(node.level - 1) * 28 + 14}px` }"
                  ></div>
                  <div class="absolute h-px w-3.5 bg-slate-200" :style="{ top: '50%', left: `${(node.level - 1) * 28 + 14}px` }"></div>
                </div>
              </div>

              <div :style="{ width: `${node.level * 28}px` }" class="flex-shrink-0"></div>

              <button
                type="button"
                class="w-6 h-6 flex items-center justify-center mr-1 flex-shrink-0 text-slate-400 hover:text-primary rounded-full hover:bg-slate-100 transition-colors"
                @click.stop="toggleNode(node.id)"
              >
                <AppIcon v-if="node.hasChildren" name="chevron-right" class="w-3.5 h-3.5 transition-transform duration-200" :class="isExpanded(node.id) ? 'rotate-90' : ''" />
                <span v-else class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
              </button>

              <div
                class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold border shadow-sm mr-3 transition-transform group-hover:scale-105"
                :class="{
                  'bg-amber-100 text-amber-700 border-amber-200': node.role === 'DIRECTOR',
                  'bg-sky-100 text-sky-700 border-sky-200': node.role === 'MANAGER',
                  'bg-emerald-100 text-emerald-700 border-emerald-200': node.role === 'SALES',
                  'bg-rose-100 text-rose-700 border-rose-200': node.role === 'ADMIN',
                  'bg-slate-100 text-slate-600 border-slate-200': node.isTeamNode,
                }"
              >
                {{ getInitials(node.name) }}
              </div>

              <div>
                <div class="text-sm font-bold text-slate-800 flex items-center">
                  {{ node.name }}
                  <span v-if="node.contractStatus" class="ml-2" :title="`Status umowy: ${node.contractStatus}`">
                    <AppIcon v-if="node.contractStatus === 'DRAFT'" name="document-text" class="h-3.5 w-3.5 text-slate-400" />
                    <AppIcon v-else-if="node.contractStatus === 'SENT_TO_AUTENTI'" name="envelope" class="h-3.5 w-3.5 text-blue-500" />
                    <AppIcon v-else-if="node.contractStatus === 'SIGNED'" name="check-circle" class="h-3.5 w-3.5 text-emerald-500" />
                    <AppIcon v-else-if="node.contractStatus === 'REJECTED'" name="x-circle" class="h-3.5 w-3.5 text-rose-500" />
                  </span>
                  <span v-if="node.id === currentUser?.id" class="bg-indigo-50 text-indigo-600 text-[10px] px-1.5 py-0.5 rounded ml-2 border border-indigo-100 font-bold">TY</span>
                  <span v-if="node.isRemovedFromStructure" class="ml-2 text-[10px] px-1.5 rounded-full border bg-rose-50 text-rose-600 border-rose-100 font-medium">USUNIĘTY</span>
                  <span v-else-if="node.enabled === false" class="ml-2 text-[10px] px-1.5 rounded-full border bg-amber-50 text-amber-600 border-amber-100 font-medium">NIEAKTYWNY</span>
                  <span
                    v-if="node.rank"
                    class="ml-2 text-[10px] px-1.5 rounded-full border font-medium"
                    :class="{
                      'bg-amber-50 text-amber-700 border-amber-200': ['SENIOR', 'MASTER', 'LEGEND'].includes(node.rank),
                      'bg-slate-50 text-slate-500 border-slate-200': !['SENIOR', 'MASTER', 'LEGEND'].includes(node.rank),
                    }"
                  >
                    {{ node.rank }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-500">{{ node.email }}</div>
              </div>
            </div>

            <div class="col-span-4 border-l border-slate-100 pl-4">
              <div class="text-xs font-bold text-slate-700">{{ node.isTeamNode ? 'ZESPÓŁ' : node.role }}</div>
              <div class="text-xs text-slate-500 font-mono mt-0.5 bg-slate-50 inline-block px-1.5 py-0.5 rounded border border-slate-100">
                {{ node.isTeamNode ? node.teamGroupPath || 'BRAK' : node.hierarchicalId || node.hierarchicalCode || 'ROOT' }}
              </div>
            </div>
          </div>

          <div v-if="selectedNodeId === node.id && !node.isTeamNode" class="bg-slate-50/50 p-4 border-t border-slate-200/50 shadow-inner animate-fade-in" @click.stop>
            <div class="flex justify-between items-center">
              <div class="flex items-center space-x-6 text-xs text-slate-600">
                <div>
                  <span class="font-bold block text-slate-400 uppercase text-[10px]">Telefon</span>
                  <span>{{ node.phone || 'Brak' }}</span>
                </div>
                <div>
                  <span class="font-bold block text-slate-400 uppercase text-[10px]">Email</span>
                  <button type="button" class="text-primary hover:underline flex items-center gap-1" @click="mailbox.initiateEmailTo(node.email)">
                    <AppIcon name="envelope" class="w-3.5 h-3.5" />
                    <span>{{ node.email }}</span>
                  </button>
                </div>
                <div>
                  <span class="font-bold block text-slate-400 uppercase text-[10px]">Status Umowy</span>
                  <span v-if="node.contractStatus === 'SENT_TO_AUTENTI'" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-medium bg-blue-50 text-blue-600 border border-blue-100">
                    <AppIcon name="envelope" class="h-3 w-3" />
                    Autenti: Wysłano
                  </span>
                  <span v-else-if="node.contractStatus === 'SIGNED'" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-medium bg-emerald-50 text-emerald-600 border border-emerald-100">
                    <AppIcon name="check-circle" class="h-3 w-3" />
                    Podpisano
                  </span>
                  <span v-else class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-medium bg-slate-100 text-slate-600 border border-slate-200">
                    <AppIcon name="document-text" class="h-3 w-3" />
                    Draft
                  </span>
                </div>
                <div v-if="currentUser?.role === 'ADMIN'">
                  <span class="font-bold block text-slate-400 uppercase text-[10px]">Rola</span>
                  <div class="flex items-center gap-2 mt-1">
                    <select
                      class="border border-slate-300 rounded px-2 py-1 text-xs bg-white focus:outline-none focus:border-primary"
                      :value="getRoleValue(node)"
                      @change="setRoleValue(node, ($event.target as HTMLSelectElement).value as UserRole)"
                    >
                      <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <button
                      type="button"
                      class="px-2 py-1 text-xs font-bold bg-slate-800 text-white rounded hover:bg-slate-700 transition"
                      @click.stop="saveRole(node)"
                    >
                      Zapisz
                    </button>
                  </div>
                </div>
                <div v-if="currentUser?.role === 'ADMIN' && node.role !== 'ADMIN' && node.role !== 'DIRECTOR'">
                  <span class="font-bold block text-slate-400 uppercase text-[10px]">Przełożony</span>
                  <div class="flex items-center gap-2 mt-1">
                    <select
                      class="border border-slate-300 rounded px-2 py-1 text-xs bg-white focus:outline-none focus:border-primary max-w-[200px]"
                      :value="getParentValue(node)"
                      @change.stop="setParentValue(node, ($event.target as HTMLSelectElement).value)"
                    >
                      <option value="">— Brak (ROOT) —</option>
                      <option v-for="p in validParentsFor(node)" :key="p.id" :value="p.id">
                        {{ p.name || p.email }}
                      </option>
                    </select>
                    <button
                      type="button"
                      class="px-2 py-1 text-xs font-bold bg-primary text-white rounded hover:opacity-90 transition disabled:opacity-50"
                      :disabled="movingNodeId === node.id"
                      @click.stop="moveToParent(node)"
                    >
                      {{ movingNodeId === node.id ? '...' : 'Przenieś' }}
                    </button>
                  </div>
                </div>
              </div>
              <div class="flex justify-end items-center space-x-2">
                <button
                  v-if="!node.isTeamNode && canAddUnder(node)"
                  type="button"
                  class="p-2 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg hover:bg-emerald-100 hover:text-emerald-700 transition shadow-sm flex items-center text-xs font-bold"
                  :title="`Dodaj osobę do struktury: ${node.name}`"
                  @click.stop="openAddModal(node)"
                >
                  <AppIcon name="user-plus" class="w-4 h-4 mr-1.5" />
                  Dodaj
                </button>
                <button
                  v-if="!node.isTeamNode && node.id !== currentUser?.id"
                  type="button"
                  class="p-2 bg-white text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-sm"
                  :title="`Wyślij wiadomość do: ${node.name}`"
                  @click.stop="openMsgModal(node)"
                >
                  <AppIcon name="chat-bubble-left-ellipsis" class="w-4 h-4" />
                </button>
                <button
                  v-if="!node.isTeamNode && node.id !== currentUser?.id && canImpersonate(node)"
                  type="button"
                  class="p-2 bg-amber-50 text-amber-600 border border-amber-200 rounded-lg hover:bg-amber-100 transition shadow-sm"
                  :title="`Podgląd konta: ${node.name}`"
                  @click.stop="initImpersonate(node)"
                >
                  <AppIcon name="eye" class="w-4 h-4" />
                </button>
                <button
                  v-if="!node.isTeamNode && node.id !== currentUser?.id && canRemove(node)"
                  type="button"
                  class="p-2 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-100 transition shadow-sm"
                  :title="`Usuń ze struktury: ${node.name}`"
                  @click.stop="initRemove(node)"
                >
                  <AppIcon name="trash" class="w-4 h-4" />
                </button>
                <button
                  v-if="currentUser?.role === 'ADMIN' && (node.isRemovedFromStructure || node.enabled === false)"
                  type="button"
                  class="p-2 bg-indigo-50 text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition shadow-sm"
                  :title="`Przywróć użytkownika: ${node.name}`"
                  @click.stop="restoreUser(node)"
                >
                  <AppIcon name="refresh" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="node.isTeamNode && selectedNodeId === node.id && canAddGlobal && !searchQuery" class="bg-slate-50/50 p-4 border-t border-slate-200/50" @click.stop>
            <div class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                class="px-4 py-2 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition shadow-sm text-xs font-bold inline-flex items-center"
                :title="`Dodaj dyrektora do zespołu: ${node.name}`"
                @click.stop="openAddModal(node)"
              >
                <AppIcon name="user-plus" class="w-4 h-4 mr-2" />
                Dodaj nowego Dyrektora
              </button>
              <button
                v-if="node.teamGroupPath"
                type="button"
                class="px-4 py-2 bg-indigo-50 text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition shadow-sm text-xs font-bold inline-flex items-center"
                :title="`Przywróć wszystkich usuniętych w zespole: ${node.name}`"
                @click.stop="restoreTeamUsers(node)"
              >
                <AppIcon name="refresh" class="w-4 h-4 mr-2" />
                Przywróć usuniętych
              </button>
              <button
                v-if="node.teamGroupPath"
                type="button"
                class="px-4 py-2 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-100 transition shadow-sm text-xs font-bold inline-flex items-center"
                :title="`Usuń zespół: ${node.name}`"
                @click.stop="openDeleteTeamModal(node)"
              >
                <AppIcon name="x-circle" class="w-4 h-4 mr-2" />
                Usuń zespół
              </button>
              <button
                v-if="node.teamGroupPath"
                type="button"
                class="px-4 py-2 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-100 transition shadow-sm text-xs font-bold inline-flex items-center"
                :title="`Usuń trwale wszystkich usuniętych w zespole: ${node.name}`"
                @click.stop="deleteRemovedUsersPermanently(node)"
              >
                <AppIcon name="trash" class="w-4 h-4 mr-2" />
                Usuń trwale
              </button>
            </div>
          </div>
        </div>

        <div v-if="visibleNodes.length === 0 && searchQuery" class="p-12 text-center text-slate-500 bg-slate-50/50">
          <p class="text-lg font-medium">Brak wyników</p>
          <p class="text-sm mt-1">Nie znaleziono osób pasujących do wyszukiwania.</p>
        </div>

        <div v-if="isStructureFetching && visibleNodes.length === 0" class="p-12 text-center text-slate-500 bg-slate-50/50">
          <AppIcon name="refresh" class="h-8 w-8 animate-spin text-primary mx-auto mb-3" />
          <p class="text-lg font-medium">Ładowanie struktury...</p>
          <p class="text-sm mt-1">Pobieranie danych organizacji.</p>
        </div>

        <div v-if="structureFetchError && visibleNodes.length === 0 && !isStructureFetching" class="p-12 text-center text-slate-500 bg-slate-50/50">
          <AppIcon name="xmark" class="h-8 w-8 text-red-400 mx-auto mb-3" />
          <p class="text-lg font-medium text-red-600">Błąd ładowania struktury</p>
          <p class="text-sm mt-1">Nie można połączyć się z serwerem. Sprawdź połączenie i spróbuj ponownie.</p>
          <button
            type="button"
            class="mt-4 px-4 py-2 text-sm font-bold bg-primary text-white rounded-lg hover:bg-primary/90 transition"
            @click="structure.fetchStructure()"
          >
            Spróbuj ponownie
          </button>
        </div>

        <div v-if="visibleNodes.length === 0 && !searchQuery && !isStructureFetching && !structureFetchError" class="p-12 text-center text-slate-500 bg-slate-50/50">
          <p class="text-lg font-medium">Struktura jest pusta</p>
          <p class="text-sm mt-1">Rozpocznij od dodania pierwszego Dyrektora Handlowego.</p>
        </div>
      </div>
      <div v-if="viewMode === 'chart'">
        <StructureChartView :users="users" />
      </div>
    </div>

    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showAddModal = false"></div>
      <div class="bg-surface rounded-card shadow-2xl p-0 w-full max-w-4xl z-10 relative max-h-[95vh] overflow-y-auto flex flex-col">
        <div class="px-6 py-4 border-b border-slate-700 bg-surface-dark text-white flex justify-between items-center rounded-t-card sticky top-0 z-20">
          <div>
            <h3 class="text-lg font-bold">
              <span v-if="targetParent">Dodaj osobę do: {{ targetParent.name }}</span>
              <span v-else>Dodaj Dyrektora Handlowego</span>
            </h3>
            <p class="text-xs text-slate-300 opacity-80">Wprowadź komplet danych do umowy.</p>
          </div>
          <button type="button" class="text-slate-400 hover:text-white text-2xl" @click="showAddModal = false">✕</button>
        </div>

        <form class="p-4 md:p-6 space-y-4 md:space-y-8" @submit.prevent="addUser">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-3">Typ Podmiotu</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <label class="cursor-pointer border-2 rounded-lg p-3 flex flex-col items-center hover:bg-slate-50 transition" :class="newUserData.type === 'PRIVATE' ? 'border-primary bg-sky-50' : 'border-slate-200'">
                <input v-model="newUserData.type" type="radio" name="etype" value="PRIVATE" class="hidden" />
                <AppIcon name="user" class="w-6 h-6 mb-1 text-slate-600" />
                <span class="font-bold text-sm">Osoba Prywatna</span>
              </label>
              <label class="cursor-pointer border-2 rounded-lg p-3 flex flex-col items-center hover:bg-slate-50 transition" :class="newUserData.type === 'B2B' ? 'border-primary bg-sky-50' : 'border-slate-200'">
                <input v-model="newUserData.type" type="radio" name="etype" value="B2B" class="hidden" />
                <AppIcon name="briefcase" class="w-6 h-6 mb-1 text-slate-600" />
                <span class="font-bold text-sm">Działalność (JDG)</span>
              </label>
              <label class="cursor-pointer border-2 rounded-lg p-3 flex flex-col items-center hover:bg-slate-50 transition" :class="newUserData.type === 'COMPANY' ? 'border-primary bg-sky-50' : 'border-slate-200'">
                <input v-model="newUserData.type" type="radio" name="etype" value="COMPANY" class="hidden" />
                <AppIcon name="building" class="w-6 h-6 mb-1 text-slate-600" />
                <span class="font-bold text-sm">Spółka</span>
              </label>
              <label class="cursor-pointer border-2 rounded-lg p-3 flex flex-col items-center hover:bg-amber-50 transition" :class="newUserData.type === 'LEADOWIEC' ? 'border-amber-500 bg-amber-50' : 'border-slate-200'">
                <input v-model="newUserData.type" type="radio" name="etype" value="LEADOWIEC" class="hidden" @change="newUserData.role = 'LEADOWIEC'" />
                <AppIcon name="user-group" class="w-6 h-6 mb-1 text-amber-600" />
                <span class="font-bold text-sm text-amber-700">Leadowiec</span>
              </label>
            </div>
          </div>

          <div class="bg-slate-50 p-6 rounded-card border border-slate-200">
            <h4 class="text-sm font-bold text-slate-700 uppercase border-b border-slate-200 pb-2 mb-4">Dane Podstawowe</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <template v-if="newUserData.type === 'PRIVATE'">
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Imię</label>
                  <input v-model="newUserData.firstName" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" @input="updateFullName" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nazwisko</label>
                  <input v-model="newUserData.lastName" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" @input="updateFullName" />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">PESEL</label>
                  <input v-model="newUserData.pesel" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
                </div>
              </template>
              <template v-else-if="newUserData.type === 'LEADOWIEC'">
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Imię</label>
                  <input v-model="newUserData.firstName" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-colors text-sm bg-white" @input="updateFullName" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nazwisko</label>
                  <input v-model="newUserData.lastName" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-colors text-sm bg-white" @input="updateFullName" />
                </div>
              </template>
              <template v-else>
                <div class="md:col-span-2">
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">NIP (Pobierz z GUS)</label>
                  <div class="flex">
                    <input v-model="newUserData.nip" type="text" class="flex-1 border border-slate-300 p-2.5 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
                    <button type="button" class="bg-slate-800 text-white px-4 rounded-r-lg text-sm font-bold hover:bg-slate-700" @click="fetchGus">
                      {{ isFetchingGus ? 'Pobieranie...' : 'Pobierz Dane' }}
                    </button>
                  </div>
                </div>
                <div class="md:col-span-2">
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nazwa Firmy</label>
                  <input v-model="newUserData.name" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">REGON</label>
                  <input v-model="newUserData.regon" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
                </div>
                <div v-if="newUserData.type === 'COMPANY'">
                  <label class="block text-xs font-bold text-slate-500 uppercase mb-1">KRS</label>
                  <input v-model="newUserData.krs" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
                </div>
              </template>

              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email</label>
                <input v-model="newUserData.email" type="email" required class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Telefon</label>
                <input v-model="newUserData.phone" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
            </div>
          </div>

          <div class="bg-slate-50 p-6 rounded-card border border-slate-200">
            <h4 class="text-sm font-bold text-slate-700 uppercase border-b border-slate-200 pb-2 mb-4">Adres Zamieszkania / Siedziby</h4>
            <div class="grid grid-cols-6 gap-4">
              <div class="col-span-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Ulica</label>
                <input v-model="newUserData.address.street" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
              <div class="col-span-1">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nr Domu</label>
                <input v-model="newUserData.address.houseNr" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
              <div class="col-span-1">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Lok.</label>
                <input v-model="newUserData.address.aptNr" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
              <div class="col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Kod Pocztowy</label>
                <input v-model="newUserData.address.zipCode" type="text" class="w-full border border-slate-300 p-2.5 rounded-input focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
              <div class="col-span-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Miasto <span class="text-rose-500 text-[10px]">(Generuje ID)</span></label>
                <input v-model="newUserData.address.city" type="text" required class="w-full border border-slate-300 p-2.5 rounded-input border-l-4 border-l-primary focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm bg-white" />
              </div>
            </div>
          </div>

          <div
            v-if="currentUser?.role === 'ADMIN' && newUserData.role === 'DIRECTOR' && !targetParent?.teamGroupPath"
            class="bg-amber-50 p-4 rounded-lg border border-amber-200"
          >
            <label class="block text-[10px] font-bold text-amber-800 uppercase mb-2">Zespół dyrektora <span class="text-rose-500">*</span></label>
            <select
              v-model="selectedDirectorTeam"
              required
              class="w-full bg-white border border-amber-300 p-2.5 rounded-input text-sm font-medium focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
            >
              <option value="" disabled>— wybierz zespół —</option>
              <option v-for="path in teamGroups" :key="path" :value="path">{{ path }}</option>
            </select>
            <p class="text-[11px] text-amber-700 mt-1">Nowy dyrektor potrzebuje przypisanego zespołu (generuje ID hierarchiczne).</p>
          </div>

          <div class="bg-slate-100 p-4 rounded-lg border border-slate-200 flex items-center justify-between">
            <div v-if="newUserData.type !== 'LEADOWIEC'">
              <label class="block text-[10px] font-bold text-slate-500 uppercase">Automatyczne ID Hierarchiczne</label>
              <input :value="generatedId" type="text" readonly class="bg-transparent text-xl font-mono font-bold text-slate-800 border-none p-0 w-full focus:ring-0" />
            </div>
            <div v-else>
              <label class="block text-[10px] font-bold text-slate-500 uppercase">Typ</label>
              <span class="text-xl font-bold text-amber-700">Leadowiec</span>
            </div>
            <div class="text-right">
              <label class="block text-[10px] font-bold text-slate-500 uppercase">Rola</label>
              <select v-model="newUserData.role" class="bg-white border border-slate-300 p-1 rounded text-sm font-bold">
                <option v-for="role in availableRoles" :key="role.val" :value="role.val">{{ role.label }}</option>
              </select>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-100 flex justify-end space-x-3 sticky bottom-0 bg-white p-4 -mx-6 -mb-6 shadow-up">
            <button type="button" class="px-5 py-3 text-slate-600 hover:bg-slate-100 rounded-lg font-medium transition" @click="showAddModal = false">Anuluj</button>
            <button
              type="submit"
              class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark shadow-lg font-bold flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="
                isSending
                  || !newUserData.email
                  || (newUserData.type !== 'LEADOWIEC' && !newUserData.address.city)
                  || (currentUser?.role === 'ADMIN' && newUserData.role === 'DIRECTOR' && !targetParent?.teamGroupPath && !selectedDirectorTeam)
              "
            >
              <AppIcon v-if="isSending" name="refresh" class="h-4 w-4 animate-spin mr-2" />
              <span>{{ isSending ? 'Zapisywanie...' : 'Zapisz' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showMsgModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeMsgModal"></div>
      <div class="bg-surface rounded-card shadow-xl p-6 w-full max-w-md z-10 relative">
        <h3 class="text-lg font-bold mb-4 text-slate-900">Wyślij powiadomienie</h3>
        <div class="mb-4">
          <span class="text-sm text-slate-500">Do:</span> <span class="font-bold text-slate-900">{{ selectedUser?.name }}</span>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Typ</label>
            <select v-model="msgData.type" class="w-full border border-slate-300 p-2.5 rounded-input bg-surface text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm">
              <option value="TASK">Zadanie / Działanie</option>
              <option value="NOTE">Notatka służbowa</option>
              <option value="INFO">Informacja</option>
              <option value="WARNING">Ostrzeżenie / Przypomnienie</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Treść wiadomości</label>
            <textarea v-model="msgData.text" rows="4" class="w-full border border-slate-300 p-2.5 rounded-input bg-surface text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors text-sm" placeholder="Wpisz treść..."></textarea>
          </div>
          <div class="flex justify-end space-x-2">
            <button type="button" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-btn text-sm font-medium" @click="closeMsgModal">Anuluj</button>
            <button type="button" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark shadow-sm text-sm font-bold" @click="sendMsg">Wyślij</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showImpersonateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-amber-900/40 backdrop-blur-sm" @click="closeImpersonateModal"></div>
      <div class="bg-white rounded-card shadow-2xl p-6 w-full max-w-sm z-10 relative border-l-4 border-amber-500">
        <div class="flex items-start mb-4">
          <div class="bg-amber-100 rounded-full p-2 mr-3 text-amber-600">
            <AppIcon name="eye" class="w-6 h-6" />
          </div>
          <div>
            <h3 class="text-lg font-bold text-slate-900">Tryb Podglądu (Shadow Mode)</h3>
            <p class="text-xs text-slate-500">
              Logowanie jako: <span class="font-bold text-slate-800">{{ userToImpersonate?.name }}</span>
            </p>
          </div>
        </div>
        <div class="bg-amber-50 p-3 rounded-lg text-sm text-amber-800 mb-6 border border-amber-100">
          <ul class="list-disc pl-4 space-y-1">
            <li>Przejdziesz w tryb tylko do odczytu.</li>
            <li>Wszelkie operacje zapisu zostaną zablokowane.</li>
            <li>Zostaniesz przeniesiony na pulpit użytkownika.</li>
            <li>Akcja zostanie odnotowana w logach.</li>
          </ul>
        </div>
        <div class="flex justify-end space-x-2">
          <button type="button" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium" @click="closeImpersonateModal">Anuluj</button>
          <button type="button" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-bold shadow" @click="confirmImpersonate">
            Rozpocznij Podgląd
          </button>
        </div>
      </div>
    </div>

    <div v-if="showRemoveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeRemoveModal"></div>
      <div class="bg-white rounded-card shadow-xl p-6 w-full max-w-sm z-10 relative border-l-4 border-rose-500">
        <h3 class="text-lg font-bold mb-2 text-rose-600">Potwierdź Dezaktywację</h3>
        <p class="text-sm text-slate-600 mb-4">
          Czy na pewno chcesz dezaktywować <strong>{{ userToRemove?.name }}</strong> w strukturze?
          <span class="text-xs text-slate-500 block">Użytkownik zostanie ukryty w drzewie, ale pozostanie w bazie danych.</span>
        </p>
        <div class="mt-6 flex justify-end space-x-2">
          <button type="button" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium" @click="closeRemoveModal">Anuluj</button>
          <button type="button" class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 text-sm font-bold shadow" @click="confirmRemove">
            Dezaktywuj
          </button>
        </div>
      </div>
    </div>

    <div v-if="showTeamModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showTeamModal = false"></div>
      <div class="bg-surface rounded-card shadow-xl p-6 w-full max-w-sm z-10 relative border-l-4 border-emerald-500">
        <h3 class="text-lg font-bold mb-2 text-emerald-700">Dodaj nowy zespół</h3>
        <p class="text-xs text-slate-500 mb-4">Zostanie utworzony nowy zespół w strukturze CRM.</p>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nazwa zespołu</label>
            <input v-model="newTeamBase" type="text" class="w-full border border-slate-300 p-2.5 rounded-input bg-surface text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-sm" placeholder="np. warszawa" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Kod zespołu (automatyczny)</label>
            <input :value="computedTeamCode" type="text" readonly class="w-full border border-slate-300 p-2.5 rounded-input bg-slate-50 text-slate-700 font-mono text-sm" />
          </div>
        </div>
        <div class="mt-6 flex justify-end space-x-2">
          <button type="button" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-btn text-sm font-medium" @click="showTeamModal = false">Anuluj</button>
          <button
            type="button"
            class="px-4 py-2 bg-emerald-600 text-white rounded-btn hover:bg-emerald-700 text-sm font-bold shadow disabled:opacity-50"
            :disabled="isCreatingTeam"
            @click="createTeam"
          >
            {{ isCreatingTeam ? 'Tworzenie...' : 'Utwórz zespół' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showRegenerateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showRegenerateModal = false"></div>
      <div class="bg-white rounded-card shadow-xl p-6 w-full max-w-sm z-10 relative border-l-4 border-rose-500">
        <h3 class="text-lg font-bold mb-2 text-rose-600">Przelicz kody struktury</h3>
        <p class="text-sm text-slate-600 mb-4">
          Operacja przeliczy kody hierarchiczne dla wszystkich użytkowników w strukturze.
        </p>
        <label class="flex items-center gap-3 text-sm text-slate-700 mb-6">
          <input v-model="resetCounters" type="checkbox" class="h-4 w-4 text-rose-600 focus:ring-rose-500 rounded" />
          Resetuj liczniki przed przeliczeniem
        </label>
        <div class="flex justify-end space-x-2">
          <button type="button" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium" @click="showRegenerateModal = false">
            Anuluj
          </button>
          <button
            type="button"
            class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 text-sm font-bold shadow disabled:opacity-50"
            :disabled="isRegenerating"
            @click="regenerateCodes"
          >
            {{ isRegenerating ? 'Przeliczam...' : 'Przelicz kody' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showDeleteTeamModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showDeleteTeamModal = false"></div>
      <div class="bg-white rounded-card shadow-xl p-6 w-full max-w-sm z-10 relative border-l-4 border-rose-500">
        <h3 class="text-lg font-bold mb-2 text-rose-600">Usuń zespół</h3>
        <p class="text-sm text-slate-600 mb-4">
          Czy na pewno chcesz usunąć zespół <strong>{{ teamToDelete?.name }}</strong>?
          <span class="text-xs text-slate-500 block">Operacja usuwa zespół. Zespół musi być pusty.</span>
        </p>
        <div class="flex justify-end space-x-2">
          <button type="button" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium" @click="showDeleteTeamModal = false">
            Anuluj
          </button>
          <button type="button" class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 text-sm font-bold shadow" @click="confirmDeleteTeam">
            Usuń zespół
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
