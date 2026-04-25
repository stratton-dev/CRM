import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useDataStore } from '@/stores/data'
import type { AuditLog } from '@/types/models'

type ApiAuditLog = {
  id: number | string
  actor_user_id: number | string
  action: string
  target_id?: string | null
  details: string
  created_at?: string
  actor?: { id: number | string; supabase_id?: string | null }
}

export const useAuditLogStore = defineStore('audit-log', () => {
  const auth = useAuthStore()
  const dataStore = useDataStore()
  const logs = ref<AuditLog[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const mapApiLog = (log: ApiAuditLog): AuditLog => ({
    id: String(log.id),
    actorId: String(log.actor?.supabase_id || log.actor_user_id),
    action: log.action,
    targetId: log.target_id || undefined,
    details: log.details,
    date: log.created_at || new Date().toISOString(),
  })

  const fetchLogs = async () => {
    if (!auth.enabled) {
      logs.value = Array.isArray(dataStore.auditLogs) ? dataStore.auditLogs : []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/v1/crm-audit-logs', { params: { per_page: 200 } })
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      logs.value = list.map(mapApiLog)
    } catch (err: any) {
      error.value = err?.message || 'Nie udało się pobrać logów.'
    } finally {
      loading.value = false
    }
  }

  return { logs, loading, error, fetchLogs }
})
