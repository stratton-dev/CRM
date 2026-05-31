<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useSessionStore } from '@/stores/session'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useUiStore } from '@/stores/ui'
import DashboardView from '@/views/DashboardView.vue'
import AppIcon from '@/components/AppIcon.vue'
import logoUrl from '@/assets/logo.svg'

const router = useRouter()
const session = useSessionStore()
const auth = useAuthStore()
const notifStore = useNotificationStore()
const ui = useUiStore()
const { currentUser } = storeToRefs(session)
const { notifications } = storeToRefs(notifStore)

const myNotifications = computed(() => {
  const user = currentUser.value
  if (!user) return []
  return notifications.value
    .filter((notif) => notif.userId === user.id)
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})
const unreadCount = computed(() => myNotifications.value.filter((notif) => !notif.read).length)

const logout = async () => {
  await auth.logout()
  session.clearSession()
  router.push('/login')
}
</script>

<template>
  <div class="space-y-2">
    <!-- Decorative Header -->
    <div class="relative w-full overflow-hidden pb-1">
      
      <div class="flex items-center justify-between w-full px-4">
        
        <!-- Left Wing -->
        <div class="flex-1 flex flex-col items-end relative h-14 md:h-24 justify-center">
           <!-- Search Bar -->
           <div
             class="absolute left-0 bottom-1 bg-white/80 hover:bg-white border border-slate-200 rounded-xl px-3 md:px-4 py-1.5 md:py-2 hidden sm:flex items-center cursor-text transition group shadow-sm hover:shadow z-30 w-40 md:w-64 backdrop-blur-sm"
             @click="ui.toggleCommandPalette"
           >
             <AppIcon name="search" class="w-4 h-4 text-slate-400 mr-2 md:mr-3 group-hover:text-stratton-gold transition" />
             <span class="text-slate-500 text-[10px] md:text-xs font-medium truncate">Szukaj (Ctrl+F)</span>
           </div>

           <!-- Lines -->
           <div class="absolute right-0 top-3 md:top-6 w-[200%] flex flex-col gap-1 md:gap-1.5 pointer-events-none">
             <div class="h-px bg-[#0f172a] w-full"></div>
             <div class="h-[2px] md:h-[3px] bg-[#0f172a] w-full"></div>
           </div>

           <!-- Text -->
           <span class="hidden lg:inline text-xl md:text-2xl font-bold text-[#0f172a] tracking-[0.25em] mr-2 mt-2 md:mt-3 relative z-10 font-serif">
            STRATTON
           </span>
        </div>

        <!-- Center Logo -->
        <div class="px-1 md:px-2 relative z-20 shrink-0">
          <img :src="logoUrl" class="h-12 md:h-24 w-auto filter drop-shadow-lg" alt="Stratton Prime" />
        </div>

        <!-- Right Wing -->
        <div class="flex-1 flex flex-col items-start relative h-14 md:h-24 justify-center">
           <!-- Controls -->
           <div class="absolute right-0 bottom-1 flex items-center space-x-2 md:space-x-4 z-30 bg-white/80 backdrop-blur-sm p-1.5 md:p-2 rounded-xl border border-slate-100 shadow-sm">
             <div class="relative cursor-pointer group" @click="ui.toggleNotifications">
                <div class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-full transition">
                  <AppIcon name="bell" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition" />
                  <span v-if="unreadCount > 0" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500 border border-white"></span>
                </div>
             </div>
             <button type="button" class="flex items-center gap-1 md:gap-2 text-slate-400 hover:text-slate-800 transition text-xs font-bold uppercase tracking-wide group" @click="logout">
               <span class="hidden sm:inline group-hover:underline">Wyloguj</span>
               <AppIcon name="logout" class="w-4 h-4" />
             </button>
           </div>

           <!-- Lines -->
           <div class="absolute left-0 top-3 md:top-6 w-[200%] flex flex-col gap-1 md:gap-1.5 pointer-events-none">
             <div class="h-px bg-[#0f172a] w-full"></div>
             <div class="h-[2px] md:h-[3px] bg-[#0f172a] w-full"></div>
           </div>

           <!-- Text -->
           <span class="hidden lg:inline text-xl md:text-2xl font-bold text-[#0f172a] tracking-[0.25em] ml-2 mt-2 md:mt-3 relative z-10 font-serif">
            PRIME
           </span>
        </div>
        
      </div>
    </div>

    <div class="animate-fade-in px-3 md:px-8 pb-3 md:pb-4 -mt-3 md:-mt-6">
      <!-- Admin tiles -->
      <div class="mb-2">
        <h2 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 md:mb-4 border-l-4 border-stratton-gold pl-3">Panel Administracyjny</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7 gap-4 md:gap-6">

          <!-- Zarządzanie użytkownikami -->
          <RouterLink to="/app/users" class="crm-tile h-32 md:h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
            <div class="absolute inset-0 z-0">
              <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-50 transition-transform duration-700 group-hover:scale-105" alt="Użytkownicy" />
              <div class="absolute inset-0 bg-linear-to-t from-slate-900/80 via-slate-900/30 to-slate-900/10"></div>
            </div>
            <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
              <div class="text-stratton-gold"><AppIcon name="users" class="w-8 h-8" /></div>
              <div>
                <h3 class="crm-tile-title text-xl text-white mb-1">Użytkownicy</h3>
                <p class="crm-tile-desc text-xs text-slate-300 font-medium">Zarządzaj zespołem</p>
              </div>
            </div>
          </RouterLink>

          <!-- Aktualności -->
          <RouterLink to="/app/news-management" class="crm-tile h-32 md:h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
            <div class="absolute inset-0 z-0">
              <img src="https://images.unsplash.com/photo-1504711434969-e33886168f5c?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-50 transition-transform duration-700 group-hover:scale-105" alt="Aktualności" />
              <div class="absolute inset-0 bg-linear-to-t from-slate-900/80 via-slate-900/30 to-slate-900/10"></div>
            </div>
            <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
              <div class="text-stratton-gold"><AppIcon name="newspaper" class="w-8 h-8" /></div>
              <div>
                <h3 class="crm-tile-title text-xl text-white mb-1">Aktualności</h3>
                <p class="crm-tile-desc text-xs text-slate-300 font-medium">Zarządzaj newsami</p>
              </div>
            </div>
          </RouterLink>

          <!-- Analityka -->
          <RouterLink to="/app/admin-analytics" class="crm-tile h-32 md:h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
            <div class="absolute inset-0 z-0">
              <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-50 transition-transform duration-700 group-hover:scale-105" alt="Analityka" />
              <div class="absolute inset-0 bg-linear-to-t from-slate-900/80 via-slate-900/30 to-slate-900/10"></div>
            </div>
            <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
              <div class="text-stratton-gold"><AppIcon name="chart-bar" class="w-8 h-8" /></div>
              <div>
                <h3 class="crm-tile-title text-xl text-white mb-1">Analityka</h3>
                <p class="crm-tile-desc text-xs text-slate-300 font-medium">Statystyki globalne</p>
              </div>
            </div>
          </RouterLink>

          <!-- Logi systemowe -->
          <RouterLink to="/app/admin-logs" class="crm-tile h-32 md:h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
            <div class="absolute inset-0 z-0">
              <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=2668&auto=format&fit=crop" class="w-full h-full object-cover opacity-50 transition-transform duration-700 group-hover:scale-105" alt="Logi" />
              <div class="absolute inset-0 bg-linear-to-t from-slate-900/80 via-slate-900/30 to-slate-900/10"></div>
            </div>
            <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
              <div class="text-stratton-gold"><AppIcon name="terminal" class="w-8 h-8" /></div>
              <div>
                <h3 class="crm-tile-title text-xl text-white mb-1">Logi systemowe</h3>
                <p class="crm-tile-desc text-xs text-slate-300 font-medium">Monitoruj aktywność</p>
              </div>
            </div>
          </RouterLink>

          <!-- Autenti -->
          <RouterLink to="/app/autenti-panel" class="crm-tile h-32 md:h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
            <div class="absolute inset-0 z-0">
              <img src="https://images.unsplash.com/photo-1568667256549-094345857637?q=80&w=2670&auto=format&fit=crop" class="w-full h-full object-cover opacity-50 transition-transform duration-700 group-hover:scale-105" alt="Autenti" />
              <div class="absolute inset-0 bg-linear-to-t from-slate-900/80 via-slate-900/30 to-slate-900/10"></div>
            </div>
            <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
              <div class="text-stratton-gold"><AppIcon name="pen" class="w-8 h-8" /></div>
              <div>
                <h3 class="crm-tile-title text-xl text-white mb-1">Autenti</h3>
                <p class="crm-tile-desc text-xs text-slate-300 font-medium">Podpisy elektroniczne</p>
              </div>
            </div>
          </RouterLink>

          <!-- Progi prowizji -->
          <RouterLink to="/app/commission-thresholds" class="crm-tile h-32 md:h-44 group relative overflow-hidden bg-slate-900 border border-slate-700">
            <div class="absolute inset-0 z-0">
              <img src="https://images.unsplash.com/photo-1579532537598-459ecdaf39cc?q=80&w=2787&auto=format&fit=crop" class="w-full h-full object-cover opacity-50 transition-transform duration-700 group-hover:scale-105" alt="Prowizje" />
              <div class="absolute inset-0 bg-linear-to-t from-slate-900/80 via-slate-900/30 to-slate-900/10"></div>
            </div>
            <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
              <div class="text-stratton-gold"><AppIcon name="currency-dollar" class="w-8 h-8" /></div>
              <div>
                <h3 class="crm-tile-title text-xl text-white mb-1">Progi prowizji</h3>
                <p class="crm-tile-desc text-xs text-slate-300 font-medium">Konfiguruj stawki</p>
              </div>
            </div>
          </RouterLink>

        </div>
      </div>
    </div>

    <div class="space-y-4 md:space-y-6 animate-fade-in px-3 md:px-8 pb-8">
      <DashboardView :show-admin-panel="false" />
    </div>
  </div>
</template>
