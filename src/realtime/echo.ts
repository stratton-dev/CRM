import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { apiBaseUrl } from '@/api/client'

type EchoConfig = {
  key: string
  host: string
  scheme: 'http' | 'https'
  wsPort: number
  wssPort: number
  forceTLS: boolean
}

let echo: Echo<any> | null = null

const resolveConfig = (): EchoConfig => {
  const schemeEnv = (import.meta.env.VITE_REVERB_SCHEME as string | undefined)
  const scheme = (schemeEnv || (window.location.protocol === 'https:' ? 'https' : 'http')) as 'http' | 'https'
  const host = (import.meta.env.VITE_REVERB_HOST as string | undefined) || window.location.hostname
  const key = (import.meta.env.VITE_REVERB_APP_KEY as string | undefined) || 'local'
  const wsPort = Number((import.meta.env.VITE_REVERB_WS_PORT as string | undefined) || (scheme === 'https' ? 443 : 80))
  const wssPort = Number((import.meta.env.VITE_REVERB_WSS_PORT as string | undefined) || wsPort)
  return {
    key,
    host,
    scheme,
    wsPort,
    wssPort,
    forceTLS: scheme === 'https',
  }
}

const getAuthHeaders = (): Record<string, string> => {
  const token = localStorage.getItem('crm_token')
  return token ? { Authorization: `Bearer ${token}` } : {}
}

export const initEcho = (): Echo<any> => {
  if (echo) return echo
  const config = resolveConfig()
  const transports = config.forceTLS ? ['wss'] : ['ws']
  ;(Pusher as any).logToConsole = false
  ;(window as any).Pusher = Pusher
  echo = new Echo({
    broadcaster: 'reverb',
    key: config.key,
    wsHost: config.host,
    wsPort: config.wsPort,
    wssPort: config.wssPort,
    forceTLS: config.forceTLS,
    enabledTransports: transports,
    disableStats: true,
    authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
    auth: {
      headers: getAuthHeaders(),
    },
  })
  ;(window as any).Echo = echo
  return echo
}

export const updateEchoAuth = () => {
  if (!echo) return
  echo.connector.options.auth = echo.connector.options.auth || {}
  echo.connector.options.auth.headers = getAuthHeaders()
}

export const disconnectEcho = () => {
  if (!echo) return
  echo.disconnect()
  echo = null
}

export const getEcho = () => echo
