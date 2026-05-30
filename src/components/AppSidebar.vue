<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useViewPermissionsStore } from '@/stores/viewPermissions'
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'toggle'): void
}>()

const isHovered = ref(false)
// Desktop: always icon-strip, expands only on hover (overlays content)
// Mobile: isOpen controls full-width drawer
const expanded = computed(() => isHovered.value || props.isOpen)

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const session = useSessionStore()
const viewPermissions = useViewPermissionsStore()

const { currentUser } = storeToRefs(session)

type NavLink = { label: string; path: string; icon: string; viewKey: string }
type NavGroup = { title: string; links: NavLink[] }

// Close sidebar on mobile after navigating (desktop keeps sidebar open)
watch(() => route.path, () => {
  if (props.isOpen && window.innerWidth < 1024) emit('toggle')
})

const authRole = computed(() => {
  const roles = auth.user?.roles || []
  const allowed = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'CLIENT_HR', 'LEADOWIEC']
  return roles.find((role) => allowed.includes(String(role).toUpperCase()))
})

const sidebarUser = computed(() => {
  const user = currentUser.value
  if (user) return { name: user.name, role: user.role, crmNumber: user.crmNumber }
  return {
    name: auth.fullName || auth.user?.username || auth.user?.email || '',
    role: authRole.value,
    crmNumber: undefined,
  }
})

const sidebarInitials = computed(() => {
  const name = sidebarUser.value.name?.trim() || ''
  if (!name) return '--'
  const parts = name.split(/\s+/).filter(Boolean)
  const initials = parts.length >= 2 ? parts[0][0] + parts[1][0] : name.slice(0, 2)
  return initials.toUpperCase()
})

const isAdmin = computed(() => {
  const fallbackRole = !auth.enabled ? (localStorage.getItem('stratton_dev_role') || undefined) : undefined
  const role = currentUser.value?.role || authRole.value || fallbackRole
  return String(role || '').toUpperCase() === 'ADMIN'
})

const navLinks = computed((): NavLink[] => {
  const fallbackRole = !auth.enabled ? (localStorage.getItem('stratton_dev_role') || undefined) : undefined
  const rawRole = currentUser.value?.role || authRole.value || fallbackRole
  const role = String(rawRole || '').toUpperCase()
  const links: Array<{ label: string; path: string; icon: string; viewKey: string }> = []

  if (role !== 'LEADOWIEC') {
    links.push({ label: 'Główny Pulpit', path: '/app/dashboard', icon: 'dashboard', viewKey: 'dashboard' })
  }

  if (role === 'ADMIN') {
    links.push(
      { label: 'Użytkownicy', path: '/app/users', icon: 'user-tie', viewKey: 'user-management' },
      { label: 'Analityka Finansowa', path: '/app/admin-analytics', icon: 'presentation-chart-line', viewKey: 'admin-analytics' },
      { label: 'Logi Systemowe', path: '/app/admin-logs', icon: 'shield-check', viewKey: 'admin-logs' },
      { label: 'Analityka (Sprzedaż)', path: '/app/analytics', icon: 'chart-line', viewKey: 'analytics' },
      { label: 'Klienci', path: '/app/clients', icon: 'users', viewKey: 'clients' },
      { label: 'Struktura', path: '/app/structure', icon: 'sitemap', viewKey: 'structure' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'invoice', viewKey: 'settlements' },
      { label: 'Faktury', path: '/app/admin-invoices', icon: 'file-invoice-dollar', viewKey: 'admin-invoices' },
      { label: 'Progi Prowizyjne', path: '/app/commission-thresholds', icon: 'sliders', viewKey: 'commission-thresholds' },
      { label: 'Prowizje Override', path: '/app/commission-distributions', icon: 'chart-bar', viewKey: 'commission-distributions' },
      { label: 'Autenti', path: '/app/autenti-panel', icon: 'signature', viewKey: 'autenti-panel' },
      { label: 'Rankingi', path: '/app/leaderboard', icon: 'trophy', viewKey: 'leaderboard' },
      { label: 'Ustawienia', path: '/app/settings', icon: 'gear', viewKey: 'settings' }
    )
  } else if (role === 'DIRECTOR') {
    links.push(
      { label: 'Analityka', path: '/app/analytics', icon: 'chart-pie', viewKey: 'analytics' },
      { label: 'Mój Zespół', path: '/app/structure', icon: 'people-roof', viewKey: 'structure' },
      { label: 'Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'wallet', viewKey: 'settlements' },
      { label: 'Rankingi', path: '/app/leaderboard', icon: 'medal', viewKey: 'leaderboard' }
    )
  } else if (role === 'MANAGER') {
    links.push(
      { label: 'Mój Zespół', path: '/app/structure', icon: 'people-group', viewKey: 'structure' },
      { label: 'Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Rozliczenia', path: '/app/settlements', icon: 'wallet', viewKey: 'settlements' },
      { label: 'Rankingi', path: '/app/leaderboard', icon: 'medal', viewKey: 'leaderboard' }
    )
  } else if (role === 'SALES') {
    links.push(
      // Spotkania removed — activity panel on each client card handles it
      { label: 'Moi Klienci', path: '/app/clients', icon: 'address-book', viewKey: 'clients' },
      { label: 'Szybka Oferta', path: '/app/quick-calculator', icon: 'calculator', viewKey: 'quick-calculator' },
      { label: 'Moje Prowizje', path: '/app/settlements', icon: 'hand-holding-dollar', viewKey: 'settlements' },
      { label: 'Ranking', path: '/app/leaderboard', icon: 'award', viewKey: 'leaderboard' }
    )
  } else if (role === 'LEADOWIEC') {
    links.push(
      { label: 'Moi Klienci',  path: '/app/leadowiec/clients',     icon: 'users',                viewKey: 'leadowiec-clients'     },
      { label: 'Kalendarz',    path: '/app/leadowiec/calendar',    icon: 'calendar',             viewKey: 'leadowiec-calendar'    },
      { label: 'Rozliczenia',  path: '/app/leadowiec/settlements', icon: 'hand-holding-dollar',  viewKey: 'leadowiec-settlements' },
      { label: 'Rekrutacja',   path: '/app/recruitment',           icon: 'people-group',         viewKey: 'recruitment'           },
      { label: 'Kalkulator',   path: '/app/quick-calculator',      icon: 'calculator',           viewKey: 'quick-calculator'      },
    )
  }

  links.push(
    { label: 'Kalendarz', path: '/app/calendar', icon: 'calendar', viewKey: 'calendar' },
    { label: 'Powiadomienia', path: '/app/notifications', icon: 'bell', viewKey: 'notifications' },
  )
  if (role !== 'LEADOWIEC') {
    links.push(
      { label: 'Poczta', path: '/app/mailbox', icon: 'envelope', viewKey: 'mailbox' },
      { label: 'Baza Wiedzy', path: '/app/knowledge-base', icon: 'book-open', viewKey: 'knowledge-base' },
    )
  }

  if (role === 'ADMIN') {
    links.push({ label: 'Aktualności', path: '/app/news-management', icon: 'document-text', viewKey: 'news-management' })
  }

  const filteredLinks = links.filter((link) => {
    if (['user-management', 'admin-analytics', 'admin-logs'].includes(link.viewKey) && role === 'ADMIN') return true
    if (link.viewKey === 'settings') {
      return viewPermissions.isSettingsAllowed(role)
    }
    return viewPermissions.isViewAllowed(link.viewKey, role)
  })

  const dashboardLink = filteredLinks.find((link) => link.viewKey === 'dashboard')
  const otherLinks = filteredLinks.filter((link) => link.viewKey !== 'dashboard').sort((a, b) => {
    return a.label.localeCompare(b.label, 'pl')
  })

  return dashboardLink ? [dashboardLink, ...otherLinks] : otherLinks
})

const ADMIN_LINK_GROUPS: Array<{ title: string; viewKeys: string[] }> = [
  { title: '', viewKeys: ['dashboard'] },
  { title: 'Sprzedaż', viewKeys: ['clients', 'analytics', 'settlements', 'leaderboard'] },
  { title: 'Finanse', viewKeys: ['admin-analytics', 'admin-invoices', 'commission-thresholds', 'commission-distributions', 'autenti-panel'] },
  { title: 'Zarządzanie', viewKeys: ['user-management', 'structure', 'admin-logs'] },
  { title: 'Treści & System', viewKeys: ['news-management', 'settings'] },
  { title: 'Komunikacja', viewKeys: ['calendar', 'notifications', 'mailbox', 'knowledge-base'] },
]

const navGroups = computed((): NavGroup[] => {
  const byKey: Record<string, NavLink> = Object.fromEntries(navLinks.value.map((l) => [l.viewKey, l]))
  return ADMIN_LINK_GROUPS
    .map((g) => ({ title: g.title, links: g.viewKeys.map((k) => byKey[k]).filter(Boolean) as NavLink[] }))
    .filter((g) => g.links.length > 0)
})
</script>

<template>
  <!-- Mobile overlay -->
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black/60 z-40 lg:hidden"
    @click="emit('toggle')"
  />

  <aside
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
    class="fixed inset-y-0 left-0 z-50 flex flex-col bg-[#12151f] border-r border-white/6 transition-[width] duration-200 ease-out overflow-hidden"
    :class="[
      // Mobile drawer
      isOpen ? 'translate-x-0 w-60' : '-translate-x-full',
      // Desktop: always visible, hover controls width
      isHovered ? 'lg:translate-x-0 lg:w-60' : 'lg:translate-x-0 lg:w-14'
    ]"
  >
    <!-- Brand header -->
    <div
      class="h-14 flex items-center border-b border-white/6 cursor-pointer hover:bg-white/4 transition-colors duration-150 shrink-0"
      @click="router.push('/app/dashboard')"
    >
      <!-- Icon always visible, centered in 56px column -->
      <div class="w-14 flex items-center justify-center shrink-0">
        <div class="w-8 h-8 rounded-lg bg-stratton-gold/15 border border-stratton-gold/25 flex items-center justify-center">
          <AppIcon name="shield-check" class="w-4 h-4 text-stratton-gold" />
        </div>
      </div>
      <!-- Brand text, slides in when expanded -->
      <div
        class="overflow-hidden whitespace-nowrap transition-all duration-200 ease-out"
        :class="expanded ? 'max-w-[180px] opacity-100' : 'max-w-0 opacity-0'"
      >
        <div class="text-stratton-gold font-black text-[13px] uppercase tracking-widest leading-tight">Stratton Prime</div>
        <div class="text-slate-500 text-[9px] font-semibold uppercase tracking-widest leading-tight mt-0.5">CRM System</div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-1 nav-scroll">

      <!-- ADMIN expanded: grouped -->
      <template v-if="isAdmin && expanded">
        <template v-for="(group, gi) in navGroups" :key="group.title || 'dashboard'">
          <!-- Group title -->
          <div
            v-if="group.title"
            class="overflow-hidden transition-all duration-150"
            :class="expanded ? 'max-h-8 opacity-100' : 'max-h-0 opacity-0'"
          >
            <p
              class="text-[9px] font-black text-slate-500 uppercase tracking-widest px-5 pb-1"
              :class="gi > 0 ? 'pt-2 mt-1 border-t border-white/6' : 'pt-1'"
            >{{ group.title }}</p>
          </div>
          <RouterLink
            v-for="link in group.links"
            :key="link.path"
            :to="link.path"
            :title="!expanded ? link.label : undefined"
            class="relative flex items-center h-8 transition-colors duration-150 select-none border-l-2"
            :class="route.path.startsWith(link.path)
              ? 'border-stratton-gold bg-stratton-gold/10 text-stratton-gold'
              : 'border-transparent text-slate-400 hover:text-slate-100 hover:bg-white/5'"
          >
            <div class="w-14 flex items-center justify-center shrink-0">
              <AppIcon :name="link.icon" class="w-4 h-4" />
            </div>
            <span
              class="text-[11px] font-semibold whitespace-nowrap overflow-hidden transition-all duration-200 ease-out"
              :class="expanded ? 'max-w-[170px] opacity-100' : 'max-w-0 opacity-0'"
            >{{ link.label }}</span>
          </RouterLink>
        </template>
      </template>

      <!-- All roles (admin collapsed or other roles): flat list -->
      <template v-else>
        <RouterLink
          v-for="link in navLinks"
          :key="link.path"
          :to="link.path"
          :title="!expanded ? link.label : undefined"
          class="relative flex items-center h-8 transition-colors duration-150 select-none border-l-2"
          :class="route.path.startsWith(link.path)
            ? 'border-stratton-gold bg-stratton-gold/10 text-stratton-gold'
            : 'border-transparent text-slate-400 hover:text-slate-100 hover:bg-white/5'"
        >
          <div class="w-14 flex items-center justify-center shrink-0">
            <AppIcon :name="link.icon" class="w-4 h-4" />
          </div>
          <span
            class="text-[11px] font-semibold whitespace-nowrap overflow-hidden transition-all duration-200 ease-out"
            :class="expanded ? 'max-w-[170px] opacity-100' : 'max-w-0 opacity-0'"
          >{{ link.label }}</span>
        </RouterLink>
      </template>
    </nav>

    <!-- User panel -->
    <div class="border-t border-white/6 px-3 py-3 shrink-0">
      <div class="flex items-center">
        <!-- Avatar always visible -->
        <div class="w-8 h-8 rounded-full bg-slate-700 border border-white/10 flex items-center justify-center text-white text-[10px] font-black shrink-0">
          {{ sidebarInitials }}
        </div>
        <!-- User info, slides in when expanded -->
        <div
          class="overflow-hidden transition-all duration-200 ease-out"
          :class="expanded ? 'max-w-[140px] opacity-100 ml-2.5' : 'max-w-0 opacity-0 ml-0'"
        >
          <div class="text-[11px] font-semibold text-slate-200 truncate whitespace-nowrap leading-tight">{{ sidebarUser?.name }}</div>
          <div class="text-[9px] text-slate-500 whitespace-nowrap leading-tight">{{ sidebarUser?.role }}</div>
          <div v-if="sidebarUser?.crmNumber" class="text-[9px] text-stratton-gold font-mono whitespace-nowrap leading-tight">{{ sidebarUser?.crmNumber }}</div>
        </div>
        <!-- Close button: only on mobile -->
        <button
          type="button"
          class="ml-auto p-1.5 rounded-md text-slate-500 hover:text-slate-200 hover:bg-white/10 transition-all lg:hidden shrink-0"
          @click="emit('toggle')"
        >
          <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.nav-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(212, 175, 55, 0.25) transparent;
}
.nav-scroll::-webkit-scrollbar {
  width: 3px;
}
.nav-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.nav-scroll::-webkit-scrollbar-thumb {
  background-color: rgba(212, 175, 55, 0.25);
  border-radius: 999px;
}
.nav-scroll::-webkit-scrollbar-thumb:hover {
  background-color: rgba(212, 175, 55, 0.5);
}
</style>
