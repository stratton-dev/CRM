<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'
import logoUrl from '@/assets/logo.svg'

defineProps<{
  unreadCount: number
}>()

const emit = defineEmits<{
  (e: 'toggleNotifications'): void
  (e: 'toggleCommandPalette'): void
  (e: 'toggleSidebar'): void
  (e: 'logout'): void
}>()
</script>

<template>
  <header class="relative w-full overflow-hidden pb-0 bg-slate-50 shrink-0 border-b border-gray-200">
    <div class="flex items-center justify-between w-full px-4 h-16">

      <!-- Mobile hamburger (hidden on lg+) -->
      <button
        class="lg:hidden shrink-0 p-2 -ml-1 mr-1 text-slate-600 hover:bg-slate-100 rounded-lg transition"
        aria-label="Otwórz menu nawigacyjne"
        @click="emit('toggleSidebar')"
      >
        <AppIcon name="bars-3" class="w-5 h-5" />
      </button>

      <!-- Left Wing -->
      <div class="flex-1 flex flex-col items-end relative h-full justify-center overflow-hidden">
        <!-- Search Bar -->
        <div
          class="absolute left-0 bottom-1.5 bg-white/90 hover:bg-white border border-slate-100 rounded-lg px-3 py-1.5 flex items-center cursor-text transition group shadow-sm z-30 w-40 sm:w-56 backdrop-blur-md overflow-hidden h-9"
          @click="emit('toggleCommandPalette')"
        >
          <AppIcon name="search" class="w-4 h-4 text-slate-400 mr-2 group-hover:text-stratton-gold transition shrink-0" />
          <span class="text-slate-500 text-[10px] font-bold truncate uppercase tracking-wider flex-1 text-right">Szukaj (Ctrl+F)</span>
        </div>

        <!-- Lines -->
        <div class="absolute right-0 top-3 w-[200%] flex flex-col gap-1 pointer-events-none overflow-hidden">
          <div class="h-px bg-[#0f172a] w-full"></div>
          <div class="h-0.5 bg-[#0f172a] w-full"></div>
        </div>

        <!-- Text -->
        <span class="text-base font-bold text-[#0f172a] tracking-[0.25em] mr-2 mt-1 relative z-10 font-cinzel">
          STRATTON
        </span>
      </div>

      <!-- Center Logo -->
      <div class="px-2 relative z-20 shrink-0">
        <img :src="logoUrl" class="h-16 w-auto filter drop-shadow-md" alt="Stratton Prime" />
      </div>

      <!-- Right Wing -->
      <div class="flex-1 flex flex-col items-start relative h-full justify-center overflow-hidden">
        <!-- Controls -->
        <div class="absolute right-0 bottom-1.5 flex items-center space-x-2 z-30 bg-white/90 backdrop-blur-md px-3 rounded-lg border border-slate-100 shadow-sm h-9">
          <button
            type="button"
            class="relative cursor-pointer group"
            :aria-label="unreadCount > 0 ? `Powiadomienia (${unreadCount} nieprzeczytanych)` : 'Powiadomienia'"
            @click="emit('toggleNotifications')"
          >
            <div
              class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-full transition"
              :class="{ 'animate-bell': unreadCount > 0 }"
            >
              <AppIcon name="bell" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold transition" />
              <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 border border-white z-10"></span>
            </div>
          </button>
          <div class="h-4 w-px bg-slate-200 mx-1"></div>
          <button
            type="button"
            class="flex items-center gap-1.5 text-slate-400 hover:text-slate-800 transition text-[11px] font-bold uppercase tracking-wide group"
            @click="emit('logout')"
          >
            <span class="group-hover:underline">Wyloguj</span>
            <AppIcon name="logout" class="w-4 h-4" />
          </button>
        </div>

        <!-- Lines -->
        <div class="absolute left-0 top-3 w-[200%] flex flex-col gap-1 pointer-events-none overflow-hidden">
          <div class="h-px bg-[#0f172a] w-full"></div>
          <div class="h-0.5 bg-[#0f172a] w-full"></div>
        </div>

        <!-- Text -->
        <span class="text-base font-bold text-[#0f172a] tracking-[0.25em] ml-2 mt-1 relative z-10 font-cinzel">
          PRIME
        </span>
      </div>

    </div>
  </header>
</template>

<style scoped>
@keyframes bell-pulse {
  0%   { transform: scale(1);   box-shadow: 0 0 0 0   rgba(234, 179, 8, 0.7); }
  70%  { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(234, 179, 8, 0);   }
  100% { transform: scale(1);   box-shadow: 0 0 0 0   rgba(234, 179, 8, 0);   }
}

.animate-bell {
  animation: bell-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
