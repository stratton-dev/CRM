import { defineStore, storeToRefs } from 'pinia'
import { computed, onScopeDispose, ref, watch } from 'vue'
import router from '@/router'
import { useDataStore } from '@/stores/data'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import type { User, Email } from '@/types/models'

type MailSettings = {
  from_name?: string | null
  from_email?: string | null
  imap_host?: string | null
  imap_port?: number | null
  imap_secure?: boolean | null
  imap_username?: string | null
  imap_inbox_folder?: string | null
  imap_sent_folder?: string | null
  imap_trash_folder?: string | null
  smtp_host?: string | null
  smtp_port?: number | null
  smtp_secure?: boolean | null
  smtp_username?: string | null
  imap_password_set?: boolean
  smtp_password_set?: boolean
}

export const useMailboxStore = defineStore('mailbox', () => {
  const auth = useAuthStore()
  const session = useSessionStore()
  const data = useDataStore()
  const notify = useNotificationStore()

  const { emails: localEmails, users } = storeToRefs(data)
  const emails = ref<Email[]>([])
  const composeState = ref<{ open: boolean; to?: string; subject?: string; body?: string }>({ open: false })
  const mailSettings = ref<MailSettings | null>(null)
  const mailSettingsLoaded = ref(false)
  const refreshIntervalMsRaw = import.meta.env.VITE_MAIL_POLL_MS
  const refreshIntervalMs = typeof refreshIntervalMsRaw === 'string' ? Number(refreshIntervalMsRaw) : 0
  let refreshTimer: number | null = null

  const hasMailConfig = computed(() => !!mailSettings.value?.imap_host && !!mailSettings.value?.imap_username && !!mailSettings.value?.smtp_host)
  const mailMode = computed(() => {
    if (!auth.enabled) return 'local'
    return hasMailConfig.value ? 'imap' : 'internal'
  })

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

  const mapMailboxEmail = (item: any): Email => ({
    id: String(item.id),
    fromName: item.fromName || item.from_name || '',
    fromEmail: item.fromEmail || item.from_email || '',
    toEmail: item.toEmail || item.to_email || '',
    subject: item.subject || '(bez tematu)',
    body: item.body || '',
    attachments: Array.isArray(item.attachments) ? item.attachments : [],
    date: item.date || item.sent_at || item.created_at || new Date().toISOString(),
    read: Boolean(item.read),
    folder: item.folder,
  })

  const fetchMailSettings = async () => {
    if (!auth.enabled) {
      mailSettings.value = null
      mailSettingsLoaded.value = true
      return
    }
    try {
      const { data: resp } = await api.get('/v1/crm-mail-settings')
      const payload = resp?.data ?? resp ?? null
      mailSettings.value = payload
    } catch {
      mailSettings.value = null
    } finally {
      mailSettingsLoaded.value = true
    }
  }

  const fetchEmails = async (folders?: Array<'INBOX' | 'SENT' | 'TRASH'>) => {
    if (!auth.enabled) {
      emails.value = Array.isArray(localEmails.value) ? localEmails.value : []
      return
    }
    if (!mailSettingsLoaded.value) await fetchMailSettings()

    if (hasMailConfig.value) {
      try {
        const targetFolders = folders && folders.length ? folders : ['INBOX']
        const list: any[] = []
        for (const folder of targetFolders) {
          const response = await api.get('/v1/crm-mailbox/messages', {
            params: { folder, limit: 50 },
          })
          const payload = response?.data?.data ?? response?.data ?? []
          if (Array.isArray(payload)) list.push(...payload)
        }
        emails.value = list.map(mapMailboxEmail)
        return
      } catch (error: any) {
        const message = error?.response?.data?.message || error?.message || 'Nie udało się połączyć z pocztą.'
        notify.add({ type: 'ERROR', message })
        return
      }
    }

    const userId = session.currentUser?.id
    try {
      const { data: resp } = await api.get('/v1/crm-emails', {
        params: { owner_id: userId || undefined, per_page: 300 },
      })
      const list = Array.isArray(resp?.data) ? resp.data : Array.isArray(resp) ? resp : []
      emails.value = list.map(mapApiEmail)
    } catch (error: any) {
      const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać wiadomości.'
      notify.add({ type: 'ERROR', message })
      return
    }
  }

  const startPolling = () => {
    if (!auth.enabled || !auth.isAuthenticated || refreshTimer) return
    if (!refreshIntervalMs || refreshIntervalMs <= 0) return
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

  const sendEmail = async (
    fromUser: User,
    toEmail: string,
    subject: string,
    body: string,
    attachments?: Array<{ filename: string; content?: string; content_type?: string; encoding?: string; html?: string; convert_to_pdf?: boolean }>
  ) => {
    if (auth.enabled) {
      if (hasMailConfig.value) {
        if (!mailSettingsLoaded.value) await fetchMailSettings()
        const senderName = mailSettings.value?.from_name?.trim() || undefined
        const senderEmail = mailSettings.value?.from_email?.trim() || undefined
        return api.post('/v1/crm-mailbox/send', {
          to: toEmail,
          subject,
          body,
          from_name: senderName,
          from_email: senderEmail,
          attachments: attachments && attachments.length ? attachments : undefined,
        }).then(fetchEmails).catch((error) => {
          const message = error?.response?.data?.message || error?.message || 'Nie udało się wysłać wiadomości.'
          notify.add({ type: 'ERROR', message })
          throw error
        })
      }

      return api.post('/v1/crm-emails', {
        sender_id: fromUser.id,
        from_name: fromUser.name,
        from_email: fromUser.email,
        to_email: toEmail,
        subject,
        body,
      }).then(fetchEmails).catch((error) => {
        const message = error?.response?.data?.message || error?.message || 'Nie udało się wysłać wiadomości.'
        notify.add({ type: 'ERROR', message })
        throw error
      })
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
      if (hasMailConfig.value) {
        return api.patch(`/v1/crm-mailbox/messages/${emailId}`, { read: true }).then(fetchEmails).catch((error) => {
          const message = error?.response?.data?.message || error?.message || 'Nie udało się oznaczyć wiadomości.'
          notify.add({ type: 'ERROR', message })
          return
        })
      }
      return api.patch(`/v1/crm-emails/${emailId}`, { read_at: new Date().toISOString() }).then(fetchEmails).catch((error) => {
        const message = error?.response?.data?.message || error?.message || 'Nie udało się oznaczyć wiadomości.'
        notify.add({ type: 'ERROR', message })
        return
      })
    }
    data.rawUpdateEmails((items) => items.map((email) => (email.id === emailId ? { ...email, read: true } : email)))
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && !isAuthed) {
        emails.value = []
        mailSettings.value = null
        mailSettingsLoaded.value = false
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

  // Mailbox data is fetched explicitly by views (Mailbox / Settings Mail tab).

  onScopeDispose(stopPolling)

  return {
    emails,
    composeState,
    mailMode,
    mailSettings,
    mailSettingsLoaded,
    initiateEmailTo,
    sendEmail,
    markAsRead,
    fetchEmails,
    fetchEmailsForFolder: (folder: 'INBOX' | 'SENT' | 'TRASH') => fetchEmails([folder]),
    fetchMailSettings,
    startPolling,
    stopPolling,
  }
})
