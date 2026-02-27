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
  client_id?: string | null
  calculation_date?: string | null
  valid_until?: string | null
  status?: string | null
}
type DashboardOverdueInvoice = {
  id: number | string
  number: string
  company: string
  amount_gross: number
  issue_date?: string | null
  due_date?: string | null
  days_overdue?: number | null
  status?: string | null
}

const fallbackEvents: DashboardEvent[] = [
  { id: 1, title: 'Spotkanie: TechSolutions Ltd', start_at: new Date().toISOString() },
]
const fallbackNews: DashboardNews[] = [
  { id: 1, tag: 'PRODUKT', title: 'Nowa oferta "Eliton Secure+"', description: 'Dostępna od 1 lutego. Zobacz webinar szkoleniowy w sekcji Edukacja.' },
]
const fallbackKpis: DashboardKpi[] = [
  { id: 1, title: 'Jednostki rozliczeniowe', value: '4 500', score: 45, min_target: '10 000', subtitle: 'Początek miesiąca', missing: '5 500' },
  { id: 2, title: 'Kalkulacje wysłane', value: '12', score: 60, min_target: '20', subtitle: 'Status READY / SENT', missing: '8' },
  { id: 3, title: 'Nowe spotkania', value: '15', score: 50, min_target: '30', subtitle: 'Status NEW', missing: '15' },
  { id: 4, title: 'Wskaźnik utrzymania umów', value: '75%', score: 75, min_target: '80%', subtitle: 'Konwersja', missing: '5%' },
  { id: 5, title: 'Oszczędności (ZUS)', value: '12 450 PLN', score: 85, min_target: '15 000 PLN', subtitle: 'Bieżący miesiąc', missing: '2 550 PLN' },
]
const fallbackCalculations: DashboardCalculation[] = [
  { id: 1, company: 'MegaBud S.A.', nip: '555-666-77-88', meeting_id: 'M-2044/01', calculation_date: new Date().toISOString(), valid_until: new Date().toISOString(), status: 'OFERTA' },
]
const fallbackOverdue: DashboardOverdueInvoice[] = []

export const useDashboardStore = defineStore('dashboard', () => {
  const auth = useAuthStore()
  const events = ref<DashboardEvent[]>([])
  const news = ref<DashboardNews[]>([])
  const kpis = ref<DashboardKpi[]>([])
  const calculations = ref<DashboardCalculation[]>([])
  const overdueInvoices = ref<DashboardOverdueInvoice[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const hydrateFallback = () => {
    events.value = [...fallbackEvents]
    news.value = [...fallbackNews]
    kpis.value = [...fallbackKpis]
    calculations.value = [...fallbackCalculations]
    overdueInvoices.value = [...fallbackOverdue]
  }

  const fetchDashboard = async (userId?: string | null, params?: { from_date?: string; to_date?: string; view_scope?: string }) => {
    if (!auth.enabled) {
      hydrateFallback()
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/v1/crm-dashboard', {
        params: {
          ...(userId ? { user_id: userId } : {}),
          ...(params || {}),
        },
      })
      events.value = Array.isArray(data?.events) ? data.events : []
      news.value = Array.isArray(data?.news) ? data.news : []
      kpis.value = Array.isArray(data?.kpis) ? data.kpis : []
      calculations.value = Array.isArray(data?.calculations) ? data.calculations : []
      overdueInvoices.value = Array.isArray(data?.overdue_invoices) ? data.overdue_invoices : []
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
    overdueInvoices,
    loading,
    error,
    fetchDashboard,
  }
})
