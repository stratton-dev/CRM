<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '@/components/calculator/store/useCalculatorStore';
import { Pracownik } from '@/components/calculator/models/employee';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { useClientStore } from '@/stores/client';
import DashboardStep from '@/components/calculator/steps/DashboardStep.vue';
import CompanyStep from '@/components/calculator/steps/CompanyStep.vue';
import EmployeesStep from '@/components/calculator/steps/EmployeesStep.vue';
import ResultsStandardStep from '@/components/calculator/steps/ResultsStandardStep.vue';
import ResultsSplitStep from '@/components/calculator/steps/ResultsSplitStep.vue';
import ComparisonStep from '@/components/calculator/steps/ComparisonStep.vue';
import SummaryStep from '@/components/calculator/steps/SummaryStep.vue';

const store = useCalculatorStore();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();
const clientStore = useClientStore();
const { clients } = storeToRefs(clientStore);
const currentStep = ref(-1);
const companySearch = ref('');
const showCompanyPicker = ref(false);

const handleBack = () => {
  if (currentStep.value > -1) {
    currentStep.value--;
  } else {
    router.push('/app/quick-calculator');
  }
};

const steps = [
  { id: 0, label: 'Firma', icon: 'building' },
  { id: 1, label: 'Pracownicy', icon: 'users' },
  { id: 2, label: 'Obecny model wynagrodzenia', icon: 'chart-line' },
  { id: 3, label: 'Podział wynagrodzenia na zasadnicze i świadczenie', icon: 'chart-pie' },
  { id: 4, label: 'Oszczędności', icon: 'sliders' },
  { id: 5, label: 'Podsumowanie', icon: 'file-invoice-dollar' },
];

const canProceed = computed(() => {
  if (currentStep.value === 0) return Boolean(store.firma.nazwa);
  if (currentStep.value === 1) return store.pracownicy.length > 0;
  return true;
});

const statusOrder = ['NEW', 'IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT', 'SPECIAL_OFFER', 'RESIGNED', 'SIGNED', 'TERMINATED'];
const eligibleStatuses = new Set(['OFFER_GENERATED', 'CALCULATION_SENT', 'SPECIAL_OFFER', 'SIGNED', 'TERMINATED', 'RESIGNED']);

const eligibleClients = computed(() => {
  const list = Array.isArray(clients.value) ? clients.value : [];
  const term = companySearch.value.trim().toLowerCase();
  return list
    .filter((client) => {
      if (!term) return true;
      const hay = `${client.name} ${client.nip}`.toLowerCase();
      return hay.includes(term);
    })
    .sort((a, b) => {
      const rankA = statusOrder.indexOf(a.status);
      const rankB = statusOrder.indexOf(b.status);
      if (rankA !== rankB) return rankB - rankA;
      return a.name.localeCompare(b.name);
    });
});

const selectCompany = (clientId: string) => {
  const match = (Array.isArray(clients.value) ? clients.value : []).find((client) => client.id === clientId);
  if (!match) return;
  store.firma = {
    ...store.firma,
    nazwa: match.name || '',
    nip: match.nip || '',
    adres: match.street || '',
    kodPocztowy: match.zip || '',
    miasto: match.city || '',
    email: match.contactEmail || '',
    telefon: match.contactPhone || '',
    osobaKontaktowa: match.contactName || '',
  };
  store.setContext({ clientId: match.id });
  showCompanyPicker.value = false;
};

const seedEmployees = (count: number, avgWage: number, contractType: string | null) => {
  if (!count || store.pracownicy.length > 0) return;
  const employees: Pracownik[] = [];
  const uopCount = contractType === 'Mix' ? Math.round(count * 0.5) : contractType === 'UZ' ? 0 : count;
  const uzCount = contractType === 'UZ' ? count : contractType === 'Mix' ? count - uopCount : 0;

  let idCounter = Date.now();
  const createEmp = (type: 'UOP' | 'UZ', i: number): Pracownik => ({
    id: idCounter + i,
    imie: 'Pracownik',
    nazwisko: `${type} ${i + 1}`,
    dataUrodzenia: '1990-01-01',
    plec: 'M',
    typUmowy: type,
    trybSkladek: 'PELNE',
    choroboweAktywne: true,
    pit2: '300',
    ulgaMlodych: false,
    kupTyp: type === 'UZ' ? 'PROC_20' : 'STANDARD',
    nettoDocelowe: avgWage,
    nettoZasadnicza: type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto,
    pitMode: 'AUTO',
    skladkaFP: true,
    skladkaFGSP: true,
  });

  for (let i = 0; i < uopCount; i++) employees.push(createEmp('UOP', i));
  for (let i = 0; i < uzCount; i++) employees.push(createEmp('UZ', uopCount + i));
  store.pracownicy = employees;
};

onMounted(() => {
  const meetingId = Array.isArray(route.query.meetingId) ? route.query.meetingId[0] : route.query.meetingId;
  const clientId = Array.isArray(route.query.clientId) ? route.query.clientId[0] : route.query.clientId;
  const employees = Array.isArray(route.query.employees) ? route.query.employees[0] : route.query.employees;
  const avgWage = Array.isArray(route.query.avgWage) ? route.query.avgWage[0] : route.query.avgWage;
  const contractType = Array.isArray(route.query.contractType) ? route.query.contractType[0] : route.query.contractType;

  store.setContext({ meetingId: meetingId ? String(meetingId) : null, clientId: clientId ? String(clientId) : null, source: 'detailed' });
  seedEmployees(Number(employees || 0), Number(avgWage || 0), contractType ? String(contractType) : null);

  if (auth.enabled) {
    clientStore.fetchClients({ perPage: 200 });
  }

  if (auth.enabled && clientId) { // Removed !store.firma.nazwa check to always refresh selected client
    api.get(`/v1/clients/${clientId}`)
      .then(({ data }) => {
        store.firma = {
          ...store.firma,
          nazwa: data?.name || store.firma.nazwa,
          nip: data?.nip || store.firma.nip,
          adres: data?.street || store.firma.adres, // Corrected from address_line1 based on other views
          kodPocztowy: data?.zip || store.firma.kodPocztowy, // Corrected from postal_code based on other views
          miasto: data?.city || store.firma.miasto,
          email: data?.email || store.firma.email,
          telefon: data?.phone || store.firma.telefon,
        };
      })
      .catch((error) => {
        const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych klienta.';
        toast.warning(message);
      });
  }
});
</script>

<template>
  <div class="w-full max-w-7xl mx-auto px-6 py-8 space-y-8">
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-serif font-bold text-slate-900 flex items-center gap-3">
          Kalkulator szczegółowy
          <span class="text-xs bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded border border-emerald-200 uppercase tracking-wider">Aktywny</span>
        </h1>
        <p class="text-xs text-slate-400 uppercase tracking-widest">
          {{ currentStep === -1 ? 'Pulpit' : `Krok ${currentStep + 1} / ${steps.length}` }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="px-4 py-2 text-xs font-bold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors" @click="handleBack">
          Wstecz
        </button>
        <button type="button" class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white shadow-sm hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all" :disabled="!canProceed || currentStep >= steps.length - 1" @click="currentStep++">
          Dalej
        </button>
      </div>
    </header>

    <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <div class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold">Firma dla kalkulacji</div>
        <div class="text-lg font-bold text-slate-900">
          {{ store.firma.nazwa || 'Nie wybrano firmy' }}
        </div>
        <div v-if="store.firma.nip" class="text-xs text-slate-500 font-mono">NIP: {{ store.firma.nip }}</div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="px-4 py-2 text-xs font-bold rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" @click="showCompanyPicker = !showCompanyPicker">
          Zmień firmę
        </button>
      </div>

      <div v-if="showCompanyPicker" class="absolute right-4 top-full mt-3 w-full max-w-xl bg-white border border-slate-200 rounded-xl shadow-xl p-4 z-20">
        <div class="flex items-center gap-2 mb-3">
          <div class="relative flex-1">
            <input v-model="companySearch" type="text" placeholder="Szukaj po nazwie lub NIP..." class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-slate-400 focus:border-slate-400" />
            <AppIcon name="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          </div>
          <button type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="showCompanyPicker = false">Zamknij</button>
        </div>
        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 border border-slate-100 rounded-lg">
          <button
            v-for="client in eligibleClients"
            :key="client.id"
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-slate-50 flex items-center justify-between"
            @click="selectCompany(client.id)"
          >
            <div>
              <div class="text-sm font-semibold text-slate-800">{{ client.name }}</div>
              <div class="text-xs text-slate-500 font-mono">{{ client.nip }}</div>
            </div>
            <div class="text-[10px] uppercase font-bold text-slate-500">
              {{ client.status }}
            </div>
          </button>
          <div v-if="eligibleClients.length === 0" class="px-3 py-4 text-xs text-slate-500">
            Brak firm spełniających warunki (min. etap generowania oferty).
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <aside class="lg:col-span-3 space-y-3">
        <button type="button" class="w-full p-4 rounded-xl border text-left transition" :class="currentStep === -1 ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:bg-slate-50'" @click="currentStep = -1">
          <div class="flex items-center gap-3">
            <AppIcon name="dashboard" class="w-5 h-5" />
            <div>
              <div class="font-bold">Pulpit</div>
              <div class="text-[10px] uppercase tracking-widest opacity-70">Start</div>
            </div>
          </div>
        </button>
        <button v-for="step in steps" :key="step.id" type="button" class="w-full p-4 rounded-xl border text-left transition" :class="currentStep === step.id ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:bg-slate-50'" @click="currentStep = step.id">
          <div class="flex items-center gap-3">
            <AppIcon :name="step.icon" class="w-5 h-5" />
            <div>
              <div class="font-bold">{{ step.label }}</div>
              <div class="text-[10px] uppercase tracking-widest opacity-70">Krok {{ step.id + 1 }}</div>
            </div>
          </div>
        </button>
      </aside>

      <section class="lg:col-span-9">
        <DashboardStep v-if="currentStep === -1" @start="currentStep = 0" />
        <CompanyStep v-else-if="currentStep === 0" />
        <EmployeesStep v-else-if="currentStep === 1" />
        <ResultsStandardStep v-else-if="currentStep === 2" />
        <ResultsSplitStep v-else-if="currentStep === 3" />
        <ComparisonStep v-else-if="currentStep === 4" />
        <SummaryStep v-else @backToDashboard="currentStep = -1" />
      </section>
    </div>
  </div>
</template>
