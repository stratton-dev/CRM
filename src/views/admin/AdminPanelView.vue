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
        <div class="flex-1 flex flex-col items-end relative h-24 justify-center">
           <!-- Search Bar -->
           <div 
             class="absolute left-0 bottom-1 bg-white/80 hover:bg-white border border-slate-200 rounded-xl px-4 py-2 flex items-center cursor-text transition group shadow-sm hover:shadow z-30 w-64 backdrop-blur-sm"
             style="transform: translateY(0);"
             @click="ui.toggleCommandPalette"
           >
             <AppIcon name="search" class="w-4 h-4 text-slate-400 mr-3 group-hover:text-stratton-gold transition" />
             <span class="text-slate-500 text-xs font-medium">Szukaj (Ctrl + F)</span>
           </div>

           <!-- Lines -->
           <div class="absolute right-0 top-6 w-[200%] flex flex-col gap-1.5 pointer-events-none">
             <div class="h-px bg-[#0f172a] w-full"></div>
             <div class="h-[3px] bg-[#0f172a] w-full"></div>
           </div>
           
           <!-- Text -->
           <span class="text-2xl font-bold text-[#0f172a] tracking-[0.25em] mr-2 mt-3 relative z-10 font-serif">
            STRATTON
           </span>
        </div>

        <!-- Center Logo -->
        <div class="px-2 relative z-20 shrink-0">
          <img :src="logoUrl" class="h-24 w-auto filter drop-shadow-lg" alt="Stratton Prime" />
        </div>

        <!-- Right Wing -->
        <div class="flex-1 flex flex-col items-start relative h-24 justify-center">
           <!-- Controls -->
           <div class="absolute right-0 bottom-1 flex items-center space-x-4 z-30 bg-white/80 backdrop-blur-sm p-2 rounded-xl border border-slate-100 shadow-sm" style="transform: translateY(0);">
             <div class="relative cursor-pointer group" @click="ui.toggleNotifications">
                <div class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-full transition">
                  <AppIcon name="bell" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition" />
                  <span v-if="unreadCount > 0" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500 border border-white"></span>
                </div>
             </div>
             <button type="button" class="flex items-center gap-2 text-slate-400 hover:text-slate-800 transition text-xs font-bold uppercase tracking-wide group" @click="logout">
               <span class="group-hover:underline">Wyloguj</span>
               <AppIcon name="logout" class="w-4 h-4" />
             </button>
           </div>

           <!-- Lines -->
           <div class="absolute left-0 top-6 w-[200%] flex flex-col gap-1.5 pointer-events-none">
             <div class="h-px bg-[#0f172a] w-full"></div>
             <div class="h-[3px] bg-[#0f172a] w-full"></div>
           </div>
           
           <!-- Text -->
           <span class="text-2xl font-bold text-[#0f172a] tracking-[0.25em] ml-2 mt-3 relative z-10 font-serif">
            PRIME
           </span>
        </div>
        
      </div>
    </div>

    <div class="space-y-6 animate-fade-in px-8 pb-8 -mt-6">
      <DashboardView :show-admin-panel="false" />
    </div>
  </div>
</template>
