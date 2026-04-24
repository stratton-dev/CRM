import { effectScope, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useNotificationStore } from '@/stores/notification'
import { useMailboxStore } from '@/stores/mailbox'
import { initEcho, disconnectEcho, updateEchoAuth } from '@/realtime/echo'

const userChannelTemplate = (import.meta.env.VITE_REVERB_USER_CHANNEL as string | undefined) || 'user.{id}'

let scope: ReturnType<typeof effectScope> | null = null
let currentChannel: ReturnType<ReturnType<typeof initEcho>['private']> | null = null
let refreshTimer: number | null = null

const buildUserChannelName = (userId: string) => userChannelTemplate.replace('{id}', userId)

const scheduleRefresh = (refresh: () => void) => {
  if (refreshTimer) window.clearTimeout(refreshTimer)
  refreshTimer = window.setTimeout(refresh, 250)
}

const unsubscribe = () => {
  if (!currentChannel) return
  currentChannel.stopListeningToAll()
  currentChannel = null
}

export const setupRealtime = () => {
  if (scope) return
  scope = effectScope()
  scope.run(() => {
    const auth = useAuthStore()
    const session = useSessionStore()
    const notifications = useNotificationStore()
    const mailbox = useMailboxStore()

    const refresh = () => {
      notifications.fetchNotifications()
      mailbox.fetchEmails()
    }

    watch(
      () => auth.token,
      () => updateEchoAuth()
    )

    watch(
      () => auth.isAuthenticated,
      (isAuthed) => {
        if (!auth.enabled || !isAuthed) {
          unsubscribe()
          disconnectEcho()
          return
        }
        initEcho()
      },
      { immediate: true }
    )

    watch(
      () => session.currentUser?.id,
      (userId) => {
        if (!auth.enabled || !auth.isAuthenticated) return
        if (!userId) return
        const echo = initEcho()
        const channelName = buildUserChannelName(userId)
        unsubscribe()
        currentChannel = echo.private(channelName)
        currentChannel.listenToAll((eventName: string) => {
          if (typeof eventName === 'string' && eventName.startsWith('pusher:')) return
          scheduleRefresh(refresh)
        })
      },
      { immediate: true }
    )
  })
}
