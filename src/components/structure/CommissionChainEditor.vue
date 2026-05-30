<script setup lang="ts">
import { ref, watch } from 'vue'
import { api } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'

interface AncestorItem {
  userSupabaseId: string
  name: string
  email: string
  role: string
  rate: number | null
}

interface ChainResponse {
  subMember: {
    userSupabaseId: string
    name: string
    email: string
    role: string
    selfRate: number | null
  }
  ancestors: AncestorItem[]
}

const props = defineProps<{ userId: string }>()
const emit = defineEmits<{ (e: 'saved'): void }>()

const toast = useToastStore()
const isLoading = ref(false)
const isSaving = ref(false)
const chain = ref<ChainResponse | null>(null)

const selfPercent = ref<number | null>(null)
const ancestorPercents = ref<Record<string, number | null>>({})

const ROLE_BADGE: Record<string, string> = {
  ADMIN:     'bg-stratton-gold/10 text-amber-700 border-stratton-gold/30',
  DIRECTOR:  'bg-indigo-50 text-indigo-700 border-indigo-200',
  MANAGER:   'bg-sky-50 text-sky-700 border-sky-200',
  SALES:     'bg-emerald-50 text-emerald-700 border-emerald-200',
  LEADOWIEC: 'bg-violet-50 text-violet-700 border-violet-200',
  CLIENT_HR: 'bg-slate-100 text-slate-600 border-slate-200',
}

async function loadChain() {
  if (!props.userId) return
  isLoading.value = true
  try {
    const { data } = await api.get<ChainResponse>(`/v1/users/${props.userId}/commission-chain`)
    chain.value = data
    selfPercent.value = data.subMember.selfRate != null ? round2(data.subMember.selfRate * 100) : null
    ancestorPercents.value = {}
    for (const a of data.ancestors) {
      ancestorPercents.value[a.userSupabaseId] = a.rate != null ? round2(a.rate * 100) : null
    }
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Nie udało się pobrać łańcucha prowizji.')
  } finally {
    isLoading.value = false
  }
}

function round2(n: number) {
  return Number(n.toFixed(2))
}

function pctToRate(p: number | null | undefined): number | null {
  if (p == null || p === '' as any) return null
  const n = Number(p)
  if (!Number.isFinite(n) || n < 0) return null
  return Number((n / 100).toFixed(6))
}

async function save() {
  if (!chain.value) return
  const overrides = chain.value.ancestors.map((a) => ({
    ancestor_supabase_id: a.userSupabaseId,
    rate: pctToRate(ancestorPercents.value[a.userSupabaseId]),
  }))
  isSaving.value = true
  try {
    await api.put(`/v1/users/${props.userId}/commission-chain`, {
      self_rate: pctToRate(selfPercent.value),
      overrides,
    })
    toast.success('Stawki prowizji zapisane.')
    emit('saved')
    await loadChain()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Zapis nie powiódł się.')
  } finally {
    isSaving.value = false
  }
}

watch(() => props.userId, loadChain, { immediate: true })
</script>

<template>
  <div class="mt-4 pt-4 border-t border-slate-200">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <AppIcon name="dollar" class="w-4 h-4 text-stratton-gold" />
        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Prowizje Override</h4>
      </div>
      <p class="text-[10px] text-slate-400">Indywidualne stawki dla tego użytkownika</p>
    </div>

    <div v-if="isLoading" class="text-center py-4 text-xs text-slate-400">Ładuje łańcuch…</div>

    <div v-else-if="chain" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- LEFT: own rate -->
      <div class="bg-emerald-50/60 border border-emerald-200 rounded-lg p-3">
        <label class="block text-[10px] font-bold text-emerald-800 uppercase tracking-wider mb-2">
          Własna stawka z dealu
        </label>
        <div class="flex items-center gap-2">
          <input
            v-model.number="selfPercent"
            type="number"
            min="0"
            max="100"
            step="0.01"
            placeholder="np. 4"
            class="flex-1 border border-emerald-300 rounded p-1.5 text-xs focus:outline-none focus:border-stratton-gold bg-white"
          />
          <span class="text-xs font-bold text-emerald-700">%</span>
        </div>
        <p class="text-[10px] text-emerald-700 mt-1 leading-snug">
          {{ chain.subMember.name }} dostaje ten % z każdego dealu który sam zamknie.
        </p>
      </div>

      <!-- RIGHT: ancestor list (spans 2 cols) -->
      <div class="md:col-span-2 bg-amber-50/40 border border-amber-200 rounded-lg p-3">
        <label class="block text-[10px] font-bold text-amber-800 uppercase tracking-wider mb-2">
          Prowizje moich przełożonych (z mojego dealu)
        </label>

        <div v-if="chain.ancestors.length === 0" class="text-xs text-slate-500 italic py-2">
          Ten użytkownik nie ma przełożonych w łańcuchu.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(a, idx) in chain.ancestors"
            :key="a.userSupabaseId"
            class="flex items-center gap-3 bg-white rounded p-2 border border-amber-100"
          >
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600 shrink-0">
              L{{ idx + 1 }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-bold text-slate-800 truncate">{{ a.name || a.email }}</div>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="px-1.5 py-0 rounded text-[10px] font-bold border" :class="ROLE_BADGE[a.role] || 'bg-slate-100 text-slate-600 border-slate-200'">{{ a.role }}</span>
                <span class="text-[10px] text-slate-400 truncate">{{ a.email }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <input
                v-model.number="ancestorPercents[a.userSupabaseId]"
                type="number"
                min="0"
                max="100"
                step="0.01"
                placeholder="0"
                class="w-20 border border-amber-300 rounded p-1.5 text-xs focus:outline-none focus:border-stratton-gold bg-white text-right font-mono"
              />
              <span class="text-xs font-bold text-amber-700">%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="chain" class="flex justify-end mt-3">
      <button
        type="button"
        class="flex items-center gap-2 px-4 py-2 bg-stratton-gold hover:bg-yellow-600 text-white rounded-lg text-xs font-bold shadow-sm transition disabled:opacity-60"
        :disabled="isSaving"
        @click="save"
      >
        <AppIcon name="check" class="w-3.5 h-3.5" />
        {{ isSaving ? 'Zapisuje...' : 'Zapisz prowizje' }}
      </button>
    </div>
  </div>
</template>
