import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: string
  type: ToastType
  message: string
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  const show = (type: ToastType, message: string, duration = 3000) => {
    const id = Math.random().toString(36).substring(2)
    const toast: Toast = { id, type, message }
    toasts.value = [toast, ...toasts.value]

    window.setTimeout(() => {
      remove(id)
    }, duration)
  }

  const success = (message: string) => show('success', message)
  const error = (message: string) => show('error', message)
  const info = (message: string) => show('info', message)
  const warning = (message: string) => show('warning', message)

  const remove = (id: string) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  return { toasts, show, success, error, info, warning, remove }
})
