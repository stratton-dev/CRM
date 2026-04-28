import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useViewPermissionsStore } from '@/stores/viewPermissions'

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
  { path: '/app/admin-invoices', name: 'admin-invoices', component: () => import('@/views/admin/AdminInvoicesView.vue'), meta: { requiresAuth: true } },
  { path: '/app/autenti-panel', name: 'autenti-panel', component: () => import('@/views/admin/AutentiPanelView.vue'), meta: { requiresAuth: true } },
  { path: '/app/commission-thresholds', name: 'commission-thresholds', component: () => import('@/views/admin/CommissionThresholdsView.vue'), meta: { requiresAuth: true } },
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
  { path: '/app/meetings', name: 'meetings', component: () => import('@/views/MeetingsManagementView.vue'), meta: { requiresAuth: true } },
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
  await viewPermissions.ensureLoaded()
  const role = session.currentUser?.role || auth.user?.roles?.find((r) => typeof r === 'string')

  if (String(to.name || '') === 'settings') {
    if (!viewPermissions.isSettingsAllowed(role)) {
      return { path: '/app/dashboard' }
    }
    return true
  }
  if (!viewPermissions.isViewAllowed(String(to.name || ''), role)) {
    return { path: '/app/dashboard' }
  }
  return true
})

router.onError((error, to) => {
  if (
    error.message.includes('Failed to fetch dynamically imported module') ||
    error.message.includes('Importing a module script failed')
  ) {
    if (!to.query?.reload) {
      // Force reload the page if the chunk fails to load
      // Append reload query parameter to avoid infinite loops
      window.location.href = to.fullPath + (to.fullPath.includes('?') ? '&' : '?') + 'reload=true'
    } else {
      console.error('Failed to load dynamic import even after reload', error)
    }
  }
})

export default router
