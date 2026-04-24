<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useFinanceStore } from '@/stores/finance'
import { useSessionStore } from '@/stores/session'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useHrStore } from '@/stores/hr'
import { useToastStore } from '@/stores/toast'

const finance = useFinanceStore()
const session = useSessionStore()
const data = useDataStore()
const clientStore = useClientStore()
const hrStore = useHrStore()
const toast = useToastStore()

const { currentUser } = storeToRefs(session)
const { clients } = storeToRefs(clientStore)
const { employees } = storeToRefs(hrStore)

const stats = computed(() => finance.getAdminStats())
const topLeaders = computed(() => finance.getTopPerformers())

const getInitials = (name: string) => name.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase()

const runMonthlyInvoicing = () => {
  if (!window.confirm('Czy na pewno chcesz uruchomić fakturowanie miesięczne dla wszystkich aktywowanych klientów?')) {
    return
  }

  const signedClients = (Array.isArray(clients.value) ? clients.value : []).filter((client) => client.status === 'SIGNED')
  const allEmployees = Array.isArray(employees.value) ? employees.value : []
  let generatedCount = 0

  signedClients.forEach((client) => {
    const clientEmployees = allEmployees.filter((emp) => emp.clientId === client.id)
    if (clientEmployees.length > 0) {
      const totalBenefitNet = clientEmployees.reduce((sum, emp) => sum + emp.benefitAmount, 0)
      const serviceFeeNet = totalBenefitNet * (client.serviceFeePercent / 100)

      finance.createInvoiceForClient(client.id, serviceFeeNet, totalBenefitNet)
      generatedCount += 1
    }
  })

  toast.success(`Wygenerowano ${generatedCount} faktur miesięcznych.`)
  if (currentUser.value) {
    data.logAction(currentUser.value.id, 'MONTHLY_INVOICING', `Uruchomiono fakturowanie, wygenerowano ${generatedCount} faktur.`)
  }
}
</script>

<template>
  <div class="space-y-6 animate-fade-in view-transition">
    <div class="flex items-center justify-between mb-4">
       <h1 class="text-2xl font-bold text-slate-900">Analityka Finansowa</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <div class="bg-linear-to-br from-slate-800 to-slate-900 rounded-xl p-6 text-white shadow-lg relative overflow-hidden">
        <p class="text-slate-400 text-sm font-bold uppercase tracking-wider">Przychód Całkowity</p>
        <h3 class="text-3xl font-extrabold mt-2">{{ Math.round(stats.revenue).toLocaleString() }} PLN</h3>
        <div class="mt-4 flex items-center text-xs text-green-400">
          <span>▲ +12% m/m</span>
        </div>
      </div>

      <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-indigo-500">
        <p class="text-slate-500 text-sm font-bold uppercase tracking-wider">Koszty Prowizji</p>
        <h3 class="text-3xl font-extrabold text-slate-900 mt-2">{{ Math.round(stats.commission).toLocaleString() }} PLN</h3>
        <div class="mt-4 text-xs text-slate-400">
          Est. marża:
          <span class="text-indigo-600 font-bold">
            {{ Math.round(((stats.revenue - stats.commission) / (stats.revenue || 1)) * 100) }}%
          </span>
        </div>
      </div>

      <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-emerald-500">
        <p class="text-slate-500 text-sm font-bold uppercase tracking-wider">Aktywne Umowy</p>
        <h3 class="text-3xl font-extrabold text-slate-900 mt-2">{{ stats.activeContracts }}</h3>
        <div class="mt-4 text-xs text-slate-400">
          Średni przychód/umowa:
          <span class="text-emerald-600 font-bold">
            {{ Math.round(stats.revenue / (stats.activeContracts || 1)).toLocaleString() }} PLN
          </span>
        </div>
      </div>

      <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-sky-500">
        <p class="text-slate-500 text-sm font-bold uppercase tracking-wider">Zespół Sprzedaży</p>
        <h3 class="text-3xl font-extrabold text-slate-900 mt-2">{{ stats.salesCount }}</h3>
        <div class="mt-4 text-xs text-slate-400">
          Efektywność: {{ (stats.activeContracts / (stats.salesCount || 1)).toFixed(1) }} umowy/os.
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-indigo-500">
      <h3 class="text-lg font-bold text-slate-800 mb-4">Akcje Systemowe</h3>
      <div>
        <h4 class="font-semibold text-slate-700">Fakturowanie Miesięczne</h4>
        <p class="text-xs text-slate-500 mb-2">
          Uruchom proces, który wystawia faktury za tokeny dla wszystkich klientów z podpisaną umową na podstawie ich aktualnej listy pracowników.
        </p>
        <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-indigo-700 flex items-center shadow-md transition-transform hover:scale-105" @click="runMonthlyInvoicing">
          Uruchom Fakturowanie
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Dynamika Finansowa (Ostatnie 6 miesięcy)</h3>
        <div class="grid grid-cols-2 gap-4">
          <div class="p-4 border border-slate-200 rounded-lg bg-slate-50">
            <p class="text-xs text-slate-500 uppercase">Przychód</p>
            <p class="text-2xl font-bold text-slate-800">{{ Math.round(stats.revenue).toLocaleString() }} PLN</p>
          </div>
          <div class="p-4 border border-slate-200 rounded-lg bg-slate-50">
            <p class="text-xs text-slate-500 uppercase">Prowizje</p>
            <p class="text-2xl font-bold text-slate-800">{{ Math.round(stats.commission).toLocaleString() }} PLN</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
          <h3 class="text-lg font-bold text-slate-800">Top Dyrektorzy / Regiony</h3>
        </div>
        <div class="divide-y divide-slate-200">
          <div v-for="leader in topLeaders" :key="leader.id" class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition">
            <div class="flex items-center">
              <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm">
                {{ getInitials(leader.name) }}
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-slate-900">{{ leader.name }}</p>
                <p class="text-xs text-slate-500">{{ leader.region || 'Centrala' }}</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-slate-900">{{ leader.salesCount }} Umów</p>
              <p class="text-xs text-green-600">{{ Math.round(leader.revenue).toLocaleString() }} PLN</p>
            </div>
          </div>
          <div v-if="topLeaders.length === 0" class="p-6 text-center text-slate-500">Brak danych sprzedażowych.</div>
        </div>
      </div>
    </div>
  </div>
</template>
