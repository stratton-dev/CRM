<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'

interface AncestorItem {
  userSupabaseId: string
  name: string
  email: string
  role: string
  isAgentAuthorized: boolean
  rate: number | null
}

interface ChainDefaults {
  selfRate: number
  l1Rate: number
  l2Rate: number
  agentRate: number
  maxChainDepth: number
}

interface ChainResponse {
  subMember: {
    userSupabaseId: string
    name: string
    email: string
    role: string
    isAgentAuthorized: boolean
    selfRate: number | null
  }
  ancestors: AncestorItem[]
  mode: 'LEADOWIEC' | 'STANDARD'
  defaults: ChainDefaults | null
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

const isLeadowiec = computed(() => chain.value?.mode === 'LEADOWIEC')

const defaults = computed(() => chain.value?.defaults ?? null)

interface LeadowiecRow {
  type: 'LEADOWIEC' | 'AGENT' | 'PASS_THROUGH'
  ancestor: AncestorItem
  level: number  // L1, L2 (or 0 for skipped)
  defaultRate: number | null
  inCap: boolean
}

/**
 * Dla LEADOWIEC source — odtwarza logikę calculator-a:
 *  L1 = pierwszy LEADOWIEC ancestor, L2 = drugi, L3+ wygaszone.
 *  Pierwszy NON-LEADOWIEC = potencjalny agent (jeśli isAgentAuthorized).
 */
const leadowiecRows = computed<LeadowiecRow[]>(() => {
  if (!isLeadowiec.value || !chain.value || !defaults.value) return []
  const rows: LeadowiecRow[] = []
  let leadowiecLevel = 1
  let foundAgent = false

  for (const a of chain.value.ancestors) {
    if (foundAgent) break

    if (a.role === 'LEADOWIEC') {
      const inCap = leadowiecLevel <= defaults.value.maxChainDepth
      const def = leadowiecLevel === 1
        ? defaults.value.l1Rate
        : leadowiecLevel === 2 ? defaults.value.l2Rate : 0
      rows.push({
        type: inCap ? 'LEADOWIEC' : 'PASS_THROUGH',
        ancestor: a,
        level: leadowiecLevel,
        defaultRate: inCap ? def : 0,
        inCap,
      })
      leadowiecLevel++
      continue
    }

    // Pierwszy non-LEADOWIEC
    rows.push({
      type: 'AGENT',
      ancestor: a,
      level: leadowiecLevel,
      defaultRate: a.isAgentAuthorized ? defaults.value.agentRate : 0,
      inCap: a.isAgentAuthorized,
    })
    foundAgent = true
  }

  return rows
})

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
        <span
          v-if="isLeadowiec"
          class="px-2 py-0.5 rounded text-[10px] font-bold bg-violet-100 text-violet-700 border border-violet-200"
        >
          MLM Leadowca
        </span>
      </div>
      <p class="text-[10px] text-slate-400">
        {{ isLeadowiec ? 'Hardkodowane defaults — można nadpisać per relację' : 'Indywidualne stawki per ancestor' }}
      </p>
    </div>

    <div v-if="isLoading" class="text-center py-4 text-xs text-slate-400">Ładuje łańcuch…</div>

    <!-- ════════════════════ TRYB STANDARD (SALES/MANAGER/DIRECTOR/ADMIN) ════════════════════ -->
    <div v-else-if="chain && !isLeadowiec" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

    <!-- ════════════════════ TRYB LEADOWIEC ════════════════════ -->
    <div v-else-if="chain && isLeadowiec && defaults" class="space-y-3">
      <!-- Info banner -->
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-3 text-[11px] text-violet-800 leading-relaxed">
        <strong>Schemat MLM leadowca:</strong>
        leadowiec dostaje {{ (defaults.selfRate * 100).toFixed(0) }}% z każdej faktury klienta,
        jego rekruter L1 dostaje {{ (defaults.l1Rate * 100).toFixed(0) }}%, L2
        ({{ (defaults.l2Rate * 100).toFixed(0) }}%), powyżej — wygaszone.
        Pierwszy <strong>uprawniony agent</strong> w chain dostaje
        {{ (defaults.agentRate * 100).toFixed(0) }}%. Domyślne wartości pokazane jako placeholder;
        wpisz aby nadpisać.
      </div>

      <!-- Self (level 0 — leadowiec) -->
      <div class="bg-violet-50/60 border border-violet-200 rounded-lg p-3">
        <label class="block text-[10px] font-bold text-violet-800 uppercase tracking-wider mb-2">
          Własna stawka z mojego klienta (default {{ (defaults.selfRate * 100).toFixed(0) }}%)
        </label>
        <div class="flex items-center gap-2">
          <input
            v-model.number="selfPercent"
            type="number"
            min="0"
            max="100"
            step="0.01"
            :placeholder="`default ${(defaults.selfRate * 100).toFixed(0)}`"
            class="flex-1 border border-violet-300 rounded p-1.5 text-xs focus:outline-none focus:border-stratton-gold bg-white"
          />
          <span class="text-xs font-bold text-violet-700">%</span>
        </div>
      </div>

      <!-- LEADOWIEC chain + agent -->
      <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
        <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-2">
          Łańcuch wypłat z mojego dealu
        </label>

        <div v-if="leadowiecRows.length === 0" class="text-xs text-slate-500 italic py-2">
          Brak przełożonych w łańcuchu — leadowiec nie ma rekrutera ani uprawnionego agenta.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="row in leadowiecRows"
            :key="row.ancestor.userSupabaseId"
            class="flex items-center gap-3 bg-white rounded p-2 border"
            :class="row.type === 'AGENT'
              ? (row.inCap ? 'border-emerald-300 bg-emerald-50/40' : 'border-red-200 bg-red-50/40')
              : (row.inCap ? 'border-violet-200' : 'border-slate-200 opacity-60')"
          >
            <span
              class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-bold shrink-0"
              :class="row.type === 'AGENT' ? 'bg-emerald-100 text-emerald-700' : row.inCap ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-400'"
            >
              {{ row.type === 'AGENT' ? 'A' : `L${row.level}` }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-bold text-slate-800 truncate flex items-center gap-1.5">
                {{ row.ancestor.name || row.ancestor.email }}
                <span
                  v-if="row.type === 'AGENT' && row.ancestor.isAgentAuthorized"
                  class="px-1.5 py-0 rounded text-[9px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"
                  title="Uprawnienia agenta nadane"
                >
                  AGENT ✓
                </span>
                <span
                  v-else-if="row.type === 'AGENT' && !row.ancestor.isAgentAuthorized"
                  class="px-1.5 py-0 rounded text-[9px] font-bold bg-red-100 text-red-700 border border-red-200"
                  title="Brak uprawnień agenta — % będzie 0"
                >
                  BRAK UPRAWNIEŃ
                </span>
                <span
                  v-else-if="!row.inCap"
                  class="px-1.5 py-0 rounded text-[9px] font-bold bg-slate-100 text-slate-500 border border-slate-200"
                  title="Poziom L3+ — poza chainem MLM"
                >
                  POZA CAP
                </span>
              </div>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="px-1.5 py-0 rounded text-[10px] font-bold border" :class="ROLE_BADGE[row.ancestor.role] || 'bg-slate-100 text-slate-600 border-slate-200'">{{ row.ancestor.role }}</span>
                <span class="text-[10px] text-slate-400 truncate">{{ row.ancestor.email }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <input
                v-model.number="ancestorPercents[row.ancestor.userSupabaseId]"
                type="number"
                min="0"
                max="100"
                step="0.01"
                :placeholder="row.inCap ? `default ${((row.defaultRate ?? 0) * 100).toFixed(0)}` : '0'"
                :disabled="!row.inCap && row.type !== 'AGENT'"
                class="w-20 border rounded p-1.5 text-xs focus:outline-none focus:border-stratton-gold bg-white text-right font-mono"
                :class="row.inCap ? 'border-slate-300' : 'border-slate-200 bg-slate-50 cursor-not-allowed'"
              />
              <span class="text-xs font-bold text-slate-700">%</span>
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
