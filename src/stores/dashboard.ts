import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

type DashboardEvent = { id: number | string; title: string; start_at: string }
type DashboardNews = { id: number | string; tag?: string | null; title: string; description: string }
type DashboardKpi = { id: number | string; title: string; value: string; score: number; min_target?: string | null; subtitle?: string | null; missing?: string | null }
type DashboardCalculation = {
  id: number | string
  company: string
  nip?: string | null
  meeting_id?: string | null
  calculation_date?: string | null
  valid_until?: string | null
  status?: string | null
}

const fallbackEvents: DashboardEvent[] = [
  { id: 1, title: 'Spotkanie: TechSolutions Ltd', start_at: new Date().toISOString() },
]
const fallbackNews: DashboardNews[] = [
  { id: 1, tag: 'PRODUKT', title: 'Nowa oferta "Eliton Secure+"', description: 'Dostępna od 1 lutego. Zobacz webinar szkoleniowy w sekcji Edukacja.' },
]
const fallbackKpis: DashboardKpi[] = [
  { id: 1, title: 'Prowizja (Bieżący m-c)', value: '4 500 PLN', score: 45, min_target: '10 000 PLN', subtitle: 'Początek miesiąca', missing: '5 500' },
]
const fallbackCalculations: DashboardCalculation[] = [
  { id: 1, company: 'MegaBud S.A.', nip: '555-666-77-88', meeting_id: 'M-2044/01', calculation_date: new Date().toISOString(), valid_until: new Date().toISOString(), status: 'OFERTA' },
]

export const useDashboardStore = defineStore('dashboard', () => {
  const auth = useAuthStore()
  const events = ref<DashboardEvent[]>([])
  const news = ref<DashboardNews[]>([])
  const kpis = ref<DashboardKpi[]>([])
  const calculations = ref<DashboardCalculation[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const hydrateFallback = () => {
    events.value = [...fallbackEvents]
    news.value = [...fallbackNews]
    kpis.value = [...fallbackKpis]
    calculations.value = [...fallbackCalculations]
  }

  const fetchDashboard = async (userId?: string | null) => {
    if (!auth.enabled) {
      hydrateFallback()
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/v1/crm-dashboard', {
        params: userId ? { user_id: userId } : undefined,
      })
      events.value = Array.isArray(data?.events) ? data.events : []
      news.value = Array.isArray(data?.news) ? data.news : []
      kpis.value = Array.isArray(data?.kpis) ? data.kpis : []
      calculations.value = Array.isArray(data?.calculations) ? data.calculations : []
    } catch (err: any) {
      error.value = err?.message || 'Nie udało się pobrać danych pulpitu.'
    } finally {
      loading.value = false
    }
  }

  return {
    events,
    news,
    kpis,
    calculations,
    loading,
    error,
    fetchDashboard,
  }
})
