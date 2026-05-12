<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/AppIcon.vue'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'
import { useFinanceStore } from '@/stores/finance'
import { useClientStore } from '@/stores/client'
import { useStructureStore } from '@/stores/structure'
import type { SettlementRow } from '@/stores/finance'
import { useToastStore } from '@/stores/toast'

interface SummaryRow {
  repId: string
  repName: string
  totalCommission: number
  directCommission: number
  overrideCommission: number
  invoiceCount: number
}

const session = useSessionStore()
const finance = useFinanceStore()
const toast = useToastStore()
const clientStore = useClientStore()
const structure = useStructureStore()

const selectedRep = ref<SummaryRow | null>(null)

const { currentUser } = storeToRefs(session)
const { clients } = storeToRefs(clientStore)
const { users: structureUsers } = storeToRefs(structure)

const isManagerView = computed(() => ['ADMIN', 'DIRECTOR', 'MANAGER'].includes(currentUser.value?.role || ''))

const settlementRows = computed<SettlementRow[]>(() => {
  const me = currentUser.value
  if (!me) return []
  return finance.getSettlementsForUser(me.id, me.role)
})

const isDirectCommission = (type: SettlementRow['commissionType']) =>
  type === 'DIRECT_FIRST' || type === 'DIRECT_RENEWAL'

const summaryData = computed(() => {
  if (!isManagerView.value) return []

  const directRows = settlementRows.value.filter((row) => isDirectCommission(row.commissionType))
  const groupedByRep = new Map<string, SummaryRow>()

  directRows.forEach((row) => {
    if (!groupedByRep.has(row.repId)) {
      groupedByRep.set(row.repId, {
        repId: row.repId,
        repName: row.repName,
        totalCommission: 0,
        directCommission: 0,
        overrideCommission: 0,
        invoiceCount: 0,
      })
    }
    const summary = groupedByRep.get(row.repId)!
    summary.directCommission += row.repCommission
    summary.invoiceCount += 1
  })

  const myId = currentUser.value?.id
  const myOverrides = settlementRows.value.filter((row) => row.commissionType === 'OVERRIDE')
  if (myOverrides.length > 0 && myId) {
    if (!groupedByRep.has(myId)) {
      groupedByRep.set(myId, {
        repId: myId,
        repName: currentUser.value?.name || 'Ja',
        totalCommission: 0,
        directCommission: 0,
        overrideCommission: 0,
        invoiceCount: 0,
      })
    }
    myOverrides.forEach((row) => {
      groupedByRep.get(myId)!.overrideCommission += row.repCommission
    })
  }

  const result = Array.from(groupedByRep.values())

  result.forEach((row) => {
    // For the logged-in user or any manager in the list
    if (row.repId === currentUser.value?.id) {
       const totalWithStructure = finance.getStructureTotalCommission(row.repId, currentUser.value.role)
       const selfOnly = finance.getStructureTotalCommission(row.repId, 'SALES') // Treat as sales to get only direct
       
       row.directCommission = selfOnly
       row.overrideCommission = totalWithStructure - selfOnly
       row.totalCommission = totalWithStructure
    } else {
       row.totalCommission = row.directCommission + row.overrideCommission
    }
  })

  return result.sort((a, b) => b.totalCommission - a.totalCommission)
})

const detailsForSelectedRep = computed(() => {
  if (!selectedRep.value) return []
  return settlementRows.value.filter(
    (row) => row.repId === selectedRep.value?.repId && isDirectCommission(row.commissionType)
  )
})

const totalCommission = computed(() => settlementRows.value.reduce((sum, row) => sum + row.repCommission, 0))

const structureTotalCommission = computed(() => {
  const me = currentUser.value
  if (!me) return 0
  return finance.getStructureTotalCommission(me.id, me.role)
})

const selectRepForDetails = (rep: SummaryRow | null) => {
  selectedRep.value = rep
}

const exportToCsv = () => {
  const rows = settlementRows.value
  if (rows.length === 0) {
    toast.warning('Brak danych do eksportu.')
    return
  }

  const header = ['Faktura', 'Data', 'Klient', 'Handlowiec', 'Typ Prowizji', 'Opłata Serwisowa', 'Stawka', 'Prowizja', 'Status']
  const csvRows = rows.map((row) => {
    const rate = `${((row.repCommission / row.serviceFee) * 100).toFixed(0)}%`
    const type = row.commissionType === 'OVERRIDE' ? 'Struktura' : row.commissionType === 'DIRECT_RENEWAL' ? 'Odnowienie' : 'Pierwsza'
    return [
      row.invoiceNumber,
      new Date(row.issueDate).toLocaleDateString(),
      `"${row.clientName}"`,
      `"${row.repName}"`,
      type,
      row.serviceFee.toFixed(2),
      rate,
      row.repCommission.toFixed(2),
      row.status,
    ].join(',')
  })

  const csvContent = '\uFEFF' + [header.join(','), ...csvRows].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `rozliczenia_stratton_${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const signedContractsList = computed(() => {
  const me = currentUser.value
  if (!me) return []
  
  const allClients = Array.isArray(clients.value) ? clients.value : []
  const signed = allClients.filter(c => c.status === 'SIGNED')
  
  if (me.role === 'ADMIN') {
    return signed
  }
  
  if (['MANAGER', 'DIRECTOR'].includes(me.role)) {
    const teamIds = [me.id, ...structure.getSubtreeUserIds(me.id)]
    return signed.filter(c => !c.ownerId || teamIds.includes(c.ownerId))
  }
  
  // Sales / Default
  return signed.filter(c => c.ownerId === me.id)
})

const signedContractsWithDetails = computed(() => {
  const users = structureUsers.value || []
  return signedContractsList.value.map(client => {
    const owner = users.find(u => u.id === client.ownerId)
    const opiekunDisplay = owner ? `${owner.name} (${owner.hierarchicalId || 'Brak ID'})` : 'Nieprzypisany'
    return { ...client, opiekunDisplay }
  })
})
</script>

<template>
  <div class="space-y-3 md:space-y-6">
    <div class="rounded-card p-4 md:p-8 mb-3 md:mb-8 shadow-card-hover border relative overflow-hidden group" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%); border-color: #003366;">
      <!-- Decor -->
      <div class="absolute top-0 right-0 w-64 h-64 bg-stratton-800 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-6">
        <div class="flex items-center gap-3 md:gap-6">
          <RouterLink to="/app/dashboard" class="hidden md:inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-md text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group">
             <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
          </RouterLink>

          <div>
            <h1 class="text-xl md:text-4xl font-serif font-bold text-white tracking-wide leading-tight">Rozliczenia i Prowizje</h1>
            <div v-if="isManagerView && selectedRep" class="mt-2 flex items-center">
              <button
                type="button"
                class="text-sm text-stratton-gold hover:text-white font-bold flex items-center transition-colors uppercase tracking-widest text-[10px]"
                @click="selectRepForDetails(null)"
              >
                <AppIcon name="chevron-left" class="w-3 h-3 mr-1" />
                Wróć do podsumowania zespołu
              </button>
            </div>
            <p v-else class="text-slate-400 max-w-xl text-xs md:text-lg mt-1 tracking-tight">Przeglądaj swoje wynagrodzenia i prowizje.</p>
          </div>
        </div>

        <div class="flex items-center gap-4 self-start md:self-auto">
          <button
            type="button"
            class="flex items-center gap-2 px-4 md:px-6 py-2 md:py-2.5 text-sm bg-green-600/90 hover:bg-green-600 text-white rounded-xl transition-all font-bold shadow-lg shadow-green-900/20 hover:scale-105 active:scale-95"
            @click="exportToCsv"
          >
            <AppIcon name="file-invoice" class="w-4 h-4" />
            <span>Eksportuj CSV</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mt-6 md:mt-10 relative z-10">
        <div class="bg-white/10 backdrop-blur-md p-4 md:p-6 rounded-2xl border border-white/10 shadow-xl group/card hover:bg-white/15 transition-all">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-hover/card:text-stratton-gold">Suma Prowizji (Ten miesiąc)</div>
          <div class="text-xl md:text-3xl font-bold text-white font-serif">{{ structureTotalCommission.toFixed(2) }} <span class="text-stratton-gold text-lg">PLN</span></div>
        </div>
        <div class="bg-white/10 backdrop-blur-md p-4 md:p-6 rounded-2xl border border-white/10 shadow-xl group/card hover:bg-white/15 transition-all">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-hover/card:text-emerald-400">Wypłacono</div>
          <div class="text-xl md:text-3xl font-bold text-white font-serif">0.00 <span class="text-emerald-400 text-lg">PLN</span></div>
        </div>
        <div class="bg-white/10 backdrop-blur-md p-4 md:p-6 rounded-2xl border border-white/10 shadow-xl group/card hover:bg-white/15 transition-all">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-hover/card:text-stratton-gold">Do wypłaty</div>
          <div class="text-xl md:text-3xl font-bold text-white font-serif">{{ totalCommission.toFixed(2) }} <span class="text-stratton-gold text-lg">PLN</span></div>
        </div>
      </div>
    </div>

    <div v-if="isManagerView" class="bg-white shadow-sm rounded-2xl border border-slate-200 overflow-hidden mb-8">
      <div v-if="!selectedRep">
        <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex items-center gap-3">
          <div class="w-1.5 h-6 bg-stratton-gold rounded-full"></div>
          <h3 class="font-bold text-slate-800 uppercase text-xs tracking-wider">Podsumowanie Zespołu</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="crm-table divide-y divide-slate-100">
            <thead class="crm-table-head">
              <tr>
                <th class="crm-table-th crm-table-th-xs">Handlowiec</th>
                <th class="crm-table-th crm-table-th-xs text-right">Prowizja Bezpośrednia</th>
                <th class="crm-table-th crm-table-th-xs text-right">Prowizja ze Struktury</th>
                <th class="crm-table-th crm-table-th-xs text-right text-stratton-blue">Łącznie</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 bg-white">
              <tr v-for="row in summaryData" :key="row.repId" class="hover:bg-slate-50 cursor-pointer transition-colors group" @click="selectRepForDetails(row)">
                <td class="crm-table-td whitespace-nowrap text-sm font-bold text-slate-800 group-hover:text-stratton-blue transition-colors">{{ row.repName }}</td>
                <td class="crm-table-td whitespace-nowrap text-right text-sm text-slate-600 font-medium">{{ row.directCommission.toFixed(2) }} PLN</td>
                <td class="crm-table-td whitespace-nowrap text-right text-sm text-slate-600 font-medium">{{ row.overrideCommission.toFixed(2) }} PLN</td>
                <td class="crm-table-td whitespace-nowrap text-right text-sm font-bold text-stratton-blue">{{ row.totalCommission.toFixed(2) }} PLN</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else>
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="font-bold text-gray-700">Szczegóły dla: {{ selectedRep.repName }}</h3>
        </div>
        <div v-if="detailsForSelectedRep.length > 0" class="overflow-x-auto">
          <table class="crm-table divide-y divide-gray-200">
            <thead class="crm-table-head">
              <tr>
                <th class="crm-table-th crm-table-th-xs">Klient / Faktura</th>
                <th class="crm-table-th crm-table-th-xs text-right">Opłata Serwisowa</th>
                <th class="crm-table-th crm-table-th-xs text-center">Typ Prowizji</th>
                <th class="crm-table-th crm-table-th-xs text-right text-indigo-600">Prowizja</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="row in detailsForSelectedRep" :key="row.invoiceNumber" class="hover:bg-gray-50">
                <td class="crm-table-td whitespace-nowrap">
                  <div class="text-sm font-bold text-gray-900">{{ row.clientName }}</div>
                  <div class="text-xs text-gray-500">{{ row.invoiceNumber }} ({{ new Date(row.issueDate).toLocaleDateString() }})</div>
                </td>
                <td class="crm-table-td whitespace-nowrap text-right text-sm text-gray-900">{{ row.serviceFee.toFixed(2) }}</td>
                <td class="crm-table-td whitespace-nowrap text-center">
                  <span
                    v-if="row.commissionType === 'DIRECT_FIRST'"
                    :class="row.isFastTrack ? 'crm-badge-success' : 'crm-badge-warn'"
                  >
                    Pierwsza ({{ row.daysDiff }} dni)
                  </span>
                  <span v-else-if="row.commissionType === 'DIRECT_RENEWAL'" class="crm-badge-info">Odnowienie</span>
                  <span v-else class="crm-badge-neutral">Struktura</span>
                </td>
                <td class="crm-table-td whitespace-nowrap text-right text-sm font-bold text-indigo-600">
                  {{ row.repCommission.toFixed(2) }}
                  <div class="text-[10px] text-gray-400 font-normal">{{ Math.round((row.repCommission / row.serviceFee) * 100) }}% bazy</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="p-6 text-center text-sm text-gray-500">Brak faktur bezpośrednich dla tego handlowca.</p>
      </div>
    </div>

    <div v-else class="bg-white shadow rounded-lg overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between">
        <h3 class="font-bold text-gray-700">Szczegóły Faktur</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="crm-table divide-y divide-gray-200">
          <thead class="crm-table-head">
            <tr>
              <th class="crm-table-th crm-table-th-xs">Klient / Faktura</th>
              <th class="crm-table-th crm-table-th-xs text-right">Opłata Serwisowa</th>
              <th class="crm-table-th crm-table-th-xs text-center">Typ Prowizji</th>
              <th class="crm-table-th crm-table-th-xs text-right text-indigo-600">Prowizja</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in settlementRows" :key="row.invoiceNumber" class="hover:bg-gray-50">
              <td class="crm-table-td whitespace-nowrap">
                <div class="text-sm font-bold text-gray-900">{{ row.clientName }}</div>
                <div class="text-xs text-gray-500">{{ row.invoiceNumber }} ({{ new Date(row.issueDate).toLocaleDateString() }})</div>
              </td>
              <td class="crm-table-td whitespace-nowrap text-right text-sm text-gray-900">{{ row.serviceFee.toFixed(2) }}</td>
              <td class="crm-table-td whitespace-nowrap text-center">
                <span
                  v-if="row.commissionType === 'DIRECT_FIRST'"
                  :class="row.isFastTrack ? 'crm-badge-success' : 'crm-badge-warn'"
                >
                  Pierwsza ({{ row.daysDiff }} dni)
                </span>
                <span v-else-if="row.commissionType === 'DIRECT_RENEWAL'" class="crm-badge-info">Odnowienie</span>
                <span v-else class="crm-badge-neutral">Struktura</span>
              </td>
              <td class="crm-table-td whitespace-nowrap text-right text-sm font-bold text-indigo-600">
                {{ row.repCommission.toFixed(2) }}
                <div class="text-[10px] text-gray-400 font-normal">{{ Math.round((row.repCommission / row.serviceFee) * 100) }}% bazy</div>
              </td>
            </tr>
            <tr v-if="settlementRows.length === 0">
              <td colspan="4" class="crm-table-empty text-center">Brak rozliczeń.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Signed Contracts Table -->
    <div class="bg-white shadow-sm rounded-2xl border border-slate-200 overflow-hidden mt-10">
      <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-1.5 h-6 bg-stratton-gold rounded-full"></div>
          <h3 class="font-bold text-slate-800 uppercase text-[10px] tracking-widest">Podpisane Umowy</h3>
        </div>
        <div class="flex items-center gap-2">
           <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
           <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full uppercase tracking-widest">{{ signedContractsWithDetails.length }}</span>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="crm-table divide-y divide-slate-100">
          <thead class="crm-table-head">
            <tr>
              <th class="crm-table-th crm-table-th-xs">Nazwa Klienta</th>
              <th class="crm-table-th crm-table-th-xs">Opiekun</th>
              <th class="crm-table-th crm-table-th-xs text-center">Status</th>
              <th class="crm-table-th crm-table-th-xs">Miasto</th>
              <th class="crm-table-th crm-table-th-xs">Data Umowy</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 bg-white">
            <tr v-for="client in signedContractsWithDetails" :key="client.id" class="hover:bg-slate-50 transition-all group">
              <td class="crm-table-td whitespace-nowrap">
                <div class="font-bold text-slate-800 text-sm group-hover:text-stratton-blue transition-colors">{{ client.name }}</div>
                <div class="text-[10px] text-slate-400 font-mono mt-0.5 tracking-tight group-hover:text-slate-500">{{ client.nip }}</div>
              </td>
              <td class="crm-table-td whitespace-nowrap">
                <div class="text-sm font-medium text-slate-600">{{ client.opiekunDisplay }}</div>
              </td>
              <td class="crm-table-td whitespace-nowrap text-center">
                <span class="crm-badge-success uppercase tracking-wider text-[10px]">Podpisany</span>
              </td>
              <td class="crm-table-td whitespace-nowrap text-sm text-slate-600 font-medium italic">
                {{ client.city || '—' }}
              </td>
              <td class="crm-table-td whitespace-nowrap text-sm text-slate-400 font-medium">
                {{ client.contractSignedDate || '—' }}
              </td>
            </tr>
            <tr v-if="signedContractsWithDetails.length === 0">
              <td colspan="5" class="crm-table-empty text-center">Brak podpisanych umów.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
