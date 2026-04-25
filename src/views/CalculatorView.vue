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
import BusinessCaseStep from '@/components/calculator/steps/BusinessCaseStep.vue';
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
    if (route.query.source === 'process') {
      const clientId = route.query.clientId
      const meetingId = route.query.meetingId
      router.push({
        path: '/app/sales/start',
        query: { clientId, meetingId, step: 4 }
      })
    } else {
      router.back();
    }
  }
};

const steps = [
  { id: 0, label: 'Firma', icon: 'building' },
  { id: 1, label: 'Pracownicy', icon: 'users' },
  { id: 2, label: 'Suma aktualnego kosztu zatrudnienia', icon: 'chart-line' },
  { id: 3, label: 'Wynagrodzenie w modelu Eliton Prime<sup>TM</sup>', icon: 'chart-pie' },
  { id: 4, label: 'Oszczędności po wdrożeniu Eliton Prime<sup>TM</sup>', icon: 'sliders' },
  { id: 5, label: 'Podsumowanie', icon: 'file-invoice-dollar' },
];

const canProceed = computed(() => {
  if (currentStep.value === 0) return Boolean(store.firma.nazwa);
  if (currentStep.value === 1) return store.pracownicy.length > 0;
  return true;
});

const statusOrder = ['NEW', 'OFFER_PREPARING', 'CALCULATION_SENT', 'RESIGNED', 'SIGNED', 'TERMINATED'];
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
  void fetchClientContacts(match.id);
  store.setContext({ clientId: match.id });
  showCompanyPicker.value = false;
};

const fetchClientContacts = async (clientId: string) => {
  if (!auth.enabled) return;
  try {
    const { data } = await api.get(`/v1/clients/${clientId}/contacts`);
    const contacts = Array.isArray(data)
      ? data.map((item: any) => ({
          id: String(item.id),
          name: String(item.name || ''),
          position: item.position || null,
          phone: item.phone || null,
          email: item.email || null,
          is_decision_maker: item.is_decision_maker ?? item.isDecisionMaker ?? null,
        }))
      : [];
    store.firma.kontakty = contacts;
    const decisionIds = contacts.filter((c: any) => c.is_decision_maker).map((c: any) => c.id);
    store.firma.kontaktIds = decisionIds.length > 0 ? decisionIds : contacts.slice(0, 1).map((c: any) => c.id);
    const decision = contacts.find((c: any) => c.is_decision_maker) || contacts[0];
    if (decision) {
      store.firma.osobaKontaktowa = decision.name || store.firma.osobaKontaktowa;
      store.firma.email = decision.email || store.firma.email;
      store.firma.telefon = decision.phone || store.firma.telefon;
    }
  } catch (error) {
    // ignore missing contacts in calculator context
  }
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
  const stepParam = Array.isArray(route.query.step) ? route.query.step[0] : route.query.step;

  store.setContext({ meetingId: meetingId ? String(meetingId) : null, clientId: clientId ? String(clientId) : null, source: 'detailed' });
  seedEmployees(Number(employees || 0), Number(avgWage || 0), contractType ? String(contractType) : null);

  if (stepParam) {
    const normalized = String(stepParam).toLowerCase();
    if (normalized === 'summary' || normalized === 'podsumowanie' || normalized === '5') {
      currentStep.value = 5;
    }
  }

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
          adres: data?.address_line1 || data?.street || store.firma.adres, 
          kodPocztowy: data?.postal_code || data?.zip || store.firma.kodPocztowy, 
          miasto: data?.city || store.firma.miasto,
          email: data?.contact_email || data?.email || store.firma.email,
          telefon: data?.contact_phone || data?.phone || store.firma.telefon,
        };
      })
      .catch((error) => {
        const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych klienta.';
        toast.warning(message);
      });
  }

  if (auth.enabled && clientId) {
    void fetchClientContacts(String(clientId));
  }

  if (auth.enabled && meetingId) {
    api.get('/v1/meeting-analyses', { params: { meeting_id: meetingId, per_page: 1 } })
      .then(({ data }) => {
        const list = Array.isArray(data?.data) ? data.data : [];
        const analysis = list.length ? list[0] : null;
        if (!analysis) return;
        store.firma = {
          ...store.firma,
          branza: analysis.industry || store.firma.branza,
          benefity: analysis.benefits || store.firma.benefity,
          udzialWProjekcie: analysis.project_participation || store.firma.udzialWProjekcie,
          oszczednosciPrzeszle: analysis.past_savings || store.firma.oszczednosciPrzeszle,
          oszczednosciAktualne: analysis.current_savings || store.firma.oszczednosciAktualne,
          inwestycjePlanowane: analysis.planned_investments || store.firma.inwestycjePlanowane,
          kwotaOszczednosciDeklarowana: analysis.declared_savings || store.firma.kwotaOszczednosciDeklarowana,
          zadluzenia: analysis.debts || store.firma.zadluzenia,
          ryczaltVat: analysis.vat_model || store.firma.ryczaltVat,
        };
      })
      .catch(() => {});
  }
});
</script>

<template>
  <div class="w-full max-w-7xl mx-auto px-6 py-8 space-y-8">
    <div class="rounded-card shadow-card-hover border p-8" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%); border-color: #003366;">
      <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-6">
        <div class="flex items-center gap-6 self-start md:self-center">
            <button type="button" class="inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-md text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group" @click="handleBack">
              <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
            </button>
            <div>
              <h1 class="text-3xl font-serif font-bold text-white flex items-center gap-3">
                Kalkulator szczegółowy
                <span class="text-[10px] bg-emerald-500/10 text-emerald-500 font-bold px-2 py-0.5 rounded border border-emerald-500/20 uppercase tracking-wider">Aktywny</span>
              </h1>
              <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">
                {{ currentStep === -1 ? 'Pulpit' : `Krok ${currentStep + 1} / ${steps.length}` }}
              </p>
            </div>
        </div>
        
        <button 
          type="button" 
          class="h-12 bg-linear-to-r from-[#D4AF37] to-[#C5A059] text-white px-8 rounded-md shadow-md transition-all duration-300 font-extrabold uppercase tracking-widest flex items-center justify-center gap-3 group disabled:opacity-50 disabled:grayscale self-end md:self-center border border-white/20 hover:brightness-110 active:scale-95"
          :disabled="!canProceed || currentStep >= steps.length - 1" 
          @click="currentStep++"
        >
          <span class="text-xs">Dalej</span>
          <AppIcon name="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
        </button>
      </div>

      <div class="relative bg-slate-800 rounded-2xl border border-slate-700 shadow-sm p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 group hover:border-slate-600 transition-colors">
        <div>
          <div class="text-[10px] uppercase tracking-widest text-slate-400 font-bold mb-1">Firma dla kalkulacji</div>
          <div class="text-xl font-bold text-white tracking-tight">
            {{ store.firma.nazwa || 'Nie wybrano firmy' }}
          </div>
          <div v-if="store.firma.nip" class="text-xs text-slate-400 font-mono mt-1">NIP: <span class="text-slate-300">{{ store.firma.nip }}</span></div>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="px-4 py-2 text-xs font-bold rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors" @click="showCompanyPicker = !showCompanyPicker">
            Zmień firmę
          </button>
        </div>

        <div v-if="showCompanyPicker" class="absolute right-4 top-full mt-3 w-full max-w-xl bg-white border border-slate-200 rounded-xl shadow-xl p-4 z-20">
        <div class="flex items-center gap-2 mb-3">
          <div class="relative flex-1">
            <input v-model="companySearch" type="text" placeholder="Szukaj po nazwie lub NIP..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-slate-400 focus:border-slate-400 text-right font-bold" />
            <AppIcon name="search" class="w-4 h-4 text-slate-400 absolute left-3 top-[10px]" />
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
  </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <aside class="lg:col-span-3 space-y-3">
        <button type="button" class="w-full p-4 rounded-xl border-2 text-left transition-all duration-300" :class="currentStep === -1 ? 'border-stratton-gold bg-linear-to-br from-[#D4AF37] to-[#C5A059] text-white shadow-[0_8px_20px_-4px_rgba(197,160,89,0.35)]' : 'border-slate-100 bg-white text-slate-500 hover:border-stratton-gold hover:bg-slate-50'" @click="currentStep = -1">
          <div class="flex items-center gap-3">
            <AppIcon name="dashboard" class="w-5 h-5" />
            <div>
              <div class="font-bold">Pulpit</div>
              <div class="text-[10px] uppercase tracking-widest font-extrabold" :class="currentStep === -1 ? 'text-white/80' : 'text-slate-400'">Start</div>
            </div>
          </div>
        </button>
        <button v-for="step in steps" :key="step.id" type="button" class="w-full p-4 rounded-xl border-2 text-left transition-all duration-300" :class="currentStep === step.id ? 'border-stratton-gold bg-linear-to-br from-[#D4AF37] to-[#C5A059] text-white shadow-[0_8px_20px_-4px_rgba(197,160,89,0.35)]' : 'border-slate-100 bg-white text-slate-500 hover:border-stratton-gold hover:bg-slate-50'" @click="currentStep = step.id">
          <div class="flex items-center gap-3">
            <AppIcon :name="step.icon" class="w-5 h-5" />
            <div>
              <div class="font-bold" v-html="step.label"></div>
              <div class="text-[10px] uppercase tracking-widest font-extrabold" :class="currentStep === step.id ? 'text-white/80' : 'text-slate-400'">Krok {{ step.id + 1 }}</div>
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
        <BusinessCaseStep v-else-if="currentStep === 4" />
        <SummaryStep v-else @backToDashboard="currentStep = -1" />
      </section>
    </div>
  </div>
</template>
