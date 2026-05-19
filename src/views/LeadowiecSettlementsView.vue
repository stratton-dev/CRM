<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'

interface SettlementClient {
  client_id: number
  client_name: string
  nip: string | null
  has_nip: boolean
  invoices_count: number
  sum_netto: number
  commission_rate: number
  commission: number
}

interface SettlementsData {
  month: string
  commission_rate: number
  total_commission: number
  clients: SettlementClient[]
}

const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const loading = ref(false)
const settlements = ref<SettlementsData | null>(null)
const error = ref('')

const fetchSettlements = async () => {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/v1/leadowiec/settlements', {
      params: { month: selectedMonth.value },
    })
    settlements.value = data
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Nie udało się pobrać rozliczeń.'
    settlements.value = null
  } finally {
    loading.value = false
  }
}

const formatPLN = (val: number) =>
  new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(val ?? 0)

const monthLabel = (ym: string) => {
  const [y, m] = ym.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('pl-PL', { month: 'long', year: 'numeric' })
}

onMounted(fetchSettlements)
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Rozliczenia</h1>
        <p class="text-sm text-slate-500 mt-0.5">Twoje prowizje od klientów z podpisaną umową</p>
      </div>
      <div class="flex items-center gap-2">
        <input
          v-model="selectedMonth"
          type="month"
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
          @change="fetchSettlements"
        />
        <button
          @click="fetchSettlements"
          class="px-3 py-2 rounded-lg text-sm font-medium text-white"
          style="background: linear-gradient(135deg, #001f3d, #003366)"
        >
          Odśwież
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-slate-300 border-t-[#C5A059] rounded-full animate-spin" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
      {{ error }}
    </div>

    <template v-else-if="settlements">
      <!-- Summary card -->
      <div class="rounded-2xl p-6 mb-6 text-white" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%)">
        <p class="text-sm opacity-70 mb-1">Prowizja za {{ monthLabel(selectedMonth) }}</p>
        <p class="text-4xl font-black" style="color: #C5A059">{{ formatPLN(settlements.total_commission) }}</p>
        <p class="text-sm opacity-60 mt-1">
          Stawka prowizji: {{ (settlements.commission_rate * 100).toFixed(1) }}%
        </p>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Szczegóły per klient</h2>
        </div>

        <div v-if="!settlements.clients?.length" class="py-12 text-center text-slate-400 text-sm">
          <AppIcon name="document-text" class="w-10 h-10 mx-auto mb-2 opacity-30" />
          Brak klientów z podpisaną umową w tym miesiącu.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Klient</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">NIP</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Faktury</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Suma netto</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Prowizja</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in settlements.clients" :key="row.client_id" class="hover:bg-slate-50 transition-colors">
                <td class="px-5 py-3 font-medium text-slate-800">
                  {{ row.client_name }}
                </td>
                <td class="px-5 py-3">
                  <span v-if="row.has_nip" class="font-mono text-slate-600">{{ row.nip }}</span>
                  <span v-else class="inline-flex items-center gap-1 text-amber-600 text-xs">
                    <AppIcon name="exclamation-triangle" class="w-3.5 h-3.5" />
                    Brak NIP
                  </span>
                </td>
                <td class="px-5 py-3 text-right text-slate-600">{{ row.invoices_count }}</td>
                <td class="px-5 py-3 text-right text-slate-600">{{ formatPLN(row.sum_netto) }}</td>
                <td class="px-5 py-3 text-right font-semibold" style="color: #C5A059">
                  {{ formatPLN(row.commission) }}
                </td>
              </tr>
            </tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
              <tr>
                <td colspan="4" class="px-5 py-3 text-sm font-semibold text-slate-700">Suma prowizji</td>
                <td class="px-5 py-3 text-right text-base font-black" style="color: #C5A059">
                  {{ formatPLN(settlements.total_commission) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
