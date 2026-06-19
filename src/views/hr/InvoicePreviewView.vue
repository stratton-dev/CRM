<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useClientStore } from '@/stores/client'
import { useHrStore } from '@/stores/hr'
import { useFinanceStore } from '@/stores/finance'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const clientStore = useClientStore()
const hrStore = useHrStore()
const financeStore = useFinanceStore()
const { invoices } = storeToRefs(financeStore)
const { clients } = storeToRefs(clientStore)
const { employees: employeeStore } = storeToRefs(hrStore)

const invoiceId = computed(() => String(route.params.invoiceId || ''))

const invoice = computed(() => {
  const list = Array.isArray(invoices.value) ? invoices.value : []
  return list.find((inv) => inv.id === invoiceId.value)
})
const client = computed(() => (invoice.value ? clients.value.find((item) => item.id === invoice.value?.clientId) : undefined))
const employees = computed(() => {
  if (!invoice.value) return []
  return employeeStore.value.filter((emp) => emp.clientId === invoice.value?.clientId)
})

const totalBenefitAmount = computed(() => employees.value.reduce((sum, emp) => sum + emp.benefitAmount, 0))

const dueDate = computed(() => {
  if (!invoice.value) return ''
  const issueDate = new Date(invoice.value.issueDate)
  const due = new Date(issueDate)
  due.setDate(issueDate.getDate() + 14)
  return due.toLocaleDateString('pl-PL', { dateStyle: 'long' })
})

const formatDate = (iso?: string) => {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('pl-PL', { dateStyle: 'long' })
}

const print = () => window.print()

const goBack = () => router.back()
</script>

<template>
  <div class="bg-gray-100 min-h-screen pb-10 overflow-x-auto">
    <div class="no-print bg-slate-900 text-white p-4 flex flex-wrap gap-3 justify-between items-center shadow-lg sticky top-0 z-50">
      <div class="flex items-center space-x-4">
        <button type="button" class="hover:text-sky-400 flex items-center" @click="goBack">
          <AppIcon name="arrow-left" class="w-5 h-5 mr-1" />
          Wróć
        </button>
        <span class="font-bold border-l border-slate-600 pl-4">Podgląd Faktury</span>
      </div>
      <div class="flex space-x-3">
        <button type="button" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded font-bold shadow transition inline-flex items-center gap-2" @click="print">
          <AppIcon name="printer" class="w-4 h-4" />
          Drukuj / Zapisz jako PDF
        </button>
      </div>
    </div>

    <div v-if="invoice && client" class="page-container font-sans text-xs leading-normal text-gray-800">
      <header class="flex justify-between items-start pb-4 border-b-2 border-gray-800">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">FAKTURA</h1>
          <p class="text-lg font-bold text-gray-700">{{ invoice.number }}</p>
        </div>
        <div class="text-right">
          <p class="font-bold text-base">Stratton Prime Sp. z o.o.</p>
          <p>ul. Biznesowa 1</p>
          <p>00-001 Warszawa</p>
          <p>NIP: 525-000-00-00</p>
        </div>
      </header>

      <section class="grid grid-cols-2 gap-8 my-6">
        <div>
          <p class="text-gray-500 font-bold uppercase text-[10px] tracking-wider">Nabywca</p>
          <p class="font-bold">{{ client.name }}</p>
          <p>{{ client.street }} {{ client.buildingNr }}{{ client.localeNr ? `/${client.localeNr}` : '' }}</p>
          <p>{{ client.zip }} {{ client.city }}</p>
          <p>NIP: {{ client.nip }}</p>
        </div>
        <div class="text-right">
          <p><span class="text-gray-500">Data wystawienia:</span> <span class="font-bold">{{ formatDate(invoice.issueDate) }}</span></p>
          <p><span class="text-gray-500">Termin płatności:</span> <span class="font-bold">{{ dueDate }}</span></p>
          <p><span class="text-gray-500">Sposób płatności:</span> <span class="font-bold">Przelew bankowy</span></p>
        </div>
      </section>

      <section>
        <table class="w-full text-left">
          <thead class="bg-gray-800 text-white">
            <tr>
              <th class="p-2 w-10">Lp.</th>
              <th class="p-2">Nazwa usługi / towaru</th>
              <th class="p-2 text-right">Ilość</th>
              <th class="p-2 text-right">Cena Netto</th>
              <th class="p-2 text-right">Wartość Netto</th>
              <th class="p-2 text-right">VAT</th>
              <th class="p-2 text-right">Wartość Brutto</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr>
              <td class="p-2 border-b">1.</td>
              <td class="p-2 border-b">
                <p class="font-bold">Zakup tokenów benefitowych Eliton Wallet</p>
                <p class="text-gray-500 text-[10px]">Dla {{ employees.length }} pracowników zgodnie z załącznikiem nr 1.</p>
              </td>
              <td class="p-2 border-b text-right">1</td>
              <td class="p-2 border-b text-right">{{ totalBenefitAmount.toFixed(2) }}</td>
              <td class="p-2 border-b text-right">{{ totalBenefitAmount.toFixed(2) }}</td>
              <td class="p-2 border-b text-right">23%</td>
              <td class="p-2 border-b text-right">{{ (totalBenefitAmount * 1.23).toFixed(2) }}</td>
            </tr>
            <tr>
              <td class="p-2 border-b">2.</td>
              <td class="p-2 border-b">
                <p class="font-bold">Opłata serwisowa ({{ client.serviceFeePercent }}%)</p>
              </td>
              <td class="p-2 border-b text-right">1</td>
              <td class="p-2 border-b text-right">{{ invoice.serviceFeeNet.toFixed(2) }}</td>
              <td class="p-2 border-b text-right">{{ invoice.serviceFeeNet.toFixed(2) }}</td>
              <td class="p-2 border-b text-right">23%</td>
              <td class="p-2 border-b text-right">{{ (invoice.serviceFeeNet * 1.23).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="flex justify-end mt-6">
        <div class="w-1/2">
          <table class="w-full">
            <tbody>
              <tr class="text-right">
                <td class="py-1 pr-4 text-gray-600">Suma netto:</td>
                <td class="py-1 font-bold">{{ invoice.amountNet.toFixed(2) }} PLN</td>
              </tr>
              <tr class="text-right">
                <td class="py-1 pr-4 text-gray-600">Podatek VAT (23%):</td>
                <td class="py-1 font-bold">{{ (invoice.amountGross - invoice.amountNet).toFixed(2) }} PLN</td>
              </tr>
              <tr class="text-right text-lg bg-gray-800 text-white font-bold">
                <td class="p-2 pr-4">Do zapłaty:</td>
                <td class="p-2">{{ invoice.amountGross.toFixed(2) }} PLN</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <footer class="mt-12 pt-6 border-t border-gray-300 text-[10px] text-gray-500">
        <p class="font-bold">Numer konta do wpłaty:</p>
        <p>PL 12 1090 1014 0000 0001 4455 6677 (Santander Bank Polska S.A.)</p>
        <p class="mt-4">Faktura wygenerowana elektronicznie, nie wymaga podpisu.</p>
      </footer>

      <div style="page-break-before: always"></div>

      <div class="mt-12">
        <h2 class="text-xl font-bold text-center mb-4">Załącznik nr 1 do faktury {{ invoice.number }}</h2>
        <p class="text-center text-gray-600 mb-6">Wykaz pracowników objętych systemem benefitowym w bieżącym okresie rozliczeniowym.</p>
        <table class="w-full text-left">
          <thead class="bg-gray-100 border-y">
            <tr>
              <th class="p-2 w-10">Lp.</th>
              <th class="p-2">Imię i Nazwisko</th>
              <th class="p-2">PESEL (fragment)</th>
              <th class="p-2">Typ Umowy</th>
              <th class="p-2 text-right">Kwota Benefitu (Netto)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(emp, index) in employees" :key="emp.id">
              <td class="p-2">{{ index + 1 }}.</td>
              <td class="p-2 font-medium">{{ emp.name }}</td>
              <td class="p-2 font-mono">***{{ emp.pesel?.slice(-4) }}</td>
              <td class="p-2">{{ emp.contractType }}</td>
              <td class="p-2 text-right font-medium">{{ emp.benefitAmount.toFixed(2) }} PLN</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-100 border-t-2 font-bold">
            <tr>
              <td colspan="4" class="p-2 text-right">RAZEM:</td>
              <td class="p-2 text-right">{{ totalBenefitAmount.toFixed(2) }} PLN</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div v-else class="text-center p-20 text-gray-500">
      <div class="flex items-center justify-center gap-2">
        <AppIcon name="refresh" class="w-5 h-5 animate-spin" />
        <span>Ładowanie danych faktury...</span>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  .no-print {
    display: none !important;
  }
  body {
    background-color: white;
  }
  .page-container {
    box-shadow: none;
    margin: 0;
    width: 100%;
    max-width: none;
    border: none;
  }
  @page {
    margin: 1cm;
  }
}

.page-container {
  width: 210mm;
  min-height: 297mm;
  padding: 15mm;
  margin: 20px auto;
  background: white;
  border: 1px solid #d3d3d3;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}
</style>
