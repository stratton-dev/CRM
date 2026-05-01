<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useChatStore, type ConversationMember } from '@/stores/chat'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'

const chat = useChatStore()
const session = useSessionStore()
const toast = useToastStore()

const {
  isOpen,
  conversations,
  activeConversationId,
  activeMessages,
  activeConversation,
  loadingMessages,
  loadingConversations,
  loadingUsers,
  chatUsers,
  generalConversation,
  teamConversations,
  dmConversations,
} = storeToRefs(chat)

// ── Theme ──────────────────────────────────────────────────────────────────
const isDark = ref(true)

const theme = computed(() => isDark.value ? {
  panel:       'chat-dark-panel',
  sidebar:     'bg-black/10 border-white/8',
  sidebarItem: 'hover:bg-white/6',
  sidebarActive:'bg-stratton-gold/10 border-l-2 border-stratton-gold',
  sidebarInactive:'border-l-2 border-transparent',
  header:      'bg-black/15 border-white/8',
  messages:    '',
  inputArea:   'bg-black/20 border-white/8',
  input:       'bg-white/6 border-white/10 text-white placeholder-slate-500 focus-within:border-stratton-gold/40 focus-within:bg-white/8',
  sectionTitle:'text-slate-400',
  nameText:    'text-white',
  subText:     'text-slate-300',
  timeText:    'text-slate-400',
  separator:   'bg-white/8',
  dateSep:     'text-slate-400',
  ownBubble:   'bg-stratton-gold/15 border border-stratton-gold/25 text-white',
  otherBubble: 'bg-white/8 border border-white/10 text-slate-100',
  avatarBg:    'bg-white/10',
  avatarText:  'text-slate-100',
  badge:       'bg-stratton-gold text-[#001f3d]',
  sendBtn:     'bg-stratton-gold hover:bg-stratton-gold/90 text-[#001f3d] shadow-[0_0_12px_rgba(212,175,55,0.3)]',
  sendDisabled:'bg-white/4 text-slate-500',
  searchInput: 'bg-white/6 border-white/10 text-slate-200 placeholder-slate-500 focus:border-stratton-gold/40',
  divider:     'bg-white/8',
  online:      'bg-emerald-400',
  tagBg:       'bg-stratton-gold/10 border-stratton-gold/20 text-stratton-gold',
  closebtn:    'text-slate-400 hover:text-white hover:bg-white/8',
  modal:       'bg-[#002040] border border-white/10',
  modalInput:  'bg-white/6 border-white/10 text-white placeholder-slate-500 focus:border-stratton-gold/40 focus:outline-none',
  checkbox:    'accent-stratton-gold',
  dangerBtn:   'text-red-400 hover:text-red-300 hover:bg-red-500/10',
} : {
  panel:       'bg-white',
  sidebar:     'bg-gray-50 border-gray-200',
  sidebarItem: 'hover:bg-gray-100',
  sidebarActive:'bg-stratton-gold/10 border-l-2 border-stratton-gold',
  sidebarInactive:'border-l-2 border-transparent',
  header:      'bg-white border-gray-200',
  messages:    'bg-gray-50',
  inputArea:   'bg-white border-gray-200',
  input:       'bg-gray-100 border-gray-200 text-gray-800 placeholder-gray-400 focus-within:border-stratton-gold/60 focus-within:bg-white',
  sectionTitle:'text-gray-400',
  nameText:    'text-gray-800',
  subText:     'text-gray-500',
  timeText:    'text-gray-400',
  separator:   'bg-gray-200',
  dateSep:     'text-gray-400',
  ownBubble:   'bg-stratton-gold/15 border border-stratton-gold/30 text-gray-900',
  otherBubble: 'bg-white border border-gray-200 text-gray-800',
  avatarBg:    'bg-gray-200',
  avatarText:  'text-gray-600',
  badge:       'bg-stratton-gold text-white',
  sendBtn:     'bg-stratton-gold hover:bg-stratton-gold/90 text-white',
  sendDisabled:'bg-gray-200 text-gray-400',
  searchInput: 'bg-white border-gray-200 text-gray-700 placeholder-gray-400 focus:border-stratton-gold/60',
  divider:     'bg-gray-200',
  online:      'bg-emerald-500',
  tagBg:       'bg-stratton-gold/10 border-stratton-gold/20 text-stratton-gold',
  closebtn:    'text-gray-400 hover:text-gray-700 hover:bg-gray-100',
  modal:       'bg-white border border-gray-200',
  modalInput:  'bg-gray-100 border-gray-200 text-gray-800 placeholder-gray-400 focus:border-stratton-gold/60 focus:outline-none',
  checkbox:    'accent-stratton-gold',
  dangerBtn:   'text-red-500 hover:text-red-700 hover:bg-red-50',
})

// ── State ──────────────────────────────────────────────────────────────────
const messageInput = ref('')
const messagesEl = ref<HTMLElement | null>(null)
const inputEl = ref<HTMLTextAreaElement | null>(null)
const userSearch = ref('')
const convSearch = ref('')
const expandedSections = ref({ general: true, teams: true, dm: true, users: false })
const collapsedNodes = ref(new Set<string>())

// Opening DM loading state per user
const openingDmFor = ref<string | null>(null)

// Group creation
const showCreateGroup = ref(false)
const newGroupName = ref('')
const newGroupMemberIds = ref<string[]>([])
const groupMemberSearch = ref('')
const creatingGroup = ref(false)

// Members panel (for current conversation)
const showMembersPanel = ref(false)
const conversationMembers = ref<ConversationMember[]>([])
const loadingMembers = ref(false)
const addMemberSearch = ref('')
const addingMember = ref(false)

// ── Tree builder ───────────────────────────────────────────────────────────
type TreeNode = {
  user: typeof chatUsers.value[0]
  depth: number
  children: TreeNode[]
}

const buildTree = (users: typeof chatUsers.value): TreeNode[] => {
  const nodeMap = new Map<string, TreeNode>()
  const roots: TreeNode[] = []

  for (const u of users) {
    nodeMap.set(u.supabaseId, { user: u, depth: 0, children: [] })
  }

  for (const u of users) {
    const node = nodeMap.get(u.supabaseId)!
    const parentId = u.parentSupabaseId
    if (parentId && nodeMap.has(parentId)) {
      nodeMap.get(parentId)!.children.push(node)
    } else {
      roots.push(node)
    }
  }

  const setDepths = (nodes: TreeNode[], depth: number) => {
    for (const n of nodes) {
      n.depth = depth
      setDepths(n.children, depth + 1)
    }
  }
  setDepths(roots, 0)
  return roots
}

const flattenTree = (nodes: TreeNode[], collapsed: Set<string>): Array<TreeNode & { visible: boolean }> => {
  const result: Array<TreeNode & { visible: boolean }> = []
  const walk = (list: TreeNode[], parentVisible: boolean) => {
    for (const node of list) {
      const visible = parentVisible
      result.push({ ...node, visible })
      const isCollapsed = collapsed.has(node.user.supabaseId)
      walk(node.children, visible && !isCollapsed)
    }
  }
  walk(nodes, true)
  return result
}

const userTree = computed(() => buildTree(chatUsers.value))

const flatUsers = computed(() => {
  const q = userSearch.value.toLowerCase().trim()
  if (q) {
    return chatUsers.value
      .filter((u) => u.name.toLowerCase().includes(q) || (u.role ?? '').toLowerCase().includes(q))
      .map((u) => ({ user: u, depth: 0, children: [], visible: true }))
  }
  return flattenTree(userTree.value, collapsedNodes.value)
})

const toggleNode = (supabaseId: string) => {
  if (collapsedNodes.value.has(supabaseId)) {
    collapsedNodes.value = new Set([...collapsedNodes.value].filter((id) => id !== supabaseId))
  } else {
    collapsedNodes.value = new Set([...collapsedNodes.value, supabaseId])
  }
}

// ── Computed ───────────────────────────────────────────────────────────────
const filteredUsers = computed(() => flatUsers.value.filter((n) => n.visible))

const filteredDms = computed(() => {
  const q = convSearch.value.toLowerCase().trim()
  return q ? dmConversations.value.filter((c) => c.name.toLowerCase().includes(q)) : dmConversations.value
})

const groupedMessages = computed(() => {
  const msgs = activeMessages.value
  if (!msgs.length) return []
  const groups: Array<{ date: string; messages: typeof msgs }> = []
  let lastDate = ''
  for (const msg of msgs) {
    const d = new Date(msg.createdAt)
    const dateStr = d.toLocaleDateString('pl', { weekday: 'long', day: 'numeric', month: 'long' })
    if (dateStr !== lastDate) {
      groups.push({ date: dateStr, messages: [] })
      lastDate = dateStr
    }
    groups[groups.length - 1].messages.push(msg)
  }
  return groups
})

const groupMemberSearchResults = computed(() => {
  const q = groupMemberSearch.value.toLowerCase().trim()
  return chatUsers.value.filter((u) =>
    !q || u.name.toLowerCase().includes(q) || (u.role ?? '').toLowerCase().includes(q)
  )
})

const addMemberCandidates = computed(() => {
  const q = addMemberSearch.value.toLowerCase().trim()
  const memberIds = new Set(conversationMembers.value.map((m) => m.supabaseId))
  return chatUsers.value.filter((u) =>
    !memberIds.has(u.supabaseId) &&
    (!q || u.name.toLowerCase().includes(q) || (u.role ?? '').toLowerCase().includes(q))
  )
})

// ── Helpers ────────────────────────────────────────────────────────────────
const initials = (name: string) => {
  const parts = name.trim().split(/\s+/)
  return parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : name.slice(0, 2).toUpperCase()
}

const timeLabel = (iso: string) => {
  const d = new Date(iso)
  const now = new Date()
  const diffH = (now.getTime() - d.getTime()) / 3600000
  if (diffH < 24) return d.toLocaleTimeString('pl', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('pl', { day: 'numeric', month: 'short' })
}

// ── Actions ────────────────────────────────────────────────────────────────
const scrollToBottom = async () => {
  await nextTick()
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
}

watch(activeMessages, () => scrollToBottom(), { deep: true })
watch(isOpen, async (open) => {
  if (open) {
    await nextTick()
    inputEl.value?.focus()
  }
})

const selectConversation = async (id: number) => {
  showMembersPanel.value = false
  await chat.openConversation(id)
  scrollToBottom()
}

const openDmWithUser = async (supabaseId: string) => {
  if (openingDmFor.value) return
  openingDmFor.value = supabaseId
  try {
    const convId = await chat.findOrCreateDm(supabaseId)
    if (convId) {
      await selectConversation(convId)
    } else {
      toast.error('Nie można otworzyć rozmowy')
    }
  } catch {
    toast.error('Błąd połączenia z serwerem')
  } finally {
    openingDmFor.value = null
  }
}

const openGeneral = async () => {
  let convId = generalConversation.value?.id
  if (!convId) {
    try {
      convId = await chat.ensureGeneralChat() ?? undefined
    } catch {
      toast.error('Nie można otworzyć kanału ogólnego')
      return
    }
  }
  if (convId) await selectConversation(convId)
}

const openTeam = async (convId: number) => {
  await selectConversation(convId)
}

const send = async () => {
  const body = messageInput.value.trim()
  if (!body) return
  messageInput.value = ''
  await chat.sendMessage(body)
  scrollToBottom()
}

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send() }
}

const autoResize = (e: Event) => {
  const el = e.target as HTMLTextAreaElement
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 96) + 'px'
}

const toggleSection = (key: keyof typeof expandedSections.value) => {
  expandedSections.value[key] = !expandedSections.value[key]
}

// ── Group creation ─────────────────────────────────────────────────────────
const openCreateGroup = () => {
  showCreateGroup.value = true
  newGroupName.value = ''
  newGroupMemberIds.value = []
  groupMemberSearch.value = ''
}

const toggleGroupMember = (supabaseId: string) => {
  const idx = newGroupMemberIds.value.indexOf(supabaseId)
  if (idx >= 0) newGroupMemberIds.value.splice(idx, 1)
  else newGroupMemberIds.value.push(supabaseId)
}

const submitCreateGroup = async () => {
  if (!newGroupName.value.trim()) {
    toast.error('Podaj nazwę grupy')
    return
  }
  creatingGroup.value = true
  try {
    const convId = await chat.createGroup(newGroupName.value.trim(), newGroupMemberIds.value)
    if (convId) {
      showCreateGroup.value = false
      await selectConversation(convId)
      toast.success('Grupa została utworzona')
    } else {
      toast.error('Nie można utworzyć grupy')
    }
  } catch {
    toast.error('Błąd tworzenia grupy')
  } finally {
    creatingGroup.value = false
  }
}

// ── Member management ──────────────────────────────────────────────────────
const openMembersPanel = async () => {
  if (!activeConversationId.value) return
  showMembersPanel.value = true
  loadingMembers.value = true
  addMemberSearch.value = ''
  conversationMembers.value = await chat.fetchConversationMembers(activeConversationId.value)
  loadingMembers.value = false
}

const handleAddMember = async (supabaseId: string) => {
  if (!activeConversationId.value || addingMember.value) return
  addingMember.value = true
  const ok = await chat.addMember(activeConversationId.value, supabaseId)
  if (ok) {
    toast.success('Dodano do grupy')
    conversationMembers.value = await chat.fetchConversationMembers(activeConversationId.value)
    addMemberSearch.value = ''
  } else {
    toast.error('Nie można dodać użytkownika')
  }
  addingMember.value = false
}

const handleRemoveMember = async (supabaseId: string) => {
  if (!activeConversationId.value) return
  const ok = await chat.removeMember(activeConversationId.value, supabaseId)
  if (ok) {
    conversationMembers.value = conversationMembers.value.filter((m) => m.supabaseId !== supabaseId)
    toast.success('Usunięto z grupy')
  } else {
    toast.error('Nie można usunąć użytkownika')
  }
}

const handleLeave = async () => {
  if (!activeConversationId.value) return
  const ok = await chat.leaveConversation(activeConversationId.value)
  if (ok) {
    showMembersPanel.value = false
    toast.success('Opuściłeś rozmowę')
  } else {
    toast.error('Nie można opuścić rozmowy')
  }
}
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition name="chat-backdrop">
      <div v-if="isOpen" class="fixed inset-0 z-80 bg-black/40 backdrop-blur-[2px]" @click="chat.close()" />
    </Transition>

    <!-- Panel -->
    <Transition name="chat-slide">
      <div
        v-if="isOpen"
        class="fixed right-0 top-0 bottom-0 z-90 flex shadow-2xl"
        :class="theme.panel"
        style="width: 820px; max-width: 100vw; box-shadow: -8px 0 60px rgba(0,0,0,0.5)"
        @click.stop
      >

        <!-- ═══════════════════════════════════════════════════
             LEFT SIDEBAR
        ════════════════════════════════════════════════════ -->
        <div
          class="w-72 shrink-0 flex flex-col border-r overflow-hidden"
          :class="theme.sidebar"
        >
          <!-- Header -->
          <div class="h-14 flex items-center justify-between px-4 border-b shrink-0" :class="theme.header">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-stratton-gold shadow-[0_0_6px_rgba(212,175,55,0.7)]" />
              <span class="text-[11px] font-black uppercase tracking-[0.2em] text-stratton-gold">Komunikator</span>
            </div>
            <div class="flex items-center gap-1">
              <!-- New group button -->
              <button
                title="Utwórz grupę"
                class="p-1.5 rounded-lg transition-all"
                :class="theme.closebtn"
                @click="openCreateGroup"
              >
                <AppIcon name="user-group" class="w-4 h-4" />
              </button>
              <!-- Theme toggle -->
              <button
                :title="isDark ? 'Tryb jasny' : 'Tryb ciemny'"
                class="p-1.5 rounded-lg transition-all"
                :class="theme.closebtn"
                @click="isDark = !isDark"
              >
                <svg v-if="isDark" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12H3m15.07-6.07-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10A5 5 0 0012 7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                </svg>
              </button>
              <button class="p-1.5 rounded-lg transition-all" :class="theme.closebtn" @click="chat.close()">
                <AppIcon name="xmark" class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Search -->
          <div class="px-3 pt-3 pb-2 shrink-0">
            <div class="relative">
              <AppIcon name="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none" :class="theme.subText" />
              <input
                v-model="convSearch"
                type="text"
                placeholder="Szukaj rozmów..."
                class="w-full rounded-lg pl-8 pr-3 py-1.5 text-[11px] border transition-all focus:outline-none"
                :class="theme.searchInput"
              />
            </div>
          </div>

          <!-- Scrollable nav -->
          <div class="flex-1 overflow-y-auto chat-scroll">

            <!-- ── OGÓLNY ──────────────────────────────── -->
            <div class="mb-1">
              <button
                class="w-full flex items-center justify-between px-4 py-1.5 transition-all"
                :class="theme.sidebarItem"
                @click="toggleSection('general')"
              >
                <span class="text-[9px] font-black uppercase tracking-[0.2em]" :class="theme.sectionTitle">Ogólny</span>
                <AppIcon :name="expandedSections.general ? 'chevron-down' : 'chevron-right'" class="w-3 h-3" :class="theme.sectionTitle" />
              </button>
              <div v-show="expandedSections.general">
                <button
                  class="w-full flex items-center gap-2.5 px-3 py-2 transition-all"
                  :class="[
                    theme.sidebarItem,
                    generalConversation?.id === activeConversationId ? theme.sidebarActive : theme.sidebarInactive
                  ]"
                  @click="openGeneral"
                >
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" :class="theme.tagBg">
                    <AppIcon name="users" class="w-4 h-4 text-stratton-gold" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold truncate" :class="theme.nameText">Ogólny</p>
                    <p class="text-[10px] truncate" :class="theme.subText">
                      {{ generalConversation?.lastMessage?.body ?? 'Kanał dla wszystkich' }}
                    </p>
                  </div>
                  <span v-if="(generalConversation?.unread ?? 0) > 0" class="text-[9px] font-black min-w-4 h-4 rounded-full flex items-center justify-center px-1" :class="theme.badge">
                    {{ generalConversation!.unread }}
                  </span>
                </button>
              </div>
            </div>

            <div class="mx-3 h-px mb-1" :class="theme.divider" />

            <!-- ── ZESPOŁY ─────────────────────────────── -->
            <div class="mb-1">
              <button
                class="w-full flex items-center justify-between px-4 py-1.5 transition-all"
                :class="theme.sidebarItem"
                @click="toggleSection('teams')"
              >
                <span class="text-[9px] font-black uppercase tracking-[0.2em]" :class="theme.sectionTitle">Zespoły</span>
                <AppIcon :name="expandedSections.teams ? 'chevron-down' : 'chevron-right'" class="w-3 h-3" :class="theme.sectionTitle" />
              </button>
              <div v-show="expandedSections.teams">
                <div v-if="teamConversations.length === 0" class="px-4 py-2">
                  <p class="text-[10px]" :class="theme.subText">Brak czatów zespołowych</p>
                </div>
                <button
                  v-for="conv in teamConversations"
                  :key="conv.id"
                  class="w-full flex items-center gap-2.5 px-3 py-2 transition-all"
                  :class="[
                    theme.sidebarItem,
                    conv.id === activeConversationId ? theme.sidebarActive : theme.sidebarInactive
                  ]"
                  @click="openTeam(conv.id)"
                >
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 bg-violet-500/15 border border-violet-500/25">
                    <AppIcon name="people-group" class="w-4 h-4 text-violet-400" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold truncate" :class="theme.nameText">{{ conv.name }}</p>
                    <p class="text-[10px] truncate" :class="theme.subText">{{ conv.lastMessage?.body ?? 'Czat zespołu' }}</p>
                  </div>
                  <span v-if="conv.unread > 0" class="text-[9px] font-black min-w-4 h-4 rounded-full flex items-center justify-center px-1" :class="theme.badge">
                    {{ conv.unread }}
                  </span>
                </button>
              </div>
            </div>

            <div class="mx-3 h-px mb-1" :class="theme.divider" />

            <!-- ── WIADOMOŚCI BEZPOŚREDNIE ─────────────── -->
            <div class="mb-1">
              <button
                class="w-full flex items-center justify-between px-4 py-1.5 transition-all"
                :class="theme.sidebarItem"
                @click="toggleSection('dm')"
              >
                <span class="text-[9px] font-black uppercase tracking-[0.2em]" :class="theme.sectionTitle">Bezpośrednie</span>
                <AppIcon :name="expandedSections.dm ? 'chevron-down' : 'chevron-right'" class="w-3 h-3" :class="theme.sectionTitle" />
              </button>
              <div v-show="expandedSections.dm">
                <div v-if="filteredDms.length === 0" class="px-4 py-2">
                  <p class="text-[10px]" :class="theme.subText">Brak aktywnych rozmów</p>
                </div>
                <button
                  v-for="conv in filteredDms"
                  :key="conv.id"
                  class="w-full flex items-center gap-2.5 px-3 py-2 transition-all"
                  :class="[
                    theme.sidebarItem,
                    conv.id === activeConversationId ? theme.sidebarActive : theme.sidebarInactive
                  ]"
                  @click="selectConversation(conv.id)"
                >
                  <div class="relative shrink-0">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black shrink-0" :class="[theme.avatarBg, theme.avatarText]">
                      {{ initials(conv.name) }}
                    </div>
                    <div class="absolute bottom-0 right-0 w-2 h-2 rounded-full border-2 border-current" :class="theme.online" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                      <span class="text-[12px] font-semibold truncate" :class="theme.nameText">{{ conv.name }}</span>
                      <span v-if="conv.lastMessage" class="text-[9px] shrink-0 ml-1" :class="theme.timeText">{{ timeLabel(conv.lastMessage.createdAt) }}</span>
                    </div>
                    <p class="text-[10px] truncate mt-0.5" :class="theme.subText">{{ conv.lastMessage?.body ?? 'Zacznij rozmowę' }}</p>
                  </div>
                  <span v-if="conv.unread > 0" class="shrink-0 min-w-4 h-4 rounded-full text-[9px] font-black flex items-center justify-center px-1" :class="theme.badge">
                    {{ conv.unread > 99 ? '99+' : conv.unread }}
                  </span>
                </button>
              </div>
            </div>

            <div class="mx-3 h-px mb-1" :class="theme.divider" />

            <!-- ── WSZYSCY UŻYTKOWNICY (drzewo) ──────── -->
            <div class="mb-2">
              <button
                class="w-full flex items-center justify-between px-4 py-1.5 transition-all"
                :class="theme.sidebarItem"
                @click="toggleSection('users')"
              >
                <span class="text-[9px] font-black uppercase tracking-[0.2em]" :class="theme.sectionTitle">
                  Użytkownicy
                  <span class="ml-1 opacity-60">({{ chatUsers.length }})</span>
                </span>
                <AppIcon :name="expandedSections.users ? 'chevron-down' : 'chevron-right'" class="w-3 h-3" :class="theme.sectionTitle" />
              </button>

              <div v-show="expandedSections.users">
                <!-- Search -->
                <div class="px-3 pb-2">
                  <div class="relative">
                    <AppIcon name="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 pointer-events-none" :class="theme.subText" />
                    <input
                      v-model="userSearch"
                      type="text"
                      placeholder="Szukaj..."
                      class="w-full rounded-lg pl-7 pr-3 py-1.5 text-[11px] border transition-all focus:outline-none"
                      :class="theme.searchInput"
                    />
                  </div>
                </div>

                <div v-if="loadingUsers" class="flex items-center justify-center h-10">
                  <div class="w-3.5 h-3.5 border-2 border-stratton-gold/30 border-t-stratton-gold rounded-full animate-spin" />
                </div>

                <!-- Tree nodes -->
                <div
                  v-for="node in filteredUsers"
                  :key="node.user.supabaseId"
                  class="flex items-center group transition-all"
                  :class="[theme.sidebarInactive, theme.sidebarItem]"
                  :style="{ paddingLeft: `${node.depth * 14 + 10}px` }"
                >
                  <!-- Indent lines -->
                  <template v-if="node.depth > 0">
                    <div
                      v-for="d in node.depth"
                      :key="d"
                      class="shrink-0 w-px h-full mr-1 opacity-20"
                      :class="theme.divider"
                    />
                  </template>

                  <!-- Collapse toggle -->
                  <button
                    v-if="node.children.length > 0"
                    class="shrink-0 w-4 h-4 flex items-center justify-center rounded mr-1 transition-colors"
                    :class="theme.sectionTitle"
                    @click.stop="toggleNode(node.user.supabaseId)"
                  >
                    <AppIcon
                      :name="collapsedNodes.has(node.user.supabaseId) ? 'chevron-right' : 'chevron-down'"
                      class="w-2.5 h-2.5"
                    />
                  </button>
                  <div v-else class="w-5 shrink-0" />

                  <!-- User row -->
                  <button
                    class="flex-1 flex items-center gap-2 py-1.5 pr-2 text-left min-w-0 relative"
                    :disabled="openingDmFor === node.user.supabaseId"
                    @click="openDmWithUser(node.user.supabaseId)"
                  >
                    <div class="relative shrink-0">
                      <div
                        class="rounded-full flex items-center justify-center font-black"
                        :class="[
                          theme.avatarBg, theme.avatarText,
                          node.depth === 0 ? 'w-7 h-7 text-[9px]' : 'w-6 h-6 text-[8px]'
                        ]"
                      >
                        <span v-if="openingDmFor !== node.user.supabaseId">{{ initials(node.user.name) }}</span>
                        <div v-else class="w-3 h-3 border border-stratton-gold/50 border-t-stratton-gold rounded-full animate-spin" />
                      </div>
                      <div class="absolute -bottom-0.5 -right-0.5 w-1.5 h-1.5 rounded-full" :class="theme.online" />
                    </div>
                    <div class="flex-1 min-w-0">
                      <p
                        class="font-semibold truncate"
                        :class="[theme.nameText, node.depth === 0 ? 'text-[11px]' : 'text-[10px]']"
                      >{{ node.user.name }}</p>
                      <p class="text-[9px] truncate" :class="theme.subText">{{ node.user.role }}</p>
                    </div>
                  </button>
                </div>

                <div v-if="filteredUsers.length === 0 && !loadingUsers" class="px-4 py-2">
                  <p class="text-[10px]" :class="theme.subText">Brak wyników</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             RIGHT: CHAT WINDOW
        ════════════════════════════════════════════════════ -->
        <div class="flex-1 flex flex-col min-w-0" :class="theme.messages">

          <!-- Empty state / Group creation -->
          <template v-if="!activeConversationId && !showCreateGroup">
            <div class="flex-1 flex flex-col items-center justify-center gap-5 p-8">
              <div
                class="w-20 h-20 rounded-3xl flex items-center justify-center border"
                :class="theme.tagBg"
              >
                <AppIcon name="chat-bubble" class="w-10 h-10 text-stratton-gold/50" />
              </div>
              <div class="text-center">
                <p class="font-semibold text-sm" :class="isDark ? 'text-white' : 'text-gray-700'">Wybierz rozmowę</p>
                <p class="text-xs mt-1" :class="theme.subText">Kliknij kontakt lub kanał po lewej</p>
              </div>
              <div class="flex gap-2">
                <button
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all text-stratton-gold"
                  :class="theme.tagBg"
                  @click="openGeneral"
                >
                  <AppIcon name="users" class="w-3.5 h-3.5" />
                  Ogólny
                </button>
                <button
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all"
                  :class="theme.tagBg"
                  @click="openCreateGroup"
                >
                  <AppIcon name="user-group" class="w-3.5 h-3.5 text-stratton-gold" />
                  <span class="text-stratton-gold">Nowa grupa</span>
                </button>
              </div>
            </div>
          </template>

          <!-- Group creation panel -->
          <template v-else-if="showCreateGroup">
            <div class="h-14 flex items-center gap-3 px-5 border-b shrink-0" :class="theme.header">
              <button class="p-1.5 rounded-lg transition-all" :class="theme.closebtn" @click="showCreateGroup = false">
                <AppIcon name="chevron-left" class="w-4 h-4" />
              </button>
              <p class="text-[13px] font-bold" :class="theme.nameText">Utwórz grupę</p>
            </div>
            <div class="flex-1 overflow-y-auto p-5 chat-scroll">
              <!-- Group name -->
              <div class="mb-4">
                <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="theme.sectionTitle">Nazwa grupy</label>
                <input
                  v-model="newGroupName"
                  type="text"
                  placeholder="np. Projekt X, Dział sprzedaży..."
                  class="w-full rounded-lg px-3 py-2 text-sm border transition-all"
                  :class="theme.modalInput"
                  @keydown.enter="submitCreateGroup"
                />
              </div>

              <!-- Member search -->
              <div class="mb-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="theme.sectionTitle">
                  Dodaj uczestników
                  <span v-if="newGroupMemberIds.length" class="ml-1 text-stratton-gold">({{ newGroupMemberIds.length }} wybranych)</span>
                </label>
                <div class="relative mb-2">
                  <AppIcon name="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 pointer-events-none" :class="theme.subText" />
                  <input
                    v-model="groupMemberSearch"
                    type="text"
                    placeholder="Szukaj pracowników..."
                    class="w-full rounded-lg pl-7 pr-3 py-1.5 text-[11px] border transition-all"
                    :class="theme.modalInput"
                  />
                </div>
                <div class="space-y-1 max-h-64 overflow-y-auto chat-scroll">
                  <label
                    v-for="user in groupMemberSearchResults"
                    :key="user.supabaseId"
                    class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg cursor-pointer transition-all"
                    :class="theme.sidebarItem"
                  >
                    <input
                      type="checkbox"
                      :checked="newGroupMemberIds.includes(user.supabaseId)"
                      :class="theme.checkbox"
                      class="w-3.5 h-3.5 shrink-0"
                      @change="toggleGroupMember(user.supabaseId)"
                    />
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-black shrink-0" :class="[theme.avatarBg, theme.avatarText]">
                      {{ initials(user.name) }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-[11px] font-semibold truncate" :class="theme.nameText">{{ user.name }}</p>
                      <p class="text-[9px]" :class="theme.subText">{{ user.role }}</p>
                    </div>
                    <div v-if="newGroupMemberIds.includes(user.supabaseId)" class="w-4 h-4 rounded-full bg-stratton-gold flex items-center justify-center shrink-0">
                      <AppIcon name="check" class="w-2.5 h-2.5 text-white" />
                    </div>
                  </label>
                </div>
              </div>
            </div>
            <!-- Create button -->
            <div class="shrink-0 p-4 border-t" :class="theme.inputArea">
              <button
                :disabled="!newGroupName.trim() || creatingGroup"
                class="w-full py-2 rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2"
                :class="newGroupName.trim() && !creatingGroup ? theme.sendBtn : theme.sendDisabled"
                @click="submitCreateGroup"
              >
                <div v-if="creatingGroup" class="w-4 h-4 border-2 border-current/30 border-t-current rounded-full animate-spin" />
                <AppIcon v-else name="user-group" class="w-4 h-4" />
                {{ creatingGroup ? 'Tworzenie...' : 'Utwórz grupę' }}
              </button>
            </div>
          </template>

          <!-- Active conversation -->
          <template v-else-if="activeConversationId">

            <!-- Members panel overlay -->
            <template v-if="showMembersPanel">
              <div class="h-14 flex items-center gap-3 px-5 border-b shrink-0" :class="theme.header">
                <button class="p-1.5 rounded-lg transition-all" :class="theme.closebtn" @click="showMembersPanel = false">
                  <AppIcon name="chevron-left" class="w-4 h-4" />
                </button>
                <p class="text-[13px] font-bold" :class="theme.nameText">
                  Uczestnicy — {{ activeConversation?.name }}
                </p>
              </div>
              <div class="flex-1 overflow-y-auto p-4 chat-scroll space-y-4">
                <!-- Current members -->
                <div>
                  <p class="text-[9px] font-black uppercase tracking-widest mb-2" :class="theme.sectionTitle">
                    Członkowie ({{ conversationMembers.length }})
                  </p>
                  <div v-if="loadingMembers" class="flex justify-center py-6">
                    <div class="w-4 h-4 border-2 border-stratton-gold/30 border-t-stratton-gold rounded-full animate-spin" />
                  </div>
                  <div v-else class="space-y-1">
                    <div
                      v-for="member in conversationMembers"
                      :key="member.supabaseId"
                      class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg"
                      :class="theme.sidebarItem"
                    >
                      <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black shrink-0" :class="[theme.avatarBg, theme.avatarText]">
                        {{ initials(member.name) }}
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-semibold truncate" :class="theme.nameText">
                          {{ member.name }}
                          <span v-if="member.isMe" class="text-stratton-gold text-[9px] ml-1">(Ty)</span>
                        </p>
                        <p class="text-[9px]" :class="theme.subText">{{ member.role }}</p>
                      </div>
                      <button
                        v-if="!member.isMe && activeConversation?.isGroup"
                        class="p-1 rounded transition-all text-[10px]"
                        :class="theme.dangerBtn"
                        title="Usuń z grupy"
                        @click="handleRemoveMember(member.supabaseId)"
                      >
                        <AppIcon name="xmark" class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Add member (groups only) -->
                <div v-if="activeConversation?.isGroup">
                  <p class="text-[9px] font-black uppercase tracking-widest mb-2" :class="theme.sectionTitle">Dodaj uczestnika</p>
                  <div class="relative mb-2">
                    <AppIcon name="search" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 pointer-events-none" :class="theme.subText" />
                    <input
                      v-model="addMemberSearch"
                      type="text"
                      placeholder="Szukaj..."
                      class="w-full rounded-lg pl-7 pr-3 py-1.5 text-[11px] border transition-all"
                      :class="theme.modalInput"
                    />
                  </div>
                  <div class="space-y-1 max-h-40 overflow-y-auto chat-scroll">
                    <button
                      v-for="user in addMemberCandidates"
                      :key="user.supabaseId"
                      class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-lg transition-all"
                      :class="theme.sidebarItem"
                      :disabled="addingMember"
                      @click="handleAddMember(user.supabaseId)"
                    >
                      <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-black shrink-0" :class="[theme.avatarBg, theme.avatarText]">
                        {{ initials(user.name) }}
                      </div>
                      <div class="flex-1 min-w-0 text-left">
                        <p class="text-[11px] font-semibold truncate" :class="theme.nameText">{{ user.name }}</p>
                        <p class="text-[9px]" :class="theme.subText">{{ user.role }}</p>
                      </div>
                      <AppIcon name="plus" class="w-3.5 h-3.5 text-stratton-gold shrink-0" />
                    </button>
                    <p v-if="addMemberCandidates.length === 0" class="text-[10px] px-2 py-2" :class="theme.subText">
                      Wszyscy użytkownicy są już w grupie
                    </p>
                  </div>
                </div>

                <!-- Leave -->
                <div class="pt-2 border-t" :class="theme.divider">
                  <button
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold transition-all"
                    :class="theme.dangerBtn"
                    @click="handleLeave"
                  >
                    <AppIcon name="logout" class="w-4 h-4" />
                    Opuść {{ activeConversation?.isGroup ? 'grupę' : 'rozmowę' }}
                  </button>
                </div>
              </div>
            </template>

            <!-- Normal chat view -->
            <template v-else>
              <!-- Chat header -->
              <div class="h-14 flex items-center gap-3 px-5 border-b shrink-0" :class="theme.header">
                <!-- Avatar -->
                <div class="relative">
                  <div
                    class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-black"
                    :class="activeConversation?.isGroup
                      ? 'bg-stratton-gold/20 ring-1 ring-stratton-gold/40 text-stratton-gold'
                      : [theme.avatarBg, theme.avatarText]"
                  >
                    <AppIcon v-if="activeConversation?.key === 'general'" name="users" class="w-4 h-4 text-stratton-gold" />
                    <AppIcon v-else-if="activeConversation?.isGroup" name="people-group" class="w-4 h-4 text-violet-400" />
                    <template v-else>{{ initials(activeConversation?.name ?? '?') }}</template>
                  </div>
                  <div v-if="!activeConversation?.isGroup" class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2" :class="[theme.online, isDark ? 'border-[#002040]' : 'border-gray-50']" />
                </div>

                <div class="flex-1 min-w-0">
                  <p class="text-[13px] font-bold truncate" :class="theme.nameText">{{ activeConversation?.name }}</p>
                  <p class="text-[10px]" :class="activeConversation?.isGroup ? theme.subText : 'text-emerald-500'">
                    {{ activeConversation?.isGroup ? 'Kanał grupowy' : 'Online' }}
                  </p>
                </div>

                <!-- Header actions -->
                <div class="flex items-center gap-1.5">
                  <!-- Members button for groups -->
                  <button
                    v-if="activeConversation?.isGroup"
                    title="Uczestnicy"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-semibold border transition-all"
                    :class="isDark
                      ? 'bg-white/6 border-white/10 text-slate-300 hover:bg-white/10 hover:text-white'
                      : 'bg-gray-100 border-gray-200 text-gray-500 hover:bg-gray-200 hover:text-gray-700'"
                    @click="openMembersPanel"
                  >
                    <AppIcon name="users" class="w-3.5 h-3.5" />
                    Uczestnicy
                  </button>
                  <!-- DM: show members/leave button -->
                  <button
                    v-else
                    title="Opcje rozmowy"
                    class="p-1.5 rounded-lg transition-all"
                    :class="theme.closebtn"
                    @click="openMembersPanel"
                  >
                    <AppIcon name="ellipsis-horizontal" class="w-4 h-4" />
                  </button>
                  <!-- Theme toggle -->
                  <button
                    :title="isDark ? 'Tryb jasny' : 'Tryb ciemny'"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-semibold border transition-all"
                    :class="isDark
                      ? 'bg-white/6 border-white/10 text-slate-300 hover:bg-white/10 hover:text-white'
                      : 'bg-gray-100 border-gray-200 text-gray-500 hover:bg-gray-200 hover:text-gray-700'"
                    @click="isDark = !isDark"
                  >
                    <svg v-if="isDark" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12H3m15.07-6.07-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10A5 5 0 0012 7z" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                    </svg>
                    {{ isDark ? 'Jasny' : 'Ciemny' }}
                  </button>
                </div>
              </div>

              <!-- Messages -->
              <div ref="messagesEl" class="flex-1 overflow-y-auto p-5 space-y-1 chat-scroll">
                <div v-if="loadingMessages" class="flex items-center justify-center h-20">
                  <div class="w-4 h-4 border-2 border-stratton-gold/30 border-t-stratton-gold rounded-full animate-spin" />
                </div>
                <template v-else>
                  <div v-for="group in groupedMessages" :key="group.date" class="space-y-1">
                    <!-- Date separator -->
                    <div class="flex items-center gap-3 my-5">
                      <div class="flex-1 h-px" :class="theme.separator" />
                      <span class="text-[9px] uppercase tracking-widest font-semibold px-2" :class="theme.dateSep">{{ group.date }}</span>
                      <div class="flex-1 h-px" :class="theme.separator" />
                    </div>

                    <div
                      v-for="(msg, idx) in group.messages"
                      :key="msg.id"
                      class="flex items-end gap-2 chat-message"
                      :class="msg.mine ? 'flex-row-reverse' : 'flex-row'"
                    >
                      <!-- Avatar for others -->
                      <div v-if="!msg.mine" class="shrink-0 mb-0.5">
                        <div
                          v-if="idx === group.messages.length - 1 || group.messages[idx+1]?.mine || group.messages[idx+1]?.senderId !== msg.senderId"
                          class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-black"
                          :class="[theme.avatarBg, theme.avatarText]"
                        >{{ initials(msg.senderName) }}</div>
                        <div v-else class="w-7" />
                      </div>

                      <div class="max-w-[72%] flex flex-col" :class="msg.mine ? 'items-end' : 'items-start'">
                        <!-- Sender name (group chats only, every message) -->
                        <span
                          v-if="!msg.mine && activeConversation?.isGroup"
                          class="text-[10px] mb-0.5 ml-1 font-bold"
                          :class="theme.subText"
                        >{{ msg.senderName }}</span>

                        <div
                          class="px-3.5 py-2 rounded-2xl text-[12.5px] leading-relaxed wrap-break-word whitespace-pre-wrap"
                          :class="msg.mine
                            ? [theme.ownBubble, 'rounded-br-sm']
                            : [theme.otherBubble, 'rounded-bl-sm']"
                        >{{ msg.body }}</div>

                        <span class="text-[9px] mt-0.5 mx-1" :class="theme.timeText">{{ timeLabel(msg.createdAt) }}</span>
                      </div>
                    </div>
                  </div>

                  <div v-if="activeMessages.length === 0" class="flex flex-col items-center justify-center h-32 gap-2">
                    <AppIcon name="chat-bubble" class="w-8 h-8 opacity-20" :class="theme.subText" />
                    <p class="text-[11px]" :class="theme.subText">Zacznij rozmowę</p>
                  </div>
                </template>
              </div>

              <!-- Input -->
              <div class="shrink-0 p-4 border-t" :class="theme.inputArea">
                <div
                  class="flex items-end gap-2 rounded-xl px-3.5 py-2.5 border transition-all"
                  :class="theme.input"
                >
                  <textarea
                    ref="inputEl"
                    v-model="messageInput"
                    placeholder="Wpisz wiadomość…"
                    rows="1"
                    class="flex-1 bg-transparent border-none text-[12.5px] focus:outline-none resize-none max-h-24 leading-relaxed"
                    :class="isDark ? 'text-white placeholder-slate-500' : 'text-gray-800 placeholder-gray-400'"
                    @keydown="onKeydown"
                    @input="autoResize"
                  />
                  <button
                    :disabled="!messageInput.trim()"
                    class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center transition-all mb-0.5"
                    :class="messageInput.trim() ? theme.sendBtn : theme.sendDisabled"
                    @click="send"
                  >
                    <AppIcon name="paper-airplane" class="w-4 h-4" />
                  </button>
                </div>
                <p class="text-[9px] mt-1 text-center" :class="theme.timeText">Enter — wyślij &nbsp;·&nbsp; Shift+Enter — nowa linia</p>
              </div>
            </template>
          </template>
        </div>

      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.chat-dark-panel {
  background: linear-gradient(160deg, #001f3d 0%, #002a52 55%, #003366 100%);
}

.chat-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(212,175,55,0.15) transparent;
}
.chat-scroll::-webkit-scrollbar { width: 3px; }
.chat-scroll::-webkit-scrollbar-track { background: transparent; }
.chat-scroll::-webkit-scrollbar-thumb {
  background-color: rgba(212,175,55,0.15);
  border-radius: 999px;
}

.chat-slide-enter-active,
.chat-slide-leave-active {
  transition: transform 280ms cubic-bezier(0.16,1,0.3,1), opacity 200ms ease;
}
.chat-slide-enter-from,
.chat-slide-leave-to { transform: translateX(100%); opacity: 0; }

.chat-backdrop-enter-active,
.chat-backdrop-leave-active { transition: opacity 220ms ease; }
.chat-backdrop-enter-from,
.chat-backdrop-leave-to { opacity: 0; }

.chat-message { animation: msgIn 160ms cubic-bezier(0.16,1,0.3,1); }
@keyframes msgIn {
  from { opacity: 0; transform: translateY(5px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
