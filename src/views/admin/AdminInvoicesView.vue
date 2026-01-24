<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useFinanceStore } from '@/stores/finance'
import { useClientStore } from '@/stores/client'
import { useToastStore } from '@/stores/toast'
import type { Invoice } from '@/types/models'

const finance = useFinanceStore()
const clientStore = useClientStore()
const toast = useToastStore()

const { invoices } = storeToRefs(finance)
const { clients } = storeToRefs(clientStore)

const query = ref('')
const statusFilter = ref<'ALL' | 'PAID' | 'UNPAID'>('ALL')

const showForm = ref(false)
const isEdit = ref(false)
const editingId = ref<string | null>(null)

const form = ref({
  number: '',
  clientId: '',
  issueDate: '',
  amountNet: '',
  amountGross: '',
  serviceFeeNet: '',
  status: 'UNPAID' as Invoice['status'],
  pdfUrl: '',
})

const clientMap = computed(() => {
  const map = new Map<string, string>()
  const list = Array.isArray(clients.value) ? clients.value : []
  list.forEach((client) => map.set(client.id, client.name))
  return map
})

const filteredInvoices = computed(() => {
  const list = Array.isArray(invoices.value) ? invoices.value : []
  const search = query.value.trim().toLowerCase()

  return list.filter((inv) => {
    if (statusFilter.value !== 'ALL' && inv.status !== statusFilter.value) return false
    if (!search) return true
    const clientName = clientMap.value.get(inv.clientId)?.toLowerCase() || ''
    return inv.number.toLowerCase().includes(search) || clientName.includes(search)
  })
})

const formatDate = (value?: string) => {
  if (!value) return ''
  return new Date(value).toLocaleDateString('pl-PL')
}

const toDateInput = (value?: string) => {
  if (!value) return ''
  const date = new Date(value)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const resetForm = () => {
  const today = toDateInput(new Date().toISOString())
  form.value = {
    number: '',
    clientId: '',
    issueDate: today,
    amountNet: '',
    amountGross: '',
    serviceFeeNet: '',
    status: 'UNPAID',
    pdfUrl: '',
  }
}

const openCreate = () => {
  resetForm()
  isEdit.value = false
  editingId.value = null
  showForm.value = true
}

const openEdit = (inv: Invoice) => {
  form.value = {
    number: inv.number,
    clientId: inv.clientId,
    issueDate: toDateInput(inv.issueDate),
    amountNet: String(inv.amountNet),
    amountGross: String(inv.amountGross),
    serviceFeeNet: String(inv.serviceFeeNet),
    status: inv.status,
    pdfUrl: inv.pdfUrl === '#' ? '' : inv.pdfUrl,
  }
  isEdit.value = true
  editingId.value = inv.id
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingId.value = null
}

const applyGross = () => {
  const amountNet = Number(form.value.amountNet || 0)
  if (!amountNet) return
  form.value.amountGross = (amountNet * 1.23).toFixed(2)
}

const saveInvoice = async () => {
  if (!form.value.number || !form.value.clientId || !form.value.issueDate) {
    toast.error('Uzupełnij numer faktury, klienta oraz datę wystawienia.')
    return
  }

  const payload = {
    number: form.value.number,
    clientId: form.value.clientId,
    issueDate: form.value.issueDate,
    amountNet: Number(form.value.amountNet || 0),
    amountGross: Number(form.value.amountGross || 0),
    serviceFeeNet: Number(form.value.serviceFeeNet || 0),
    status: form.value.status,
    pdfUrl: form.value.pdfUrl,
  }

  if (!payload.amountNet || !payload.amountGross) {
    toast.error('Uzupełnij kwoty netto i brutto.')
    return
  }

  try {
    if (isEdit.value && editingId.value) {
      await finance.updateInvoice(editingId.value, payload)
      toast.success('Faktura została zaktualizowana.')
    } else {
      await finance.createInvoice(payload)
      toast.success('Faktura została wystawiona.')
    }
    closeForm()
  } catch (err: any) {
    toast.error(err?.response?.data?.message || err?.message || 'Nie udało się zapisać faktury.')
  }
}

onMounted(() => {
  finance.fetchApiInvoices()
  clientStore.fetchClients({ perPage: 200, page: 1 })
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Faktury (Admin)</h1>
        <p class="text-sm text-gray-500">Zarządzaj wystawianiem i korektami faktur powiązanych z API.</p>
      </div>
      <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold shadow hover:bg-indigo-700" @click="openCreate">
        Wystaw nową fakturę
      </button>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-100 p-4 flex flex-wrap gap-4 items-center">
      <input v-model="query" type="text" placeholder="Szukaj po numerze lub kliencie" class="flex-1 min-w-[220px] text-sm" />
      <select v-model="statusFilter" class="text-sm">
        <option value="ALL">Wszystkie statusy</option>
        <option value="UNPAID">Nieopłacone</option>
        <option value="PAID">Opłacone</option>
      </select>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800">Lista faktur</h2>
        <span class="text-xs text-gray-500">{{ filteredInvoices.length }} pozycji</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
              <th class="px-6 py-3 text-left">Numer</th>
              <th class="px-6 py-3 text-left">Klient</th>
              <th class="px-6 py-3 text-left">Data wystawienia</th>
              <th class="px-6 py-3 text-right">Netto</th>
              <th class="px-6 py-3 text-right">Brutto</th>
              <th class="px-6 py-3 text-center">Status</th>
              <th class="px-6 py-3 text-right">Akcje</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="inv in filteredInvoices" :key="inv.id" class="hover:bg-gray-50">
              <td class="px-6 py-3 font-medium text-gray-800">{{ inv.number }}</td>
              <td class="px-6 py-3 text-gray-700">{{ clientMap.get(inv.clientId) || '—' }}</td>
              <td class="px-6 py-3 text-gray-600">{{ formatDate(inv.issueDate) }}</td>
              <td class="px-6 py-3 text-right text-gray-700">{{ inv.amountNet.toFixed(2) }} PLN</td>
              <td class="px-6 py-3 text-right text-gray-700">{{ inv.amountGross.toFixed(2) }} PLN</td>
              <td class="px-6 py-3 text-center">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="inv.status === 'PAID' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                >
                  {{ inv.status === 'PAID' ? 'Opłacona' : 'Nieopłacona' }}
                </span>
              </td>
              <td class="px-6 py-3 text-right">
                <button type="button" class="text-indigo-600 hover:text-indigo-900 text-xs font-semibold" @click="openEdit(inv)">
                  Koryguj / Edytuj
                </button>
              </td>
            </tr>
            <tr v-if="filteredInvoices.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">Brak faktur spełniających kryteria.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-800">{{ isEdit ? 'Korekta faktury' : 'Wystaw nową fakturę' }}</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeForm">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="text-sm text-gray-600">
              Numer faktury
              <input v-model="form.number" type="text" class="mt-1 w-full text-sm" placeholder="FV/01/2026/0001" />
            </label>
            <label class="text-sm text-gray-600">
              Klient
              <select v-model="form.clientId" class="mt-1 w-full text-sm">
                <option value="">Wybierz klienta</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
              </select>
            </label>
            <label class="text-sm text-gray-600">
              Data wystawienia
              <input v-model="form.issueDate" type="date" class="mt-1 w-full text-sm" />
            </label>
            <label class="text-sm text-gray-600">
              Status
              <select v-model="form.status" class="mt-1 w-full text-sm">
                <option value="UNPAID">Nieopłacona</option>
                <option value="PAID">Opłacona</option>
              </select>
            </label>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="text-sm text-gray-600">
              Kwota netto
              <input v-model="form.amountNet" type="number" step="0.01" class="mt-1 w-full text-sm" />
            </label>
            <label class="text-sm text-gray-600">
              Opłata serwisowa netto
              <input v-model="form.serviceFeeNet" type="number" step="0.01" class="mt-1 w-full text-sm" />
            </label>
            <label class="text-sm text-gray-600">
              Kwota brutto
              <input v-model="form.amountGross" type="number" step="0.01" class="mt-1 w-full text-sm" />
            </label>
          </div>

          <div class="flex items-center gap-3 text-xs text-gray-500">
            <button type="button" class="text-indigo-600 hover:text-indigo-800 font-semibold" @click="applyGross">
              Przelicz brutto (23% VAT)
            </button>
            <span>Kwoty możesz edytować ręcznie.</span>
          </div>

          <label class="text-sm text-gray-600">
            Link do PDF (opcjonalnie)
            <input v-model="form.pdfUrl" type="text" class="mt-1 w-full text-sm" placeholder="https://..." />
          </label>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-800" @click="closeForm">
            Anuluj
          </button>
          <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold hover:bg-indigo-700" @click="saveInvoice">
            {{ isEdit ? 'Zapisz korektę' : 'Wystaw fakturę' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
