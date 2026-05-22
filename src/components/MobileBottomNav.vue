<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-200"
    style="padding-bottom: env(safe-area-inset-bottom)"
  >
    <div class="flex items-center justify-around h-16">

      <!-- Dashboard -->
      <RouterLink
        v-if="currentUser?.role !== 'LEADOWIEC'"
        to="/app/dashboard"
        class="mobile-nav-item"
        :class="{ active: route.path === '/app/dashboard' }"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>Start</span>
      </RouterLink>

      <!-- Klienci -->
      <RouterLink
        to="/app/clients"
        class="mobile-nav-item"
        :class="{ active: route.path.startsWith('/app/clients') }"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span>Klienci</span>
      </RouterLink>

      <!-- Kalkulator — przycisk centralny -->
      <RouterLink
        to="/app/calculator"
        class="mobile-nav-item mobile-nav-center"
        :class="{ active: route.path.startsWith('/app/calculator') || route.path.startsWith('/app/quick-calculator') }"
      >
        <div class="w-12 h-12 rounded-full flex items-center justify-center -mt-4
                    bg-gradient-to-br from-[#001f3d] to-[#003366] shadow-lg">
          <svg class="w-6 h-6 text-[#C5A059]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <span class="mt-1">Kalkulator</span>
      </RouterLink>

      <!-- AI Chat -->
      <button
        class="mobile-nav-item"
        :class="{ active: aiChatOpen }"
        @click="toggleAiChat"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
        <span>AI</span>
      </button>

      <!-- Więcej -->
      <button class="mobile-nav-item" @click="showMoreMenu = true">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <span>Więcej</span>
      </button>

    </div>

    <!-- Pełnoekranowe menu "Więcej" -->
    <Transition name="slide-up">
      <div
        v-if="showMoreMenu"
        class="fixed inset-0 z-50 bg-white flex flex-col"
        style="padding-bottom: env(safe-area-inset-bottom)"
      >
        <!-- Nagłówek -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-slate-200">
          <h2 class="text-lg font-semibold text-slate-800">Menu</h2>
          <button class="p-2 rounded-full bg-slate-100 min-w-[44px] min-h-[44px] flex items-center justify-center" @click="showMoreMenu = false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Siatka kafelków -->
        <nav class="flex-1 overflow-y-auto p-4">
          <div class="grid grid-cols-2 gap-3">
            <RouterLink
              v-for="item in moreMenuItems"
              :key="item.path"
              :to="item.path"
              class="flex flex-col items-center gap-2 p-4 rounded-xl
                     bg-slate-50 border border-slate-200 active:bg-slate-100
                     min-h-[80px] justify-center"
              @click="showMoreMenu = false"
            >
              <span class="text-2xl">{{ item.emoji }}</span>
              <span class="text-xs font-medium text-slate-700 text-center">{{ item.label }}</span>
            </RouterLink>
          </div>
        </nav>

        <!-- Dane użytkownika -->
        <div class="px-4 py-4 border-t border-slate-200 flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-[#001f3d] flex items-center justify-center text-white font-bold shrink-0">
            {{ initials }}
          </div>
          <div class="min-w-0">
            <div class="font-medium text-slate-800 truncate">{{ currentUser?.name }}</div>
            <div class="text-xs text-slate-500">{{ currentUser?.role }}</div>
          </div>
        </div>
      </div>
    </Transition>

  </nav>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useSessionStore } from '@/stores/session'
import { storeToRefs } from 'pinia'

const route = useRoute()
const session = useSessionStore()
const { currentUser } = storeToRefs(session)

const showMoreMenu = ref(false)
const aiChatOpen = ref(false)

const initials = computed(() => {
  const name = currentUser.value?.name?.trim() || ''
  if (!name) return 'U'
  const parts = name.split(/\s+/).filter(Boolean)
  return (parts.length >= 2 ? parts[0][0] + parts[1][0] : name.slice(0, 2)).toUpperCase()
})

function toggleAiChat() {
  window.dispatchEvent(new CustomEvent('toggle-ai-chat'))
  aiChatOpen.value = !aiChatOpen.value
}

const moreMenuItems = computed(() => {
  const role = currentUser.value?.role
  const leadowiecHidden = new Set(['/app/mailbox', '/app/knowledge-base', '/app/leaderboard'])
  const items = [
    { path: '/app/calendar', label: 'Kalendarz', emoji: '📅' },
    { path: '/app/mailbox', label: 'Skrzynka', emoji: '✉️' },
    { path: '/app/meetings', label: 'Spotkania', emoji: '🤝' },
    { path: '/app/notifications', label: 'Powiadomienia', emoji: '🔔' },
    { path: '/app/knowledge-base', label: 'Baza wiedzy', emoji: '📚' },
    { path: '/app/leaderboard', label: 'Ranking', emoji: '🏆' },
    { path: '/app/settlements', label: 'Rozliczenia', emoji: '💰' },
    { path: '/app/quick-calculator', label: 'Szybki kalk.', emoji: '⚡' },
  ].filter(item => role !== 'LEADOWIEC' || !leadowiecHidden.has(item.path))
  if (role === 'ADMIN' || role === 'DIRECTOR' || role === 'MANAGER') {
    items.push({ path: '/app/structure', label: 'Struktura', emoji: '🏢' })
    items.push({ path: '/app/analytics', label: 'Analityka', emoji: '📊' })
  }
  if (role === 'ADMIN') {
    items.push({ path: '/app/users', label: 'Użytkownicy', emoji: '👥' })
    items.push({ path: '/app/settings', label: 'Ustawienia', emoji: '⚙️' })
  }
  return items
})
</script>

<style scoped>
.mobile-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.5rem;
  min-width: 52px;
  color: rgb(148 163 184);
  font-size: 10px;
  font-weight: 500;
  transition-property: color, background-color;
  transition-duration: 150ms;
}
.mobile-nav-item:active {
  color: #001f3d;
}
.mobile-nav-item.active {
  color: #001f3d;
}
.mobile-nav-item.active svg {
  stroke: #C5A059;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
}
</style>
