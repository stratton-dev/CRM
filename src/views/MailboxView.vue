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
import type { Email } from '@/types/models'

const mailboxStore = useMailboxStore()
const session = useSessionStore()
const data = useDataStore()
const clientStore = useClientStore()
const structure = useStructureStore()
const auth = useAuthStore()
const toast = useToastStore()

const { composeState, emails, mailMode, mailSettingsLoaded } = storeToRefs(mailboxStore)
const { clients } = storeToRefs(clientStore)
const editor = ref<HTMLDivElement | null>(null)
const currentFolder = ref<'INBOX' | 'SENT' | 'TRASH'>('INBOX')
const selectedEmail = ref<Email | null>(null)
const composeData = ref({ to: '', subject: '', body: '' })
const showAddressBook = ref(false)
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
      }
      await nextTick()
      if (editor.value) editor.value.innerHTML = composeData.value.body
    }
  },
  { deep: true }
)

const emailsInCurrentFolder = computed(() => {
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
  mailboxStore.fetchEmailsForFolder(folder)
}

const selectEmail = (email: Email) => {
  selectedEmail.value = email
  if (!email.read && email.folder === 'INBOX') {
    mailboxStore.markAsRead(email.id)
  }
}

const openCompose = () => {
  composeState.value = { open: true }
}

const closeCompose = () => {
  composeState.value = { open: false }
}

const sendEmail = async () => {
  const user = currentUser.value
  if (!user) return
  const { to, subject } = composeData.value
  const body = editor.value?.innerHTML || ''

  if (!to || !subject) {
    toast.error('Adresat i temat są wymagane.')
    return
  }

  try {
    await mailboxStore.sendEmail(user, to, subject, body)
    toast.success(`Wiadomość do ${to} została wysłana.`)
    closeCompose()
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
  mailboxStore.fetchEmailsForFolder(currentFolder.value)
  mailboxStore.startPolling?.()
})

onBeforeUnmount(() => {
  mailboxStore.stopPolling?.()
})

watch(
  () => currentUser.value?.id,
  () => {
    mailboxStore.fetchEmailsForFolder(currentFolder.value)
  }
)
</script>

<template>
  <div class="h-full bg-white rounded-lg shadow border border-gray-200 flex overflow-hidden">
    <div class="w-64 bg-gray-50 border-r border-gray-200 p-4 flex flex-col">
      <h2 class="text-lg font-bold text-gray-800 mb-6">Skrzynka Pocztowa</h2>

      <div
        v-if="auth.enabled && mailSettingsLoaded && mailMode !== 'imap'"
        class="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
      >
        Skrzynka działa w trybie wewnętrznym. Skonfiguruj IMAP/SMTP w
        <RouterLink to="/app/settings" class="font-semibold underline">Ustawieniach</RouterLink>,
        aby pobierać prawdziwą pocztę.
      </div>

      <button type="button" class="w-full bg-sky-600 text-white font-bold py-2 px-4 rounded hover:bg-sky-700 mb-6 shadow" @click="openCompose">
        Nowa Wiadomość
      </button>

      <nav class="space-y-1">
        <a class="flex justify-between items-center px-3 py-2 text-sm font-medium rounded cursor-pointer" :class="currentFolder === 'INBOX' ? 'bg-sky-100 text-sky-700' : 'text-gray-600 hover:bg-gray-200'" @click="selectFolder('INBOX')">
          <span>Odebrane</span>
          <span v-if="unreadCount > 0" class="px-2 py-0.5 bg-sky-600 text-white text-xs rounded-full">{{ unreadCount }}</span>
        </a>
        <a class="flex items-center px-3 py-2 text-sm font-medium rounded cursor-pointer" :class="currentFolder === 'SENT' ? 'bg-sky-100 text-sky-700' : 'text-gray-600 hover:bg-gray-200'" @click="selectFolder('SENT')">
          <span>Wysłane</span>
        </a>
        <a class="flex items-center px-3 py-2 text-sm font-medium rounded cursor-pointer" :class="currentFolder === 'TRASH' ? 'bg-sky-100 text-sky-700' : 'text-gray-600 hover:bg-gray-200'" @click="selectFolder('TRASH')">
          <span>Kosz</span>
        </a>
      </nav>
    </div>

    <div class="w-96 border-r border-gray-200 flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <input type="text" placeholder="Szukaj w poczcie..." class="w-full text-sm" />
      </div>
      <div class="flex-1 overflow-y-auto">
        <div
          v-for="email in emailsInCurrentFolder"
          :key="email.id"
          class="p-4 border-b border-gray-100 cursor-pointer"
          :class="selectedEmail?.id === email.id ? 'bg-sky-50' : 'hover:bg-gray-50'"
          @click="selectEmail(email)"
        >
          <div class="flex justify-between items-baseline">
            <p class="text-sm font-semibold text-gray-800 truncate" :class="!email.read ? 'font-bold' : ''">{{ email.fromName }}</p>
            <p class="text-xs text-gray-500">{{ new Date(email.date).toLocaleString() }}</p>
          </div>
          <p class="text-sm text-gray-700 truncate" :class="!email.read ? 'font-bold' : ''">{{ email.subject }}</p>
          <p class="text-xs text-gray-500 truncate mt-1">{{ email.body.replace(/<[^>]*>?/gm, '').substring(0, 80) }}...</p>
        </div>
        <p v-if="emailsInCurrentFolder.length === 0" class="p-6 text-center text-sm text-gray-500">Brak wiadomości w tym folderze.</p>
      </div>
    </div>

    <div class="flex-1 flex flex-col">
      <div v-if="selectedEmail" class="p-6 border-b border-gray-200">
        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ selectedEmail.subject }}</h3>
        <div class="flex items-center space-x-2 text-sm">
          <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center font-bold text-gray-600">{{ selectedEmail.fromName.charAt(0) }}</div>
          <div>
            <p class="font-semibold text-gray-800">{{ selectedEmail.fromName }} <span class="text-gray-500 font-normal">&lt;{{ selectedEmail.fromEmail }}&gt;</span></p>
            <p class="text-xs text-gray-500">{{ new Date(selectedEmail.date).toLocaleString() }}</p>
          </div>
        </div>
      </div>
      <div v-if="selectedEmail" class="flex-1 p-6 overflow-y-auto" v-html="selectedEmail.body"></div>
      <div v-if="selectedEmail" class="p-4 border-t border-gray-200 bg-gray-50 flex space-x-2">
        <button type="button" class="px-4 py-2 text-sm font-medium border border-gray-300 rounded bg-white hover:bg-gray-100">Odpowiedz</button>
        <button type="button" class="px-4 py-2 text-sm font-medium border border-gray-300 rounded bg-white hover:bg-gray-100">Prześlij dalej</button>
      </div>
      <div v-else class="flex-1 flex items-center justify-center text-gray-500">
        <div class="text-center">
          <svg class="w-16 h-16 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          <p class="mt-2">Wybierz wiadomość do przeczytania.</p>
        </div>
      </div>
    </div>

    <div v-if="safeCompose.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl h-auto max-h-[80vh] flex flex-col border border-gray-300">
        <div class="px-6 py-3 bg-slate-800 text-white rounded-t-lg flex justify-between items-center">
          <h3 class="font-bold text-sm">Nowa Wiadomość</h3>
          <button type="button" class="text-gray-300 hover:text-white" @click="closeCompose">✕</button>
        </div>
        <div class="p-6 flex flex-col flex-1 overflow-hidden">
          <div class="flex items-center border-b border-gray-200">
            <button type="button" class="px-3 py-2 text-sm font-bold bg-gray-100 border-r border-gray-200 text-gray-700 hover:bg-gray-200" @click="showAddressBook = true">Do:</button>
            <input v-model="composeData.to" type="text" name="to" placeholder="Adresat..." class="flex-1 border-0 p-2 text-sm focus:ring-0 focus:border-sky-500 !shadow-none" />
          </div>
          <input v-model="composeData.subject" type="text" name="subject" placeholder="Temat" class="border-0 border-b border-gray-200 p-2 text-sm font-semibold focus:ring-0 focus:border-sky-500 !shadow-none" />

          <div class="border border-b-0 border-gray-200 rounded-t-md mt-4 p-2 flex items-center space-x-2 bg-gray-50">
            <select class="w-32 text-sm !py-1 !px-2" @change="formatDoc('fontName', ($event.target as HTMLSelectElement).value)">
              <option value="Arial" selected>Arial</option>
              <option value="Calibri">Calibri</option>
              <option value="Times New Roman">Times New Roman</option>
              <option value="Verdana">Verdana</option>
              <option value="Courier New">Courier New</option>
            </select>
            <select class="w-20 text-sm !py-1 !px-2" @change="formatDoc('fontSize', ($event.target as HTMLSelectElement).value)">
              <option value="1">8 pt</option>
              <option value="2">10 pt</option>
              <option value="3" selected>12 pt</option>
              <option value="4">14 pt</option>
              <option value="5">18 pt</option>
              <option value="6">24 pt</option>
              <option value="7">36 pt</option>
            </select>
            <div class="w-px h-5 bg-gray-300"></div>
            <button type="button" class="font-bold w-8 h-8 hover:bg-gray-200 rounded text-gray-700" @click="formatDoc('bold')">B</button>
            <button type="button" class="italic w-8 h-8 hover:bg-gray-200 rounded text-gray-700" @click="formatDoc('italic')">I</button>
            <button type="button" class="underline w-8 h-8 hover:bg-gray-200 rounded text-gray-700" @click="formatDoc('underline')">U</button>
          </div>

          <div ref="editor" contenteditable="true" class="flex-1 w-full border border-gray-200 p-4 text-sm focus:ring-0 focus:outline-none focus:border-sky-500 overflow-y-auto min-h-[280px]"></div>
        </div>
        <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-end">
          <button type="button" class="bg-sky-600 text-white font-bold py-2 px-6 rounded hover:bg-sky-700 shadow-sm" @click="sendEmail">
            Wyślij teraz
          </button>
        </div>
      </div>
    </div>

    <div v-if="showAddressBook" class="fixed inset-0 z-[51] flex items-center justify-center p-4 bg-gray-900/30">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg h-auto max-h-[70vh] flex flex-col border">
        <div class="px-6 py-3 bg-gray-50 border-b flex justify-between items-center">
          <h3 class="font-bold text-gray-700">Książka Adresowa</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600" @click="showAddressBook = false">✕</button>
        </div>
        <div class="flex-1 p-2 overflow-y-auto">
          <div v-for="contact in addressBookContacts" :key="contact.email" class="p-3 hover:bg-sky-50 cursor-pointer rounded" @click="selectContact(contact.email)">
            <p class="font-semibold text-sm text-gray-800">{{ contact.name }}</p>
            <p class="text-xs text-gray-500">{{ contact.email }} - <span class="italic text-sky-700">{{ contact.source }}</span></p>
          </div>
          <p v-if="addressBookContacts.length === 0" class="p-8 text-center text-gray-400">Brak kontaktów.</p>
        </div>
      </div>
    </div>
  </div>
</template>
