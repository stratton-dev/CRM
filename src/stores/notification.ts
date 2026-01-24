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
  user?: { id: number | string; keycloak_id?: string | null }
}

export const useNotificationStore = defineStore('notification', () => {
  const auth = useAuthStore()
  const session = useSessionStore()
  const data = useDataStore()
  const notifications = ref<Notification[]>([])
  const refreshIntervalMs = 30000
  let refreshTimer: number | null = null

  const mapApiNotification = (item: ApiNotification): Notification => ({
    id: String(item.id),
    userId: String(item.user?.keycloak_id || item.user_id),
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
    const { data: resp } = await api.get('/v1/notifications', {
      params: {
        per_page: 200,
        user_keycloak_id: userId || undefined,
      },
    })
    const list = Array.isArray(resp?.data) ? resp.data : Array.isArray(resp) ? resp : []
    notifications.value = list.map(mapApiNotification)
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
        user_keycloak_id: notification.userId,
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

  const markAsRead = (id: string) => {
    if (auth.enabled) {
      return api.patch(`/v1/notifications/${id}`, { read_at: new Date().toISOString() }).then(fetchNotifications)
    }
    data.rawUpdateNotifications((items) => items.map((notif) => (notif.id === id ? { ...notif, read: true } : notif)))
  }

  const markAllAsRead = (userId: string) => {
    if (auth.enabled) {
      return api.post('/v1/notifications/mark-all-read', { user_keycloak_id: userId }).then(fetchNotifications)
    }
    data.rawUpdateNotifications((items) => items.map((notif) => (notif.userId === userId ? { ...notif, read: true } : notif)))
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

  return { notifications, add, markAsRead, markAllAsRead, fetchNotifications }
})
