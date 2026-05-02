<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import type { Email } from '@/types/models'

const props = defineProps<{
  open: boolean
  selectedEmail: Email | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
}

const convId = ref<number | null>(null)
const messages = ref<ChatMessage[]>([])
const input = ref('')
const loading = ref(false)
const messagesContainer = ref<HTMLDivElement | null>(null)

const close = () => emit('update:open', false)

const scrollToBottom = async () => {
  await nextTick()
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const ensureConversation = async (): Promise<number> => {
  if (convId.value) return convId.value
  const { data } = await api.post('/v1/ai/conversations', { title: 'Skrzynka — AI' })
  convId.value = data.data.id
  return convId.value!
}

const send = async (text?: string) => {
  const msg = (text ?? input.value).trim()
  if (!msg || loading.value) return
  input.value = ''
  messages.value.push({ role: 'user', content: msg })
  loading.value = true
  await scrollToBottom()

  try {
    const id = await ensureConversation()
    const { data } = await api.post(`/v1/ai/conversations/${id}/messages`, { message: msg })
    messages.value.push({ role: 'assistant', content: data.data.content })
  } catch (e: any) {
    messages.value.push({ role: 'assistant', content: '⚠️ Błąd: ' + (e?.response?.data?.error || e?.message || 'nieznany błąd') })
  } finally {
    loading.value = false
    await scrollToBottom()
  }
}

const analyzeEmail = () => {
  if (!props.selectedEmail) return
  const e = props.selectedEmail
  const bodyPreview = (e.body ? e.body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 500) : '')
  const prompt = `Przeanalizuj ten email:\n\nOd: ${e.fromName} <${e.fromEmail}>\nTemat: ${e.subject}\nData: ${e.date}\n\n${bodyPreview}\n\nCzy zawiera informacje o spotkaniu? Jeśli tak, dodaj je do kalendarza. Podsumuj o czym jest ten email.`
  send(prompt)
}

const handleKey = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

watch(() => props.open, (val) => {
  if (val && messages.value.length === 0) {
    messages.value.push({
      role: 'assistant',
      content: 'Cześć! Jestem Twoim asystentem skrzynki pocztowej. Mogę czytać, wyszukiwać i wysyłać emaile, a także automatycznie zapisywać spotkania z korespondencji do kalendarza. Czym mogę pomóc?'
    })
  }
})

const resetConversation = () => {
  convId.value = null
  messages.value = []
}

const formatContent = (text: string) => {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br>')
}
</script>

<template>
  <Transition name="slide-panel">
    <div
      v-if="open"
      class="absolute right-0 top-0 h-full w-96 bg-white border-l border-slate-200 shadow-2xl flex flex-col z-20"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-800 shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-stratton-gold/20 flex items-center justify-center">
            <svg class="w-4 h-4 text-stratton-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2" />
            </svg>
          </div>
          <div>
            <p class="text-xs font-bold text-white leading-none">AI Asystent</p>
            <p class="text-[10px] text-slate-400 mt-0.5">Skrzynka pocztowa</p>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-700 transition-colors"
            title="Nowa rozmowa"
            @click="resetConversation"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
          <button
            type="button"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-700 transition-colors"
            @click="close"
          >
            <AppIcon name="x" class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      <!-- Quick actions when email is selected -->
      <div v-if="selectedEmail" class="px-3 py-2 bg-sky-50 border-b border-sky-100 shrink-0">
        <p class="text-[10px] text-sky-700 font-bold uppercase tracking-wider mb-1.5">Wybrany email</p>
        <p class="text-xs text-sky-900 font-medium truncate mb-2">{{ selectedEmail.subject }}</p>
        <button
          type="button"
          class="w-full text-xs font-bold py-1.5 px-3 bg-sky-600 text-white rounded-lg hover:bg-sky-700 transition-colors flex items-center justify-center gap-1.5"
          @click="analyzeEmail"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          Analizuj ten email
        </button>
      </div>

      <!-- Messages -->
      <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-3 space-y-3 custom-scrollbar">
        <div
          v-for="(msg, i) in messages"
          :key="i"
          class="flex"
          :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            class="max-w-[85%] rounded-2xl px-3 py-2 text-xs leading-relaxed"
            :class="msg.role === 'user'
              ? 'bg-slate-900 text-white rounded-br-sm'
              : 'bg-slate-100 text-slate-800 rounded-bl-sm'"
            v-html="formatContent(msg.content)"
          />
        </div>
        <div v-if="loading" class="flex justify-start">
          <div class="bg-slate-100 rounded-2xl rounded-bl-sm px-3 py-2 flex items-center gap-1">
            <div class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-bounce" style="animation-delay:0s" />
            <div class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-bounce" style="animation-delay:0.15s" />
            <div class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-bounce" style="animation-delay:0.3s" />
          </div>
        </div>
      </div>

      <!-- Quick suggestions -->
      <div class="px-3 py-2 border-t border-slate-100 flex gap-1.5 overflow-x-auto shrink-0">
        <button
          v-for="suggestion in ['Nieprzeczytane dziś', 'Szukaj od klienta', 'Zaplanuj spotkanie']"
          :key="suggestion"
          type="button"
          class="whitespace-nowrap text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors"
          @click="send(suggestion)"
        >
          {{ suggestion }}
        </button>
      </div>

      <!-- Input -->
      <div class="px-3 pb-3 pt-1 shrink-0">
        <div class="flex items-end gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
          <textarea
            v-model="input"
            rows="2"
            placeholder="Zapytaj o skrzynkę..."
            class="flex-1 bg-transparent text-xs text-slate-800 placeholder-slate-400 resize-none outline-none leading-relaxed"
            :disabled="loading"
            @keydown="handleKey"
          />
          <button
            type="button"
            class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center transition-colors"
            :class="input.trim() && !loading ? 'bg-slate-900 text-white hover:bg-slate-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
            :disabled="!input.trim() || loading"
            @click="send()"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-panel-enter-active,
.slide-panel-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}
.slide-panel-enter-from,
.slide-panel-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
</style>
