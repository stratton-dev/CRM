<script setup lang="ts">
import { ref, watch, nextTick, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAiChatStore } from '@/stores/aiChat'

const store = useAiChatStore()
const { isOpen, isSending, messages, conversations, activeConversation, error,
        pendingFileId, pendingFileName, uploadProgress, uploadError } = storeToRefs(store)

const inputText    = ref('')
const messagesEnd  = ref<HTMLDivElement | null>(null)
const inputRef     = ref<HTMLTextAreaElement | null>(null)
const fileInput    = ref<HTMLInputElement | null>(null)
const showSidebar  = ref(false)

const quickSuggestions = [
  'Pokaż mi moje aktywne leady',
  'Jakie mam dziś spotkania?',
  'Wyjaśnij zasady ZUS w modelu Eliton Prime™',
  'Jaka jest procedura odkupu voucherów EBS?',
]

onMounted(() => {
  store.loadConversations()
  window.addEventListener('toggle-ai-chat', () => {
    if (isOpen.value) store.closeWidget()
    else store.openWidget()
  })
})

watch(messages, async () => {
  await nextTick()
  messagesEnd.value?.scrollIntoView({ behavior: 'smooth' })
}, { deep: true })

watch(isOpen, async (val) => {
  if (val) {
    await nextTick()
    inputRef.value?.focus()
  }
})

async function send() {
  const text = inputText.value.trim()
  if (!text || isSending.value) return
  inputText.value = ''
  await store.sendMessage(text)
  await nextTick()
  inputRef.value?.focus()
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

function handleFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  const file  = input.files?.[0]
  if (!file) return
  store.uploadFile(file, activeConversation.value?.id)
  input.value = ''
}

function clearPendingFile() {
  store.pendingFileId   = null
  store.pendingFileName = null
  store.uploadProgress  = 0
  store.uploadError     = null
}

function formatContent(content: string | null): string {
  if (!content) return ''
  let html = content
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/`(.*?)`/g, '<code>$1</code>')
    .replace(/\n/g, '<br>')

  html = html.replace(
    /\[FILE:(\d+):([^\]]+)\]/g,
    (_, fileId, filename) => {
      const url = `${import.meta.env.VITE_API_BASE_URL}/ai-chat/files/${fileId}`
      return `<a href="${url}" target="_blank" download="${filename}"
         class="inline-flex items-center gap-1.5 mt-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white"
         style="background:linear-gradient(135deg,#C5A059,#d4b06a);">` +
        `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>` +
        `${filename}</a>`
    }
  )

  return html
}
</script>

<template>
  <Teleport to="body">
    <!-- Trigger button — hidden on mobile (MobileBottomNav handles it) -->
    <button
      v-if="!isOpen"
      @click="store.openWidget()"
      class="hidden md:flex fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full shadow-xl items-center justify-center transition-all duration-200 hover:scale-110"
      style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%);"
      title="Asystent AI"
    >
      <svg class="w-7 h-7 text-stratton-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
      </svg>
    </button>

    <!-- Chat widget — full-screen on mobile, floating on desktop -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 bg-slate-50 flex flex-col border border-slate-200 overflow-hidden shadow-2xl
             md:inset-auto md:bottom-6 md:right-6 md:w-[420px] md:h-[600px] md:rounded-2xl"
    >
      <!-- Header — złoty gradient z granatowym napisem -->
      <div
        class="flex items-center justify-between px-4 py-3 flex-shrink-0"
        style="background: linear-gradient(135deg, #C5A059 0%, #d4b06a 50%, #C5A059 100%);"
      >
        <div class="flex items-center gap-2">
          <button
            @click="showSidebar = !showSidebar"
            class="p-1 rounded transition-colors text-white hover:bg-white/20"
            title="Historia rozmów"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
          <div class="w-7 h-7 rounded-lg bg-white/20 border border-white/30 flex items-center justify-center">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
          </div>
          <div>
            <div class="font-semibold text-sm text-white">Asystent AI Stratton</div>
            <div class="text-xs text-white/70">{{ activeConversation?.title ?? 'Nowa rozmowa' }}</div>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button
            @click="store.startNewConversation()"
            class="p-1 rounded transition-colors text-white hover:bg-white/20"
            title="Nowa rozmowa"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
          </button>
          <button @click="store.closeWidget()" class="p-1 rounded transition-colors text-white hover:bg-white/20" title="Zamknij">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar — historia rozmów -->
        <div
          v-if="showSidebar"
          class="w-48 border-r border-slate-200 flex flex-col bg-white overflow-hidden"
        >
          <div class="p-2 text-xs font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-200">
            Historia
          </div>
          <div class="flex-1 overflow-y-auto">
            <button
              v-for="conv in conversations"
              :key="conv.id"
              @click="store.selectConversation(conv.id); showSidebar = false"
              class="w-full text-left px-3 py-2 text-xs transition-colors border-b border-slate-100 text-slate-600 hover:text-[#003366] hover:bg-[#003366]/5 border-l-2"
              :class="activeConversation?.id === conv.id ? 'border-l-[#003366] bg-[#003366]/8 text-[#003366] font-medium' : 'border-l-transparent'"
            >
              <div class="truncate">{{ conv.title }}</div>
              <div class="text-slate-400 mt-0.5">
                {{ new Date(conv.updated_at).toLocaleDateString('pl') }}
              </div>
            </button>
            <div v-if="conversations.length === 0" class="p-3 text-xs text-slate-400 text-center">
              Brak historii
            </div>
          </div>
        </div>

        <!-- Główny obszar czatu -->
        <div class="flex-1 flex flex-col overflow-hidden">
          <!-- Wiadomości -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <!-- Powitanie -->
            <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center text-slate-500 space-y-3">
              <div class="w-16 h-16 rounded-full bg-[#003366]/10 border border-[#003366]/20 flex items-center justify-center">
                <svg class="w-8 h-8 text-[#003366]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
              </div>
              <div>
                <p class="font-medium text-slate-700">Cześć! Jestem asystentem AI Stratton Prime.</p>
                <p class="text-sm mt-1 text-slate-500">Mogę pomóc Ci z leadami, klientami, spotkaniami i pytaniami o produkty.</p>
              </div>
              <div class="grid grid-cols-1 gap-2 w-full mt-2">
                <button
                  v-for="suggestion in quickSuggestions"
                  :key="suggestion"
                  @click="inputText = suggestion; send()"
                  class="text-xs text-left px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:border-[#003366]/40 hover:bg-[#003366]/5 hover:text-[#003366] transition-colors"
                >
                  {{ suggestion }}
                </button>
              </div>
            </div>

            <!-- Wiadomości -->
            <div
              v-for="msg in messages"
              :key="msg.id"
              class="flex"
              :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
            >
              <!-- Avatar asystenta -->
              <div v-if="msg.role === 'assistant'" class="w-7 h-7 rounded-full bg-[#003366]/10 border border-[#003366]/20 flex items-center justify-center flex-shrink-0 mr-2 mt-0.5">
                <svg class="w-4 h-4 text-[#003366]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
              </div>

              <div
                class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm"
                :class="msg.role === 'user'
                  ? 'text-white rounded-br-sm'
                  : 'bg-white text-slate-700 rounded-bl-sm border border-slate-200 shadow-sm'"
                :style="msg.role === 'user' ? 'background: linear-gradient(135deg, #001f3d 0%, #003366 100%)' : ''"
              >
                <!-- Animacja pisania -->
                <div v-if="msg.isStreaming" class="flex items-center gap-1 py-1">
                  <span class="w-2 h-2 bg-[#003366]/50 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                  <span class="w-2 h-2 bg-[#003366]/50 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                  <span class="w-2 h-2 bg-[#003366]/50 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                </div>
                <!-- Treść -->
                <div
                  v-else
                  class="leading-relaxed"
                  v-html="formatContent(msg.content)"
                ></div>
              </div>
            </div>

            <!-- Error -->
            <div v-if="error" class="flex items-center gap-2 text-red-600 text-xs bg-red-50 border border-red-200 rounded-lg p-2">
              <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
              </svg>
              {{ error }}
            </div>

            <div ref="messagesEnd"></div>
          </div>

          <!-- Input -->
          <div class="border-t border-slate-200 p-3 flex-shrink-0 bg-white">
            <!-- File chip -->
            <div v-if="pendingFileName" class="px-3 pb-1 flex items-center gap-2">
              <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 max-w-full">
                <svg class="w-3.5 h-3.5 text-[#003366] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="truncate max-w-[220px]">{{ pendingFileName }}</span>
                <span v-if="uploadProgress > 0 && uploadProgress < 100" class="text-slate-400 shrink-0">{{ uploadProgress }}%</span>
                <button @click="clearPendingFile" class="text-slate-400 hover:text-slate-600 shrink-0">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
              <span v-if="uploadError" class="text-red-500 text-xs">{{ uploadError }}</span>
            </div>
            <div class="flex items-end gap-2 bg-slate-50 rounded-xl border border-slate-200 px-3 py-2 focus-within:border-[#003366]/40 focus-within:ring-1 focus-within:ring-[#003366]/20 transition-all">
              <button
                type="button"
                @click="fileInput?.click()"
                class="p-1.5 rounded-lg text-slate-400 hover:text-[#003366] hover:bg-slate-100 transition-colors shrink-0"
                title="Dodaj plik"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
              </button>
              <textarea
                ref="inputRef"
                v-model="inputText"
                @keydown="handleKeydown"
                placeholder="Napisz wiadomość... (Enter = wyślij, Shift+Enter = nowa linia)"
                rows="1"
                class="flex-1 bg-transparent text-sm text-slate-700 placeholder-slate-400 resize-none outline-none max-h-24 overflow-y-auto"
                :disabled="isSending"
                style="field-sizing: content;"
              ></textarea>
              <button
                @click="send"
                :disabled="!inputText.trim() || isSending"
                class="p-1.5 rounded-lg text-white disabled:opacity-30 transition-colors flex-shrink-0"
                style="background: linear-gradient(135deg, #001f3d 0%, #003366 100%);"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
              </button>
            </div>
            <div class="text-center mt-1">
              <span class="text-xs text-slate-400">Powered by Claude AI · Stratton Prime</span>
            </div>
          </div>
        </div>
      </div>

      <input
        ref="fileInput"
        type="file"
        class="hidden"
        accept=".pdf,.doc,.docx,.txt,.md"
        @change="handleFileSelect"
      />
    </div>
  </Teleport>
</template>
