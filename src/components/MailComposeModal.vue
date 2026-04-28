<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useMailboxStore } from '@/stores/mailbox'
import { useSessionStore } from '@/stores/session'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useStructureStore } from '@/stores/structure'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'

const mailboxStore = useMailboxStore()
const session = useSessionStore()
const data = useDataStore()
const clientStore = useClientStore()
const structure = useStructureStore()
const auth = useAuthStore()
const toast = useToastStore()
const knowledgeBaseStore = useKnowledgeBaseStore()

const { composeState } = storeToRefs(mailboxStore)
const { currentUser } = storeToRefs(session)
const { clients } = storeToRefs(clientStore)
const { files: knowledgeFiles } = storeToRefs(knowledgeBaseStore)

const editor = ref<HTMLDivElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const showAddressBook = ref(false)
const showKbDropdown = ref(false)

const composeData = ref<{
  to: string
  subject: string
  body: string
  attachments: {
    filename: string
    content?: string
    content_type?: string
    encoding?: string
    html?: string
    convert_to_pdf?: boolean
  }[]
}>({ to: '', subject: '', body: '', attachments: [] })

const safeCompose = computed(() => composeState.value || { open: false })

watch(
  () => composeState.value,
  async (state) => {
    if (state?.open) {
      composeData.value = {
        to: state.to || '',
        subject: state.subject || 'Wiadomość',
        body: state.body || '<p><br></p>',
        attachments: state.attachments || [],
      }
      await nextTick()
      if (editor.value) editor.value.innerHTML = composeData.value.body
    }
  },
  { deep: true, immediate: true }
)

const closeCompose = () => {
  composeState.value = { open: false }
}

const isSending = ref(false)

const sendEmail = async () => {
  let user = currentUser.value
  if (!user) {
    await session.resolveUserFromAuth()
    user = currentUser.value
  }
  if (!user) {
    toast.error('Brak sesji użytkownika. Odśwież stronę i spróbuj ponownie.')
    return
  }

  const { to, subject, attachments } = composeData.value
  const body = editor.value?.innerHTML || ''

  if (!to || !subject) {
    toast.error('Adresat i temat są wymagane.')
    return
  }

  if (isSending.value) return
  isSending.value = true

  try {
    closeCompose()
    await mailboxStore.sendEmail(user, to, subject, body, attachments)
    toast.success(`Wiadomość do ${to} została wysłana.`)
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się wysłać wiadomości.'
    toast.error(message)
  } finally {
    isSending.value = false
  }
}

const saveAsDraft = async () => {
  const { to, subject } = composeData.value
  const body = editor.value?.innerHTML || ''
  try {
    await mailboxStore.saveDraft(to, subject, body)
    toast.success('Zapisano jako roboczy.')
    closeCompose()
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać roboczego.'
    toast.error(message)
  }
}

const formatDoc = (command: string, value?: string) => {
  document.execCommand(command, false, value)
  editor.value?.focus()
}

const handleFileAttachment = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (!target.files?.length) return
  const files = Array.from(target.files)
  for (const file of files) {
    const reader = new FileReader()
    reader.onload = (e) => {
      const content = e.target?.result
      if (typeof content === 'string') {
        const base64 = content.includes('base64,') ? content.split('base64,')[1] : content
        composeData.value.attachments.push({
          filename: file.name,
          content: base64,
          content_type: file.type || 'application/octet-stream',
          encoding: 'base64',
        })
      }
    }
    reader.readAsDataURL(file)
  }
  target.value = ''
}

const removeAttachment = (index: number) => {
  composeData.value.attachments.splice(index, 1)
}

const previewAttachment = (file: typeof composeData.value.attachments[0]) => {
  if (file.html) {
    const win = window.open('', '_blank')
    if (win) { win.document.write(file.html); win.document.close() }
    return
  }
  if (file.content) {
    try {
      const byteCharacters = atob(file.content)
      const byteNumbers = new Array(byteCharacters.length)
      for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i)
      const byteArray = new Uint8Array(byteNumbers)
      let mimeType = file.content_type || 'application/octet-stream'
      if (!file.content_type || file.content_type === 'application/octet-stream') {
        if (file.filename.endsWith('.pdf')) mimeType = 'application/pdf'
        else if (file.filename.endsWith('.png')) mimeType = 'image/png'
        else if (file.filename.endsWith('.jpg') || file.filename.endsWith('.jpeg')) mimeType = 'image/jpeg'
      }
      const blob = new Blob([byteArray], { type: mimeType })
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } catch { toast.error('Nie udało się otworzyć podglądu załącznika.') }
    return
  }
  toast.info('Brak zawartości do podglądu dla tego pliku.')
}

const kbDownloads = computed(() => (knowledgeFiles.value || []).filter((f) => f.category === 'CASH_FLOW'))

const attachKbFile = async (file: any) => {
  try {
    showKbDropdown.value = false
    const response = await api.get(`/v1/crm-knowledge-files/${file.id}/download`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/octet-stream' })
    const reader = new FileReader()
    reader.onloadend = () => {
      const result = reader.result as string
      const base64 = result.includes('base64,') ? result.split('base64,')[1] : result
      composeData.value.attachments.push({ filename: file.name, content: base64, content_type: blob.type, encoding: 'base64' })
      toast.success(`Dodano załącznik: ${file.name}`)
    }
    reader.readAsDataURL(blob)
  } catch { toast.error('Nie udało się pobrać pliku z bazy wiedzy.') }
}

const addressBookContacts = computed(() => {
  const me = currentUser.value
  if (!me) return [] as Array<{ email: string; name: string; source: string }>
  const contacts = new Map<string, { name: string; source: string }>()
  ;(me.addressBook || []).forEach((contact) => {
    if (!contacts.has(contact.email)) contacts.set(contact.email, { name: contact.name, source: 'Personal' })
  })
  let visibleClients = Array.isArray(clients.value) ? clients.value : []
  if (me.role === 'SALES') visibleClients = visibleClients.filter((c) => c.ownerId === me.id)
  else if (me.role === 'MANAGER' || me.role === 'DIRECTOR') {
    const teamIds = [me.id, ...(auth.enabled ? structure.getSubtreeUserIds(me.id) : data.getSubtreeUserIds(me.id))]
    visibleClients = visibleClients.filter((c) => teamIds.includes(c.ownerId))
  }
  visibleClients.forEach((client) => {
    if (client.contactEmail && !contacts.has(client.contactEmail))
      contacts.set(client.contactEmail, { name: client.contactName, source: client.name })
  })
  return Array.from(contacts.entries()).map(([email, item]) => ({ email, ...item })).sort((a, b) => a.name.localeCompare(b.name))
})

const selectContact = (email: string) => {
  composeData.value = { ...composeData.value, to: email }
  showAddressBook.value = false
}
</script>

<template>
  <Teleport to="body">
    <!-- Compose Modal -->
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div v-if="safeCompose.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-8 bg-slate-950/80 backdrop-blur-xl">
        <div class="bg-white rounded-4xl shadow-[0_30px_100px_rgba(0,0,0,0.5)] w-full max-w-4xl h-full max-h-[90vh] flex flex-col border border-slate-200/50 overflow-hidden relative">

          <div class="px-8 py-5 bg-slate-900 text-white flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-[#D4AF37]/20 border border-[#D4AF37]/30 flex items-center justify-center">
                <AppIcon name="pencil" class="w-4 h-4 text-[#D4AF37]" />
              </div>
              <h3 class="font-black text-sm uppercase tracking-widest">Nowa Wiadomość</h3>
            </div>
            <button type="button" class="w-8 h-8 rounded-full flex items-center justify-center bg-white/5 border border-white/10 text-white/50 hover:bg-white/10 hover:text-white transition-all" @click="closeCompose">✕</button>
          </div>

          <div class="flex flex-col flex-1 overflow-hidden">
            <div class="px-8 pt-6 pb-4 space-y-px">
              <div class="group flex items-center border-b border-slate-100 focus-within:border-[#D4AF37] transition-colors">
                <button type="button" class="pr-4 py-3 text-[11px] font-black uppercase tracking-wider text-slate-400 group-focus-within:text-[#D4AF37] transition-colors whitespace-nowrap" @click="showAddressBook = true">Adresat —</button>
                <input v-model="composeData.to" type="text" name="to" placeholder="Wpisz adres e-mail lub wybierz z listy..."
                  class="flex-1 bg-transparent border-0 p-3 text-sm font-bold text-slate-800 placeholder-slate-300 focus:ring-0 shadow-none!" />
                <button type="button" @click="showAddressBook = true" class="p-2 text-slate-400 hover:text-[#D4AF37]">
                  <AppIcon name="users" class="w-4 h-4" />
                </button>
              </div>
              <div class="group flex items-center border-b border-slate-100 focus-within:border-[#D4AF37] transition-colors">
                <span class="pr-4 py-3 text-[11px] font-black uppercase tracking-wider text-slate-400 group-focus-within:text-[#D4AF37] transition-colors whitespace-nowrap">Temat —</span>
                <input v-model="composeData.subject" type="text" name="subject" placeholder="O czym chcesz napisać?"
                  class="flex-1 bg-transparent border-0 p-3 text-sm font-black text-slate-800 placeholder-slate-300 focus:ring-0 shadow-none!" />
              </div>
            </div>

            <!-- Toolbar -->
            <div class="px-8 py-3 bg-slate-50/50 border-y border-slate-100 flex items-center flex-wrap gap-2 overflow-x-auto no-scrollbar">
              <div class="flex items-center bg-white rounded-xl border border-slate-200 p-1 shadow-sm shrink-0">
                <select class="bg-transparent border-0 text-[11px] h-9 font-black uppercase tracking-tight px-3 focus:ring-0 cursor-pointer min-w-[120px]" @change="formatDoc('fontName', ($event.target as HTMLSelectElement).value)">
                  <option value="DM Sans" selected>DM Sans</option>
                  <option value="Cinzel">Cinzel</option>
                  <option value="Arial">Arial</option>
                  <option value="Inter">Inter</option>
                  <option value="Georgia">Georgia</option>
                  <option value="Verdana">Verdana</option>
                  <option value="Courier New">Mono</option>
                </select>
                <div class="w-px h-5 bg-slate-200 mx-1"></div>
                <select class="bg-transparent border-0 text-[11px] h-9 font-black uppercase tracking-tight px-3 focus:ring-0 cursor-pointer min-w-[100px]" @change="formatDoc('fontSize', ($event.target as HTMLSelectElement).value)">
                  <option value="2">Mała</option>
                  <option value="3" selected>Norma</option>
                  <option value="4">Duża</option>
                  <option value="5">X-L</option>
                  <option value="6">XXL</option>
                  <option value="7">Nagłówek</option>
                </select>
              </div>
              <div class="flex items-center bg-white rounded-xl border border-slate-200 p-1 shadow-sm shrink-0 gap-0.5">
                <button type="button" class="w-9 h-9 flex items-center justify-center font-black rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" @mousedown.prevent @click="formatDoc('bold')">B</button>
                <button type="button" class="w-9 h-9 flex items-center justify-center italic rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" @mousedown.prevent @click="formatDoc('italic')">I</button>
                <button type="button" class="w-9 h-9 flex items-center justify-center underline rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" @mousedown.prevent @click="formatDoc('underline')">U</button>
              </div>
              <div class="flex items-center bg-white rounded-xl border border-slate-200 p-1 shadow-sm shrink-0 gap-0.5">
                <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" title="Wyrównaj do lewej" @mousedown.prevent @click="formatDoc('justifyLeft')">
                  <AppIcon name="align-left" class="w-5 h-5" />
                </button>
                <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" title="Wyśrodkuj" @mousedown.prevent @click="formatDoc('justifyCenter')">
                  <AppIcon name="align-center" class="w-5 h-5" />
                </button>
                <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" title="Wyrównaj do prawej" @mousedown.prevent @click="formatDoc('justifyRight')">
                  <AppIcon name="align-right" class="w-5 h-5" />
                </button>
                <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" title="Pełne wyjustowanie" @mousedown.prevent @click="formatDoc('justifyFull')">
                  <AppIcon name="align-justify" class="w-5 h-5" />
                </button>
              </div>
              <div class="flex items-center bg-white rounded-xl border border-slate-200 p-1 shadow-sm shrink-0 gap-0.5">
                <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" title="Wypunktowanie" @mousedown.prevent @click="formatDoc('insertUnorderedList')">
                  <AppIcon name="list-bullet" class="w-5 h-5" />
                </button>
                <div class="w-px h-5 bg-slate-200 mx-1"></div>
                <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-700 transition-colors" title="Numerowanie" @mousedown.prevent @click="formatDoc('insertOrderedList')">
                  <AppIcon name="list-ordered" class="w-5 h-5" />
                </button>
              </div>
              <div class="ml-auto relative flex items-center bg-white rounded-xl border border-slate-200 p-1 shadow-sm shrink-0 gap-0.5">
                <button class="p-2 text-slate-400 hover:text-slate-600" @click="fileInput?.click()"><AppIcon name="paperclip" class="w-4 h-4" /></button>
                <input type="file" ref="fileInput" class="hidden" multiple @change="handleFileAttachment" />
                <div class="w-px h-3 bg-slate-200 mx-1"></div>
                <button class="p-2 text-slate-400 hover:text-[#D4AF37] relative" @click.stop="showKbDropdown = !showKbDropdown">
                  <AppIcon name="folder" class="w-4 h-4" />
                </button>
                <div v-show="showKbDropdown" class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden" @click.stop>
                  <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Dokumenty do pobrania</p>
                    <button class="text-slate-400 hover:text-slate-600" @click="showKbDropdown = false">✕</button>
                  </div>
                  <div class="max-h-60 overflow-y-auto p-1">
                    <button v-for="file in kbDownloads" :key="file.id"
                      class="w-full text-left px-3 py-2 text-xs font-bold text-slate-600 hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-colors flex items-center gap-2 truncate"
                      @click="attachKbFile(file)">
                      <AppIcon name="document-text" class="w-3.5 h-3.5 text-slate-400" />
                      <span class="truncate">{{ file.name }}</span>
                    </button>
                    <div v-if="kbDownloads.length === 0" class="p-4 text-center">
                      <p class="text-xs text-slate-400">Brak dostępnych plików.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="px-8 flex-1 flex flex-col overflow-hidden bg-white">
              <div ref="editor" contenteditable="true"
                class="flex-1 w-full py-8 text-base leading-relaxed text-slate-700 focus:ring-0 focus:outline-none overflow-y-auto custom-scrollbar"
                placeholder="Twoja wiadomość..."></div>
              <div v-if="composeData.attachments.length > 0" class="py-4 border-t border-slate-100/50">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Załączniki:</p>
                <div class="flex flex-wrap gap-2">
                  <div v-for="(file, index) in composeData.attachments" :key="index" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 shadow-sm group hover:border-[#D4AF37]/50 transition-colors">
                    <button type="button" class="flex items-center gap-2 hover:text-[#D4AF37] transition-colors" @click="previewAttachment(file)">
                      <AppIcon name="document-text" class="w-3.5 h-3.5 text-[#D4AF37]" />
                      <span class="max-w-[200px] truncate underline decoration-dotted decoration-slate-300 underline-offset-2">{{ file.filename }}</span>
                    </button>
                    <button type="button" class="ml-1 text-slate-300 hover:text-rose-500 transition-colors" @click="removeAttachment(index)">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="px-8 py-6 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between shrink-0">
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest flex items-center gap-2">
              <AppIcon name="lock-closed" class="w-3 h-3" />
              Szyfrowanie SSL AKTYWNE
            </p>
            <div class="flex items-center gap-3">
              <button type="button" class="px-6 py-2.5 text-xs font-black text-slate-500 hover:text-slate-700" @click="closeCompose">Anuluj</button>
              <button type="button"
                class="px-4 py-2.5 text-xs font-black text-slate-600 bg-white border border-slate-200 rounded-2xl hover:border-slate-400 hover:bg-slate-50 transition-all flex items-center gap-2 active:scale-95"
                @click="saveAsDraft">
                <AppIcon name="document-text" class="w-3.5 h-3.5" />
                Zapisz jako roboczy
              </button>
              <button type="button"
                class="group relative overflow-hidden bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white font-black py-3.5 px-10 rounded-2xl hover:brightness-110 hover:shadow-xl hover:shadow-[#D4AF37]/20 active:scale-[0.98] transition-all duration-300 flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                :disabled="isSending"
                @click="sendEmail">
                <AppIcon v-if="!isSending" name="paper-airplane" class="w-4 h-4 rotate-12 group-hover:rotate-0 transition-transform" />
                <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ isSending ? 'Wysyłanie...' : 'Wyślij teraz' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Address Book Overlay -->
    <transition
      enter-active-class="transition duration-400 ease-out"
      enter-from-class="opacity-0 translate-y-12"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-300 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-12"
    >
      <div v-if="showAddressBook" class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-4xl shadow-[0_30px_100px_rgba(0,0,0,0.3)] w-full max-w-xl h-full max-h-[70vh] flex flex-col border border-slate-200/50 overflow-hidden">
          <div class="px-8 py-5 bg-white border-b border-slate-100 flex justify-between items-center shrink-0">
            <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest flex items-center gap-3">
              <AppIcon name="users" class="w-4 h-4 text-sky-500" />
              Książka Adresowa
            </h3>
            <button type="button" class="text-slate-300 hover:text-slate-600" @click="showAddressBook = false">✕</button>
          </div>
          <div class="flex-1 p-4 overflow-y-auto custom-scrollbar space-y-1">
            <div v-for="contact in addressBookContacts" :key="contact.email"
              class="group flex items-center gap-4 p-3 hover:bg-sky-50 cursor-pointer rounded-2xl transition-all border border-transparent hover:border-sky-100 active:scale-[0.99]"
              @click="selectContact(contact.email)">
              <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-400 text-xs shadow-inner transition-colors group-hover:bg-white group-hover:text-sky-600">
                {{ contact.name.substring(0, 2).toUpperCase() }}
              </div>
              <div class="flex-1">
                <p class="font-black text-sm text-slate-800 leading-tight group-hover:text-sky-700">{{ contact.name }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <p class="text-[10px] text-slate-500 opacity-70 truncate">{{ contact.email }}</p>
                  <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                  <span class="text-[9px] font-black uppercase tracking-tighter text-sky-700 bg-sky-100 px-1.5 py-0.5 rounded shadow-sm">{{ contact.source }}</span>
                </div>
              </div>
              <AppIcon name="chevron-right" class="w-4 h-4 text-slate-200 opacity-0 group-hover:opacity-100 transition-all transform group-hover:translate-x-1" />
            </div>
            <div v-if="addressBookContacts.length === 0" class="flex flex-col items-center justify-center p-12 text-center">
              <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                <AppIcon name="users" class="w-6 h-6 text-slate-200" />
              </div>
              <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Brak kontaktów</p>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #CBD5E1; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
[contenteditable=true]:empty:before { content: attr(placeholder); color: #CBD5E1; pointer-events: none; }
[contenteditable="true"] ul { list-style-type: disc !important; padding-left: 2rem !important; margin: 1rem 0 !important; display: block !important; }
[contenteditable="true"] ol { list-style-type: decimal !important; padding-left: 2rem !important; margin: 1rem 0 !important; display: block !important; }
[contenteditable="true"] li { display: list-item !important; margin-bottom: 0.25rem; }
</style>
