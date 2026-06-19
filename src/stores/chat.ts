import { defineStore } from 'pinia'
import { computed, onScopeDispose, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import { getEcho, initEcho } from '@/realtime/echo'

export type ChatConversation = {
  id: number
  type: string
  key: string | null
  name: string
  isGroup: boolean
  participant: { id: number; name: string; supabaseId: string } | null
  lastMessage: { body: string; senderName: string; createdAt: string } | null
  unread: number
}

export type ConversationMember = {
  id: string
  name: string
  supabaseId: string
  role: string | null
  isMe: boolean
}

export type ChatMessage = {
  id: number
  senderId: number
  senderName: string
  body: string
  type: string
  createdAt: string
  mine: boolean
}

export type ChatUser = {
  id: number
  supabaseId: string
  name: string
  role: string | null
  teamGroupPath: string | null
  parentSupabaseId: string | null
}

export const useChatStore = defineStore('chat', () => {
  const auth = useAuthStore()
  const session = useSessionStore()

  const isOpen = ref(false)
  const conversations = ref<ChatConversation[]>([])
  const activeConversationId = ref<number | null>(null)
  const messages = ref<Record<number, ChatMessage[]>>({})
  const loadingMessages = ref(false)
  const loadingConversations = ref(false)
  const loadingChatUsers = ref(false)
  const apiChatUsers = ref<ChatUser[]>([])
  const subscribedChannels = new Map<number, any>()

  const totalUnread = computed(() => conversations.value.reduce((sum, c) => sum + (c.unread || 0), 0))
  const activeConversation = computed(() => conversations.value.find((c) => c.id === activeConversationId.value) ?? null)
  const activeMessages = computed(() => (activeConversationId.value ? messages.value[activeConversationId.value] ?? [] : []))

  const generalConversation = computed(() => conversations.value.find((c) => c.key === 'general') ?? null)
  // Wszystkie czaty grupowe (team:* oraz tworzone ręcznie przez użytkowników), poza kanałem ogólnym
  const groupConversations = computed(() => conversations.value.filter((c) => c.isGroup && c.key !== 'general'))
  const dmConversations = computed(() => conversations.value.filter((c) => !c.isGroup))

  const chatUsers = computed<ChatUser[]>(() => apiChatUsers.value)

  const loadingUsers = computed(() => loadingChatUsers.value)

  const fetchChatUsers = async () => {
    if (!auth.enabled || !auth.isAuthenticated) return
    loadingChatUsers.value = true
    try {
      const { data: resp } = await api.get('/v1/chat/users')
      apiChatUsers.value = Array.isArray(resp?.data) ? resp.data : []
    } catch {
      apiChatUsers.value = []
    } finally {
      loadingChatUsers.value = false
    }
  }

  const ensureGroup = async (key: string, name: string): Promise<number | null> => {
    try {
      const { data: resp } = await api.post('/v1/chat/groups', { key, name })
      const convId = resp?.data?.id
      if (!convId) return null
      if (!conversations.value.find((c) => c.id === convId)) {
        await fetchConversations()
      }
      return convId
    } catch {
      return null
    }
  }

  const ensureGeneralChat = async () => {
    return ensureGroup('general', 'Ogólny')
  }

  const ensureTeamChats = async () => {
    const me = session.currentUser
    if (!me) return
    const myPath = chatUsers.value.find((u) => String(u.id) === String(me.id))?.teamGroupPath
      ?? (me as any).teamGroupPath
      ?? null
    if (myPath) {
      const teamName = myPath.split('/').filter(Boolean).pop() ?? myPath
      await ensureGroup(`team:${myPath}`, `Zespół: ${teamName}`)
    }
  }

  const fetchConversations = async () => {
    if (!auth.enabled || !auth.isAuthenticated) return
    loadingConversations.value = true
    try {
      const { data: resp } = await api.get('/v1/chat/conversations')
      conversations.value = Array.isArray(resp?.data) ? resp.data : []
    } catch {
      // silent
    } finally {
      loadingConversations.value = false
    }
  }

  const fetchMessages = async (convId: number) => {
    loadingMessages.value = true
    try {
      const { data: resp } = await api.get(`/v1/chat/conversations/${convId}/messages`)
      messages.value = { ...messages.value, [convId]: Array.isArray(resp?.data) ? resp.data : [] }
      conversations.value = conversations.value.map((c) => (c.id === convId ? { ...c, unread: 0 } : c))
    } catch {
      // silent
    } finally {
      loadingMessages.value = false
    }
  }

  const subscribeToConversation = (convId: number) => {
    if (subscribedChannels.has(convId)) return
    const echo = getEcho() ?? initEcho()
    const channel = echo.private(`chat.${convId}`)
    channel.listen('.chat.message', (payload: any) => {
      const myId = session.currentUser?.id
      const msg: ChatMessage = {
        id: payload.id,
        senderId: payload.senderId,
        senderName: payload.senderName,
        body: payload.body,
        type: payload.type,
        createdAt: payload.createdAt,
        mine: String(payload.senderId) === String(myId),
      }
      if (!messages.value[convId]) messages.value[convId] = []
      const exists = messages.value[convId].some((m) => m.id === msg.id)
      if (!exists) messages.value[convId] = [...messages.value[convId], msg]

      conversations.value = conversations.value.map((c) => {
        if (c.id !== convId) return c
        const unreadDelta = activeConversationId.value === convId ? 0 : 1
        return {
          ...c,
          unread: c.unread + unreadDelta,
          lastMessage: { body: msg.body, senderName: msg.senderName, createdAt: msg.createdAt },
        }
      })
    })
    subscribedChannels.set(convId, channel)
  }

  const openConversation = async (convId: number) => {
    activeConversationId.value = convId
    subscribeToConversation(convId)
    if (!messages.value[convId]) {
      await fetchMessages(convId)
    } else {
      await api.put(`/v1/chat/conversations/${convId}/read`).catch(() => {})
      conversations.value = conversations.value.map((c) => (c.id === convId ? { ...c, unread: 0 } : c))
    }
  }

  const findOrCreateDm = async (supabaseId: string): Promise<number | null> => {
    const { data: resp } = await api.post('/v1/chat/conversations', { supabase_id: supabaseId })
    const convId = resp?.data?.id
    if (!convId) return null
    if (!conversations.value.find((c) => c.id === convId)) {
      await fetchConversations()
    }
    return convId
  }

  const createGroup = async (name: string, memberSupabaseIds: string[]): Promise<number | null> => {
    const { data: resp } = await api.post('/v1/chat/groups/create', {
      name,
      member_supabase_ids: memberSupabaseIds,
    })
    const convId = resp?.data?.id
    if (!convId) return null
    await fetchConversations()
    return convId
  }

  const fetchConversationMembers = async (convId: number): Promise<ConversationMember[]> => {
    try {
      const { data: resp } = await api.get(`/v1/chat/conversations/${convId}/members`)
      return Array.isArray(resp?.data) ? resp.data : []
    } catch {
      return []
    }
  }

  const addMember = async (convId: number, supabaseId: string): Promise<boolean> => {
    try {
      await api.post(`/v1/chat/conversations/${convId}/members`, { supabase_id: supabaseId })
      return true
    } catch {
      return false
    }
  }

  const removeMember = async (convId: number, supabaseId: string): Promise<boolean> => {
    try {
      await api.delete(`/v1/chat/conversations/${convId}/members/${supabaseId}`)
      return true
    } catch {
      return false
    }
  }

  const leaveConversation = async (convId: number): Promise<boolean> => {
    try {
      await api.delete(`/v1/chat/conversations/${convId}`)
      conversations.value = conversations.value.filter((c) => c.id !== convId)
      if (activeConversationId.value === convId) activeConversationId.value = null
      return true
    } catch {
      return false
    }
  }

  const sendMessage = async (body: string) => {
    if (!activeConversationId.value || !body.trim()) return
    const convId = activeConversationId.value
    const myId = session.currentUser?.id
    const myName = session.currentUser?.name ?? 'Ja'
    const tempId = -Date.now()
    const optimistic: ChatMessage = {
      id: tempId,
      senderId: myId as any,
      senderName: myName,
      body,
      type: 'text',
      createdAt: new Date().toISOString(),
      mine: true,
    }
    messages.value = { ...messages.value, [convId]: [...(messages.value[convId] ?? []), optimistic] }

    try {
      const { data: resp } = await api.post(`/v1/chat/conversations/${convId}/messages`, { body })
      const real: ChatMessage = resp.data
      messages.value[convId] = messages.value[convId].map((m) => (m.id === tempId ? real : m))
      conversations.value = conversations.value.map((c) =>
        c.id === convId
          ? { ...c, lastMessage: { body: real.body, senderName: real.senderName, createdAt: real.createdAt } }
          : c
      )
    } catch {
      messages.value[convId] = messages.value[convId].filter((m) => m.id !== tempId)
    }
  }

  const toggle = () => {
    isOpen.value = !isOpen.value
    if (isOpen.value && conversations.value.length === 0) {
      fetchConversations()
    }
    if (isOpen.value && apiChatUsers.value.length === 0) {
      fetchChatUsers()
    }
  }

  const close = () => { isOpen.value = false }

  onScopeDispose(() => {
    subscribedChannels.forEach((ch) => ch.stopListeningToAll())
    subscribedChannels.clear()
  })

  return {
    isOpen,
    conversations,
    activeConversationId,
    messages,
    chatUsers,
    loadingMessages,
    loadingConversations,
    loadingUsers,
    fetchChatUsers,
    totalUnread,
    activeConversation,
    activeMessages,
    generalConversation,
    groupConversations,
    dmConversations,
    toggle,
    close,
    fetchConversations,
    fetchMessages,
    ensureGeneralChat,
    ensureTeamChats,
    openConversation,
    findOrCreateDm,
    createGroup,
    fetchConversationMembers,
    addMember,
    removeMember,
    leaveConversation,
    sendMessage,
  }
})
