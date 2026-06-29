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
import { sanitizeHtml } from '@/utils/sanitizeHtml'
import { api } from '@/api/client'
import type { Email } from '@/types/models'
import AppIcon from '@/components/AppIcon.vue'
import TabHeader from '@/components/ui/TabHeader.vue'
import MailAiPanel from '@/components/MailAiPanel.vue'

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
const isMobile = ref(window.innerWidth < 768)
const onWindowResize = () => { isMobile.value = window.innerWidth < 768 }

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

const showMobileDrawer = ref(false)

const currentFolder = ref<'INBOX' | 'SENT' | 'TRASH' | 'DRAFTS' | 'SPAM' | 'STARRED'>('INBOX')
// W folderach wychodzących (Wysłane / Robocze) nadawcą jest zawsze zalogowany
// użytkownik — pokazujemy ODBIORCĘ ("Do:"), nie nadawcę. Inaczej widać tylko siebie.
const isOutgoingFolder = computed(() => currentFolder.value === 'SENT' || currentFolder.value === 'DRAFTS')
const contactName = (email: any) => isOutgoingFolder.value
  ? (email?.toName || email?.toEmail || '—')
  : (email?.fromName || email?.fromEmail || '—')
const contactEmail = (email: any) => isOutgoingFolder.value ? (email?.toEmail || '') : (email?.fromEmail || '')
const starredIds = ref<Set<string>>(new Set(JSON.parse(localStorage.getItem('mailbox_starred') || '[]')))
const saveStarred = () => localStorage.setItem('mailbox_starred', JSON.stringify([...starredIds.value]))
const selectedEmail = ref<Email | null>(null)
const showAiPanel = ref(false)
const bodyLoading = ref(false)
const currentPage = ref(1)
const PAGE_SIZE = mailboxStore.PAGE_SIZE

// Search & filter
const searchQuery = ref('')
const showFilterPanel = ref(false)
const filterUnread = ref(false)
const filterHasAttachment = ref(false)
const filterDateFrom = ref('')
const filterDateTo = ref('')

const clearFilters = () => {
  filterUnread.value = false
  filterHasAttachment.value = false
  filterDateFrom.value = ''
  filterDateTo.value = ''
  searchQuery.value = ''
}

const hasActiveFilters = computed(() =>
  !!searchQuery.value || filterUnread.value || filterHasAttachment.value || !!filterDateFrom.value || !!filterDateTo.value
)

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

const formatFileSize = (bytes: number): string => {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const isPreviewable = (mimeType: string): boolean =>
  !!mimeType && (mimeType.startsWith('image/') || mimeType === 'application/pdf')

const attIconBg = (mimeType: string): string => {
  if (mimeType === 'application/pdf') return 'bg-red-50'
  if (mimeType?.startsWith('image/')) return 'bg-sky-50'
  if (mimeType?.includes('word') || mimeType?.includes('document')) return 'bg-blue-50'
  if (mimeType?.includes('sheet') || mimeType?.includes('excel')) return 'bg-emerald-50'
  return 'bg-slate-100'
}

const attachmentLoadingSet = ref<Set<string>>(new Set())

const previewModal = ref<{ open: boolean; filename: string; mimeType: string; blobUrl: string }>({
  open: false, filename: '', mimeType: '', blobUrl: '',
})

const closeAttachmentPreview = () => {
  if (previewModal.value.blobUrl) URL.revokeObjectURL(previewModal.value.blobUrl)
  previewModal.value = { open: false, filename: '', mimeType: '', blobUrl: '' }
}

const base64ToBlob = (base64: string, mimeType: string): Blob => {
  const byteChars = atob(base64)
  const byteArr = new Uint8Array(byteChars.length)
  for (let i = 0; i < byteChars.length; i++) byteArr[i] = byteChars.charCodeAt(i)
  return new Blob([byteArr], { type: mimeType })
}

const fetchAttachmentData = async (filename: string) => {
  if (!selectedEmail.value) throw new Error('No email selected')
  const { data } = await api.get(
    `/v1/crm-mailbox/messages/${encodeURIComponent(selectedEmail.value.id)}/attachment`,
    { params: { filename }, timeout: 60000 }
  )
  return { content: data.data.content as string, mimeType: data.data.mimeType as string }
}

const downloadAttachment = async (filename: string) => {
  if (!selectedEmail.value) return
  const key = `${selectedEmail.value.id}:${filename}`
  if (attachmentLoadingSet.value.has(key)) return
  attachmentLoadingSet.value = new Set([...attachmentLoadingSet.value, key])
  try {
    const { content, mimeType } = await fetchAttachmentData(filename)
    const blob = base64ToBlob(content, mimeType)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 5000)
  } catch {
    toast.error('Nie udało się pobrać załącznika.')
  } finally {
    const next = new Set(attachmentLoadingSet.value)
    next.delete(key)
    attachmentLoadingSet.value = next
  }
}

const openAttachmentPreview = async (filename: string, mimeType: string) => {
  if (!isPreviewable(mimeType)) {
    await downloadAttachment(filename)
    return
  }
  if (!selectedEmail.value) return
  const key = `${selectedEmail.value.id}:${filename}`
  if (attachmentLoadingSet.value.has(key)) return
  attachmentLoadingSet.value = new Set([...attachmentLoadingSet.value, key])
  try {
    const { content, mimeType: responseMime } = await fetchAttachmentData(filename)
    const blob = base64ToBlob(content, responseMime)
    const url = URL.createObjectURL(blob)
    previewModal.value = { open: true, filename, mimeType: responseMime, blobUrl: url }
  } catch {
    toast.error('Nie udało się otworzyć podglądu.')
  } finally {
    const next = new Set(attachmentLoadingSet.value)
    next.delete(key)
    attachmentLoadingSet.value = next
  }
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
  let result: typeof list

  if (folder === 'STARRED') {
    result = list
      .filter((email) => starredIds.value.has(email.id))
      .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
  } else if (mailMode.value === 'imap') {
    result = list
      .filter((email) => email.folder === folder)
      .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
  } else {
    result = list
      .filter((email) => {
        if (folder === 'INBOX' || folder === 'TRASH') return email.toEmail === userEmail && email.folder === folder
        if (folder === 'SENT') return email.fromEmail === userEmail && email.folder === folder
        return false
      })
      .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
  }

  // Apply search query
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    result = result.filter((e) =>
      e.subject.toLowerCase().includes(q) ||
      e.fromName.toLowerCase().includes(q) ||
      e.fromEmail.toLowerCase().includes(q) ||
      (e.body || '').toLowerCase().includes(q)
    )
  }

  // Apply filters
  if (filterUnread.value) result = result.filter((e) => !e.read)
  if (filterHasAttachment.value) result = result.filter((e) => Array.isArray(e.attachments) && e.attachments.length > 0)
  if (filterDateFrom.value) {
    const from = new Date(filterDateFrom.value).getTime()
    result = result.filter((e) => new Date(e.date).getTime() >= from)
  }
  if (filterDateTo.value) {
    const to = new Date(filterDateTo.value + 'T23:59:59').getTime()
    result = result.filter((e) => new Date(e.date).getTime() <= to)
  }

  return result
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

const selectFolder = (folder: 'INBOX' | 'SENT' | 'TRASH' | 'DRAFTS' | 'SPAM' | 'STARRED') => {
  currentFolder.value = folder
  selectedEmail.value = null
  currentPage.value = 1
  showMobileDrawer.value = false
  if (folder !== 'STARRED') mailboxStore.fetchEmailsForFolder(folder as any, 1)
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

const saveAsDraft = async () => {
  const { to, subject } = composeData.value
  const body = editor.value?.innerHTML || ''
  try {
    await mailboxStore.saveDraft(to, subject, body)
    toast.success('Zapisano jako roboczy.')
    closeCompose()
    // refresh drafts if we're on that folder
    if (currentFolder.value === 'DRAFTS') {
      mailboxStore.fetchEmailsForFolder('DRAFTS', 1)
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać roboczego.'
    toast.error(message)
  }
}

const moveToSpam = async () => {
  if (!selectedEmail.value) return
  const emailId = selectedEmail.value.id
  selectedEmail.value = null
  try {
    await mailboxStore.moveMessage(emailId, 'SPAM')
    toast.success('Wiadomość przeniesiona do spamu.')
  } catch {}
}

const moveToInbox = async () => {
  if (!selectedEmail.value) return
  const emailId = selectedEmail.value.id
  selectedEmail.value = null
  try {
    await mailboxStore.moveMessage(emailId, 'INBOX')
    toast.success('Wiadomość przywrócona do skrzynki odbiorczej.')
  } catch {}
}

const moveToTrash = async () => {
  if (!selectedEmail.value) return
  if (currentFolder.value === 'TRASH') return
  const emailId = selectedEmail.value.id
  selectedEmail.value = null
  try {
    await mailboxStore.moveMessage(emailId, 'TRASH')
    toast.success('Wiadomość przeniesiona do kosza.')
  } catch {}
}

const toggleStarredById = (id: string) => {
  if (starredIds.value.has(id)) {
    starredIds.value.delete(id)
  } else {
    starredIds.value.add(id)
  }
  starredIds.value = new Set(starredIds.value)
  saveStarred()
}

const toggleStarred = () => {
  if (!selectedEmail.value) return
  toggleStarredById(selectedEmail.value.id)
}

const moveEmailToTrash = async (emailId: string) => {
  const email = mailboxStore.emails.find(e => e.id === emailId)
  if (!email || email.folder === 'TRASH') return
  if (selectedEmail.value?.id === emailId) selectedEmail.value = null
  try {
    await mailboxStore.moveMessage(emailId, 'TRASH')
    toast.success('Wiadomość przeniesiona do kosza.')
  } catch {}
}

const editDraft = () => {
  if (!selectedEmail.value) return
  const e = selectedEmail.value
  composeState.value = { open: true, to: e.toEmail || '', subject: e.subject, body: e.body || '' }
  selectedEmail.value = null
}

const formatDoc = (command: string, value?: string) => {
  document.execCommand(command, false, value)
  editor.value?.focus()
}

onMounted(() => {
  mailboxStore.fetchEmailsForFolder(currentFolder.value, 1)
  mailboxStore.startPolling?.()
  window.addEventListener('resize', onWindowResize)
})

onBeforeUnmount(() => {
  mailboxStore.stopPolling?.()
  window.removeEventListener('resize', onWindowResize)
})

watch(
  () => currentUser.value?.id,
  () => {
    mailboxStore.fetchEmailsForFolder(currentFolder.value, 1)
  }
)

watch(searchQuery, () => {
  currentPage.value = 1
  selectedEmail.value = null
})
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)] bg-surface-subtle">

    <TabHeader icon="envelope" title="Skrzynka Pocztowa">
      <template #actions>
        <div class="w-full md:w-96 relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Wyszukaj w korespondencji..."
            class="w-full pl-4 pr-10 py-2 bg-white border border-slate-200 text-slate-800 placeholder-slate-400 rounded-lg focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-sm font-bold text-sm"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition-colors"
            @click="searchQuery = ''"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </template>
    </TabHeader>

    <!-- Main Content -->
    <div class="flex-1 mx-3 md:mx-6 my-3 md:my-4 bg-white rounded-xl md:rounded-[2rem] shadow-[0_20px_50px_-20px_rgba(0,0,0,0.1)] border border-slate-200/60 flex overflow-hidden min-h-0 relative">
      
      <!-- Mobile Drawer (folders) — visible only on mobile via Teleport -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          leave-active-class="transition-all duration-250 ease-in"
        >
          <div v-if="showMobileDrawer" class="fixed inset-0 z-200 md:hidden flex">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showMobileDrawer = false"></div>
            <!-- Drawer panel -->
            <div class="relative z-10 w-72 max-w-[85vw] h-full bg-white shadow-2xl flex flex-col" style="animation: mailbox-drawer-in 0.25s cubic-bezier(0.25,0.46,0.45,0.94) forwards">
              <!-- Drawer header -->
              <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%);">
                <span class="text-white font-black text-sm uppercase tracking-widest">Skrzynka</span>
                <button class="text-slate-300 hover:text-white p-1 rounded-lg transition-colors" @click="showMobileDrawer = false">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Compose button -->
              <div class="px-4 pt-5 pb-3">
                <button
                  type="button"
                  class="w-full bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white font-black py-3 px-5 rounded-2xl flex items-center justify-center gap-2 text-sm uppercase tracking-wider shadow-lg"
                  @click="showMobileDrawer = false; openCompose()"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                  </svg>
                  Nowa Wiadomość
                </button>
              </div>

              <!-- Folder list -->
              <nav class="flex-1 overflow-y-auto px-3 pb-6 space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-3 mb-2 mt-2">Foldery</p>

                <a class="group flex justify-between items-center px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-200"
                  :class="currentFolder === 'INBOX' ? 'bg-[#D4AF37]/10 text-[#D4AF37]' : 'text-slate-600 hover:bg-slate-100'"
                  @click="selectFolder('INBOX')">
                  <div class="flex items-center gap-3">
                    <div class="p-1.5 rounded-lg" :class="currentFolder === 'INBOX' ? 'bg-[#D4AF37]/20' : 'bg-slate-100'">
                      <AppIcon name="inbox" class="w-4 h-4" />
                    </div>
                    <span class="font-bold text-[14px]">Odebrane</span>
                  </div>
                  <div v-if="unreadCount > 0" class="min-w-5 h-5 px-1.5 bg-[#D4AF37] text-white text-[10px] font-black rounded-lg flex items-center justify-center shadow-md">
                    {{ unreadCount }}
                  </div>
                </a>

                <a class="group flex items-center gap-3 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-200"
                  :class="currentFolder === 'SENT' ? 'bg-[#D4AF37]/10 text-[#D4AF37]' : 'text-slate-600 hover:bg-slate-100'"
                  @click="selectFolder('SENT')">
                  <div class="p-1.5 rounded-lg" :class="currentFolder === 'SENT' ? 'bg-[#D4AF37]/20' : 'bg-slate-100'">
                    <AppIcon name="paper-airplane" class="w-4 h-4" />
                  </div>
                  <span class="font-bold text-[14px]">Wysłane</span>
                </a>

                <a class="group flex items-center gap-3 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-200"
                  :class="currentFolder === 'DRAFTS' ? 'bg-[#D4AF37]/10 text-[#D4AF37]' : 'text-slate-600 hover:bg-slate-100'"
                  @click="selectFolder('DRAFTS')">
                  <div class="p-1.5 rounded-lg" :class="currentFolder === 'DRAFTS' ? 'bg-[#D4AF37]/20' : 'bg-slate-100'">
                    <AppIcon name="document-text" class="w-4 h-4" />
                  </div>
                  <span class="font-bold text-[14px]">Robocze</span>
                </a>

                <a class="group flex items-center gap-3 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-200"
                  :class="currentFolder === 'TRASH' ? 'bg-[#D4AF37]/10 text-[#D4AF37]' : 'text-slate-600 hover:bg-slate-100'"
                  @click="selectFolder('TRASH')">
                  <div class="p-1.5 rounded-lg" :class="currentFolder === 'TRASH' ? 'bg-[#D4AF37]/20' : 'bg-slate-100'">
                    <AppIcon name="trash" class="w-4 h-4" />
                  </div>
                  <span class="font-bold text-[14px]">Kosz</span>
                </a>

                <a class="group flex items-center gap-3 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-200"
                  :class="currentFolder === 'SPAM' ? 'bg-[#D4AF37]/10 text-[#D4AF37]' : 'text-slate-600 hover:bg-slate-100'"
                  @click="selectFolder('SPAM')">
                  <div class="p-1.5 rounded-lg" :class="currentFolder === 'SPAM' ? 'bg-[#D4AF37]/20' : 'bg-slate-100'">
                    <AppIcon name="exclamation-circle" class="w-4 h-4" />
                  </div>
                  <span class="font-bold text-[14px]">Spam</span>
                </a>

                <a class="group flex justify-between items-center px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-200"
                  :class="currentFolder === 'STARRED' ? 'bg-[#D4AF37]/10 text-[#D4AF37]' : 'text-slate-600 hover:bg-slate-100'"
                  @click="selectFolder('STARRED')">
                  <div class="flex items-center gap-3">
                    <div class="p-1.5 rounded-lg" :class="currentFolder === 'STARRED' ? 'bg-[#D4AF37]/20' : 'bg-slate-100'">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                      </svg>
                    </div>
                    <span class="font-bold text-[14px]">Oznaczone</span>
                  </div>
                  <div v-if="starredIds.size > 0" class="min-w-5 h-5 px-1.5 bg-amber-400 text-white text-[10px] font-black rounded-lg flex items-center justify-center">
                    {{ starredIds.size }}
                  </div>
                </a>
              </nav>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Folders Sidebar — hidden on mobile -->
      <div class="hidden md:flex w-52 bg-slate-50/80 border-r border-slate-200/60 p-4 flex-col shrink-0">
        
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

              <a class="group flex items-center gap-3.5 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-300"
                :class="currentFolder === 'DRAFTS' ? 'bg-[#D4AF37]/10 text-[#D4AF37] shadow-sm' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900'"
                @click="selectFolder('DRAFTS')">
                <div class="p-1.5 rounded-lg transition-colors" :class="currentFolder === 'DRAFTS' ? 'bg-[#D4AF37]/20' : 'bg-slate-100 group-hover:bg-slate-200'">
                  <AppIcon name="document-text" class="w-4.5 h-4.5" />
                </div>
                <span class="font-bold text-[13px]">Robocze</span>
              </a>

              <a class="group flex items-center gap-3.5 px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-300"
                :class="currentFolder === 'SPAM' ? 'bg-[#D4AF37]/10 text-[#D4AF37] shadow-sm' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900'"
                @click="selectFolder('SPAM')">
                <div class="p-1.5 rounded-lg transition-colors" :class="currentFolder === 'SPAM' ? 'bg-[#D4AF37]/20' : 'bg-slate-100 group-hover:bg-slate-200'">
                  <AppIcon name="exclamation-circle" class="w-4.5 h-4.5" />
                </div>
                <span class="font-bold text-[13px]">Spam</span>
              </a>

              <a class="group flex justify-between items-center px-4 py-3.5 rounded-2xl cursor-pointer transition-all duration-300"
                :class="currentFolder === 'STARRED' ? 'bg-[#D4AF37]/10 text-[#D4AF37] shadow-sm' : 'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900'"
                @click="selectFolder('STARRED')">
                <div class="flex items-center gap-3.5">
                  <div class="p-1.5 rounded-lg transition-colors" :class="currentFolder === 'STARRED' ? 'bg-[#D4AF37]/20' : 'bg-slate-100 group-hover:bg-slate-200'">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
                  </div>
                  <span class="font-bold text-[13px]">Oznaczone</span>
                </div>
                <div v-if="starredIds.size > 0" class="flex items-center justify-center min-w-5 h-5 px-1.5 bg-amber-400 text-white text-[10px] font-black rounded-lg">
                  {{ starredIds.size }}
                </div>
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

      <!-- Email List Column — full-width on mobile, hidden when email selected on mobile -->
      <div
        :class="[
          'border-r border-slate-200/60 flex-col shrink-0 bg-white relative',
          selectedEmail && isMobile ? 'hidden' : 'flex'
        ]"
        :style="isMobile ? {} : { width: sidebarWidth + 'px' }"
      >
        <!-- Resizer Handle -->
        <div 
           class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-500/20 active:bg-sky-500/40 transition-colors z-50 translate-x-1/2" 
           @mousedown.prevent="startResize"
        ></div>

        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
           <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
             <!-- Mobile drawer toggle -->
             <button
               class="md:hidden p-1.5 rounded-xl hover:bg-slate-100 text-slate-500 transition-colors mr-1"
               @click="showMobileDrawer = true"
               title="Foldery"
             >
               <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
               </svg>
             </button>
             <div class="w-1.5 h-4 bg-stratton-gold rounded-full"></div>
             Wiadomości
             <span v-if="hasActiveFilters" class="ml-1 px-2 py-0.5 bg-sky-100 text-sky-600 text-[10px] font-black rounded-full uppercase tracking-wide">filtr</span>
           </h2>
           <div class="flex items-center gap-2">
             <button class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 transition-colors" :class="{'animate-spin': emailsLoading}" @click="mailboxStore.fetchEmailsForFolder(currentFolder, currentPage, true)">
               <AppIcon name="refresh" class="w-4 h-4" />
             </button>
             <button
               class="p-2 rounded-xl hover:bg-slate-100 transition-colors relative"
               :class="hasActiveFilters ? 'text-sky-500 bg-sky-50' : 'text-slate-400'"
               @click="showFilterPanel = !showFilterPanel"
             >
               <AppIcon name="filter" class="w-4 h-4" />
               <span v-if="hasActiveFilters" class="absolute top-1 right-1 w-2 h-2 bg-sky-500 rounded-full"></span>
             </button>
           </div>
        </div>

        <!-- Filter Panel -->
        <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
          <div v-if="showFilterPanel" class="border-b border-slate-100 px-4 py-3 bg-slate-50/80 space-y-3">
            <div class="flex items-center justify-between mb-1">
              <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Filtruj wiadomości</p>
              <button v-if="hasActiveFilters" class="text-[10px] font-black text-sky-500 hover:text-sky-700 uppercase tracking-wider" @click="clearFilters">Wyczyść</button>
            </div>
            <label class="flex items-center gap-2 cursor-pointer group">
              <input type="checkbox" v-model="filterUnread" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
              <span class="text-[12px] font-bold text-slate-600 group-hover:text-slate-900">Tylko nieprzeczytane</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer group">
              <input type="checkbox" v-model="filterHasAttachment" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
              <span class="text-[12px] font-bold text-slate-600 group-hover:text-slate-900">Z załącznikami</span>
            </label>
            <div class="flex items-center gap-2">
              <div class="flex-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Od daty</label>
                <input type="date" v-model="filterDateFrom" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-[12px] font-bold text-slate-700 bg-white focus:ring-2 focus:ring-sky-400/20 focus:border-sky-400" />
              </div>
              <div class="flex-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Do daty</label>
                <input type="date" v-model="filterDateTo" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-[12px] font-bold text-slate-700 bg-white focus:ring-2 focus:ring-sky-400/20 focus:border-sky-400" />
              </div>
            </div>
          </div>
        </transition>

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
            <!-- Starred indicator -->
            <div v-if="starredIds.has(email.id)" class="absolute right-3 top-7 w-2 h-2 text-amber-400">
              <svg fill="#FBBF24" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
            </div>
             
            <div class="flex gap-3 items-center">
              <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center font-black text-[11px] transition-transform group-hover:scale-105"
                :class="selectedEmail?.id === email.id ? 'bg-white text-sky-600 border border-sky-100' : 'bg-slate-100 text-slate-500 border border-slate-200/50'">
                {{ contactName(email).substring(0, 2).toUpperCase() }}
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex justify-between items-center mb-0.5">
                  <p class="text-[12px] font-black text-slate-900 group-hover:text-stratton-gold transition-colors truncate pr-1" :class="!email.read ? 'font-black' : 'font-bold opacity-80'">
                    <span v-if="isOutgoingFolder" class="text-slate-400 font-bold">Do: </span>{{ contactName(email) }}
                  </p>
                  <div class="flex items-center gap-0.5 shrink-0">
                    <button
                      class="p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-amber-50 transition-all"
                      :class="starredIds.has(email.id) ? 'text-amber-400 !opacity-100' : 'text-slate-400 hover:text-amber-400'"
                      title="Oznacz gwiazdką"
                      @click.stop="toggleStarredById(email.id)"
                    >
                      <svg class="w-3 h-3" :fill="starredIds.has(email.id) ? '#FBBF24' : 'none'" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
                    </button>
                    <button
                      v-if="currentFolder !== 'TRASH'"
                      class="p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-all"
                      title="Przenieś do kosza"
                      @click.stop="moveEmailToTrash(email.id)"
                    >
                      <AppIcon name="trash" class="w-3 h-3" />
                    </button>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter whitespace-nowrap ml-0.5">
                      {{ formatEmailDate(email.date) }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-1 min-w-0">
                  <svg v-if="email.hasAttachments || email.attachments?.some(a => !a.isInline)" class="w-3 h-3 text-slate-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                  <p class="text-[12px] text-slate-800 leading-tight truncate" :class="!email.read ? 'font-extrabold' : 'font-semibold opacity-90'">
                    {{ email.subject }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          
          <div v-if="allEmailsInCurrentFolder.length === 0" class="flex flex-col items-center justify-center h-full p-12 text-center">
             <div class="w-24 h-24 bg-slate-50 rounded-[2.5rem] flex items-center justify-center mb-6">
                <AppIcon name="inbox" class="w-10 h-10 text-slate-200" />
             </div>
             <p class="text-sm font-bold text-slate-400 uppercase tracking-[0.2em]">
               {{ hasActiveFilters ? 'Brak wyników' : 'Pusto tutaj' }}
             </p>
             <p class="text-xs text-slate-300 mt-2 max-w-[180px]">
               {{ hasActiveFilters ? 'Zmień kryteria wyszukiwania lub wyczyść filtry.' : 'Twoja skrzynka odbiorcza jest na ten moment czysta.' }}
             </p>
             <button v-if="hasActiveFilters" class="mt-4 text-xs font-black text-sky-500 hover:text-sky-700 uppercase tracking-wider" @click="clearFilters">Wyczyść filtry</button>
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
            <div class="px-3 md:px-5 py-2.5 border-b border-slate-100 bg-white sticky top-0 z-20 flex items-center gap-2 md:gap-3 min-w-0">
              <button class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition shrink-0" @click="selectedEmail = null">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <div class="w-8 h-8 rounded-xl shrink-0 bg-slate-100 border border-slate-200/60 flex items-center justify-center font-black text-slate-500 text-[11px]">
                {{ contactName(selectedEmail).charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0 flex items-baseline gap-2">
                <span v-if="isOutgoingFolder" class="text-slate-400 text-[12px] font-bold shrink-0">Do:</span>
                <span class="font-black text-slate-900 text-[13px] truncate max-w-[180px] shrink-0">{{ contactName(selectedEmail) }}</span>
                <span class="text-slate-400 text-[11px] truncate max-w-[160px] shrink-0 hidden sm:inline">&lt;{{ contactEmail(selectedEmail) }}&gt;</span>
                <span class="text-slate-200 mx-0.5 shrink-0">·</span>
                <span class="font-semibold text-slate-700 text-[13px] truncate">{{ selectedEmail.subject }}</span>
              </div>
              <span class="text-[11px] font-bold text-slate-400 whitespace-nowrap shrink-0">
                {{ new Date(selectedEmail.date).toLocaleString([], { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
              </span>
              <div class="flex items-center gap-0.5 shrink-0">
                <button
                  class="p-1.5 rounded-lg hover:bg-amber-50 transition-all"
                  :class="starredIds.has(selectedEmail.id) ? 'text-amber-400' : 'text-slate-400 hover:text-amber-400'"
                  title="Oznacz gwiazdką"
                  @click="toggleStarred"
                >
                  <svg class="w-4 h-4" :fill="starredIds.has(selectedEmail.id) ? '#FBBF24' : 'none'" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
                </button>
                <button
                  v-if="currentFolder !== 'TRASH'"
                  class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-all"
                  title="Przenieś do kosza"
                  @click="moveToTrash"
                >
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
                <div v-else v-html="sanitizeHtml(selectedEmail.body)"></div>
              </div>

              <!-- Attachments -->
              <div v-if="!bodyLoading && selectedEmail.attachments?.some(a => !a.isInline)" class="px-12 pb-10">
                <div class="border-t border-slate-100 pt-6">
                  <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    Załączniki ({{ selectedEmail.attachments!.filter(a => !a.isInline).length }})
                  </p>
                  <div class="flex flex-wrap gap-2">
                    <div
                      v-for="att in selectedEmail.attachments!.filter(a => !a.isInline)"
                      :key="att.filename"
                      class="flex items-center gap-2.5 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl hover:border-stratton-gold/40 hover:bg-amber-50/20 transition-all group"
                    >
                      <!-- Icon -->
                      <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="attIconBg(att.contentType)">
                        <!-- PDF -->
                        <svg v-if="att.contentType === 'application/pdf'" class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 19c-.3 0-.5-.2-.5-.5v-5c0-.3.2-.5.5-.5s.5.2.5.5v5c0 .3-.2.5-.5.5zm3.5 0h-1.5v-6H12c.8 0 1.5.7 1.5 1.5v3c0 .8-.7 1.5-1.5 1.5zm4 0h-1v-6h1c.6 0 1 .4 1 1v4c0 .6-.4 1-1 1z"/>
                        </svg>
                        <!-- Image -->
                        <svg v-else-if="att.contentType?.startsWith('image/')" class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <!-- Excel -->
                        <svg v-else-if="att.contentType?.includes('sheet') || att.contentType?.includes('excel') || att.filename.endsWith('.xlsx') || att.filename.endsWith('.xls')" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M3 3h18v18H3z" />
                        </svg>
                        <!-- Word / generic doc -->
                        <svg v-else class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                      </div>

                      <!-- Name + size -->
                      <div class="min-w-0 max-w-[160px]">
                        <p class="text-[12px] font-bold text-slate-800 truncate" :title="att.filename">{{ att.filename }}</p>
                        <p class="text-[10px] text-slate-400 font-medium">{{ formatFileSize(att.size) }}</p>
                      </div>

                      <!-- Actions -->
                      <div class="flex items-center gap-0.5 ml-1 shrink-0">
                        <!-- Preview (PDF / images only) -->
                        <button
                          v-if="isPreviewable(att.contentType)"
                          :disabled="attachmentLoadingSet.has(`${selectedEmail.id}:${att.filename}`)"
                          class="p-1.5 rounded-lg hover:bg-white text-slate-400 hover:text-sky-600 transition-colors disabled:opacity-40"
                          title="Podgląd"
                          @click="openAttachmentPreview(att.filename, att.contentType)"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                          </svg>
                        </button>
                        <!-- Download -->
                        <button
                          :disabled="attachmentLoadingSet.has(`${selectedEmail.id}:${att.filename}`)"
                          class="p-1.5 rounded-lg hover:bg-white text-slate-400 hover:text-emerald-600 transition-colors disabled:opacity-40"
                          title="Pobierz"
                          @click="downloadAttachment(att.filename)"
                        >
                          <svg v-if="!attachmentLoadingSet.has(`${selectedEmail.id}:${att.filename}`)" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                          </svg>
                          <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Action Bar -->
            <div class="px-4 py-2 border-t border-slate-100 bg-white/90 backdrop-blur-xl sticky bottom-0 z-20 flex items-center gap-2">
              <!-- DRAFTS folder: Edit button -->
              <template v-if="currentFolder === 'DRAFTS'">
                <button type="button"
                  @click="editDraft"
                  class="px-4 py-2 text-xs font-black text-white bg-slate-900 border border-slate-800 rounded-xl hover:bg-black transition-all flex items-center gap-2 active:scale-95">
                  <AppIcon name="pencil" class="w-3.5 h-3.5" />
                  Edytuj roboczą
                </button>
              </template>
              <!-- SPAM folder: Not Spam button -->
              <template v-else-if="currentFolder === 'SPAM'">
                <button type="button"
                  @click="moveToInbox"
                  class="px-4 py-2 text-xs font-black text-white bg-emerald-600 border border-emerald-700 rounded-xl hover:bg-emerald-700 transition-all flex items-center gap-2 active:scale-95">
                  <AppIcon name="check-circle" class="w-3.5 h-3.5" />
                  To nie spam
                </button>
              </template>
              <!-- Normal folders: Reply, Forward, Spam -->
              <template v-else>
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
                <button type="button"
                  @click="moveToSpam"
                  class="px-4 py-2 text-xs font-black text-slate-500 bg-white border border-slate-200 rounded-xl hover:border-rose-300 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center gap-2 active:scale-95">
                  <AppIcon name="exclamation-circle" class="w-3.5 h-3.5" />
                  Spam
                </button>
              </template>
              <button
                type="button"
                class="ml-auto flex items-center gap-1.5 px-3 py-2 text-xs font-black rounded-xl border transition-all active:scale-95"
                :class="showAiPanel ? 'bg-slate-900 text-white border-slate-800' : 'text-slate-600 bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                @click="showAiPanel = !showAiPanel"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2" />
                </svg>
                AI
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
      <!-- AI Panel -->
      <MailAiPanel v-model:open="showAiPanel" :selected-email="selectedEmail" />
    </div>

  </div>

  <!-- Attachment Preview Modal -->
  <Teleport to="body">
    <div v-if="previewModal.open" class="fixed inset-0 z-[300] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeAttachmentPreview"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden" style="width: 90vw; max-width: 1200px; height: 90vh;">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 shrink-0">
          <p class="text-sm font-black text-slate-900 truncate max-w-[500px]">{{ previewModal.filename }}</p>
          <div class="flex items-center gap-2">
            <button
              class="px-3 py-1.5 text-xs font-black bg-white border border-slate-200 rounded-xl hover:border-emerald-400 hover:text-emerald-600 transition-all flex items-center gap-1.5"
              @click="downloadAttachment(previewModal.filename)"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Pobierz
            </button>
            <button
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors"
              @click="closeAttachmentPreview"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>
        <!-- Content -->
        <div class="flex-1 overflow-auto min-h-0 bg-slate-50">
          <iframe
            v-if="previewModal.mimeType === 'application/pdf'"
            :src="previewModal.blobUrl"
            class="w-full h-full border-0"
          />
          <div
            v-else-if="previewModal.mimeType?.startsWith('image/')"
            class="flex items-center justify-center h-full p-8"
          >
            <img
              :src="previewModal.blobUrl"
              :alt="previewModal.filename"
              class="max-w-full max-h-full object-contain rounded-xl shadow-xl"
            />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
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

@keyframes slide-in-left {
  from { transform: translateX(-100%); }
  to { transform: translateX(0); }
}

.animate-slide-in-left {
  animation: slide-in-left 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
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

<style>
@keyframes mailbox-drawer-in {
  from { transform: translateX(-100%); }
  to { transform: translateX(0); }
}
</style>
