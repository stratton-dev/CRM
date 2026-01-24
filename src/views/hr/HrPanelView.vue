<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useHrStore } from '@/stores/hr'
import { useSessionStore } from '@/stores/session'
import { useClientStore } from '@/stores/client'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'
import type { Employee } from '@/types/models'

const hr = useHrStore()
const session = useSessionStore()
const { currentUser } = storeToRefs(session)
const clientStore = useClientStore()
const toast = useToastStore()
const router = useRouter()

const { employees, invoices } = storeToRefs(hr)
const { clients } = storeToRefs(clientStore)
const showModal = ref(false)
const isDragging = ref(false)

const newEmp = reactive<Employee>({
  id: '',
  clientId: '',
  name: '',
  pesel: '',
  contractType: 'UoP',
  benefitAmount: 500,
})

const currentClientId = computed(() => currentUser.value?.linkedClientId || '')

const myEmployees = computed(() => {
  if (!currentClientId.value) return []
  const list = Array.isArray(employees.value) ? employees.value : []
  return list.filter((item) => item.clientId === currentClientId.value)
})

const myInvoices = computed(() => {
  if (!currentClientId.value) return []
  const list = Array.isArray(invoices.value) ? invoices.value : []
  return list
    .filter((item) => item.clientId === currentClientId.value)
    .sort((a, b) => new Date(b.issueDate).getTime() - new Date(a.issueDate).getTime())
})

const totalAmount = computed(() => myEmployees.value.reduce((sum, emp) => sum + emp.benefitAmount, 0))

const openAddModal = () => {
  newEmp.name = ''
  newEmp.pesel = ''
  newEmp.contractType = 'UoP'
  newEmp.benefitAmount = 500
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveEmployee = () => {
  if (!currentClientId.value) {
    toast.error('Błąd: Brak powiązanego klienta z kontem.')
    return
  }
  if (!newEmp.name.trim() || !newEmp.pesel?.trim()) {
    toast.warning('Uzupełnij imię i nazwisko oraz PESEL.')
    return
  }

  hr.addEmployee({
    clientId: currentClientId.value,
    name: newEmp.name.trim(),
    pesel: newEmp.pesel?.trim(),
    contractType: newEmp.contractType,
    benefitAmount: Number(newEmp.benefitAmount),
  })
  closeModal()
}

const placeOrder = () => {
  if (!currentClientId.value) return
  if (myEmployees.value.length === 0) {
    toast.info('Dodaj pracowników do listy przed zamówieniem.')
    return
  }

  if (window.confirm(`Potwierdzasz zamówienie na kwotę ${totalAmount.value} PLN Netto?\nZostanie wystawiona faktura VAT.`)) {
    const list = Array.isArray(clients.value) ? clients.value : []
    const client = list.find((item) => item.id === currentClientId.value)
    const elitonWalletPayload = {
      clientId: currentClientId.value,
      clientName: client?.name || 'Nieznany klient',
      orderDate: new Date().toISOString(),
      totalBenefitAmountNet: totalAmount.value,
      employeeList: myEmployees.value.map((emp) => ({
        employeeId: emp.id,
        employeeName: emp.name,
        pesel: emp.pesel,
        benefitAmount: emp.benefitAmount,
      })),
    }

    console.log('--- SYMULACJA INTEGRACJI: Eliton Wallet ---')
    console.log('Wysyłanie danych o zamówieniu tokenów do zewnętrznego systemu:')
    console.log(JSON.stringify(elitonWalletPayload, null, 2))
    console.log('---------------------------------------------')

    hr.generateInvoice(currentClientId.value, totalAmount.value)
    toast.success('Zamówienie zostało złożone i faktura została wygenerowana.')
  }
}

const handleDragOver = (event: DragEvent) => {
  event.preventDefault()
  event.stopPropagation()
  isDragging.value = true
}

const handleDragLeave = (event: DragEvent) => {
  event.preventDefault()
  event.stopPropagation()
  isDragging.value = false
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  event.stopPropagation()
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
    toast.error('Proszę upuścić plik w formacie CSV.')
    return
  }
  parseCsv(file)
}

const parseCsv = (file: File) => {
  if (!currentClientId.value) {
    toast.error('Błąd: Brak powiązanego klienta z kontem.')
    return
  }

  const reader = new FileReader()
  reader.onload = () => {
    const text = String(reader.result || '')
    const rows = text.split('\n').filter((row) => row.trim() !== '')
    let importedCount = 0
    let failedCount = 0

    rows.forEach((row) => {
      const [name, pesel, contractType, benefitAmountStr] = row.split(',')
      if (!name || !pesel || !contractType || !benefitAmountStr) {
        failedCount += 1
        return
      }

      const normalizedType = contractType.trim()
      if (normalizedType !== 'UoP' && normalizedType !== 'UZ') {
        failedCount += 1
        return
      }

      const amount = Number.parseFloat(benefitAmountStr.trim())
      if (Number.isNaN(amount)) {
        failedCount += 1
        return
      }

      hr.addEmployee({
        clientId: currentClientId.value,
        name: name.trim(),
        pesel: pesel.trim(),
        contractType: normalizedType as 'UoP' | 'UZ',
        benefitAmount: amount,
      })
      importedCount += 1
    })

    if (importedCount > 0) toast.success(`Zaimportowano pomyślnie ${importedCount} pracowników.`)
    if (failedCount > 0) toast.warning(`Pominięto ${failedCount} nieprawidłowych wierszy.`)
    if (importedCount === 0 && failedCount === 0) toast.info('Plik CSV był pusty lub nie zawierał poprawnych danych.')
  }
  reader.onerror = () => toast.error('Nie udało się odczytać pliku.')
  reader.readAsText(file, 'UTF-8')
}
</script>

<template>
  <div class="space-y-8">
    <RouterLink to="/app/calculator" class="flex items-center justify-between bg-slate-900 text-white rounded-2xl p-6 shadow-lg hover:bg-slate-800 transition">
      <div>
        <h2 class="text-lg font-bold">Kalkulator kosztow zatrudnienia</h2>
        <p class="text-xs text-slate-300 uppercase tracking-widest mt-1">Pelna symulacja w HR/Kadry</p>
      </div>
      <AppIcon name="chart-line" class="w-6 h-6" />
    </RouterLink>
    <section class="bg-white shadow rounded-lg p-6 relative overflow-hidden">
      <div class="absolute top-0 right-0 p-4 opacity-10">
        <svg class="w-32 h-32 text-sky-900" fill="currentColor" viewBox="0 0 24 24">
          <path
            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.15-1.46-3.27-3.23h1.96c.1.92.98 1.64 2.53 1.64 1.52 0 2.51-.81 2.51-1.96 0-1.14-.68-1.76-2.19-2.09l-1.02-.22c-1.98-.43-3-1.43-3-2.9 0-1.7 1.48-2.85 3.2-3.19V4h2.67v1.93c1.71.36 2.94 1.5 3.07 3.06h-1.95c-.07-.75-.8-1.42-2.36-1.42-1.51 0-2.36.85-2.36 1.83 0 .97.64 1.55 1.95 1.84l1.24.29c2.25.5 3.26 1.57 3.26 3.12 0 1.77-1.47 2.96-3.34 3.32z"
          />
        </svg>
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-4">Zamówienie Tokenów (Bieżący Miesiąc)</h2>
      <div class="flex flex-col md:flex-row items-center justify-between bg-sky-50 p-4 rounded border border-sky-100 relative z-10">
        <div class="mb-4 md:mb-0">
          <p class="text-sm text-sky-700">Liczba pracowników na liście</p>
          <p class="text-2xl font-bold text-sky-900">{{ myEmployees.length }}</p>
        </div>
        <div class="mb-4 md:mb-0">
          <p class="text-sm text-sky-700">Suma świadczeń (Netto)</p>
          <p class="text-2xl font-bold text-sky-900">{{ totalAmount.toFixed(2) }} PLN</p>
        </div>
        <button type="button" class="bg-sky-600 text-white px-6 py-3 rounded-lg hover:bg-sky-700 shadow-lg font-bold disabled:opacity-50 transition" :disabled="myEmployees.length === 0" @click="placeOrder">
          Złóż Zamówienie
        </button>
      </div>
    </section>

    <section class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Import Danych z Pliku</h3>
      <div
        class="border-2 border-dashed rounded-lg p-10 text-center cursor-pointer transition-colors"
        :class="isDragging ? 'border-sky-500 bg-sky-50 text-sky-600' : 'border-gray-300 text-gray-500'"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
      >
        <div class="flex flex-col items-center pointer-events-none">
          <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
          <p class="font-semibold">Upuść tutaj plik CSV</p>
          <p class="text-xs mt-1">Obsługiwane formaty: Optima, RAKS, Symfonia (jako CSV).</p>
          <p class="text-xs mt-4 text-gray-400">
            Oczekiwany format wiersza: <code class="bg-gray-200 p-1 rounded text-gray-600">Imię Nazwisko,PESEL,UoP,500.00</code>
          </p>
        </div>
      </div>
    </section>

    <section class="bg-white shadow rounded-lg overflow-hidden">
      <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Lista Pracowników</h3>
        <button type="button" class="px-3 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-700" @click="openAddModal">+ Dodaj Pracownika</button>
      </div>
      <table v-if="myEmployees.length > 0" class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imię Nazwisko</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PESEL (Fragment)</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Umowa</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kwota Benefitu</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="emp in myEmployees" :key="emp.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ emp.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">******{{ emp.pesel?.slice(-4) || '0000' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="emp.contractType === 'UoP' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800'">
                {{ emp.contractType }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ emp.benefitAmount }} PLN</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="p-10 text-center text-gray-500">Brak pracowników na liście. Zaimportuj plik lub dodaj ręcznie.</div>
    </section>

    <section class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium mb-4 text-gray-900">Faktury i Historia Zamówień</h3>
      <div class="space-y-4">
        <div v-for="inv in myInvoices" :key="inv.id" class="flex items-center justify-between border-b border-gray-100 pb-3 last:border-0 hover:bg-gray-50 p-2 rounded transition">
          <div class="flex items-center space-x-4">
            <div class="bg-gray-100 p-2 rounded text-gray-500">
              <AppIcon name="document-text" class="w-4 h-4" />
            </div>
            <div>
              <div class="text-sm font-bold text-gray-900">{{ inv.number }}</div>
              <div class="text-xs text-gray-500">{{ new Date(inv.issueDate).toLocaleDateString('pl-PL', { dateStyle: 'medium' }) }}</div>
            </div>
          </div>
          <div class="flex items-center space-x-6">
            <div class="text-right">
              <div class="text-sm font-bold">{{ inv.amountGross.toFixed(2) }} PLN</div>
              <div class="text-xs text-gray-400">Brutto</div>
            </div>
            <div>
              <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="inv.status === 'PAID' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                {{ inv.status === 'PAID' ? 'Opłacona' : 'Do zapłaty' }}
              </span>
            </div>
            <button type="button" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium" @click="router.push(`/app/invoice-preview/${inv.id}`)">
              Pobierz PDF
            </button>
          </div>
        </div>
        <p v-if="myInvoices.length === 0" class="text-sm text-gray-500">Brak faktur.</p>
      </div>
    </section>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeModal"></div>
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md z-10 relative">
        <h3 class="text-lg font-bold mb-4">Dodaj Pracownika</h3>
        <form class="space-y-4" @submit.prevent="saveEmployee">
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">Imię i Nazwisko</label>
            <input v-model="newEmp.name" type="text" required class="w-full border p-2 rounded mt-1" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">PESEL</label>
            <input v-model="newEmp.pesel" type="text" required class="w-full border p-2 rounded mt-1" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">Typ Umowy</label>
            <select v-model="newEmp.contractType" class="w-full border p-2 rounded mt-1">
              <option value="UoP">Umowa o Pracę</option>
              <option value="UZ">Umowa Zlecenie</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 uppercase">Kwota Benefitu (Netto)</label>
            <input v-model.number="newEmp.benefitAmount" type="number" required class="w-full border p-2 rounded mt-1" />
          </div>
          <div class="mt-6 flex justify-end space-x-3">
            <button type="button" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded" @click="closeModal">Anuluj</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Dodaj</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
