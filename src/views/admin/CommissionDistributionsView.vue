<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useStructureStore } from '@/stores/structure'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import { api } from '@/api/client'
import TabHeader from '@/components/ui/TabHeader.vue'
import AppIcon from '@/components/AppIcon.vue'
import type { User } from '@/types/models'

interface PreviewItem {
  level: number
  userSupabaseId: string
  name: string
  role: string
  rate: number
  amount: number
  skipped: boolean
  skipReason: string | null
}

interface PreviewResponse {
  sourceUserSupabaseId: string
  baseAmount: number
  items: PreviewItem[]
  totals: { paidOut: number; levels: number }
}

interface DistributionItem {
  id: number
  level: number
  receiverUserSupabaseId: string
  receiverRoleAtTime: string
  rate: number
  amount: number
}

interface Distribution {
  id: number
  sourceUserSupabaseId: string
  baseAmount: number
  period: string | null
  source: string
  sourceReference: string | null
  note: string | null
  createdAt: string
  items: DistributionItem[]
}

const structure = useStructureStore()
const session = useSessionStore()
const toast = useToastStore()
const { users } = storeToRefs(structure)
const { currentUser } = storeToRefs(session)

const isAdmin = computed(() => currentUser.value?.role === 'ADMIN')

// Form state
const sourceUserId = ref<string>('')
const baseAmount = ref<number>(10000)
const period = ref<string>(currentMonthPeriod())
const note = ref<string>('')

// Results
const preview = ref<PreviewResponse | null>(null)
const isPreviewing = ref(false)
const isSaving = ref(false)

// History
const history = ref<Distribution[]>([])
const isLoadingHistory = ref(false)
const filterPeriod = ref<string>('')
const filterSourceUser = ref<string>('')

const ROLE_BADGE: Record<string, string> = {
  ADMIN:     'bg-stratton-gold/10 text-amber-700 border-stratton-gold/30',
  DIRECTOR:  'bg-indigo-50 text-indigo-700 border-indigo-200',
  MANAGER:   'bg-sky-50 text-sky-700 border-sky-200',
  SALES:     'bg-emerald-50 text-emerald-700 border-emerald-200',
  LEADOWIEC: 'bg-violet-50 text-violet-700 border-violet-200',
  CLIENT_HR: 'bg-slate-100 text-slate-600 border-slate-200',
}

const SOURCE_BADGE: Record<string, string> = {
  MANUAL:       'bg-slate-100 text-slate-700 border-slate-200',
  EBS_WEBHOOK:  'bg-emerald-50 text-emerald-700 border-emerald-200',
  OFFER_SIGNED: 'bg-sky-50 text-sky-700 border-sky-200',
}

function currentMonthPeriod() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function formatPLN(n: number) {
  return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN', maximumFractionDigits: 2 }).format(n || 0)
}

function formatPercent(n: number) {
  return `${(n * 100).toFixed(2)}%`
}

const selectableUsers = computed(() => {
  const list = Array.isArray(users.value) ? users.value : []
  return list
    .filter((u) => !u.isRemovedFromStructure && u.role !== 'CLIENT_HR' && u.role !== 'ADMIN')
    .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
})

const userById = computed(() => {
  const map = new Map<string, User>()
  for (const u of users.value || []) map.set(u.id, u)
  return map
})

async function loadHistory() {
  isLoadingHistory.value = true
  try {
    const params: Record<string, string> = {}
    if (filterPeriod.value) params.period = filterPeriod.value
    if (filterSourceUser.value) params.source_user_supabase_id = filterSourceUser.value
    const { data } = await api.get('/v1/commission/distributions', { params })
    history.value = Array.isArray(data) ? data : []
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Nie udało się pobrać historii.')
  } finally {
    isLoadingHistory.value = false
  }
}

async function runPreview() {
  if (!sourceUserId.value) {
    toast.error('Wybierz handlowca.')
    return
  }
  if (!baseAmount.value || baseAmount.value <= 0) {
    toast.error('Kwota bazowa musi być większa od 0.')
    return
  }
  isPreviewing.value = true
  try {
    const { data } = await api.post<PreviewResponse>('/v1/commission/preview', {
      source_user_supabase_id: sourceUserId.value,
      base_amount: baseAmount.value,
    })
    preview.value = data
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Symulacja nie udała się.')
  } finally {
    isPreviewing.value = false
  }
}

async function saveDistribution() {
  if (!preview.value) return
  isSaving.value = true
  try {
    await api.post('/v1/commission/distributions', {
      source_user_supabase_id: preview.value.sourceUserSupabaseId,
      base_amount: preview.value.baseAmount,
      period: period.value || null,
      source: 'MANUAL',
      note: note.value || null,
    })
    toast.success('Distribution zapisana.')
    preview.value = null
    sourceUserId.value = ''
    baseAmount.value = 10000
    note.value = ''
    await loadHistory()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Zapis nie powiódł się.')
  } finally {
    isSaving.value = false
  }
}

async function deleteDistribution(d: Distribution) {
  if (!confirm(`Usunąć distribution #${d.id} (${formatPLN(d.baseAmount)})?`)) return
  try {
    await api.delete(`/v1/commission/distributions/${d.id}`)
    toast.success('Usunięto.')
    await loadHistory()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Usunięcie nie powiodło się.')
  }
}

function totalDistributed(d: Distribution) {
  return d.items.reduce((acc, it) => acc + Number(it.amount), 0)
}

function sourceUserName(d: Distribution): string {
  const u = (users.value || []).find((u) => u.id === d.sourceUserSupabaseId)
  return u ? (u.name || u.email) : d.sourceUserSupabaseId
}

function receiverUserName(item: DistributionItem): string {
  const u = (users.value || []).find((u) => u.id === item.receiverUserSupabaseId)
  return u ? (u.name || u.email) : item.receiverUserSupabaseId
}

const expandedDistributions = ref<Set<number>>(new Set())
function toggleExpand(id: number) {
  if (expandedDistributions.value.has(id)) {
    expandedDistributions.value.delete(id)
  } else {
    expandedDistributions.value.add(id)
  }
}

onMounted(async () => {
  if (!users.value || users.value.length === 0) {
    await structure.fetchStructure()
  }
  await loadHistory()
})
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)]">
    <TabHeader icon="chart-bar" title="Prowizje Override">
      <template #actions>
        <span class="text-xs font-bold text-slate-500 px-3 py-1 bg-slate-100 rounded-md">
          ADMIN ONLY
        </span>
      </template>
    </TabHeader>

    <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">

      <div v-if="!isAdmin" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <p class="text-red-700 font-bold">Brak uprawnień. Tylko ADMIN może operować na prowizjach.</p>
      </div>

      <template v-else>
        <!-- SIMULATOR -->
        <div class="bg-white rounded-card shadow-card border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50/50 to-transparent">
            <h3 class="font-bold text-slate-800 text-lg">Symulator Prowizji</h3>
            <p class="text-xs text-slate-500 mt-0.5">Sprawdź jak rozdzieli się prowizja od dealu zamkniętego przez handlowca</p>
          </div>
          <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-5">
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Handlowiec / sub-member</label>
              <select v-model="sourceUserId" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold bg-white cursor-pointer">
                <option value="">— wybierz —</option>
                <option v-for="u in selectableUsers" :key="u.id" :value="u.id">
                  {{ u.name || u.email }} ({{ u.role }})
                </option>
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Kwota bazowa (zł)</label>
              <input v-model.number="baseAmount" type="number" min="0" step="100" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Okres</label>
              <input v-model="period" type="month" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div class="md:col-span-2 flex items-end">
              <button
                type="button"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-sm font-bold shadow-sm transition disabled:opacity-60"
                :disabled="isPreviewing"
                @click="runPreview"
              >
                <AppIcon name="search" class="w-4 h-4" />
                {{ isPreviewing ? 'Liczy...' : 'Podgląd' }}
              </button>
            </div>
          </div>

          <!-- PREVIEW RESULT -->
          <div v-if="preview" class="px-6 pb-6">
            <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h4 class="text-sm font-bold text-slate-800">Rozdział prowizji</h4>
                  <p class="text-xs text-slate-500">Kwota bazowa: <strong class="text-slate-800">{{ formatPLN(preview.baseAmount) }}</strong></p>
                </div>
                <div class="text-right">
                  <p class="text-xs text-slate-500 uppercase font-bold">Suma wypłat</p>
                  <p class="text-xl font-bold text-emerald-700">{{ formatPLN(preview.totals.paidOut) }}</p>
                  <p class="text-xs text-slate-500">{{ preview.totals.levels }} poziomów</p>
                </div>
              </div>

              <table class="w-full">
                <thead>
                  <tr class="text-xs font-bold text-slate-500 uppercase border-b border-slate-200">
                    <th class="text-left py-2">Poziom</th>
                    <th class="text-left py-2">Osoba</th>
                    <th class="text-left py-2">Rola</th>
                    <th class="text-right py-2">Stawka</th>
                    <th class="text-right py-2">Wypłata</th>
                  </tr>
                </thead>
                <tbody class="text-sm">
                  <tr v-for="item in preview.items" :key="item.userSupabaseId" :class="{ 'opacity-50': item.skipped }">
                    <td class="py-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold"
                            :class="item.level === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                        {{ item.level }}
                      </span>
                      <span v-if="item.level === 0" class="text-xs text-slate-500 ml-1">(self)</span>
                    </td>
                    <td class="py-2 font-medium text-slate-800">{{ item.name }}</td>
                    <td class="py-2">
                      <span class="px-2 py-0.5 rounded text-xs font-bold border" :class="ROLE_BADGE[item.role] || 'bg-slate-100 text-slate-600 border-slate-200'">{{ item.role }}</span>
                    </td>
                    <td class="py-2 text-right font-mono text-slate-700">{{ formatPercent(item.rate) }}</td>
                    <td class="py-2 text-right font-bold" :class="item.amount > 0 ? 'text-emerald-700' : 'text-slate-400'">
                      {{ formatPLN(item.amount) }}
                      <span v-if="item.skipped" class="text-xs text-red-500 block">(pominięty: {{ item.skipReason }})</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-slate-300">
                    <td colspan="4" class="py-2 text-right text-xs font-bold uppercase text-slate-500">Razem do wypłaty</td>
                    <td class="py-2 text-right font-bold text-emerald-700 text-lg">{{ formatPLN(preview.totals.paidOut) }}</td>
                  </tr>
                  <tr>
                    <td colspan="4" class="py-1 text-right text-xs font-bold uppercase text-slate-500">Zostaje dla Stratton Prime</td>
                    <td class="py-1 text-right font-bold text-slate-700">{{ formatPLN(preview.baseAmount - preview.totals.paidOut) }}</td>
                  </tr>
                </tfoot>
              </table>

              <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div class="md:col-span-2">
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Notatka (opcjonalnie)</label>
                  <input v-model="note" type="text" placeholder="np. faktura EBS #2025-10-001" class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:outline-none focus:border-stratton-gold" />
                </div>
                <div>
                  <button
                    type="button"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-stratton-gold hover:bg-yellow-600 text-white rounded-lg text-sm font-bold shadow-sm transition disabled:opacity-60"
                    :disabled="isSaving"
                    @click="saveDistribution"
                  >
                    <AppIcon name="check" class="w-4 h-4" />
                    {{ isSaving ? 'Zapisuje...' : 'Zapisz Distribution' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- HISTORY -->
        <div class="bg-white rounded-card shadow-card border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3 justify-between">
            <div>
              <h3 class="font-bold text-slate-800 text-lg">Historia Distributions</h3>
              <p class="text-xs text-slate-500 mt-0.5">{{ history.length }} rekordów</p>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="filterPeriod" type="month" placeholder="Okres" class="px-3 py-1.5 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-stratton-gold" />
              <select v-model="filterSourceUser" class="px-3 py-1.5 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-stratton-gold cursor-pointer">
                <option value="">Wszyscy handlowcy</option>
                <option v-for="u in selectableUsers" :key="u.id" :value="u.id">{{ u.name || u.email }}</option>
              </select>
              <button type="button" class="px-3 py-1.5 text-sm font-bold bg-slate-100 hover:bg-slate-200 rounded-lg transition" @click="loadHistory">
                Filtruj
              </button>
            </div>
          </div>

          <div class="max-h-[500px] overflow-y-auto">
            <div v-if="isLoadingHistory" class="p-10 text-center text-slate-400 text-sm">Ładuje...</div>
            <div v-else-if="history.length === 0" class="p-10 text-center text-slate-400 text-sm">Brak distributions w wybranym filtrze.</div>
            <table v-else class="w-full divide-y divide-slate-100">
              <thead class="bg-slate-50 sticky top-0 z-10">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">#</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Handlowiec</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Okres</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Źródło</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Baza</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Wypłacono</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Akcje</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <template v-for="d in history" :key="d.id">
                  <tr class="hover:bg-slate-50 cursor-pointer" @click="toggleExpand(d.id)">
                    <td class="px-4 py-3 text-xs font-mono text-slate-500">#{{ d.id }}</td>
                    <td class="px-4 py-3 text-sm font-bold text-slate-800">{{ sourceUserName(d) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ d.period || '—' }}</td>
                    <td class="px-4 py-3">
                      <span class="px-2 py-0.5 rounded text-xs font-bold border" :class="SOURCE_BADGE[d.source] || 'bg-slate-100 text-slate-600 border-slate-200'">
                        {{ d.source }}
                      </span>
                      <span v-if="d.sourceReference" class="text-xs text-slate-400 ml-2">({{ d.sourceReference }})</span>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-mono text-slate-700">{{ formatPLN(d.baseAmount) }}</td>
                    <td class="px-4 py-3 text-right text-sm font-bold text-emerald-700">{{ formatPLN(totalDistributed(d)) }}</td>
                    <td class="px-4 py-3 text-right">
                      <button type="button" class="text-red-500 hover:text-red-700 text-xs font-bold" @click.stop="deleteDistribution(d)">
                        Usuń
                      </button>
                    </td>
                  </tr>
                  <tr v-if="expandedDistributions.has(d.id)" class="bg-slate-50/60">
                    <td colspan="7" class="px-6 py-4">
                      <div class="bg-white rounded border border-slate-200 overflow-hidden">
                        <table class="w-full">
                          <thead class="bg-slate-100">
                            <tr class="text-xs font-bold text-slate-500 uppercase">
                              <th class="text-left px-3 py-2">Poziom</th>
                              <th class="text-left px-3 py-2">Odbiorca</th>
                              <th class="text-left px-3 py-2">Rola w czasie</th>
                              <th class="text-right px-3 py-2">Stawka</th>
                              <th class="text-right px-3 py-2">Wypłata</th>
                            </tr>
                          </thead>
                          <tbody class="text-sm">
                            <tr v-for="it in d.items" :key="it.id" class="border-t border-slate-100">
                              <td class="px-3 py-2">{{ it.level }}{{ it.level === 0 ? ' (self)' : '' }}</td>
                              <td class="px-3 py-2 font-medium">{{ receiverUserName(it) }}</td>
                              <td class="px-3 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold border" :class="ROLE_BADGE[it.receiverRoleAtTime] || 'bg-slate-100 text-slate-600 border-slate-200'">{{ it.receiverRoleAtTime }}</span>
                              </td>
                              <td class="px-3 py-2 text-right font-mono">{{ formatPercent(it.rate) }}</td>
                              <td class="px-3 py-2 text-right font-bold text-emerald-700">{{ formatPLN(it.amount) }}</td>
                            </tr>
                          </tbody>
                        </table>
                        <div v-if="d.note" class="px-3 py-2 bg-amber-50 border-t border-amber-200 text-xs text-amber-800">
                          <strong>Notatka:</strong> {{ d.note }}
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
