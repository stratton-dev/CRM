import { defineStore } from 'pinia'
import { onScopeDispose, ref, watch } from 'vue'
import { useDataStore } from '@/stores/data'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import type { Notification } from '@/types/models'

type ApiNotification = {
  id: number | string
  user_id: number | string
  type: string
  title: string
  body: string
  read_at?: string | null
  created_at?: string
  user?: { id: number | string; supabase_id?: string | null }
}

export const useNotificationStore = defineStore('notification', () => {
  const auth = useAuthStore()
  const session = useSessionStore()
  const data = useDataStore()
  const notifications = ref<Notification[]>([])
  const sentNotifications = ref<Notification[]>([])
  const refreshIntervalMs = 30000
  let refreshTimer: number | null = null

  const mapApiNotification = (item: ApiNotification): Notification => ({
    id: String(item.id),
    userId: String(item.user?.supabase_id || item.user_id),
    type: item.type as Notification['type'],
    message: item.body || item.title,
    date: item.created_at || new Date().toISOString(),
    read: Boolean(item.read_at),
  })

  const fetchNotifications = async () => {
    if (!auth.enabled) {
      notifications.value = Array.isArray(data.notifications) ? data.notifications : []
      return
    }
    const userId = session.currentUser?.id
    try {
      const { data: resp } = await api.get('/v1/notifications', {
        timeout: 30000,
        params: {
          per_page: 25,
          user_supabase_id: userId || undefined,
        },
      })
      const list = Array.isArray(resp?.data) ? resp.data : Array.isArray(resp) ? resp : []
      notifications.value = list.map(mapApiNotification)
    } catch {
      // Silent fail — will retry on next polling cycle
    }
  }

  const fetchSentNotifications = async () => {
    if (!auth.enabled) return
    const userId = session.currentUser?.id
    try {
      const { data: resp } = await api.get('/v1/notifications/sent', {
         params: { user_supabase_id: userId }
      })
      const list = Array.isArray(resp?.data) ? resp.data : Array.isArray(resp) ? resp : []
      sentNotifications.value = list.map(mapApiNotification)
    } catch (e) {
      console.error('Failed to fetch sent notifications', e)
      sentNotifications.value = []
    }
  }

  const sendBatch = async (recipients: string[], type: string, message: string) => {
    if (auth.enabled) {
      return api.post('/v1/notifications/batch', {
        recipients, 
        type,
        title: message,
        body: message,
      })
    }
    // Mock implementation for local dev
    recipients.forEach(userId => {
        const newNotif = {
            userId,
            type: type as any,
            message,
        }
        add(newNotif)
    })
    
    // Mock adding to sent list
    const newSent: Notification = {
        id: Math.random().toString(36).substr(2, 9),
        userId: recipients.length > 5 ? `${recipients.length} recipients` : recipients.join(', '), 
        type: type as any,
        message,
        date: new Date().toISOString(),
        read: false
    }
    sentNotifications.value.unshift(newSent)
    return Promise.resolve()
  }

  const startPolling = () => {
    if (!auth.enabled || !auth.isAuthenticated || refreshTimer) return
    refreshTimer = window.setInterval(() => {
      if (auth.isAuthenticated) fetchNotifications()
    }, refreshIntervalMs)
  }

  const stopPolling = () => {
    if (!refreshTimer) return
    window.clearInterval(refreshTimer)
    refreshTimer = null
  }

  const add = (notification: Omit<Notification, 'id' | 'date' | 'read'>) => {
    if (auth.enabled) {
      return api.post('/v1/notifications', {
        user_supabase_id: notification.userId,
        type: notification.type,
        title: notification.message,
        body: notification.message,
      })
    }
    const newNotification: Notification = {
      ...notification,
      id: Math.random().toString(36).substr(2, 9),
      date: new Date().toISOString(),
      read: false,
    }
    data.rawAddNotification(newNotification)
  }

  const markAsRead = async (id: string) => {
    // Optimistically update
    notifications.value = notifications.value.map(n => 
        n.id == id ? { ...n, read: true } : n
    )

    if (auth.enabled) {
      try {
        await api.put(`/v1/notifications/${id}`, { 
            read_at: new Date().toISOString()
        })
      } catch (e) {
        console.error('Failed to mark as read', e)
        // Revert on failure? Usually not worth the complexity for read status
      }
    } else {
        data.rawUpdateNotifications((items) => items.map((notif) => (notif.id === id ? { ...notif, read: true } : notif)))
    }
  }

  const markAllAsRead = async (userId: string) => {
    // Optimistically update
    notifications.value = notifications.value.map(n => 
        n.userId === userId ? { ...n, read: true } : n
    )
    
    if (auth.enabled) {
      try {
        await api.post('/v1/notifications/mark-all-read', { user_supabase_id: userId })
      } catch (e) {
          console.error("Failed to mark all as read", e)
      }
    } else {
        data.rawUpdateNotifications((items) => items.map((notif) => (notif.userId === userId ? { ...notif, read: true } : notif)))
    }
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && isAuthed) {
        fetchNotifications()
        startPolling()
        return
      }
      if (auth.enabled && !isAuthed) {
        notifications.value = []
        stopPolling()
      }
    },
    { immediate: true }
  )

  watch(
    () => session.currentUser?.id,
    () => {
      if (auth.enabled && auth.isAuthenticated) fetchNotifications()
    }
  )

  onScopeDispose(stopPolling)

  return { notifications, sentNotifications, add, sendBatch, markAsRead, markAllAsRead, fetchNotifications, fetchSentNotifications }
})
