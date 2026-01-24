<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useDataStore } from '@/stores/data'
import { useStructureStore } from '@/stores/structure'
import { useFinanceStore } from '@/stores/finance'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import { useClientStore } from '@/stores/client'
import { useHrStore } from '@/stores/hr'
import { useAuditLogStore } from '@/stores/auditLog'
import DashboardView from '@/views/DashboardView.vue'
import type { User } from '@/types/models'

const structure = useStructureStore()
const finance = useFinanceStore()
const session = useSessionStore()
const { currentUser } = storeToRefs(session)
const data = useDataStore()
const clientStore = useClientStore()
const hrStore = useHrStore()
const auditLogStore = useAuditLogStore()
const { users } = storeToRefs(structure)
const { clients } = storeToRefs(clientStore)
const { employees } = storeToRefs(hrStore)
const { commissionConfig } = storeToRefs(finance)
const { logs: auditLogs } = storeToRefs(auditLogStore)
const toast = useToastStore()

const activeTab = ref<'GENERAL' | 'DASHBOARD' | 'USERS' | 'LOGS'>('GENERAL')
const userSearchQuery = ref('')
const selectedUserForEdit = ref<User | null>(null)
const config = ref({ ...commissionConfig.value })

const stats = computed(() => finance.getAdminStats())
const topLeaders = computed(() => finance.getTopPerformers())

const filteredUsers = computed(() => {
  const query = userSearchQuery.value.toLowerCase()
  const userList = Array.isArray(users.value) ? users.value : []
  if (!query) return userList
  return userList.filter((user) => user.name.toLowerCase().includes(query) || user.email.toLowerCase().includes(query))
})

const switchTab = (tab: 'GENERAL' | 'DASHBOARD' | 'USERS' | 'LOGS') => {
  activeTab.value = tab
}

const editUser = (user: User) => {
  selectedUserForEdit.value = { ...user }
}

const closeEditPanel = () => {
  selectedUserForEdit.value = null
}

const toggleBlock = async (user: User) => {
  const me = currentUser.value
  if (me) await structure.toggleBlockUser(user, me.id)
}

const saveUser = async () => {
  const me = currentUser.value
  const userToSave = selectedUserForEdit.value
  if (userToSave && me) {
    await structure.updateUserAdmin(userToSave.id, userToSave, me.id)
    closeEditPanel()
  }
}

const saveConfig = () => {
  finance.updateCommissionConfig(config.value)
  toast.success('Konfiguracja zapisana.')
}

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

const getInitials = (name: string) => name.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase()

onMounted(() => {
  auditLogStore.fetchLogs()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">Panel Administratora (God Mode)</h1>
      <div class="flex space-x-2">
        <button
          type="button"
          class="px-4 py-2 rounded text-sm font-medium border border-slate-300 shadow-sm transition-all"
          :class="activeTab === 'GENERAL' ? 'bg-slate-900 text-white' : ''"
          @click="switchTab('GENERAL')"
        >
          Kokpit ogólny
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded text-sm font-medium border border-slate-300 shadow-sm transition-all"
          :class="activeTab === 'DASHBOARD' ? 'bg-slate-900 text-white' : ''"
          @click="switchTab('DASHBOARD')"
        >
          Analityka
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded text-sm font-medium border border-slate-300 shadow-sm transition-all"
          :class="activeTab === 'USERS' ? 'bg-slate-900 text-white' : ''"
          @click="switchTab('USERS')"
        >
          Użytkownicy
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded text-sm font-medium border border-slate-300 shadow-sm transition-all"
          :class="activeTab === 'LOGS' ? 'bg-slate-900 text-white' : ''"
          @click="switchTab('LOGS')"
        >
          Logi Systemowe
        </button>
      </div>
    </div>

    <div v-if="activeTab === 'GENERAL'" class="space-y-6 animate-fade-in">
      <DashboardView :show-admin-panel="false" />
    </div>

    <div v-else-if="activeTab === 'DASHBOARD'" class="space-y-6 animate-fade-in">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-lg p-6 text-white shadow-lg relative overflow-hidden">
          <p class="text-slate-400 text-sm font-bold uppercase tracking-wider">Przychód Całkowity</p>
          <h3 class="text-3xl font-extrabold mt-2">{{ Math.round(stats.revenue).toLocaleString() }} PLN</h3>
          <div class="mt-4 flex items-center text-xs text-green-400">
            <span>▲ +12% m/m</span>
          </div>
        </div>

        <div class="bg-white rounded-lg p-6 shadow-md border-l-4 border-indigo-500">
          <p class="text-gray-500 text-sm font-bold uppercase tracking-wider">Koszty Prowizji</p>
          <h3 class="text-3xl font-extrabold text-gray-900 mt-2">{{ Math.round(stats.commission).toLocaleString() }} PLN</h3>
          <div class="mt-4 text-xs text-gray-400">
            Est. marża:
            <span class="text-indigo-600 font-bold">
              {{ Math.round(((stats.revenue - stats.commission) / (stats.revenue || 1)) * 100) }}%
            </span>
          </div>
        </div>

        <div class="bg-white rounded-lg p-6 shadow-md border-l-4 border-emerald-500">
          <p class="text-gray-500 text-sm font-bold uppercase tracking-wider">Aktywne Umowy</p>
          <h3 class="text-3xl font-extrabold text-gray-900 mt-2">{{ stats.activeContracts }}</h3>
          <div class="mt-4 text-xs text-gray-400">
            Średni przychód/umowa:
            <span class="text-emerald-600 font-bold">
              {{ Math.round(stats.revenue / (stats.activeContracts || 1)).toLocaleString() }} PLN
            </span>
          </div>
        </div>

        <div class="bg-white rounded-lg p-6 shadow-md border-l-4 border-sky-500">
          <p class="text-gray-500 text-sm font-bold uppercase tracking-wider">Zespół Sprzedaży</p>
          <h3 class="text-3xl font-extrabold text-gray-900 mt-2">{{ stats.salesCount }}</h3>
          <div class="mt-4 text-xs text-gray-400">
            Efektywność: {{ (stats.activeContracts / (stats.salesCount || 1)).toFixed(1) }} umowy/os.
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-500">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Akcje Systemowe</h3>
        <div>
          <h4 class="font-semibold text-gray-700">Fakturowanie Miesięczne</h4>
          <p class="text-xs text-gray-500 mb-2">
            Uruchom proces, który wystawia faktury za tokeny dla wszystkich klientów z podpisaną umową na podstawie ich aktualnej listy pracowników.
          </p>
          <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-indigo-700 flex items-center shadow-md transition-transform hover:scale-105" @click="runMonthlyInvoicing">
            Uruchom Fakturowanie
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Dynamika Finansowa (Ostatnie 6 miesięcy)</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 border rounded-lg bg-slate-50">
              <p class="text-xs text-slate-500 uppercase">Przychód</p>
              <p class="text-2xl font-bold text-slate-800">{{ Math.round(stats.revenue).toLocaleString() }} PLN</p>
            </div>
            <div class="p-4 border rounded-lg bg-slate-50">
              <p class="text-xs text-slate-500 uppercase">Prowizje</p>
              <p class="text-2xl font-bold text-slate-800">{{ Math.round(stats.commission).toLocaleString() }} PLN</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">Top Dyrektorzy / Regiony</h3>
          </div>
          <div class="divide-y divide-gray-200">
            <div v-for="leader in topLeaders" :key="leader.id" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
              <div class="flex items-center">
                <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm">
                  {{ getInitials(leader.name) }}
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-900">{{ leader.name }}</p>
                  <p class="text-xs text-gray-500">{{ leader.region || 'Centrala' }}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-gray-900">{{ leader.salesCount }} Umów</p>
                <p class="text-xs text-green-600">{{ Math.round(leader.revenue).toLocaleString() }} PLN</p>
              </div>
            </div>
            <div v-if="topLeaders.length === 0" class="p-6 text-center text-gray-500">Brak danych sprzedażowych.</div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="activeTab === 'USERS'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in">
      <div class="lg:col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
          <h3 class="font-bold text-gray-700">Użytkownicy Systemu</h3>
          <input v-model="userSearchQuery" type="text" placeholder="Filtruj użytkowników..." class="w-64 text-sm" />
        </div>
        <div class="max-h-[600px] overflow-y-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Użytkownik</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rola</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ u.name }}</div>
                  <div class="text-xs text-gray-500">{{ u.email }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ u.role }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button type="button" class="text-indigo-600 hover:text-indigo-900 mr-3" @click="editUser(u)">Edytuj</button>
                  <button v-if="u.isBlocked" type="button" class="text-green-600 hover:text-green-900" @click="toggleBlock(u)">Odblokuj</button>
                  <button v-else type="button" class="text-red-600 hover:text-red-900" @click="toggleBlock(u)">Zablokuj</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow h-fit">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
          <h3 class="font-bold text-gray-700">Konfiguracja Prowizji (Globalna)</h3>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Prowizja Handlowca (Umowa &lt;= 14 dni)</label>
            <div class="flex items-center">
              <input v-model.number="config.salesCommissionFirstMonthLt14" type="number" step="0.01" class="flex-1 border p-2 rounded text-sm bg-white" />
              <span class="ml-2 text-sm text-gray-500">% (dziesiętnie)</span>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Prowizja Handlowca (Umowa &gt; 14 dni)</label>
            <div class="flex items-center">
              <input v-model.number="config.salesCommissionFirstMonthGt14" type="number" step="0.01" class="flex-1 border p-2 rounded text-sm bg-white" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Prowizja Odnowieniowa (2 msc+)</label>
            <div class="flex items-center">
              <input v-model.number="config.salesCommissionRenewal" type="number" step="0.01" class="flex-1 border p-2 rounded text-sm bg-white" />
            </div>
          </div>
          <button type="button" class="w-full bg-slate-900 text-white py-2 rounded hover:bg-slate-800 text-sm font-bold mt-4" @click="saveConfig">
            Zapisz Konfigurację
          </button>
        </div>
      </div>
    </div>

    <div v-else class="bg-white rounded-lg shadow overflow-hidden animate-fade-in">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="font-bold text-gray-700">Rejestr Zdarzeń (Audit Log)</h3>
      </div>
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Użytkownik (ID)</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Akcja</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Szczegóły</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ new Date(log.date).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-gray-700">{{ log.actorId }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-xs text-indigo-600 font-medium">{{ log.action }}</td>
            <td class="px-6 py-4 text-xs text-gray-600">{{ log.details }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedUserForEdit" class="fixed inset-0 z-50 flex items-start justify-end">
      <div class="absolute inset-0 bg-black/30" @click="closeEditPanel"></div>
      <div class="relative h-full w-full max-w-md bg-gray-50 shadow-2xl flex flex-col animate-slide-in-right">
        <div class="p-4 bg-white border-b border-gray-200 flex-shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-lg font-bold text-gray-900">Edytuj Użytkownika</h3>
              <p class="text-xs text-gray-500">{{ selectedUserForEdit.email }}</p>
            </div>
            <button type="button" class="p-2 text-gray-400 hover:bg-gray-100 rounded-full" @click="closeEditPanel">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <div class="bg-white p-4 border border-gray-200 rounded-lg space-y-4">
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Imię i Nazwisko</label>
              <input v-model="selectedUserForEdit.name" class="w-full" />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Rola</label>
              <select v-model="selectedUserForEdit.role" class="w-full">
                <option value="SALES">Handlowiec</option>
                <option value="MANAGER">Menadżer</option>
                <option value="DIRECTOR">Dyrektor</option>
                <option value="ADMIN">Admin</option>
                <option value="CLIENT_HR">Klient HR</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Telefon</label>
              <input v-model="selectedUserForEdit.phone" class="w-full" />
            </div>
          </div>
        </div>

        <div class="p-4 bg-white border-t border-gray-200 flex-shrink-0 flex justify-end gap-3">
          <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50" @click="closeEditPanel">Anuluj</button>
          <button type="button" class="px-4 py-2 bg-sky-600 text-white rounded text-sm hover:bg-sky-700 shadow-sm font-semibold" @click="saveUser">Zapisz Zmiany</button>
        </div>
      </div>
    </div>
  </div>
</template>
