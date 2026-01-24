<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useStructureStore } from '@/stores/structure'
import { useFinanceStore } from '@/stores/finance'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import { api } from '@/api/client'
import type { User } from '@/types/models'

type TreeNode = User & { level: number; hasChildren: boolean; isLast: boolean; parentChain: boolean[]; isTeamNode?: boolean }

const auth = useAuthStore()
const structure = useStructureStore()
const finance = useFinanceStore()
const session = useSessionStore()
const toast = useToastStore()

const selectedUser = ref<TreeNode | null>(null)
const editableUser = ref<{ renewalCommissionRate?: number | null; overrideCommissionRate?: number | null }>({})
const teamThresholds = ref<Record<string, { id: string; renewalCommissionRate: number | null; overrideCommissionRate: number | null }>>({})
const teamThresholdsLoading = ref(false)

const { users, teamGroups } = storeToRefs(structure)
const { commissionConfig } = storeToRefs(finance)
const { currentUser } = storeToRefs(session)

const expandedNodes = ref<Set<string>>(new Set((Array.isArray(users.value) ? users.value : []).map((user) => user.id)))
const globalConfig = ref({ ...commissionConfig.value })

const isExpanded = (id: string) => expandedNodes.value.has(id)

const toggleNode = (id: string) => {
  if (expandedNodes.value.has(id)) {
    expandedNodes.value.delete(id)
  } else {
    expandedNodes.value.add(id)
  }
}

const visibleNodes = computed<TreeNode[]>(() => {
  const allUsers = (Array.isArray(users.value) ? users.value : []).filter((user) => !user.isRemovedFromStructure && user.role !== 'CLIENT_HR')
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
  const normalizedUsers = allUsers.map((user) => {
    if (user.role !== 'ADMIN' && !user.parentKeycloakId) {
      const teamKey = teamNodeId(user.teamGroupPath)
      if (!teamNodesMap.has(teamKey)) {
        teamNodesMap.set(teamKey, {
          id: teamKey,
          email: '',
          name: teamLabel(user.teamGroupPath),
          role: 'CLIENT_HR',
          type: 'PRIVATE',
          parentKeycloakId: null,
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
      return { ...user, parentKeycloakId: teamKey }
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
        parentKeycloakId: null,
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
  const childrenByParentId = new Map<string | null, User[]>()

  treeUsers.forEach((user) => {
    const parentKey = user.parentKeycloakId ?? null
    const bucket = childrenByParentId.get(parentKey) || []
    bucket.push(user)
    childrenByParentId.set(parentKey, bucket)
  })

  childrenByParentId.forEach((list) => list.sort((a, b) => a.name.localeCompare(b.name)))

  const roots = treeUsers.filter((user) => user.role === 'ADMIN' || !user.parentKeycloakId)
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
  return nodes
})

const getInitials = (name?: string) => structure.getInitials(name)

const getTeamThreshold = (node: TreeNode) => {
  const key = node.teamGroupPath || ''
  if (!key) return null
  return teamThresholds.value[key] || null
}

const getNodeRenewalRate = (node: TreeNode) => {
  if (node.isTeamNode) return getTeamThreshold(node)?.renewalCommissionRate ?? null
  return node.renewalCommissionRate ?? null
}

const getNodeOverrideRate = (node: TreeNode) => {
  if (node.isTeamNode) return getTeamThreshold(node)?.overrideCommissionRate ?? null
  return node.overrideCommissionRate ?? null
}

const selectUserForEditing = (user: TreeNode) => {
  if (user.role === 'ADMIN') return
  if (user.isTeamNode && !user.teamGroupPath) {
    toast.warning('Zespół nie ma przypisanego kodu.')
    return
  }
  selectedUser.value = user
  editableUser.value = {
    renewalCommissionRate: getNodeRenewalRate(user) != null ? (getNodeRenewalRate(user) as number) * 100 : null,
    overrideCommissionRate: getNodeOverrideRate(user) != null ? (getNodeOverrideRate(user) as number) * 100 : null,
  }
}

const closePanel = () => {
  selectedUser.value = null
  editableUser.value = {}
}

const normalizeRateValue = (value?: number | null) => {
  if (value === null) return null
  if (typeof value === 'number') return value / 100
  return undefined
}

const saveTeamChanges = async (target: TreeNode) => {
  if (!auth.enabled) {
    toast.warning('Tryb API jest wyłączony.')
    return
  }
  const teamGroupPath = target.teamGroupPath
  if (!teamGroupPath) return
  const renewal = normalizeRateValue(editableUser.value.renewalCommissionRate)
  const override = normalizeRateValue(editableUser.value.overrideCommissionRate)

  const { data: updated } = await api.put('/v1/crm-team-commission-thresholds', {
    team_group_path: teamGroupPath,
    renewal_commission_rate: renewal ?? null,
    override_commission_rate: override ?? null,
  })

  const mapped = {
    id: String(updated.id),
    renewalCommissionRate: updated.renewal_commission_rate != null ? Number(updated.renewal_commission_rate) : null,
    overrideCommissionRate: updated.override_commission_rate != null ? Number(updated.override_commission_rate) : null,
  }
  teamThresholds.value = { ...teamThresholds.value, [teamGroupPath]: mapped }
  toast.success(`Zaktualizowano stawki dla ${target.name}.`)
  closePanel()
}

const saveChanges = async () => {
  const target = selectedUser.value
  const actor = currentUser.value
  if (!target || !actor) return

  if ((target as TreeNode).isTeamNode) {
    await saveTeamChanges(target as TreeNode)
    return
  }

  const update: Partial<User> = {}
  const renewal = normalizeRateValue(editableUser.value.renewalCommissionRate)
  const override = normalizeRateValue(editableUser.value.overrideCommissionRate)

  update.renewalCommissionRate = renewal ?? undefined

  if (['MANAGER', 'DIRECTOR'].includes(target.role)) {
    update.overrideCommissionRate = override ?? undefined
  }

  await structure.updateUserAdmin(target.id, update, actor.id)
  toast.success(`Zaktualizowano stawki dla ${target.name}.`)
  closePanel()
}

const fetchTeamThresholds = async () => {
  if (!auth.enabled) return
  teamThresholdsLoading.value = true
  try {
    const { data } = await api.get('/v1/crm-team-commission-thresholds')
    const list = Array.isArray(data) ? data : []
    const mapped = list.reduce((acc, item) => {
      if (!item?.team_group_path) return acc
      acc[item.team_group_path] = {
        id: String(item.id),
        renewalCommissionRate: item.renewal_commission_rate != null ? Number(item.renewal_commission_rate) : null,
        overrideCommissionRate: item.override_commission_rate != null ? Number(item.override_commission_rate) : null,
      }
      return acc
    }, {} as Record<string, { id: string; renewalCommissionRate: number | null; overrideCommissionRate: number | null }>)
    teamThresholds.value = mapped
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać progów zespołów.'
    toast.error(message)
  } finally {
    teamThresholdsLoading.value = false
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
  commissionConfig,
  (value) => {
    globalConfig.value = { ...value }
  },
  { immediate: true }
)

const saveGlobalConfig = () => {
  finance.updateCommissionConfig(globalConfig.value)
  toast.success('Zapisano konfigurację prowizji.')
}

watch(
  () => auth.isAuthenticated,
  (isAuthed) => {
    if (auth.enabled && isAuthed) fetchTeamThresholds()
    if (auth.enabled && !isAuthed) teamThresholds.value = {}
  },
  { immediate: true }
)

onMounted(() => {
  if (auth.enabled && auth.isAuthenticated) fetchTeamThresholds()
})
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)]">
    <header class="mb-6 flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Progi Prowizyjne</h1>
        <p class="text-sm text-gray-500">Zarządzaj indywidualnymi stawkami prowizji dla struktury sprzedażowej.</p>
      </div>
      <div v-if="teamThresholdsLoading" class="text-xs text-gray-500 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
        Ładowanie progów zespołów
      </div>
    </header>

    <div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="font-bold text-gray-700">Konfiguracja Prowizji (Globalna)</h3>
      </div>
      <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Prowizja Handlowca (Umowa &lt;= 14 dni)</label>
          <div class="flex items-center">
            <input v-model.number="globalConfig.salesCommissionFirstMonthLt14" type="number" step="0.01" class="flex-1 border p-2 rounded text-sm bg-white" />
            <span class="ml-2 text-sm text-gray-500">% (dziesiętnie)</span>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Prowizja Handlowca (Umowa &gt; 14 dni)</label>
          <input v-model.number="globalConfig.salesCommissionFirstMonthGt14" type="number" step="0.01" class="w-full border p-2 rounded text-sm bg-white" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Prowizja Odnowieniowa (2 msc+)</label>
          <input v-model.number="globalConfig.salesCommissionRenewal" type="number" step="0.01" class="w-full border p-2 rounded text-sm bg-white" />
        </div>
        <div class="md:col-span-3 flex justify-end">
          <button type="button" class="px-4 py-2 bg-slate-900 text-white rounded hover:bg-slate-800 text-sm font-bold" @click="saveGlobalConfig">
            Zapisz Konfigurację
          </button>
        </div>
      </div>
    </div>

    <div class="flex-1 relative overflow-hidden bg-white shadow-lg rounded-xl border border-gray-200">
      <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 grid grid-cols-12 text-xs font-bold text-gray-500 uppercase tracking-wider">
        <div class="col-span-4">Struktura Handlowa</div>
        <div class="col-span-2">Prow. Odnowieniowa</div>
        <div class="col-span-2">Prow. od Zespołu</div>
        <div class="col-span-4"></div>
      </div>

      <div class="overflow-y-auto h-[calc(100%-57px)]">
        <div class="divide-y divide-gray-100">
          <div
            v-for="node in visibleNodes"
            :key="node.id"
            class="group relative transition-colors duration-200"
            :class="selectedUser?.id === node.id ? 'bg-sky-50' : 'hover:bg-gray-50'"
          >
            <div class="px-6 py-2 grid grid-cols-12 items-center min-h-[50px]">
              <div class="col-span-4 flex items-center relative overflow-hidden">
                <div class="absolute left-0 top-0 h-full" :style="{ width: `${node.level * 28}px` }">
                  <div v-for="(hasSibling, index) in node.parentChain" :key="index">
                    <div v-if="hasSibling" class="absolute top-0 w-px h-full bg-gray-300" :style="{ left: `${index * 28 + 14}px` }"></div>
                  </div>
                  <div v-if="node.level > 0">
                    <div
                      class="absolute top-0 w-px bg-gray-300"
                      :class="node.isLast ? 'h-1/2' : 'h-full'"
                      :style="{ left: `${(node.level - 1) * 28 + 14}px` }"
                    ></div>
                    <div class="absolute h-px w-3.5 bg-gray-300" :style="{ top: '50%', left: `${(node.level - 1) * 28 + 14}px` }"></div>
                  </div>
                </div>

                <div :style="{ width: `${node.level * 28}px` }" class="flex-shrink-0"></div>

                <button
                  type="button"
                  class="w-6 h-6 flex items-center justify-center mr-1 flex-shrink-0 text-gray-400 hover:text-sky-600 rounded-full hover:bg-gray-200"
                  @click.stop="toggleNode(node.id)"
                >
                  <svg v-if="node.hasChildren" class="w-4 h-4 transition-transform duration-200" :class="isExpanded(node.id) ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                  </svg>
                  <span v-else class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                </button>

                <div
                  class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border shadow-sm mr-3"
                  :class="{
                    'bg-amber-100 text-amber-800 border-amber-200': node.role === 'DIRECTOR',
                    'bg-sky-100 text-sky-800 border-sky-200': node.role === 'MANAGER',
                    'bg-emerald-100 text-emerald-800 border-emerald-200': node.role === 'SALES',
                    'bg-red-100 text-red-800 border-red-200': node.role === 'ADMIN',
                    'bg-slate-100 text-slate-700 border-slate-200': node.isTeamNode,
                  }"
                >
                  {{ getInitials(node.name) }}
                </div>
                <div>
                  <div class="text-sm font-semibold text-gray-800">{{ node.name }}</div>
                  <div class="text-[10px] text-gray-500">{{ node.isTeamNode ? 'ZESPÓŁ' : node.role }}</div>
                </div>
              </div>
              <div class="col-span-2 text-sm font-mono font-bold text-gray-700">
                <span v-if="getNodeRenewalRate(node) != null">{{ (getNodeRenewalRate(node) as number * 100).toFixed(2) }}%</span>
                <span v-else>-</span>
              </div>
              <div class="col-span-2 text-sm font-mono font-bold text-indigo-700">
                <template v-if="node.isTeamNode || ['MANAGER', 'DIRECTOR'].includes(node.role)">
                  <span v-if="getNodeOverrideRate(node) != null">{{ (getNodeOverrideRate(node) as number * 100).toFixed(2) }}%</span>
                  <span v-else>-</span>
                </template>
                <span v-else class="text-gray-400">-</span>
              </div>
              <div class="col-span-4 text-right">
                <button
                  v-if="node.role !== 'ADMIN'"
                  type="button"
                  class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-100 hover:text-sky-700 hover:border-sky-300"
                  @click="selectUserForEditing(node)"
                >
                  Zarządzaj
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedUser" class="fixed inset-0 z-40">
      <div class="absolute inset-0 bg-black/30" @click="closePanel"></div>
      <div class="fixed top-0 right-0 h-full w-full max-w-md bg-gray-50 z-50 shadow-2xl flex flex-col animate-slide-in-right">
        <div class="p-4 bg-white border-b border-gray-200 flex-shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-lg font-bold text-gray-900">{{ selectedUser.name }}</h3>
              <p class="text-xs text-gray-500">{{ selectedUser.isTeamNode ? 'ZESPÓŁ' : selectedUser.role }}</p>
            </div>
            <button type="button" class="p-2 text-gray-400 hover:bg-gray-100 rounded-full" @click="closePanel">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <div class="bg-white p-4 border border-gray-200 rounded">
            <h4 class="font-bold text-gray-700 uppercase text-xs mb-3">Prowizja Odnowieniowa</h4>
            <p class="text-xs text-gray-500 mb-2">Procent od opłaty serwisowej dla tego handlowca przy fakturach od 2-go miesiąca współpracy klienta.</p>
            <div class="relative">
              <input v-model.number="editableUser.renewalCommissionRate" type="number" step="0.1" class="w-full pl-4" placeholder="np. 4" />
              <span class="absolute right-3 top-2.5 text-gray-400">%</span>
            </div>
          </div>

          <div v-if="selectedUser.isTeamNode || ['MANAGER', 'DIRECTOR'].includes(selectedUser.role)" class="bg-white p-4 border border-gray-200 rounded">
            <h4 class="font-bold text-indigo-700 uppercase text-xs mb-3">Prowizja od Zespołu (Override)</h4>
            <p class="text-xs text-gray-500 mb-2">Procent od opłaty serwisowej z faktur wystawionych przez bezpośrednio podległych handlowców.</p>
            <div class="relative">
              <input v-model.number="editableUser.overrideCommissionRate" type="number" step="0.1" class="w-full pl-4" placeholder="np. 10" />
              <span class="absolute right-3 top-2.5 text-gray-400">%</span>
            </div>
          </div>
        </div>

        <div class="p-4 bg-white border-t border-gray-200 flex-shrink-0 flex justify-end gap-3">
          <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50" @click="closePanel">Anuluj</button>
          <button type="button" class="px-4 py-2 bg-sky-600 text-white rounded text-sm hover:bg-sky-700 shadow-sm font-semibold" @click="saveChanges">Zapisz Zmiany</button>
        </div>
      </div>
    </div>
  </div>
</template>
