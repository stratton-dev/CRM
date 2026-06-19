<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import AppIcon from '@/components/AppIcon.vue'
import TabHeader from '@/components/ui/TabHeader.vue'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'
import { useClientStore } from '@/stores/client'
import { useStructureStore } from '@/stores/structure'
import { useToastStore } from '@/stores/toast'
import { api } from '@/api/client'

interface SourceRef {
  userSupabaseId: string
  name: string
  role: string
}

interface ReceiverRef extends SourceRef {
  isAgentAuthorized: boolean
}

type ContextKind = 'SELF' | 'LEADOWIEC_CHAIN' | 'AGENT' | 'STANDARD_OVERRIDE'

interface SettlementRow {
  id: number
  distributionId: number
  period: string | null
  sourceKind: string  // MANUAL | EBS_WEBHOOK | OFFER_SIGNED
  sourceReference: string | null
  baseAmount: number
  rate: number
  amount: number
  level: number
  context: ContextKind
  source: SourceRef | null
  receiver: ReceiverRef | null
  createdAt: string | null
}

interface ByReceiverSummary {
  receiverUserSupabaseId: string
  name: string
  role: string | null
  isAgentAuthorized: boolean
  totalAmount: number
  selfAmount: number
  overrideAmount: number
  agentAmount: number
  leadowiecChainAmount: number
  distributionCount: number
}

interface ApiResponse {
  actor: {
    userSupabaseId: string
    name: string
    role: string
    isAgentAuthorized: boolean
  }
  scopeUserCount: number
  period: string | null
  totals: {
    grossPaid: number
    distributionCount: number
    itemCount: number
  }
  byReceiver: ByReceiverSummary[]
  rows: SettlementRow[]
}

const session = useSessionStore()
const toast = useToastStore()
const clientStore = useClientStore()
const structure = useStructureStore()

const { currentUser } = storeToRefs(session)
const { clients } = storeToRefs(clientStore)
const { users: structureUsers } = storeToRefs(structure)

const isManagerView = computed(() =>
  ['ADMIN', 'DIRECTOR', 'MANAGER'].includes(currentUser.value?.role || '')
)

const period = ref<string>(currentPeriod())
const isLoading = ref(false)
const data = ref<ApiResponse | null>(null)
const selectedReceiver = ref<string | null>(null)

const ROLE_BADGE: Record<string, string> = {
  ADMIN:     'bg-stratton-gold/10 text-amber-700 border-stratton-gold/30',
  DIRECTOR:  'bg-indigo-50 text-indigo-700 border-indigo-200',
  MANAGER:   'bg-sky-50 text-sky-700 border-sky-200',
  SALES:     'bg-emerald-50 text-emerald-700 border-emerald-200',
  LEADOWIEC: 'bg-violet-50 text-violet-700 border-violet-200',
  CLIENT_HR: 'bg-slate-100 text-slate-600 border-slate-200',
}

const CONTEXT_LABEL: Record<ContextKind, string> = {
  SELF: 'Własna prowizja',
  LEADOWIEC_CHAIN: 'Chain Leadowca',
  AGENT: 'Prowizja Agenta',
  STANDARD_OVERRIDE: 'Override Struktury',
}

const CONTEXT_BADGE: Record<ContextKind, string> = {
  SELF: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  LEADOWIEC_CHAIN: 'bg-violet-50 text-violet-700 border-violet-200',
  AGENT: 'bg-sky-50 text-sky-700 border-sky-200',
  STANDARD_OVERRIDE: 'bg-amber-50 text-amber-700 border-amber-200',
}

const SOURCE_BADGE: Record<string, string> = {
  MANUAL:       'bg-slate-100 text-slate-700 border-slate-200',
  EBS_WEBHOOK:  'bg-emerald-50 text-emerald-700 border-emerald-200',
  OFFER_SIGNED: 'bg-sky-50 text-sky-700 border-sky-200',
}

function currentPeriod() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function formatPLN(n: number) {
  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n || 0)
}

function formatPct(n: number) {
  return `${(n * 100).toFixed(2)}%`
}

async function loadSettlements() {
  isLoading.value = true
  try {
    const params: Record<string, string> = {}
    if (period.value) params.period = period.value
    const { data: response } = await api.get<ApiResponse>('/v1/commission/my-settlements', { params })
    data.value = response
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Nie udało się pobrać rozliczeń.')
    data.value = null
  } finally {
    isLoading.value = false
  }
}

// === KPI ===
const myReceiverSummary = computed<ByReceiverSummary | null>(() => {
  if (!data.value || !currentUser.value) return null
  return data.value.byReceiver.find((r) => r.receiverUserSupabaseId === currentUser.value!.id) || null
})

const myTotal = computed(() => myReceiverSummary.value?.totalAmount ?? 0)
const myCounts = computed(() => ({
  self: myReceiverSummary.value?.selfAmount ?? 0,
  override: myReceiverSummary.value?.overrideAmount ?? 0,
  agent: myReceiverSummary.value?.agentAmount ?? 0,
  leadowiecChain: myReceiverSummary.value?.leadowiecChainAmount ?? 0,
}))

const teamTotal = computed(() => data.value?.totals.grossPaid ?? 0)

const rowsForSelected = computed(() => {
  if (!data.value) return []
  if (!selectedReceiver.value) return data.value.rows
  return data.value.rows.filter(
    (r) => r.receiver?.userSupabaseId === selectedReceiver.value
  )
})

const myRows = computed(() => {
  if (!data.value || !currentUser.value) return []
  return data.value.rows.filter((r) => r.receiver?.userSupabaseId === currentUser.value!.id)
})

const teamSummary = computed(() =>
  (data.value?.byReceiver ?? [])
    .filter((r) => r.receiverUserSupabaseId !== currentUser.value?.id)
    .sort((a, b) => b.totalAmount - a.totalAmount)
)

function selectReceiver(uuid: string | null) {
  selectedReceiver.value = uuid
}

// === Signed contracts (left as-is) ===
const signedContractsList = computed(() => {
  const me = currentUser.value
  if (!me) return []
  const allClients = Array.isArray(clients.value) ? clients.value : []
  const signed = allClients.filter((c) => c.status === 'SIGNED')
  if (me.role === 'ADMIN') return signed
  if (['MANAGER', 'DIRECTOR'].includes(me.role)) {
    const teamIds = [me.id, ...structure.getSubtreeUserIds(me.id)]
    return signed.filter((c) => !c.ownerId || teamIds.includes(c.ownerId))
  }
  return signed.filter((c) => c.ownerId === me.id)
})

const signedContractsWithDetails = computed(() => {
  const users = structureUsers.value || []
  return signedContractsList.value.map((client) => {
    const owner = users.find((u) => u.id === client.ownerId)
    const opiekunDisplay = owner
      ? `${owner.name} (${owner.hierarchicalId || 'Brak ID'})`
      : 'Nieprzypisany'
    return { ...client, opiekunDisplay }
  })
})

// === CSV export ===
function exportToCsv() {
  if (!data.value || data.value.rows.length === 0) {
    toast.warning('Brak danych do eksportu.')
    return
  }
  const headers = [
    'Okres', 'Distribution', 'Źródło', 'Źródło-ref',
    'Generuje (kto)', 'Rola', 'Odbiorca', 'Rola odbiorcy',
    'Poziom', 'Kontekst', 'Baza (zł)', 'Stawka', 'Wypłata (zł)', 'Data',
  ]
  const lines = data.value.rows.map((r) => [
    r.period ?? '',
    `#${r.distributionId}`,
    r.sourceKind,
    r.sourceReference ?? '',
    `"${r.source?.name ?? ''}"`,
    r.source?.role ?? '',
    `"${r.receiver?.name ?? ''}"`,
    r.receiver?.role ?? '',
    r.level,
    CONTEXT_LABEL[r.context],
    r.baseAmount.toFixed(2),
    formatPct(r.rate),
    r.amount.toFixed(2),
    r.createdAt ? new Date(r.createdAt).toLocaleDateString('pl-PL') : '',
  ].join(','))
  const content = '﻿' + [headers.join(','), ...lines].join('\n')
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `rozliczenia_${period.value || 'all'}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

watch(period, loadSettlements)

onMounted(async () => {
  if (!structureUsers.value || structureUsers.value.length === 0) {
    await structure.fetchStructure()
  }
  await loadSettlements()
})
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)]">

    <TabHeader icon="banknotes" title="Rozliczenia i Prowizje">
      <template #actions>
        <input
          v-model="period"
          type="month"
          class="px-3 py-1.5 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-stratton-gold"
        />
        <button
          type="button"
          class="flex items-center gap-2 px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold transition-colors disabled:opacity-60"
          :disabled="isLoading"
          @click="loadSettlements"
        >
          <AppIcon name="refresh" class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
          Odśwież
        </button>
        <button
          type="button"
          class="flex items-center gap-2 px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-sm transition-colors"
          @click="exportToCsv"
        >
          <AppIcon name="file-invoice" class="w-4 h-4" />
          Eksportuj CSV
        </button>
      </template>
    </TabHeader>

    <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">

      <!-- ═══════════════════════ KPI === osobiste ═══════════════════════ -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 p-4 rounded-card shadow-sm">
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 flex items-center justify-between">
            <span>Suma prowizji (okres)</span>
            <span class="text-stratton-gold">{{ period }}</span>
          </div>
          <div class="text-3xl font-bold text-slate-800 font-serif">
            {{ formatPLN(myTotal) }}
          </div>
          <div class="text-[10px] text-slate-500 mt-1">{{ myReceiverSummary?.distributionCount ?? 0 }} pozycji</div>
        </div>

        <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-card shadow-sm">
          <div class="text-[10px] font-bold text-emerald-700 uppercase tracking-widest mb-2">Własna prowizja</div>
          <div class="text-2xl font-bold text-emerald-900 font-serif">{{ formatPLN(myCounts.self) }}</div>
          <div class="text-[10px] text-emerald-700 mt-1">z dealu który sam zamknąłem/-łam</div>
        </div>

        <div class="bg-amber-50 border border-amber-200 p-4 rounded-card shadow-sm">
          <div class="text-[10px] font-bold text-amber-700 uppercase tracking-widest mb-2">Override Struktury</div>
          <div class="text-2xl font-bold text-amber-900 font-serif">{{ formatPLN(myCounts.override) }}</div>
          <div class="text-[10px] text-amber-700 mt-1">z dealów osób w moim pionie</div>
        </div>

        <div
          class="border p-4 rounded-card shadow-sm"
          :class="currentUser?.role === 'LEADOWIEC' ? 'bg-violet-50 border-violet-200' : 'bg-sky-50 border-sky-200'"
        >
          <div
            class="text-[10px] font-bold uppercase tracking-widest mb-2"
            :class="currentUser?.role === 'LEADOWIEC' ? 'text-violet-700' : 'text-sky-700'"
          >
            {{ currentUser?.role === 'LEADOWIEC' ? 'Chain Leadowca' : 'Prowizja Agenta' }}
          </div>
          <div
            class="text-2xl font-bold font-serif"
            :class="currentUser?.role === 'LEADOWIEC' ? 'text-violet-900' : 'text-sky-900'"
          >
            {{ formatPLN(currentUser?.role === 'LEADOWIEC' ? myCounts.leadowiecChain : myCounts.agent) }}
          </div>
          <div
            class="text-[10px] mt-1"
            :class="currentUser?.role === 'LEADOWIEC' ? 'text-violet-700' : 'text-sky-700'"
          >
            {{
              currentUser?.role === 'LEADOWIEC'
                ? 'L1=5%, L2=2% od rekrutów'
                : '10% z dealu mojego leadowca'
            }}
          </div>
        </div>
      </div>

      <!-- ═══════════════════════ KPI === zespół ═══════════════════════ -->
      <div v-if="isManagerView" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-slate-50 border border-slate-200 p-4 rounded-card shadow-sm">
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Łącznie zespół (okres)</div>
          <div class="text-3xl font-bold text-slate-800 font-serif">{{ formatPLN(teamTotal) }}</div>
          <div class="text-[10px] text-slate-500 mt-1">{{ data?.totals.itemCount ?? 0 }} items / {{ data?.totals.distributionCount ?? 0 }} distributions</div>
        </div>
        <div class="bg-slate-50 border border-slate-200 p-4 rounded-card shadow-sm">
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Osób w scope</div>
          <div class="text-3xl font-bold text-slate-800 font-serif">{{ data?.scopeUserCount ?? 0 }}</div>
        </div>
        <div class="bg-white border border-slate-200 p-4 rounded-card shadow-sm">
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Twoja rola</div>
          <div class="text-2xl font-bold text-slate-800 font-serif flex items-center gap-2">
            {{ currentUser?.role }}
            <span
              v-if="data?.actor.isAgentAuthorized"
              class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"
            >
              AGENT ✓
            </span>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════ TEAM SUMMARY (manager+) ═══════════════════════ -->
      <div
        v-if="isManagerView && teamSummary.length > 0 && !selectedReceiver"
        class="bg-white shadow-sm rounded-card border border-slate-200 overflow-hidden"
      >
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center gap-3">
          <div class="w-1.5 h-6 bg-stratton-gold rounded-full"></div>
          <h3 class="font-bold text-slate-800 uppercase text-xs tracking-wider">Podsumowanie zespołu — okres {{ period }}</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Osoba</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Rola</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Własna</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Override</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Agent</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Chain Lead.</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Łącznie</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="row in teamSummary"
                :key="row.receiverUserSupabaseId"
                class="hover:bg-slate-50 cursor-pointer transition-colors"
                @click="selectReceiver(row.receiverUserSupabaseId)"
              >
                <td class="px-6 py-3 text-sm font-bold text-slate-800 flex items-center gap-2">
                  {{ row.name }}
                  <span
                    v-if="row.isAgentAuthorized"
                    class="px-1.5 py-0 rounded text-[9px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"
                  >
                    AGENT
                  </span>
                </td>
                <td class="px-6 py-3">
                  <span
                    class="px-2 py-0.5 rounded text-xs font-bold border"
                    :class="ROLE_BADGE[row.role || ''] || 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ row.role }}
                  </span>
                </td>
                <td class="px-6 py-3 text-right text-sm text-emerald-700 font-mono">{{ formatPLN(row.selfAmount) }}</td>
                <td class="px-6 py-3 text-right text-sm text-amber-700 font-mono">{{ formatPLN(row.overrideAmount) }}</td>
                <td class="px-6 py-3 text-right text-sm text-sky-700 font-mono">{{ formatPLN(row.agentAmount) }}</td>
                <td class="px-6 py-3 text-right text-sm text-violet-700 font-mono">{{ formatPLN(row.leadowiecChainAmount) }}</td>
                <td class="px-6 py-3 text-right text-sm font-bold text-slate-900 font-mono">{{ formatPLN(row.totalAmount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ═══════════════════════ DETAILS TABLE ═══════════════════════ -->
      <div class="bg-white shadow-sm rounded-card border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-1.5 h-6 bg-stratton-gold rounded-full"></div>
            <h3 class="font-bold text-slate-800 uppercase text-xs tracking-wider">
              <template v-if="selectedReceiver">
                Szczegóły dla:
                {{ teamSummary.find((r) => r.receiverUserSupabaseId === selectedReceiver)?.name || '...' }}
              </template>
              <template v-else>
                {{ isManagerView ? 'Wszystkie pozycje (mój scope)' : 'Moje rozliczenia' }}
              </template>
            </h3>
          </div>
          <button
            v-if="selectedReceiver"
            type="button"
            class="text-xs text-stratton-gold hover:text-amber-700 font-bold flex items-center gap-1 transition-colors uppercase tracking-widest"
            @click="selectReceiver(null)"
          >
            <AppIcon name="arrow-left" class="w-3 h-3" />
            Wróć do zespołu
          </button>
        </div>

        <div v-if="isLoading" class="p-10 text-center text-slate-400 text-sm">Ładuje...</div>
        <div v-else-if="!rowsForSelected.length" class="p-10 text-center text-slate-400 text-sm italic">
          Brak rozliczeń w tym okresie.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full divide-y divide-slate-100">
            <thead class="bg-slate-50 sticky top-0 z-10">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Distribution</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Źródło dealu</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Odbiorca</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Kontekst</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Baza</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Stawka</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Wypłata</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in (selectedReceiver ? rowsForSelected : (isManagerView ? rowsForSelected : myRows))" :key="row.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 text-xs">
                  <div class="font-mono text-slate-500">#{{ row.distributionId }}</div>
                  <span
                    class="px-1.5 py-0 rounded text-[9px] font-bold border mt-0.5 inline-block"
                    :class="SOURCE_BADGE[row.sourceKind] || 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ row.sourceKind }}
                  </span>
                  <div v-if="row.sourceReference" class="text-[10px] text-slate-400 mt-0.5">{{ row.sourceReference }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-xs font-bold text-slate-800">{{ row.source?.name || '—' }}</div>
                  <span
                    v-if="row.source?.role"
                    class="px-1.5 py-0 rounded text-[9px] font-bold border mt-0.5 inline-block"
                    :class="ROLE_BADGE[row.source.role] || 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ row.source.role }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="text-xs font-bold text-slate-800">{{ row.receiver?.name || '—' }}</div>
                  <span
                    v-if="row.receiver?.role"
                    class="px-1.5 py-0 rounded text-[9px] font-bold border mt-0.5 inline-block"
                    :class="ROLE_BADGE[row.receiver.role] || 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ row.receiver.role }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="px-1.5 py-0.5 rounded text-[10px] font-bold border"
                    :class="CONTEXT_BADGE[row.context]"
                  >
                    {{ CONTEXT_LABEL[row.context] }}
                  </span>
                  <div class="text-[10px] text-slate-400 mt-0.5">L{{ row.level }}</div>
                </td>
                <td class="px-4 py-3 text-right text-xs text-slate-700 font-mono">{{ formatPLN(row.baseAmount) }}</td>
                <td class="px-4 py-3 text-right text-xs text-slate-700 font-mono">{{ formatPct(row.rate) }}</td>
                <td class="px-4 py-3 text-right text-sm text-emerald-700 font-bold font-mono">{{ formatPLN(row.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ═══════════════════════ PODPISANE UMOWY (kept) ═══════════════════════ -->
      <div class="bg-white shadow-sm rounded-card border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-1.5 h-6 bg-stratton-gold rounded-full"></div>
            <h3 class="font-bold text-slate-800 uppercase text-xs tracking-wider">Podpisane umowy</h3>
          </div>
          <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full uppercase tracking-widest">
            {{ signedContractsWithDetails.length }}
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Klient</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Opiekun</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Miasto</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Data umowy</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="client in signedContractsWithDetails" :key="client.id" class="hover:bg-slate-50">
                <td class="px-6 py-3 text-sm">
                  <div class="font-bold text-slate-800">{{ client.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ client.nip }}</div>
                </td>
                <td class="px-6 py-3 text-sm text-slate-600">{{ client.opiekunDisplay }}</td>
                <td class="px-6 py-3 text-sm text-slate-600 italic">{{ client.city || '—' }}</td>
                <td class="px-6 py-3 text-sm text-slate-500">{{ client.contractSignedDate || '—' }}</td>
              </tr>
              <tr v-if="signedContractsWithDetails.length === 0">
                <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400 italic">Brak podpisanych umów.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</template>
