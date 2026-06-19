<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { LocationQueryValue } from 'vue-router'
import { useClientStore } from '@/stores/client'
import { storeToRefs } from 'pinia'
import AppIcon from '@/components/AppIcon.vue'

const clientStore = useClientStore()
const { clients } = storeToRefs(clientStore)
const route = useRoute()
const router = useRouter()

const client = ref<any>(undefined)
const today = new Date()
const currentYear = new Date().getFullYear()
const viewMode = ref<'preview' | 'sent'>('preview')
const isDraft = ref(false)
const annualSaving = ref(0)

const getQueryString = (value: LocationQueryValue | LocationQueryValue[] | undefined) => {
  const raw = Array.isArray(value) ? value[0] : value
  return raw || ''
}

onMounted(() => {
  const id = route.params.clientId as string | undefined
  if (id) {
    const c = clients.value.find((cl) => cl.id === id)
    if (c) client.value = c
    return
  }

  if (route.query.companyName) {
    isDraft.value = true
    annualSaving.value = Number(getQueryString(route.query.annual)) || 0
    client.value = {
      name: getQueryString(route.query.companyName) || 'Nowa Firma Sp. z o.o.',
      nip: getQueryString(route.query.nip) || '000-000-00-00',
      street: getQueryString(route.query.address) || 'ul. Testowa',
      city: getQueryString(route.query.city) || 'Warszawa',
      zip: getQueryString(route.query.zip) || '00-000',
      employeesTotal: Number(getQueryString(route.query.employees)) || 10,
      serviceFeePercent: 1.5,
      contactName: 'Jan Kowalski',
    }
  }
})

const printDoc = () => {
  window.print()
}

const sendToAutenti = () => {
  viewMode.value = 'sent'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const markAsSigned = () => {
  if (!client.value) return
  if (window.confirm('Czy na pewno chcesz oznaczyć umowę jako PODPISANĄ?')) {
    clientStore.signContract(client.value.id)
    router.push({ path: '/app/clients', query: { expand: client.value.id } })
  }
}

const goBack = () => {
  if (viewMode.value === 'sent') {
    router.push('/app/dashboard')
    return
  }

  if (isDraft.value) {
    router.push('/app/sales/email-compose')
  } else {
    router.push('/app/clients')
  }
}
</script>

<template>
  <div class="bg-gray-100 min-h-screen pb-10 overflow-x-auto">
    <div class="no-print bg-slate-900 text-white p-4 flex flex-wrap gap-3 justify-between items-center shadow-lg sticky top-0 z-50">
      <div class="flex items-center space-x-4">
        <button type="button" class="hover:text-sky-400 flex items-center" @click="goBack">
          <AppIcon name="arrow-left" class="w-5 h-5 mr-1" />
          Wróć
        </button>
        <span class="font-bold border-l border-slate-600 pl-4">Podgląd Umowy</span>
        <span v-if="client?.status === 'SIGNED'" class="bg-green-500 text-white text-xs px-2 py-1 rounded font-bold uppercase tracking-wide ml-2">Umowa Podpisana</span>
        <span v-else-if="viewMode === 'sent'" class="bg-blue-500 text-white text-xs px-2 py-1 rounded font-bold uppercase tracking-wide ml-2">Wysłano (Autenti)</span>
      </div>
      <div class="flex space-x-3">
        <template v-if="viewMode === 'preview'">
          <button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded font-bold shadow transition flex items-center animate-pulse" @click="sendToAutenti">
            Wyślij przez Autenti
          </button>
          <button type="button" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded font-bold shadow transition inline-flex items-center gap-2" @click="printDoc">
            <AppIcon name="printer" class="w-4 h-4" />
            PDF
          </button>
        </template>
      </div>
    </div>

    <div v-if="viewMode === 'sent'" class="max-w-2xl mx-auto mt-20 text-center animate-fade-in-up">
      <div class="bg-white p-10 rounded-xl shadow-xl border border-gray-200">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-600">
          <AppIcon name="check-circle" class="w-10 h-10" />
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Dokument wysłany do podpisu</h2>
        <p class="text-gray-500 mb-6">Umowa została przekazana do platformy Autenti. Klient otrzyma powiadomienie email z prośbą o podpisanie dokumentu.</p>
        <div class="bg-gray-50 p-4 rounded-lg flex items-center justify-between mb-8 text-sm">
          <span class="text-gray-500">Status:</span>
          <span class="font-bold text-blue-600 flex items-center">
            <span class="w-2 h-2 bg-blue-600 rounded-full animate-ping mr-2"></span>
            Oczekiwanie na podpis
          </span>
        </div>
        <button type="button" class="text-indigo-600 font-bold hover:underline" @click="router.push('/app/dashboard')">Wróć do pulpitu</button>
      </div>
    </div>

    <div v-if="client && viewMode === 'preview'" class="page-container font-serif text-sm leading-relaxed text-justify text-gray-900 relative">
      <div v-if="isDraft" class="autenti-watermark">DRAFT</div>

      <div class="text-center mb-8">
        <h1 class="text-xl font-bold uppercase mb-2">Umowa Ramowa o Współpracy</h1>
        <p class="text-gray-500">Nr: STR/{{ currentYear }}/{{ String(client.nip || '').slice(0, 4) }}</p>
      </div>

      <div class="mb-6">
        <p class="mb-2">Zawarta w dniu <span class="font-bold">{{ new Date(client.contractSignedDate || today).toLocaleDateString('pl-PL') }}</span> w Warszawie, pomiędzy:</p>
        <div class="mb-4 pl-4 border-l-2 border-gray-300">
          <p class="font-bold">Stratton Prime Sp. z o.o.</p>
          <p>ul. Biznesowa 1, 00-001 Warszawa</p>
          <p>NIP: 525-000-00-00, zwaną dalej <span class="font-bold">"Zleceniobiorcą"</span>,</p>
        </div>
        <p class="mb-2">a</p>
        <div class="mb-4 pl-4 border-l-2 border-gray-300">
          <p class="font-bold">{{ client.name }}</p>
          <p>{{ client.street }} {{ client.buildingNr }} {{ client.localeNr }}, {{ client.zip }} {{ client.city }}</p>
          <p>NIP: {{ client.nip }}, reprezentowaną przez: {{ client.contactName }}</p>
          <p>zwaną dalej <span class="font-bold">"Zleceniodawcą"</span>.</p>
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="font-bold uppercase text-xs border-b border-gray-400 pb-1 mt-6">§1 Przedmiot Umowy</h3>
        <p>1. Przedmiotem niniejszej umowy jest świadczenie przez Zleceniobiorcę usług optymalizacji kosztów pracowniczych oraz udostępnienie systemu benefitowego "Eliton Wallet".</p>
        <p>2. Zleceniobiorca zobowiązuje się do rzetelnego wykonywania powierzonych zadań, zgodnie z przyjętym harmonogramem wdrożenia.</p>

        <h3 class="font-bold uppercase text-xs border-b border-gray-400 pb-1 mt-6">§2 Oświadczenia Stron</h3>
        <p>1. Zleceniodawca oświadcza, że zatrudnia pracowników ({{ client.employeesTotal }} os.) i jest uprawniony do zawarcia niniejszej umowy.</p>
        <p>2. Strony ustalają, że wdrożenie systemu obejmie {{ client.employeesTotal }} użytkowników w pierwszym etapie.</p>

        <h3 class="font-bold uppercase text-xs border-b border-gray-400 pb-1 mt-6">§3 Wynagrodzenie</h3>
        <p>1. Z tytułu realizacji umowy Zleceniodawca zapłaci Zleceniobiorcy wynagrodzenie prowizyjne (opłatę serwisową) w wysokości <span class="font-bold">{{ client.serviceFeePercent }}%</span> wartości netto zamówionych tokenów.</p>
        <p>2. Płatność nastąpi na podstawie faktury VAT w terminie 7 dni od dnia wystawienia.</p>
        <p>3. Szacowana wartość oszczędności rocznej wynosi: <span class="font-bold">{{ annualSaving.toLocaleString() }} PLN</span>.</p>

        <h3 class="font-bold uppercase text-xs border-b border-gray-400 pb-1 mt-6">§4 Postanowienia Końcowe</h3>
        <p>1. Umowa zostaje zawarta na czas nieokreślony z 1-miesięcznym okresem wypowiedzenia.</p>
        <p>2. Wszelkie zmiany umowy wymagają formy pisemnej lub elektronicznej (Autenti) pod rygorem nieważności.</p>
      </div>

      <div class="mt-16 grid grid-cols-2 gap-10">
        <div class="text-center">
          <div class="border-b border-dotted border-black h-12 mb-2 bg-yellow-50/50 flex items-end justify-center pb-1 relative">
            <span class="text-xs text-gray-300 absolute bottom-1 right-1">Podpisano przez: Autenti</span>
          </div>
          <p class="text-xs uppercase">Zleceniodawca</p>
        </div>
        <div class="text-center">
          <div class="border-b border-dotted border-black h-12 mb-2 relative"></div>
          <p class="text-xs uppercase">Zleceniobiorca</p>
        </div>
      </div>

      <div v-if="!isDraft" class="mt-10 text-center no-print">
        <button type="button" class="bg-emerald-600 text-white px-4 py-2 rounded font-bold" @click="markAsSigned">
          Oznacz jako podpisaną
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }

  .print-only {
    display: block !important;
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
    margin: 2cm;
  }
}

.page-container {
  width: 210mm;
  min-height: 297mm;
  padding: 20mm;
  margin: 20px auto;
  background: white;
  border: 1px solid #d3d3d3;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.autenti-watermark {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-45deg);
  font-size: 120px;
  color: rgba(0, 0, 0, 0.05);
  font-weight: bold;
  pointer-events: none;
  z-index: 10;
  white-space: nowrap;
}
</style>
