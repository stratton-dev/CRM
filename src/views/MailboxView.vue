<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
import type { Email } from '@/types/models'
import AppIcon from '@/components/AppIcon.vue'

const mailboxStore = useMailboxStore()
const knowledgeBaseStore = useKnowledgeBaseStore()
const session = useSessionStore()
const data = useDataStore()
const clientStore = useClientStore()
const structure = useStructureStore()
const auth = useAuthStore()
const toast = useToastStore()

const { composeState, emails, emailsTotal, emailsLoading, mailMode, mailSettingsLoaded } = storeToRefs(mailboxStore)
const { files: knowledgeFiles } = storeToRefs(knowledgeBaseStore)
const { clients } = storeToRefs(clientStore)
const editor = ref<HTMLDivElement | null>(null)
const showKbDropdown = ref(false)

const sidebarWidth = ref(300)
const isResizing = ref(false)

const startResize = () => {
  isResizing.value = true
  document.addEventListener('mousemove', handleResize)
  document.addEventListener('mouseup', stopResize)
  document.body.style.userSelect = 'none'
  document.body.style.cursor = 'col-resize'
}

const handleResize = (e: MouseEvent) => {
  if (!isResizing.value) return
  // Min width 300px, max width 800px
  const newWidth = Math.max(200, Math.min(700, e.clientX - 260)) // approximate offset for left sidebar
  sidebarWidth.value = newWidth
}

const stopResize = () => {
  isResizing.value = false
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
  document.body.style.userSelect = ''
  document.body.style.cursor = ''
}

onMounted(() => {
  knowledgeBaseStore.fetchFiles()
})

const kbDownloads = computed(() => {
  const list = knowledgeFiles.value || []
  return list.filter((f) => f.category === 'CASH_FLOW')
})

const attachKbFile = async (file: any) => {
  try {
    showKbDropdown.value = false
    const response = await api.get(`/v1/crm-knowledge-files/${file.id}/download`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/octet-stream' })
    
    const reader = new FileReader()
    reader.onloadend = () => {
      const result = reader.result as string
      const base64 = result.includes('base64,') ? result.split('base64,')[1] : result
      
      composeData.value.attachments.push({
        filename: file.name,
        content: base64,
        content_type: blob.type,
        encoding: 'base64'
      })
      toast.success(`Dodano załącznik: ${file.name}`)
    }
    reader.readAsDataURL(blob)

  } catch (error) {
    console.error(error)
    toast.error('Nie udało się pobrać pliku z bazy wiedzy.')
  }
}

const currentFolder = ref<'INBOX' | 'SENT' | 'TRASH'>('INBOX')
const selectedEmail = ref<Email | null>(null)
const bodyLoading = ref(false)
const currentPage = ref(1)
const PAGE_SIZE = mailboxStore.PAGE_SIZE

const formatEmailDate = (dateStr: string) => {
  const d = new Date(dateStr)
  const now = new Date()
  const isToday = d.getDate() === now.getDate() && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
  const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  if (isToday) return time
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}.${mm}.${yyyy}, ${time}`
}
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
const showAddressBook = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

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
          encoding: 'base64'
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
  // If we have HTML content (like for generated PDF offer), open a new window and write it
  if (file.html) {
    const win = window.open('', '_blank');
    if (win) {
      win.document.write(file.html);
      win.document.close();
    }
    return;
  }

  // If we have base64 content, try to create a blob and open it
  if (file.content) {
    try {
      // Decode base64
      const byteCharacters = atob(file.content);
      const byteNumbers = new Array(byteCharacters.length);
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
      }
      const byteArray = new Uint8Array(byteNumbers);
      
      // Determine mime type
      let mimeType = file.content_type || 'application/octet-stream';
      // Fallback for common extensions if mime type is generic or missing
      if (!file.content_type || file.content_type === 'application/octet-stream') {
        if (file.filename.endsWith('.pdf')) mimeType = 'application/pdf';
        else if (file.filename.endsWith('.png')) mimeType = 'image/png';
        else if (file.filename.endsWith('.jpg') || file.filename.endsWith('.jpeg')) mimeType = 'image/jpeg';
        else if (file.filename.endsWith('.txt')) mimeType = 'text/plain';
      }

      const blob = new Blob([byteArray], { type: mimeType });
      const url = URL.createObjectURL(blob);
      const win = window.open(url, '_blank');
      
      if (!win) {
         toast.warning('Zablokowano wyskakujące okno. Sprawdź ustawienia przeglądarki.');
      }
      
      // We don't revoke immediately because the new window needs to load it
      setTimeout(() => URL.revokeObjectURL(url), 60000); 

    } catch (e) {
      console.error('Failed to preview attachment', e);
      toast.error('Nie udało się otworzyć podglądu załącznika.');
    }
    return;
  }
  
  toast.info('Brak zawartości do podglądu dla tego pliku.');
}

const safeCompose = computed(() => composeState.value || { open: false })

const { currentUser } = storeToRefs(session)

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

const allEmailsInCurrentFolder = computed(() => {
  const userEmail = currentUser.value?.email
  const folder = currentFolder.value
  const list = Array.isArray(emails.value) ? emails.value : []
  if (mailMode.value === 'imap') {
    return list
      .filter((email) => email.folder === folder)
      .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
  }
  return list
    .filter((email) => {
      if (folder === 'INBOX' || folder === 'TRASH') return email.toEmail === userEmail && email.folder === folder
      if (folder === 'SENT') return email.fromEmail === userEmail && email.folder === folder
      return false
    })
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

// For IMAP: total pages from server; for non-IMAP: local count
const totalPages = computed(() => {
  if (mailMode.value === 'imap') return Math.max(1, Math.ceil(emailsTotal.value / PAGE_SIZE))
  return Math.max(1, Math.ceil(allEmailsInCurrentFolder.value.length / PAGE_SIZE))
})
const visiblePages = computed(() => {
  const total = totalPages.value
  const cur = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages: (number | '...')[] = [1]
  if (cur > 3) pages.push('...')
  for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) pages.push(p)
  if (cur < total - 2) pages.push('...')
  pages.push(total)
  return pages
})
// For IMAP: already one page from server; for non-IMAP: local slice
const emailsInCurrentFolder = computed(() => {
  if (mailMode.value === 'imap') return allEmailsInCurrentFolder.value
  const start = (currentPage.value - 1) * PAGE_SIZE
  return allEmailsInCurrentFolder.value.slice(start, start + PAGE_SIZE)
})

const unreadCount = computed(() => {
  const userEmail = currentUser.value?.email
  const list = Array.isArray(emails.value) ? emails.value : []
  if (mailMode.value === 'imap') {
    return list.filter((email) => email.folder === 'INBOX' && !email.read).length
  }
  return list.filter((email) => email.toEmail === userEmail && email.folder === 'INBOX' && !email.read).length
})

const addressBookContacts = computed(() => {
  const me = currentUser.value
  if (!me) return [] as Array<{ email: string; name: string; source: string }>

  const contacts = new Map<string, { name: string; source: string }>()
  ;(me.addressBook || []).forEach((contact) => {
    if (!contacts.has(contact.email)) {
      contacts.set(contact.email, { name: contact.name, source: 'Personal' })
    }
  })

  let visibleClients = Array.isArray(clients.value) ? clients.value : []
  if (me.role === 'SALES') visibleClients = visibleClients.filter((client) => client.ownerId === me.id)
  else if (me.role === 'MANAGER' || me.role === 'DIRECTOR') {
    const teamIds = [me.id, ...(auth.enabled ? structure.getSubtreeUserIds(me.id) : data.getSubtreeUserIds(me.id))]
    visibleClients = visibleClients.filter((client) => teamIds.includes(client.ownerId))
  }

  visibleClients.forEach((client) => {
    if (client.contactEmail && !contacts.has(client.contactEmail)) {
      contacts.set(client.contactEmail, { name: client.contactName, source: client.name })
    }
  })

  return Array.from(contacts.entries())
    .map(([email, item]) => ({ email, ...item }))
    .sort((a, b) => a.name.localeCompare(b.name))
})

const selectContact = (email: string) => {
  composeData.value = { ...composeData.value, to: email }
  showAddressBook.value = false
}

const selectFolder = (folder: 'INBOX' | 'SENT' | 'TRASH') => {
  currentFolder.value = folder
  selectedEmail.value = null
  currentPage.value = 1
  mailboxStore.fetchEmailsForFolder(folder, 1)
}

const selectEmail = async (email: Email) => {
  selectedEmail.value = email
  if (!email.read && email.folder === 'INBOX') {
    mailboxStore.markAsRead(email.id)
  }
  if (mailMode.value === 'imap' && !email.body) {
    bodyLoading.value = true
    try {
      const { data } = await api.get(`/v1/crm-mailbox/messages/${encodeURIComponent(email.id)}/body`, { timeout: 60000 })
      const msg = data?.data
      if (msg && selectedEmail.value?.id === email.id) {
        selectedEmail.value = { ...selectedEmail.value, body: msg.body || '', attachments: msg.attachments || [] }
        const storeEmail = mailboxStore.emails.find((e) => e.id === email.id)
        if (storeEmail) {
          storeEmail.body = msg.body || ''
          storeEmail.attachments = msg.attachments || []
        }
      }
    } catch {
      // body stays empty — non-fatal
    } finally {
      bodyLoading.value = false
    }
  }
}

const openCompose = () => {
  composeState.value = { open: true }
}

const replyEmail = () => {
  if (!selectedEmail.value) return
  const e = selectedEmail.value
  const dateStr = new Date(e.date).toLocaleString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  const subject = e.subject.startsWith('Re:') ? e.subject : `Re: ${e.subject}`
  const quotedBody = `<p><br></p><p><br></p><blockquote style="margin:0 0 0 0.8em;padding-left:1em;border-left:3px solid #cbd5e1;color:#64748b">W dniu ${dateStr}, ${e.fromName} &lt;${e.fromEmail}&gt; napisał(a):<br><br>${e.body || ''}</blockquote>`
  composeState.value = { open: true, to: e.fromEmail, subject, body: quotedBody }
}

const forwardEmail = () => {
  if (!selectedEmail.value) return
  const e = selectedEmail.value
  const dateStr = new Date(e.date).toLocaleString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  const subject = e.subject.startsWith('Fwd:') ? e.subject : `Fwd: ${e.subject}`
  const forwardedBody = `<p><br></p><p><br></p><p style="color:#64748b">---------- Wiadomość przekazana dalej ----------<br>Od: ${e.fromName} &lt;${e.fromEmail}&gt;<br>Data: ${dateStr}<br>Temat: ${e.subject}</p><br>${e.body || ''}`
  composeState.value = { open: true, to: '', subject, body: forwardedBody }
}

const closeCompose = () => {
  composeState.value = { open: false }
}

const sendEmail = async () => {
  const user = currentUser.value
  if (!user) return
  const { to, subject, attachments } = composeData.value
  const body = editor.value?.innerHTML || ''

  if (!to || !subject) {
    toast.error('Adresat i temat są wymagane.')
    return
  }

  try {
    closeCompose()
    await mailboxStore.sendEmail(user, to, subject, body, attachments)
    toast.success(`Wiadomość do ${to} została wysłana.`)
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się wysłać wiadomości.'
    toast.error(message)
  }
}

const formatDoc = (command: string, value?: string) => {
  document.execCommand(command, false, value)
  editor.value?.focus()
}

onMounted(() => {
  mailboxStore.fetchEmailsForFolder(currentFolder.value, 1)
  mailboxStore.startPolling?.()
})

onBeforeUnmount(() => {
  mailboxStore.stopPolling?.()
})

watch(
  () => currentUser.value?.id,
  () => {
    mailboxStore.fetchEmailsForFolder(currentFolder.value, 1)
  }
)
</script>

<template>
  <div class="p-4 md:p-6 lg:p-8 max-w-[1920px] mx-auto space-y-6 h-full flex flex-col bg-surface-subtle">
    
    <!-- Header: Reimagined with more depth and professional feel -->
    <div class="rounded-card shadow-card-hover border p-6 md:p-8 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden shrink-0 group" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%); border-color: #003366;">
      <!-- Decorative element for "enterprise" feel -->
      <div class="absolute top-0 right-0 w-64 h-64 bg-stratton-800 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
      
      <div class="relative z-10 flex items-center gap-6">
        <RouterLink to="/app/dashboard" class="w-12 h-12 rounded-2xl bg-slate-800/80 backdrop-blur-md border border-slate-700 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 hover:border-stratton-gold/30 transition-all duration-300 shadow-lg group/back">
          <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover/back:-translate-x-1" />
        </RouterLink>
        <div>
          <div class="flex items-center gap-3">
            <h1 class="font-serif font-bold text-3xl md:text-4xl text-white tracking-tight leading-none">Skrzynka Pocztowa</h1>
            <div class="px-2 py-1 bg-stratton-gold/10 border border-stratton-gold/20 rounded-md">
              <span class="text-[10px] text-stratton-gold font-bold uppercase tracking-tighter">Enterprise Edition</span>
            </div>
          </div>
          <p class="text-xs text-slate-400 font-medium uppercase tracking-[0.2em] mt-2 opacity-80">Zintegrowany panel komunikacji biznesowej</p>
        </div>
      </div>
      
      <div class="relative z-10 w-full md:w-auto md:min-w-60 lg:w-[450px]">
         <div class="relative group/search">
            <input type="text" placeholder="Wyszukaj w Twojej korespondencji..." 
              class="w-full pl-12 pr-4 py-3 bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all duration-300 shadow-sm text-right font-bold" />
            <AppIcon name="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within/search:text-sky-500 transition-colors pointer-events-none" />
         </div>
      </div>
    </div>

    <!-- Main Content: Refined with Modern Glassmorphism & Structured Layout -->
    <div class="flex-1 bg-white rounded-[2.5rem] shadow-[0_20px_50px_-20px_rgba(0,0,0,0.1)] border border-slate-200/60 flex overflow-hidden min-h-0 relative">
      
      <!-- Folders Sidebar -->
      <div class="w-52 bg-slate-50/80 border-r border-slate-200/60 p-4 flex flex-col shrink-0">
        
        <div
          v-if="auth.enabled && mailSettingsLoaded && mailMode !== 'imap'"
          class="mb-6 rounded-2xl border border-amber-200/50 bg-amber-50/50 backdrop-blur-sm p-4 text-[11px] text-amber-900 leading-relaxed animate-pulse-slow"
        >
          <div class="flex items-center gap-2 mb-1.5 font-bold">
            <AppIcon name="exclamation-circle" class="w-3.5 h-3.5" />
            TRYB WEWNĘTRZNY
          </div>
          System używa lokalnych wiadomości. Skonfiguruj IMAP w 
          <RouterLink to="/app/settings" class="font-bold underline decoration-amber-300 underline-offset-2 hover:text-amber-700 transition-colors">Ustawieniach</RouterLink>.
        </div>

        <button type="button" 
          class="w-full group relative overflow-hidden bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white font-black py-4 px-6 rounded-2xl hover:brightness-110 active:scale-[0.98] transition-all duration-300 shadow-lg shadow-[#D4AF37]/20 flex items-center justify-center gap-3 mb-8 uppercase tracking-widest text-xs" 
          @click="openCompose">
          <div class="absolute inset-0 bg-linear-to-r from-white/0 via-white/10 to-white/0 -translate-x-full group-hover:animate-shimmer"></div>
          <AppIcon name="pencil" class="w-4 h-4 shadow-sm" />
          <span class="tracking-tight text-sm">Nowa Wiadomość</span>
        </button>

        <div class="space-y-6 flex-1 overflow-y-auto pr-1 custom-scrollbar">
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-4">Foldery</p>
            <nav class="space-y-1.5">
              <a class="group flex justify-between items-center px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-300" 
                :class="currentFolder === 'INBOX' ? 'bg-[#D4AF37]/10 text-[#D4AF37] shadow-sm' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900'" 
                @click="selectFolder('INBOX')">
                <div class="flex items-center gap-3.5">
                  <div class="p-1.5 rounded-lg transition-colors" :class="currentFolder === 'INBOX' ? 'bg-[#D4AF37]/20' : 'bg-slate-100 group-hover:bg-slate-200'">
                    <AppIcon name="inbox" class="w-4.5 h-4.5" />
                  </div>
                  <span class="font-bold text-[13px]">Odebrane</span>
                </div>
                <div v-if="unreadCount > 0" class="flex items-center justify-center min-w-5 h-5 px-1.5 bg-[#D4AF37] text-white text-[10px] font-black rounded-lg shadow-md shadow-[#D4AF37]/20">
                  {{ unreadCount }}
                </div>
              </a>
              
              <a class="group flex items-center gap-3.5 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-300" 
                :class="currentFolder === 'SENT' ? 'bg-[#D4AF37]/10 text-[#D4AF37] shadow-sm' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900'" 
                @click="selectFolder('SENT')">
                <div class="p-1.5 rounded-lg transition-colors" :class="currentFolder === 'SENT' ? 'bg-[#D4AF37]/20' : 'bg-slate-100 group-hover:bg-slate-200'">
                  <AppIcon name="paper-airplane" class="w-4.5 h-4.5" />
                </div>
                <span class="font-bold text-[13px]">Wysłane</span>
              </a>
              
              <a class="group flex items-center gap-3.5 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-300" 
                :class="currentFolder === 'TRASH' ? 'bg-[#D4AF37]/10 text-[#D4AF37] shadow-sm' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900'" 
                @click="selectFolder('TRASH')">
                <div class="p-1.5 rounded-lg transition-colors" :class="currentFolder === 'TRASH' ? 'bg-[#D4AF37]/20' : 'bg-slate-100 group-hover:bg-slate-200'">
                  <AppIcon name="trash" class="w-4.5 h-4.5" />
                </div>
                <span class="font-bold text-[13px]">Kosz</span>
              </a>
            </nav>
          </div>
          
          <div class="pt-4 mt-4 border-t border-slate-200/60">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-4">Etykiety</p>
            <div class="px-4 space-y-3">
              <div class="flex items-center gap-3 text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer transition-colors group">
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-emerald-100 group-hover:scale-125 transition-transform"></div>
                Klient
              </div>
              <div class="flex items-center gap-3 text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer transition-colors group">
                <div class="w-2.5 h-2.5 rounded-full bg-rose-400 border-2 border-rose-100 group-hover:scale-125 transition-transform"></div>
                Prowizje
              </div>
              <div class="flex items-center gap-3 text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer transition-colors group">
                <div class="w-2.5 h-2.5 rounded-full bg-amber-400 border-2 border-amber-100 group-hover:scale-125 transition-transform"></div>
                Systemowe
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Email List Column -->
      <div class="border-r border-slate-200/60 flex flex-col shrink-0 bg-white relative" :style="{ width: sidebarWidth + 'px' }">
        <!-- Resizer Handle -->
        <div 
           class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-500/20 active:bg-sky-500/40 transition-colors z-50 translate-x-1/2" 
           @mousedown.prevent="startResize"
        ></div>

        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
           <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
             <div class="w-1.5 h-4 bg-stratton-gold rounded-full"></div>
             Wiadomości
           </h2>
           <div class="flex items-center gap-2">
             <button class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 transition-colors" :class="{'animate-spin': emailsLoading}" @click="mailboxStore.fetchEmailsForFolder(currentFolder, currentPage)">
               <AppIcon name="refresh" class="w-4 h-4" />
             </button>
             <button class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 transition-colors">
               <AppIcon name="filter" class="w-4 h-4" />
             </button>
           </div>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar min-h-0 divide-y divide-slate-50">
          <div
            v-for="email in emailsInCurrentFolder"
            :key="email.id"
            class="group px-3 py-2 cursor-pointer transition-all duration-300 relative border-l-4"
            :class="[
              selectedEmail?.id === email.id ? 'bg-sky-50/70 border-sky-500' : 'hover:bg-slate-50 border-transparent',
              !email.read && selectedEmail?.id !== email.id ? 'bg-slate-50/30' : ''
            ]"
            @click="selectEmail(email)"
          >
            <!-- Unread Status Dot -->
            <div v-if="!email.read" class="absolute right-3 top-4 w-1.5 h-1.5 bg-sky-500 rounded-full shadow-[0_0_6px_rgba(14,165,233,0.5)]"></div>
             
            <div class="flex gap-3 items-center">
              <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center font-black text-[11px] transition-transform group-hover:scale-105"
                :class="selectedEmail?.id === email.id ? 'bg-white text-sky-600 border border-sky-100' : 'bg-slate-100 text-slate-500 border border-slate-200/50'">
                {{ email.fromName.substring(0, 2).toUpperCase() }}
              </div>
              
              <div class="flex-1 min-w-0">
                <div class="flex justify-between items-center mb-0.5">
                  <p class="text-[12px] font-black text-slate-900 group-hover:text-stratton-gold transition-colors truncate pr-1" :class="!email.read ? 'font-black' : 'font-bold opacity-80'">
                    {{ email.fromName }}
                  </p>
                  <div class="flex items-center gap-0.5 shrink-0">
                    <button
                      class="p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-slate-200 text-slate-400 hover:text-sky-500 transition-all"
                      title="Oznacz gwiazdką"
                      @click.stop
                    >
                      <AppIcon name="star" class="w-3 h-3" />
                    </button>
                    <button
                      class="p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-all"
                      title="Usuń"
                      @click.stop
                    >
                      <AppIcon name="trash" class="w-3 h-3" />
                    </button>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter whitespace-nowrap ml-0.5">
                      {{ formatEmailDate(email.date) }}
                    </p>
                  </div>
                </div>
                <p class="text-[12px] text-slate-800 leading-tight truncate" :class="!email.read ? 'font-extrabold' : 'font-semibold opacity-90'">
                  {{ email.subject }}
                </p>
              </div>
            </div>
          </div>
          
          <div v-if="allEmailsInCurrentFolder.length === 0" class="flex flex-col items-center justify-center h-full p-12 text-center">
             <div class="w-24 h-24 bg-slate-50 rounded-[2.5rem] flex items-center justify-center mb-6">
                <AppIcon name="inbox" class="w-10 h-10 text-slate-200" />
             </div>
             <p class="text-sm font-bold text-slate-400 uppercase tracking-[0.2em]">Pusto tutaj</p>
             <p class="text-xs text-slate-300 mt-2 max-w-[180px]">Twoja skrzynka odbiorcza jest na ten moment czysta.</p>
          </div>

        </div>

        <!-- Pagination bar — fixed outside scroll, always visible -->
        <div v-if="totalPages > 1 || (mailMode === 'imap' && emailsLoading)" class="shrink-0 border-t border-slate-100 px-3 py-2 flex items-center justify-between gap-1 bg-white">
            <button
              class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 disabled:opacity-30 disabled:cursor-not-allowed transition"
              :disabled="currentPage === 1 || emailsLoading"
              @click="currentPage--; selectedEmail = null; mailboxStore.fetchEmailsForFolder(currentFolder, currentPage)"
            >
              <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
            </button>
            <div class="flex items-center gap-1">
              <template v-for="p in visiblePages" :key="p">
                <span v-if="p === '...'" class="text-[11px] text-slate-400 px-0.5">…</span>
                <button
                  v-else
                  class="min-w-[24px] h-6 px-1.5 rounded-lg text-[11px] font-black transition"
                  :class="p === currentPage ? 'bg-stratton-gold text-white shadow-sm' : 'hover:bg-slate-100 text-slate-500'"
                  :disabled="emailsLoading"
                  @click="currentPage = p; selectedEmail = null; mailboxStore.fetchEmailsForFolder(currentFolder, p)"
                >{{ p }}</button>
              </template>
            </div>
            <button
              class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 disabled:opacity-30 disabled:cursor-not-allowed transition"
              :disabled="currentPage === totalPages || emailsLoading"
              @click="currentPage++; selectedEmail = null; mailboxStore.fetchEmailsForFolder(currentFolder, currentPage)"
            >
              <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
            </button>
          </div>
      </div>

      <!-- Email Content Area -->
      <div class="flex-1 flex flex-col min-w-0 bg-white relative">
        <transition 
          enter-active-class="transition duration-500 ease-out"
          enter-from-class="opacity-0 translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
          mode="out-in"
        >
          <div v-if="selectedEmail" :key="selectedEmail.id" class="flex flex-col h-full">
            <!-- Email Header — compact single-line bar -->
            <div class="px-5 py-2.5 border-b border-slate-100 bg-white sticky top-0 z-20 flex items-center gap-3 min-w-0">
              <div class="w-8 h-8 rounded-xl shrink-0 bg-slate-100 border border-slate-200/60 flex items-center justify-center font-black text-slate-500 text-[11px]">
                {{ selectedEmail.fromName.charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0 flex items-baseline gap-2">
                <span class="font-black text-slate-900 text-[13px] truncate max-w-[180px] shrink-0">{{ selectedEmail.fromName }}</span>
                <span class="text-slate-400 text-[11px] truncate max-w-[160px] shrink-0 hidden sm:inline">&lt;{{ selectedEmail.fromEmail }}&gt;</span>
                <span class="text-slate-200 mx-0.5 shrink-0">·</span>
                <span class="font-semibold text-slate-700 text-[13px] truncate">{{ selectedEmail.subject }}</span>
              </div>
              <span class="text-[11px] font-bold text-slate-400 whitespace-nowrap shrink-0">
                {{ new Date(selectedEmail.date).toLocaleString([], { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
              </span>
              <div class="flex items-center gap-0.5 shrink-0">
                <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-sky-500 transition-all" title="Oznacz gwiazdką">
                  <AppIcon name="star" class="w-4 h-4" />
                </button>
                <button class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-all" title="Usuń">
                  <AppIcon name="trash" class="w-4 h-4" />
                </button>
                <button class="ml-1 px-3 py-1.5 text-[11px] font-black bg-white border border-slate-200 rounded-lg text-slate-600 hover:border-sky-500 hover:text-sky-600 transition-all shadow-sm active:scale-95">
                  Szczegóły
                </button>
              </div>
            </div>
            
            <!-- Email Body Content -->
            <div class="flex-1 overflow-y-auto custom-scrollbar bg-white">
              <div class="mx-auto p-12 pr-12 prose prose-slate prose-lg max-w-none prose-p:leading-relaxed prose-p:text-slate-700 prose-headings:font-serif prose-headings:font-black prose-a:text-sky-600 hover:prose-a:text-sky-700 prose-img:rounded-3xl prose-img:shadow-2xl">
                <div v-if="bodyLoading" class="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
                  <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                  Ładowanie treści...
                </div>
                <div v-else v-html="selectedEmail.body"></div>
              </div>
            </div>
            
            <!-- Action Bar -->
            <div class="px-4 py-2 border-t border-slate-100 bg-white/90 backdrop-blur-xl sticky bottom-0 z-20 flex items-center gap-2">
              <button type="button"
                @click="replyEmail"
                class="px-4 py-2 text-xs font-black text-white bg-slate-900 border border-slate-800 rounded-xl hover:bg-black transition-all flex items-center gap-2 active:scale-95">
                <AppIcon name="reply" class="w-3.5 h-3.5" />
                Odpowiedz
              </button>
              <button type="button"
                @click="forwardEmail"
                class="px-4 py-2 text-xs font-black text-slate-700 bg-white border border-slate-200 rounded-xl hover:border-slate-300 hover:bg-slate-50 transition-all flex items-center gap-2 active:scale-95">
                <AppIcon name="forward" class="w-3.5 h-3.5" />
                Prześlij dalej
              </button>
              <button class="ml-auto p-2 rounded-xl border border-slate-100 hover:bg-slate-50 text-slate-400 transition-colors">
                <AppIcon name="dots-horizontal" class="w-4 h-4" />
              </button>
            </div>
          </div>
          
          <!-- Empty State -->
          <div v-else class="flex-1 flex flex-col items-center justify-center bg-slate-50/20">
            <div class="relative w-48 h-48 mb-6 group">
                <div class="absolute inset-0 bg-stratton-gold/20 rounded-[3rem] blur-2xl animate-pulse group-hover:scale-125 transition-transform duration-700"></div>
                <div class="relative h-full flex items-center justify-center bg-white rounded-[3rem] border border-slate-100 shadow-xl overflow-hidden p-10">
                   <AppIcon name="mail" class="w-20 h-20 text-slate-100 transform -rotate-12 transition-transform group-hover:rotate-0 duration-500" />
                   <div class="absolute inset-0 bg-linear-to-tr from-sky-400/5 to-transparent"></div>
                </div>
            </div>
            <h3 class="text-xl font-serif font-black text-slate-800 tracking-tight">Wybierz korespondencję</h3>
            <p class="text-sm text-slate-400 font-medium mt-2 max-w-[280px] text-center px-4 leading-relaxed">System zarządzania korespondencją Stratton Prime jest gotowy do współpracy.</p>
            <div class="mt-8 flex gap-2">
               <div class="w-1.5 h-1.5 rounded-full bg-slate-200 animate-bounce" style="animation-delay: 0s"></div>
               <div class="w-1.5 h-1.5 rounded-full bg-slate-200 animate-bounce" style="animation-delay: 0.2s"></div>
               <div class="w-1.5 h-1.5 rounded-full bg-slate-200 animate-bounce" style="animation-delay: 0.4s"></div>
            </div>
          </div>
        </transition>
      </div>
    </div>
    
    <!-- Compose Modal: Modern Redesign -->
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

            <!-- Editor Toolbar -->
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
                 
                 <!-- Knowledge Base Dropdown -->
                 <div v-show="showKbDropdown" class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden" @click.stop>
                   <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                     <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Dokumenty do pobrania</p>
                     <button class="text-slate-400 hover:text-slate-600" @click="showKbDropdown = false">✕</button>
                   </div>
                   <div class="max-h-60 overflow-y-auto custom-scrollbar p-1">
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
              
              <!-- Attachments List -->
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
                class="group relative overflow-hidden bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white font-black py-3.5 px-10 rounded-2xl hover:brightness-110 hover:shadow-xl hover:shadow-[#D4AF37]/20 active:scale-[0.98] transition-all duration-300 flex items-center gap-2" 
                @click="sendEmail">
                <AppIcon name="paper-airplane" class="w-4 h-4 rotate-12 group-hover:rotate-0 transition-transform" />
                Wyślij teraz
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Address Book: Enterprise Overlay -->
    <transition
      enter-active-class="transition duration-400 ease-out"
      enter-from-class="opacity-0 translate-y-12 backdrop-blur-0"
      enter-to-class="opacity-100 translate-y-0 backdrop-blur-md"
      leave-active-class="transition duration-300 ease-in"
      leave-from-class="opacity-100 translate-y-0 backdrop-blur-md"
      leave-to-class="opacity-0 translate-y-12 backdrop-blur-0"
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
          
          <div class="p-4 shrink-0">
             <div class="relative">
                <input type="text" placeholder="Szukaj kontaktu..." class="w-full pl-10 bg-slate-50 border-slate-100 rounded-xl text-sm focus:ring-sky-500/20 text-right font-bold" />
                <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300" />
             </div>
          </div>
          
          <div class="flex-1 p-4 overflow-y-auto custom-scrollbar pt-0 space-y-1">
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
                   <span class="text-[9px] font-black uppercase tracking-tighter text-sky-700 bg-sky-100 px-1.5 py-0.5 rounded shadow-sm shadow-sky-600/5">{{ contact.source }}</span>
                </div>
              </div>
              <AppIcon name="chevron-right" class="w-4 h-4 text-slate-200 opacity-0 group-hover:opacity-100 transition-all transform group-hover:translate-x-1" />
            </div>
            
            <div v-if="addressBookContacts.length === 0" class="flex flex-col items-center justify-center p-12 text-center">
              <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4 transition-transform hover:scale-110">
                 <AppIcon name="users" class="w-6 h-6 text-slate-200" />
              </div>
              <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Brak kontaktów</p>
              <p class="text-xs text-slate-300 mt-2">Twoja lista kontaktów jest pusta.</p>
            </div>
          </div>
          
          <div class="p-6 bg-slate-50/50 border-t border-slate-100/50 flex justify-center">
             <button class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-sky-600 transition-colors">Importuj kontakty z systemu</button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #E2E8F0;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #CBD5E1;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.animate-shimmer {
  animation: shimmer 1.5s infinite;
}

@keyframes pulse-slow {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

.animate-pulse-slow {
  animation: pulse-slow 3s infinite;
}

/* Typography Overrides for Enterprise feel */
h1, h2, h3, .font-serif {
  letter-spacing: -0.02em;
}

[contenteditable=true]:empty:before {
  content: attr(placeholder);
  color: #CBD5E1;
  pointer-events: none;
}

  /* List styles for editor */
  [contenteditable="true"] ul {
    list-style-type: disc !important;
    padding-left: 2rem !important;
    margin: 1rem 0 !important;
    display: block !important;
  }

  [contenteditable="true"] ol {
    list-style-type: decimal !important;
    padding-left: 2rem !important;
    margin: 1rem 0 !important;
    display: block !important;
  }

  [contenteditable="true"] li {
    display: list-item !important;
    margin-bottom: 0.25rem;
  }
</style>
