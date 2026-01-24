<script setup lang="ts">
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'
import { useFinanceStore } from '@/stores/finance'
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

const selectedRep = ref<SummaryRow | null>(null)

const { currentUser } = storeToRefs(session)

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
    row.totalCommission = row.directCommission + row.overrideCommission
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
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Rozliczenia i Prowizje</h1>
        <button
          v-if="isManagerView && selectedRep"
          type="button"
          class="text-sm text-sky-600 hover:text-sky-800 font-medium flex items-center"
          @click="selectRepForDetails(null)"
        >
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
          Wróć do podsumowania zespołu
        </button>
      </div>
      <button type="button" class="flex items-center px-4 py-2 bg-green-600 text-white rounded shadow hover:bg-green-700 font-bold text-sm" @click="exportToCsv">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        Eksportuj do CSV
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white p-6 rounded-lg shadow border-l-4 border-indigo-500">
        <div class="text-sm text-gray-500">Suma Prowizji (Ten miesiąc)</div>
        <div class="text-3xl font-bold text-gray-900">{{ totalCommission.toFixed(2) }} PLN</div>
      </div>
      <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Wypłacono</div>
        <div class="text-3xl font-bold text-gray-900">0.00 PLN</div>
      </div>
      <div class="bg-white p-6 rounded-lg shadow border-l-4 border-gray-300">
        <div class="text-sm text-gray-500">Do wypłaty</div>
        <div class="text-3xl font-bold text-gray-900">{{ totalCommission.toFixed(2) }} PLN</div>
      </div>
    </div>

    <div v-if="isManagerView" class="bg-white shadow rounded-lg overflow-hidden">
      <div v-if="!selectedRep">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="font-bold text-gray-700">Podsumowanie Zespołu</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Handlowiec</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Prowizja Bezpośrednia</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Prowizja ze Struktury</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase text-indigo-600">Łącznie</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="row in summaryData" :key="row.repId" class="hover:bg-sky-50 cursor-pointer" @click="selectRepForDetails(row)">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.repName }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-700">{{ row.directCommission.toFixed(2) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-700">{{ row.overrideCommission.toFixed(2) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-indigo-600">{{ row.totalCommission.toFixed(2) }}</td>
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
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klient / Faktura</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opłata Serwisowa</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Typ Prowizji</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase text-indigo-600">Prowizja</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="row in detailsForSelectedRep" :key="row.invoiceNumber" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-bold text-gray-900">{{ row.clientName }}</div>
                  <div class="text-xs text-gray-500">{{ row.invoiceNumber }} ({{ new Date(row.issueDate).toLocaleDateString() }})</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">{{ row.serviceFee.toFixed(2) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span v-if="row.commissionType === 'DIRECT_FIRST'" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="row.isFastTrack ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                    Pierwsza ({{ row.daysDiff }} dni)
                  </span>
                  <span v-else-if="row.commissionType === 'DIRECT_RENEWAL'" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Odnowienie</span>
                  <span v-else class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">Struktura</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-indigo-600">
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
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klient / Faktura</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opłata Serwisowa</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Typ Prowizji</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase text-indigo-600">Prowizja</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in settlementRows" :key="row.invoiceNumber" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-bold text-gray-900">{{ row.clientName }}</div>
                <div class="text-xs text-gray-500">{{ row.invoiceNumber }} ({{ new Date(row.issueDate).toLocaleDateString() }})</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">{{ row.serviceFee.toFixed(2) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <span v-if="row.commissionType === 'DIRECT_FIRST'" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="row.isFastTrack ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                  Pierwsza ({{ row.daysDiff }} dni)
                </span>
                <span v-else-if="row.commissionType === 'DIRECT_RENEWAL'" class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Odnowienie</span>
                <span v-else class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">Struktura</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-indigo-600">
                {{ row.repCommission.toFixed(2) }}
                <div class="text-[10px] text-gray-400 font-normal">{{ Math.round((row.repCommission / row.serviceFee) * 100) }}% bazy</div>
              </td>
            </tr>
            <tr v-if="settlementRows.length === 0">
              <td colspan="4" class="px-6 py-8 text-center text-gray-500">Brak rozliczeń.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
