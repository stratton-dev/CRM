import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import { useClientStore } from '@/stores/client'
import { isChunkLoadError, shouldReloadForChunkError } from './chunkReload'

// Widoki, które faktycznie czytają dane klienckie (clients/activities/offers).
// Tylko dla nich ładujemy ciężkie listy (leniwie, raz na sesję — cache w store).
// Lekkie widoki (knowledge-base, notifications, leaderboard, structure, leads,
// recruitment, admin-*) NIE pobierają tych danych — to była przyczyna zbędnych
// zapytań per_page=500 na każdym widoku.
const CLIENT_DATA_ROUTES = new Set([
  'dashboard', 'sales-start', 'sales-email-compose', 'analytics', 'clients',
  'offer-tool', 'sales-contract-preview', 'contract-preview', 'invoice-preview',
  'hr-panel', 'admin-analytics', 'autenti-panel', 'calendar', 'mailbox',
  'calculator', 'payroll', 'settings',
])

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue') },
  { path: '/app/dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { requiresAuth: true } },
  { path: '/app/sales/start', name: 'sales-start', component: () => import('@/views/sales/ProcessStartView.vue'), meta: { requiresAuth: true } },
  { path: '/app/leads', name: 'leads', component: () => import('@/views/sales/LeadsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/sales/email-compose', name: 'sales-email-compose', component: () => import('@/views/sales/EmailComposeView.vue'), meta: { requiresAuth: true } },
  { path: '/app/analytics', name: 'analytics', component: () => import('@/views/AnalyticsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/clients', name: 'clients', component: () => import('@/views/ClientsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/offer-tool/:clientId', name: 'offer-tool', component: () => import('@/views/sales/OfferToolView.vue'), meta: { requiresAuth: true } },
  { path: '/app/sales/contract-preview', name: 'sales-contract-preview', component: () => import('@/views/sales/ContractPreviewView.vue'), meta: { requiresAuth: true } },
  { path: '/app/contract-preview/:clientId', name: 'contract-preview', component: () => import('@/views/ContractPreviewView.vue'), meta: { requiresAuth: true } },
  { path: '/app/invoice-preview/:invoiceId', name: 'invoice-preview', component: () => import('@/views/hr/InvoicePreviewView.vue'), meta: { requiresAuth: true } },
  { path: '/app/structure', name: 'structure', component: () => import('@/views/StructureView.vue'), meta: { requiresAuth: true } },
  { path: '/app/hr-panel', name: 'hr-panel', component: () => import('@/views/hr/HrPanelView.vue'), meta: { requiresAuth: true } },
  { path: '/app/recruitment', name: 'recruitment', component: () => import('@/views/RecruitmentView.vue'), meta: { requiresAuth: true } },
  { path: '/app/news-management', name: 'news-management', component: () => import('@/views/admin/NewsManagementView.vue'), meta: { requiresAuth: true } },
  { path: '/app/users', name: 'user-management', component: () => import('@/views/admin/UsersManagementView.vue'), meta: { requiresAuth: true } },
  { path: '/app/admin-analytics', name: 'admin-analytics', component: () => import('@/views/admin/AdminAnalyticsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/admin-logs', name: 'admin-logs', component: () => import('@/views/admin/SystemLogsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/admin', name: 'admin', component: () => import('@/views/admin/AdminPanelView.vue'), meta: { requiresAuth: true } },
  { path: '/app/autenti-panel', name: 'autenti-panel', component: () => import('@/views/admin/AutentiPanelView.vue'), meta: { requiresAuth: true } },
  { path: '/app/commission-thresholds', name: 'commission-thresholds', component: () => import('@/views/admin/CommissionThresholdsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/commission-distributions', name: 'commission-distributions', component: () => import('@/views/admin/CommissionDistributionsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/settlements', name: 'settlements', component: () => import('@/views/SettlementsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/notifications', name: 'notifications', component: () => import('@/views/NotificationsView.vue'), meta: { requiresAuth: true } },
  { path: '/app/calendar', name: 'calendar', component: () => import('@/views/CalendarView.vue'), meta: { requiresAuth: true } },
  { path: '/app/mailbox', name: 'mailbox', component: () => import('@/views/MailboxView.vue'), meta: { requiresAuth: true, fullHeight: true } },
  { path: '/app/knowledge-base', name: 'knowledge-base', component: () => import('@/views/KnowledgeBaseView.vue'), meta: { requiresAuth: true } },
  { path: '/app/quick-calculator', name: 'quick-calculator', component: () => import('@/views/QuickCalculatorView.vue'), meta: { requiresAuth: true } },
  { path: '/app/calculator', name: 'calculator', component: () => import('@/views/CalculatorView.vue'), meta: { requiresAuth: true } },
  { path: '/app/payroll', name: 'payroll', component: () => import('@/views/PayrollView.vue'), meta: { requiresAuth: true } },
  { path: '/app/leaderboard', name: 'leaderboard', component: () => import('@/views/LeaderboardView.vue'), meta: { requiresAuth: true } },
  { path: '/app/settings', name: 'settings', component: () => import('@/views/SettingsView.vue'), meta: { requiresAuth: true } },
  // /app/meetings removed — clients view + activity panel replaces it
  { path: '/app/admin/knowledge-base', name: 'admin-knowledge-base', component: () => import('@/views/admin/KnowledgeBaseView.vue'), meta: { requiresAuth: true } },
  { path: '/app/leadowiec', redirect: '/app/clients' },
  { path: '/app/leadowiec/clients', redirect: '/app/clients' },
  { path: '/app/leadowiec/calendar', name: 'leadowiec-calendar', component: () => import('@/views/LeadowiecCalendarView.vue'), meta: { requiresAuth: true } },
  { path: '/app/leadowiec/settlements', name: 'leadowiec-settlements', component: () => import('@/views/LeadowiecSettlementsView.vue'), meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const session = useSessionStore()
  const viewPermissions = useViewPermissionsStore()
  if (!to.meta.requiresAuth) return true

  if (!auth.enabled) {
    return true
  }

  await auth.ensureInitialized()
  if (!auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (!session.currentUser) {
    await session.resolveUserFromAuth()
  }

  await viewPermissions.ensureLoaded()

  // Leniwe ładowanie danych klienckich tylko dla widoków, które ich używają
  // (non-blocking — nie opóźnia renderu; cache w store zapobiega ponownym fetchom).
  if (CLIENT_DATA_ROUTES.has(String(to.name || ''))) {
    void useClientStore().ensureClientData()
  }

  const role = session.currentUser?.role || auth.user?.roles?.find((r) => typeof r === 'string')

  if (String(to.name || '') === 'settings') {
    if (!viewPermissions.isSettingsAllowed(role)) {
      return { path: '/app/dashboard' }
    }
    return true
  }
  if (String(to.name || '') === 'admin-knowledge-base' && role === 'ADMIN') {
    return true
  }

  // LEADOWIEC: redirect to their equivalent page when accessing non-allowed routes
  const LEADOWIEC_ROUTE_MAP: Record<string, string> = {
    'calendar': '/app/leadowiec/calendar',
    'settlements': '/app/leadowiec/settlements',
  }
  if (String(role || '').toUpperCase() === 'LEADOWIEC' && !viewPermissions.isViewAllowed(String(to.name || ''), role)) {
    const redirect = LEADOWIEC_ROUTE_MAP[String(to.name || '')] ?? '/app/clients'
    return { path: redirect }
  }

  if (!viewPermissions.isViewAllowed(String(to.name || ''), role)) {
    return { path: '/app/dashboard' }
  }
  return true
})

// Stale lazy-route chunk after a new deploy (old hashed asset 404s) → the import
// rejects and the view goes blank. Recover with ONE full reload to pull the fresh
// index + chunk names. A sessionStorage timestamp guards against reload loops
// (and avoids the old approach's leftover `?reload=true` in the URL).
const CHUNK_RELOAD_KEY = 'chunk-reload-at'
router.onError((error, to) => {
  const message = (error as { message?: unknown })?.message
  const lastReloadAt = Number(sessionStorage.getItem(CHUNK_RELOAD_KEY)) || null
  if (shouldReloadForChunkError(message, Date.now(), lastReloadAt)) {
    sessionStorage.setItem(CHUNK_RELOAD_KEY, String(Date.now()))
    // href assignment (not .assign()) — navigation isn't implemented in jsdom and
    // .assign() throws there; the setter is a harmless no-op in tests.
    try {
      window.location.href = to.fullPath
    } catch {
      /* navigation unavailable (e.g. test env) — nothing to recover */
    }
  } else if (isChunkLoadError(message)) {
    console.error('Failed to load dynamic import even after reload', error)
  }
})

export default router
