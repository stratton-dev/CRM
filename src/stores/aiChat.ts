import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/api/client'
import type { AiConversation, AiMessage, AiFrontendAction } from '@/types/ai'
import { useMailboxStore } from '@/stores/mailbox'

export const useAiChatStore = defineStore('aiChat', () => {
  const isOpen            = ref(false)
  const isLoading         = ref(false)
  const isSending         = ref(false)
  const conversations     = ref<AiConversation[]>([])
  const activeConversation = ref<AiConversation | null>(null)
  const messages          = ref<AiMessage[]>([])
  const error             = ref<string | null>(null)

  const hasConversation = computed(() => activeConversation.value !== null)

  function openWidget() {
    isOpen.value = true
  }

  function closeWidget() {
    isOpen.value = false
  }

  function toggleWidget() {
    isOpen.value = !isOpen.value
  }

  async function loadConversations() {
    try {
      const { data } = await api.get('/v1/ai-chat/conversations')
      conversations.value = data.data ?? []
    } catch (e) {
      console.error('AiChat: loadConversations error', e)
    }
  }

  async function createConversation(title?: string): Promise<AiConversation> {
    const { data } = await api.post('/v1/ai-chat/conversations', { title: title ?? 'Nowa rozmowa' })
    const conv = data.data
    conversations.value.unshift(conv)
    await selectConversation(conv.id)
    return conv
  }

  async function selectConversation(id: number) {
    isLoading.value = true
    try {
      const { data } = await api.get(`/v1/ai-chat/conversations/${id}`)
      activeConversation.value = data.data
      messages.value = data.data.messages ?? []
    } finally {
      isLoading.value = false
    }
  }

  async function deleteConversation(id: number) {
    await api.delete(`/v1/ai-chat/conversations/${id}`)
    conversations.value = conversations.value.filter(c => c.id !== id)
    if (activeConversation.value?.id === id) {
      activeConversation.value = null
      messages.value = []
    }
  }

  async function sendMessage(text: string) {
    if (!activeConversation.value) {
      await createConversation(text.substring(0, 60))
    }
    if (!activeConversation.value) return

    error.value = null
    isSending.value = true

    const userMsg: AiMessage = {
      id: Date.now(),
      conversation_id: activeConversation.value.id,
      role: 'user',
      content: text,
      tool_calls: null,
      tokens_used: 0,
      created_at: new Date().toISOString(),
    }
    messages.value.push(userMsg)

    const placeholderId = Date.now() + 1
    const placeholder: AiMessage = {
      id: placeholderId,
      conversation_id: activeConversation.value.id,
      role: 'assistant',
      content: null,
      tool_calls: null,
      tokens_used: 0,
      created_at: new Date().toISOString(),
      isStreaming: true,
    }
    messages.value.push(placeholder)

    try {
      const { data } = await api.post(
        `/v1/ai-chat/conversations/${activeConversation.value.id}/messages`,
        { message: text },
        { timeout: 90000 }
      )
      const aiMsg: AiMessage = data.data

      const idx = messages.value.findIndex(m => m.id === placeholderId)
      if (idx !== -1) {
        messages.value[idx] = { ...aiMsg, isStreaming: false }
      } else {
        messages.value.push(aiMsg)
      }

      if (aiMsg.actions && aiMsg.actions.length > 0) {
        handleActions(aiMsg.actions)
      }

      if (activeConversation.value) {
        const convIdx = conversations.value.findIndex(c => c.id === activeConversation.value!.id)
        if (convIdx !== -1) {
          conversations.value[convIdx].updated_at = new Date().toISOString()
        }
      }
    } catch (e: any) {
      messages.value = messages.value.filter(m => m.id !== placeholderId)
      error.value = e?.response?.data?.error ?? 'Błąd komunikacji z AI'
    } finally {
      isSending.value = false
    }
  }

  function handleActions(actions: AiFrontendAction[]) {
    const mailboxStore = useMailboxStore()
    for (const action of actions) {
      if (action.type === 'open_compose') {
        mailboxStore.composeState = {
          open: true,
          to: action.to ?? '',
          subject: action.subject ?? '',
          body: action.body ?? '',
        }
      }
      if (action.type === 'refresh_calendar') {
        window.dispatchEvent(new CustomEvent('crm:refresh-calendar'))
      }
    }
  }

  async function startNewConversation() {
    activeConversation.value = null
    messages.value = []
  }

  return {
    isOpen,
    isLoading,
    isSending,
    conversations,
    activeConversation,
    messages,
    error,
    hasConversation,
    openWidget,
    closeWidget,
    toggleWidget,
    loadConversations,
    createConversation,
    selectConversation,
    deleteConversation,
    sendMessage,
    startNewConversation,
  }
})
