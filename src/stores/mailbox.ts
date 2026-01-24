import { defineStore, storeToRefs } from 'pinia'
import { onScopeDispose, ref, watch } from 'vue'
import router from '@/router'
import { useDataStore } from '@/stores/data'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import type { User, Email } from '@/types/models'

export const useMailboxStore = defineStore('mailbox', () => {
  const auth = useAuthStore()
  const session = useSessionStore()
  const data = useDataStore()
  const notify = useNotificationStore()

  const { emails: localEmails, users } = storeToRefs(data)
  const emails = ref<Email[]>([])
  const composeState = ref<{ open: boolean; to?: string; subject?: string; body?: string }>({ open: false })
  const refreshIntervalMs = 30000
  let refreshTimer: number | null = null

  const mapApiEmail = (item: any): Email => ({
    id: String(item.id),
    fromName: item.from_name,
    fromEmail: item.from_email,
    toEmail: item.to_email,
    subject: item.subject,
    body: item.body,
    date: item.sent_at || item.created_at || new Date().toISOString(),
    read: Boolean(item.read_at),
    folder: item.folder,
  })

  const fetchEmails = async () => {
    if (!auth.enabled) {
      emails.value = Array.isArray(localEmails.value) ? localEmails.value : []
      return
    }
    const userId = session.currentUser?.id
    const { data: resp } = await api.get('/v1/crm-emails', {
      params: { owner_id: userId || undefined, per_page: 300 },
    })
    const list = Array.isArray(resp?.data) ? resp.data : Array.isArray(resp) ? resp : []
    emails.value = list.map(mapApiEmail)
  }

  const startPolling = () => {
    if (!auth.enabled || !auth.isAuthenticated || refreshTimer) return
    refreshTimer = window.setInterval(() => {
      if (auth.isAuthenticated) fetchEmails()
    }, refreshIntervalMs)
  }

  const stopPolling = () => {
    if (!refreshTimer) return
    window.clearInterval(refreshTimer)
    refreshTimer = null
  }

  const initiateEmailTo = (address: string) => {
    composeState.value = {
      open: true,
      to: address,
      subject: 'Oferta Współpracy - Stratton Prime',
      body: `<p>Szanowni Państwo,</p><p>Nawiązując do naszej rozmowy, przesyłam szczegóły propozycji optymalizacji kosztów pracowniczych.</p><p><br></p><p>Z poważaniem,</p><p>${users.value.find((u) => u.email === address)?.name || ''}</p>`,
    }
    router.push('/app/mailbox')
  }

  const sendEmail = (fromUser: User, toEmail: string, subject: string, body: string) => {
    if (auth.enabled) {
      return api.post('/v1/crm-emails', {
        sender_id: fromUser.id,
        from_name: fromUser.name,
        from_email: fromUser.email,
        to_email: toEmail,
        subject,
        body,
      }).then(fetchEmails)
    }

    const sentEmail: Email = {
      id: Math.random().toString(36).substr(2, 9),
      fromName: fromUser.name,
      fromEmail: fromUser.email,
      toEmail,
      subject,
      body,
      date: new Date().toISOString(),
      read: true,
      folder: 'SENT',
    }

    const recipientUser = users.value.find((user) => user.email === toEmail)
    const inboxEmail: Email = {
      ...sentEmail,
      id: Math.random().toString(36).substr(2, 9),
      folder: 'INBOX',
      read: false,
    }

    data.rawUpdateEmails((items) => [sentEmail, inboxEmail, ...items])

    if (recipientUser) {
      notify.add({
        userId: recipientUser.id,
        type: 'INFO',
        message: `Nowa wiadomość e-mail od ${fromUser.name}: "${subject}"`,
      })
    }

    data.logAction(fromUser.id, 'SEND_EMAIL', `Wysłano e-mail do ${toEmail}`, recipientUser?.id)
  }

  const markAsRead = (emailId: string) => {
    if (auth.enabled) {
      return api.patch(`/v1/crm-emails/${emailId}`, { read_at: new Date().toISOString() }).then(fetchEmails)
    }
    data.rawUpdateEmails((items) => items.map((email) => (email.id === emailId ? { ...email, read: true } : email)))
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && isAuthed) {
        fetchEmails()
        startPolling()
        return
      }
      if (auth.enabled && !isAuthed) {
        emails.value = []
        stopPolling()
      }
    },
    { immediate: true }
  )

  watch(
    () => localEmails.value,
    (list) => {
      if (auth.enabled) return
      emails.value = Array.isArray(list) ? list : []
    },
    { immediate: true }
  )

  watch(
    () => session.currentUser?.id,
    () => {
      if (auth.enabled && auth.isAuthenticated) fetchEmails()
    }
  )

  onScopeDispose(stopPolling)

  return { emails, composeState, initiateEmailTo, sendEmail, markAsRead, fetchEmails }
})
