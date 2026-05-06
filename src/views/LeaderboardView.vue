<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useStructureStore } from '@/stores/structure'
import { useSessionStore } from '@/stores/session'
import { useGamificationStore } from '@/stores/gamification'
import AppIcon from '@/components/AppIcon.vue'
import type { Rank, User } from '@/types/models'

const structure = useStructureStore()
const session = useSessionStore()
const { currentUser } = storeToRefs(session)
const { users } = storeToRefs(structure)
const gamification = useGamificationStore()

const isLoading = ref(true)
onMounted(() => {
  structure.fetchStructure().finally(() => { isLoading.value = false })
})

const currentUserRef = computed(() => currentUser.value)

const leaderboardData = computed(() => {
  const userList = (Array.isArray(users.value) ? users.value : [])
    .filter((u) => ['SALES', 'MANAGER', 'DIRECTOR'].includes(u.role) && !u.isRemovedFromStructure)
    .sort((a, b) => (b.points || 0) - (a.points || 0))

  return userList.map((user, index) => ({
    ...user,
    position: index + 1,
    progress: getNextRankProgress(user),
  }))
})

const getRankIcon = (rank: Rank | null | undefined) => {
  switch (rank) {
    case 'JUNIOR':
      return 'medal'
    case 'REGULAR':
      return 'award'
    case 'SENIOR':
      return 'trophy'
    case 'MASTER':
      return 'sparkles'
    case 'LEGEND':
      return 'trophy'
    default:
      return 'bolt'
  }
}

const getRankIconClass = (rank: Rank | null | undefined) => {
  switch (rank) {
    case 'JUNIOR':
      return 'text-amber-500'
    case 'REGULAR':
      return 'text-slate-400'
    case 'SENIOR':
      return 'text-amber-600'
    case 'MASTER':
      return 'text-indigo-500'
    case 'LEGEND':
      return 'text-yellow-500'
    default:
      return 'text-emerald-500'
  }
}

const getNextRankProgress = (user: User) => {
  const nextRank = gamification.getNextRankTarget(user.points || 0)
  if (!nextRank) return { progress: 100, target: 'MAX (MASTER)' }
  const progress = ((user.points || 0) / nextRank.required) * 100
  return { progress, target: `${user.points}/${nextRank.required}` }
}

const getInitials = (name?: string) => {
  if (!name) return '??'
  const parts = name.trim().split(' ')
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return parts.map((part) => part[0]).join('').toUpperCase().substring(0, 2)
}
</script>

<template>
  <div class="space-y-6 h-full flex flex-col">
    <div class="flex justify-between items-end border-b border-slate-200 pb-4 shrink-0">
      <div>
        <h1 class="text-xl md:text-3xl font-serif font-bold text-slate-900">Ranking Sprzedawców</h1>
        <p class="text-sm text-slate-500 mt-1 uppercase tracking-wider font-bold">Rywalizacja i Prestiż</p>
      </div>
      <div class="bg-indigo-50 border border-indigo-100 rounded px-4 py-2">
        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Aktualny Sezon</p>
        <p class="text-sm font-bold text-indigo-800">Q1 2024</p>
      </div>
    </div>

    <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
      <div class="overflow-x-auto flex-1 flex flex-col min-w-0">
      <div class="bg-slate-900 px-6 py-4 grid grid-cols-12 items-center shrink-0 min-w-[480px]">
        <div class="col-span-1 text-center text-[10px] font-bold text-[#B1905E] uppercase tracking-widest">Poz</div>
        <div class="col-span-1 text-center text-[10px] font-bold text-[#B1905E] uppercase tracking-widest">Ranga</div>
        <div class="col-span-5 text-left pl-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Handlowiec</div>
        <div class="col-span-5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Postęp do kolejnej rangi</div>
      </div>

      <div class="overflow-y-auto flex-1 custom-scrollbar min-w-[480px]">
        <!-- Skeleton rows while loading -->
        <template v-if="isLoading">
          <div v-for="i in 8" :key="`lb-sk-${i}`" class="grid grid-cols-12 items-center px-6 py-4 border-b border-slate-100 animate-pulse">
            <div class="col-span-1 flex justify-center"><div class="h-6 w-6 bg-slate-200 rounded"></div></div>
            <div class="col-span-1 flex justify-center"><div class="h-6 w-6 bg-slate-200 rounded-full"></div></div>
            <div class="col-span-5 flex items-center pl-4 gap-3">
              <div class="w-10 h-10 rounded-full bg-slate-200 shrink-0"></div>
              <div class="space-y-1 flex-1">
                <div class="h-3 bg-slate-200 rounded w-2/3"></div>
                <div class="h-3 bg-slate-200 rounded w-1/3"></div>
              </div>
            </div>
            <div class="col-span-5">
              <div class="h-3 bg-slate-200 rounded-full w-full"></div>
            </div>
          </div>
        </template>
        <div
          v-for="user in leaderboardData"
          :key="user.id"
          class="group grid grid-cols-12 items-center px-6 py-4 border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors relative"
          :class="user.id === currentUserRef?.id ? 'bg-[#B1905E] bg-opacity-5 border-l-4 border-[#B1905E]' : ''"
        >
          <div v-if="user.id === currentUser?.id" class="absolute right-2 top-2 text-[8px] font-bold text-[#B1905E] uppercase bg-white border border-[#B1905E] px-1.5 py-0.5 rounded shadow-sm opacity-60">
            To Ty
          </div>

          <div
            class="col-span-1 text-center font-serif font-bold text-xl relative"
            :class="user.position === 1 ? 'text-[#B1905E] scale-110' : user.position === 2 ? 'text-slate-400' : user.position === 3 ? 'text-amber-700' : 'text-slate-300'"
          >
            {{ user.position }}
            <AppIcon v-if="user.position === 1" name="trophy" class="absolute -top-3 -right-2 w-4 h-4 text-[#B1905E]" />
          </div>

          <div class="col-span-1 text-center drop-shadow-sm grayscale group-hover:grayscale-0 transition-all duration-300" :title="user.rank ?? undefined">
            <AppIcon :name="getRankIcon(user.rank)" class="w-6 h-6 mx-auto" :class="getRankIconClass(user.rank)" />
          </div>

          <div class="col-span-5 flex items-center pl-4">
            <div
              class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold border-2 shadow-sm mr-4 transition-transform group-hover:scale-110"
              :class="{
                'bg-slate-800 text-white border-slate-600': user.role === 'DIRECTOR',
                'bg-white text-slate-700 border-slate-200': user.role === 'MANAGER',
                'bg-white text-slate-500 border-slate-100': user.role === 'SALES',
            'border-[#B1905E] text-[#B1905E]': user.id === currentUserRef?.id,
              }"
            >
              {{ getInitials(user.name) }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <p class="font-bold text-sm text-slate-800 font-serif group-hover:text-[#B1905E] transition-colors">{{ user.name }}</p>
                <span v-if="user.role === 'DIRECTOR'" class="bg-slate-900 text-white text-[9px] px-1 rounded uppercase tracking-wider font-bold">DIR</span>
              </div>
              <p class="text-[10px] text-slate-400 uppercase tracking-wide font-medium">{{ user.role }}</p>
            </div>
          </div>

          <div class="col-span-5">
            <div>
              <div class="flex justify-between items-center text-[10px] uppercase font-bold text-slate-400 mb-1.5">
                <span class="text-slate-600 group-hover:text-[#B1905E] transition-colors">{{ user.points || 0 }} XP</span>
                <span class="tracking-widest">{{ user.progress.target }}</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-100">
                <div
                  class="h-full rounded-full transition-all duration-1000 ease-out relative overflow-hidden"
                  :style="{ width: `${user.progress.progress}%` }"
                  :class="user.position <= 3 ? 'bg-linear-to-r from-[#B1905E] to-[#d4af37]' : user.id === currentUserRef?.id ? 'bg-emerald-500' : 'bg-slate-400'"
                >
                  <div class="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background-color: transparent;
}
</style>
