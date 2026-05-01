import { api } from '@/api/client'

/**
 * Manages Web Push subscription lifecycle:
 * 1. Registers the service worker (sw.js)
 * 2. Requests notification permission
 * 3. Fetches VAPID public key from backend
 * 4. Creates / refreshes the push subscription
 * 5. Registers the subscription with the backend (POST /v1/push-tokens)
 * 6. Listens for SW messages (navigation on notification click)
 */
export class WebPushService {
  private static instance: WebPushService | null = null
  private registration: ServiceWorkerRegistration | null = null

  static getInstance(): WebPushService {
    if (!WebPushService.instance) {
      WebPushService.instance = new WebPushService()
    }
    return WebPushService.instance
  }

  get isSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  }

  get permissionState(): NotificationPermission {
    return Notification.permission
  }

  async init(): Promise<void> {
    if (!this.isSupported) return

    // Register SW
    try {
      this.registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
    } catch (err) {
      console.warn('[Push] SW registration failed:', err)
      return
    }

    // Listen for navigation messages from SW (notification click)
    navigator.serviceWorker.addEventListener('message', (event) => {
      if (event.data?.type === 'SW_NAVIGATE') {
        window.location.href = event.data.url
      }
    })

    // If already granted, subscribe silently
    if (Notification.permission === 'granted') {
      await this.subscribe()
    }
  }

  async requestPermissionAndSubscribe(): Promise<boolean> {
    if (!this.isSupported) return false

    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return false

    return this.subscribe()
  }

  async subscribe(): Promise<boolean> {
    if (!this.registration) return false

    try {
      // Fetch VAPID public key
      const { data } = await api.get('/v1/push-tokens/vapid-public-key')
      const vapidKey = data?.publicKey
      if (!vapidKey) return false

      const subscription = await this.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(vapidKey),
      })

      const sub = subscription.toJSON()

      // Register with backend
      await api.post('/v1/push-tokens', {
        type:         'web',
        web_endpoint: sub.endpoint,
        web_p256dh:   sub.keys?.p256dh,
        web_auth:     sub.keys?.auth,
      })

      return true
    } catch (err) {
      console.warn('[Push] Subscribe failed:', err)
      return false
    }
  }

  async unsubscribe(): Promise<void> {
    if (!this.registration) return

    try {
      const subscription = await this.registration.pushManager.getSubscription()
      if (!subscription) return

      await api.delete('/v1/push-tokens', {
        data: { web_endpoint: subscription.endpoint },
      })

      await subscription.unsubscribe()
    } catch (err) {
      console.warn('[Push] Unsubscribe failed:', err)
    }
  }

  private urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = window.atob(base64)
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)))
  }
}

export const webPush = WebPushService.getInstance()
