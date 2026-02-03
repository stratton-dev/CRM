import axios, { AxiosError, AxiosRequestConfig } from 'axios'

const envNumber = (value: unknown, fallback: number): number => {
  if (typeof value === 'string') {
    const parsed = Number(value)
    if (Number.isFinite(parsed)) return parsed
  }
  return fallback
}

export const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL as string | undefined) || 'http://localhost:8000/api'
const apiTimeoutMs = envNumber(import.meta.env.VITE_API_TIMEOUT_MS, 15000)
const apiRetryMax = envNumber(import.meta.env.VITE_API_RETRY_MAX, 2)
const apiRetryDelayMs = envNumber(import.meta.env.VITE_API_RETRY_DELAY_MS, 300)

export const api = axios.create({
  baseURL: apiBaseUrl,
  timeout: apiTimeoutMs,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Attach token from localStorage
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('crm_token')
  if (token) {
    config.headers = config.headers || {}
    config.headers.Authorization = `Bearer ${token}`
  }
  
  const impersonateId = localStorage.getItem('x_impersonate_user')
  if (impersonateId) {
    config.headers = config.headers || {}
    config.headers['X-Impersonate-User'] = impersonateId
  }
  
  return config
})

// Handle 401 globally
api.interceptors.response.use(
  (resp) => resp,
  (error: AxiosError) => {
    if (error?.response?.status === 401) {
      localStorage.removeItem('crm_token')
      // optional: redirect to login if router available
    }

    const config = error.config as (AxiosRequestConfig & { __retryCount?: number }) | undefined
    if (!config || apiRetryMax <= 0) {
      return Promise.reject(error)
    }

    const method = (config.method || 'get').toLowerCase()
    const idempotent = method === 'get' || method === 'head' || method === 'options'
    const status = error.response?.status
    const isTimeout = error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT'
    const isNetworkError = !error.response && error.code === 'ERR_NETWORK'
    const isRetryableStatus = status === 408 || status === 429 || status === 500 || status === 502 || status === 503 || status === 504

    if (!idempotent || !(isTimeout || isNetworkError || isRetryableStatus)) {
      return Promise.reject(error)
    }

    config.__retryCount = (config.__retryCount || 0) + 1
    if (config.__retryCount > apiRetryMax) {
      return Promise.reject(error)
    }

    const backoff = apiRetryDelayMs * Math.pow(2, config.__retryCount - 1)
    const jitter = Math.floor(Math.random() * 100)
    const delay = backoff + jitter

    return new Promise((resolve) => setTimeout(resolve, delay)).then(() => api.request(config))
  }
)

export default api
