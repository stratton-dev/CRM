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
  { id: 2, label: 'Suma aktualnego Kosztu zatrudnienia', icon: 'chart-line' },
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
    } else if (normalized === '4') {
      currentStep.value = 4;
    } else if (normalized === '3') {
      currentStep.value = 3;
    } else if (normalized === '2') {
      currentStep.value = 2;
    } else if (normalized === '1' || normalized === 'pracownicy') {
      currentStep.value = 1;   // start od kroku Pracownicy (z listy płac z ProcessStart)
    } else if (normalized === '0' || normalized === 'firma') {
      currentStep.value = 0;
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
          inwestycjePlanowane: analysis.planned_investments != null ? String(analysis.planned_investments) : store.firma.inwestycjePlanowane,
          kwotaOszczednosciDeklarowana: analysis.declared_savings || store.firma.kwotaOszczednosciDeklarowana,
          zadluzenia: analysis.debts || store.firma.zadluzenia,
          ryczaltVat: analysis.vat_model || store.firma.ryczaltVat,
          zusWysokie: analysis.zus_cost_level != null
            ? (Number(analysis.zus_cost_level) ? 'tak' : 'nie')
            : store.firma.zusWysokie,
          wdrazaOszczednosci: analysis.implementing_savings != null
            ? (Number(analysis.implementing_savings) ? 'tak' : 'nie')
            : store.firma.wdrazaOszczednosci,
          wyzwanieKlienta: analysis.client_challenge || store.firma.wyzwanieKlienta,
        };
      })
      .catch(() => {});
  }
});
</script>

<template>
  <div class="w-full min-h-screen bg-slate-50">

    <!-- D365 Top Command Bar — slim dark rail -->
    <div class="bg-[#1a1a2e] border-b border-slate-800/80">
      <div class="w-full px-5 lg:px-8 h-11 flex items-center justify-between gap-4">

        <!-- Left: back + breadcrumb -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            type="button"
            class="inline-flex items-center justify-center w-6 h-6 shrink-0 bg-white/10 border border-white/10 rounded text-slate-400 hover:bg-white/20 hover:text-white transition-all"
            @click="handleBack"
          >
            <AppIcon name="arrow-left" class="w-3 h-3" />
          </button>
          <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-widest select-none">
            <span class="text-slate-500">CRM</span>
            <span class="text-slate-600">/</span>
            <span class="text-slate-400">Kalkulator</span>
            <span class="text-slate-600">/</span>
            <span class="text-white">{{ currentStep === -1 ? 'Pulpit' : steps[currentStep]?.label.replace(/&lt;[^&gt;]*&gt;/g, '').replace(/<[^>]*>/g, '') }}</span>
          </div>
        </div>

        <!-- Center: Firma chip -->
        <div v-if="store.firma.nazwa" class="hidden md:flex items-center gap-2 bg-white/8 border border-white/10 rounded px-2.5 h-6">
          <div class="w-1.5 h-1.5 rounded-full bg-stratton-gold shrink-0"></div>
          <span class="text-[10px] font-bold text-slate-200 truncate max-w-52">{{ store.firma.nazwa }}</span>
          <span v-if="store.firma.nip" class="text-[9px] font-mono text-slate-500 hidden lg:block">· NIP {{ store.firma.nip }}</span>
        </div>

        <!-- Right: Progress indicator + Dalej -->
        <div class="flex items-center gap-3 shrink-0">
          <div v-if="currentStep >= 0" class="hidden lg:flex items-center gap-2">
            <div class="w-24 h-1 bg-white/10 rounded-full overflow-hidden">
              <div
                class="h-full bg-linear-to-r from-stratton-gold to-[#D4AF37] rounded-full transition-all duration-500"
                :style="{ width: `${((currentStep + 1) / steps.length) * 100}%` }"
              ></div>
            </div>
            <span class="text-[9px] text-slate-500 font-black uppercase tracking-widest">{{ currentStep + 1 }}/{{ steps.length }}</span>
          </div>
          <button
            type="button"
            class="h-7 px-4 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded text-[10px] font-black uppercase tracking-widest border border-white/20 hover:brightness-110 active:scale-95 transition-all disabled:opacity-40 disabled:grayscale flex items-center gap-1.5"
            :disabled="!canProceed || currentStep >= steps.length - 1"
            @click="currentStep++"
          >
            Dalej <AppIcon name="arrow-right" class="w-2.5 h-2.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="w-full px-5 lg:px-8 py-6 flex gap-6 items-start">

      <!-- D365 Navigation Rail — left sidebar -->
      <aside class="hidden lg:flex flex-col gap-0.5 w-56 xl:w-60 shrink-0 sticky top-6">

        <!-- App branding sub-header -->
        <div class="flex items-center gap-2 px-3 py-2 mb-2">
          <div class="w-6 h-6 rounded bg-stratton-gold/10 border border-stratton-gold/20 flex items-center justify-center">
            <AppIcon name="shield-check" class="w-3 h-3 text-stratton-gold" />
          </div>
          <div>
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-700">Stratton Prime</div>
            <div class="text-[8px] text-slate-400 font-medium">Kalkulator Eliton Prime™</div>
          </div>
        </div>

        <!-- Pulpit nav item -->
        <button
          type="button"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left transition-all"
          :class="currentStep === -1
            ? 'bg-white border border-stratton-gold/30 text-stratton-gold shadow-sm'
            : 'text-slate-500 hover:bg-white hover:text-slate-700 hover:border-slate-200 hover:shadow-sm border border-transparent'"
          @click="currentStep = -1"
        >
          <div
            class="w-5 h-5 rounded flex items-center justify-center shrink-0 transition-colors"
            :class="currentStep === -1 ? 'bg-stratton-gold/10 text-stratton-gold' : 'bg-slate-100 text-slate-400'"
          >
            <AppIcon name="dashboard" class="w-3 h-3" />
          </div>
          <span class="text-[10px] font-black uppercase tracking-wide">Pulpit</span>
          <span v-if="currentStep === -1" class="ml-auto w-1 h-4 rounded-full bg-stratton-gold shrink-0"></span>
        </button>

        <div class="w-full h-px bg-slate-200/80 my-1.5"></div>

        <!-- Step nav items -->
        <button
          v-for="step in steps"
          :key="step.id"
          type="button"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left transition-all"
          :class="currentStep === step.id
            ? 'bg-white border border-stratton-gold/30 text-stratton-gold shadow-sm'
            : 'text-slate-500 hover:bg-white hover:text-slate-700 hover:border-slate-200 hover:shadow-sm border border-transparent'"
          @click="currentStep = step.id"
        >
          <div
            class="w-5 h-5 rounded flex items-center justify-center shrink-0 text-[8px] font-black transition-colors"
            :class="currentStep === step.id
              ? 'bg-stratton-gold/10 text-stratton-gold'
              : currentStep > step.id
                ? 'bg-emerald-500 text-white'
                : 'bg-slate-100 text-slate-400'"
          >
            <AppIcon v-if="currentStep > step.id" name="check" class="w-2.5 h-2.5" />
            <span v-else>{{ step.id + 1 }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-[10px] font-black uppercase tracking-wide leading-tight truncate" v-html="step.label"></div>
          </div>
          <span v-if="currentStep === step.id" class="ml-auto w-1 h-4 rounded-full bg-stratton-gold shrink-0"></span>
          <AppIcon v-else-if="currentStep > step.id" name="check-circle" class="w-3 h-3 text-emerald-500 shrink-0" />
        </button>

        <!-- Firma card -->
        <div class="relative mt-4 rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/60">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Wybrana firma</span>
          </div>
          <div class="px-3 py-2.5">
            <div v-if="store.firma.nazwa">
              <div class="text-[11px] font-bold text-slate-800 leading-snug">{{ store.firma.nazwa }}</div>
              <div v-if="store.firma.nip" class="text-[9px] text-slate-400 font-mono mt-0.5">NIP {{ store.firma.nip }}</div>
            </div>
            <div v-else class="text-[10px] text-slate-400 italic">Nie wybrano firmy</div>
            <button
              type="button"
              class="mt-2 w-full h-6 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-stratton-gold border border-slate-200 rounded transition-colors hover:border-stratton-gold/40"
              @click="showCompanyPicker = !showCompanyPicker"
            >
              Zmień firmę
            </button>
          </div>

          <!-- Company picker dropdown -->
          <div v-if="showCompanyPicker" class="absolute left-0 top-full z-20 mt-1.5 w-80 bg-white border border-slate-200 rounded-xl shadow-2xl p-3">
            <div class="flex items-center gap-2 mb-2">
              <div class="relative flex-1">
                <input
                  v-model="companySearch"
                  type="text"
                  placeholder="Szukaj po nazwie lub NIP..."
                  class="w-full pl-7 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold"
                />
                <AppIcon name="search" class="w-3 h-3 text-slate-400 absolute left-2.5 top-2" />
              </div>
              <button type="button" class="text-[10px] text-slate-400 hover:text-slate-700 font-bold w-5 h-5 flex items-center justify-center" @click="showCompanyPicker = false">✕</button>
            </div>
            <div class="max-h-52 overflow-y-auto border border-slate-100 rounded-lg divide-y divide-slate-50">
              <button
                v-for="client in eligibleClients"
                :key="client.id"
                type="button"
                class="w-full text-left px-3 py-2 hover:bg-amber-50/40 flex items-center justify-between gap-2 transition-colors"
                @click="selectCompany(client.id)"
              >
                <div class="min-w-0">
                  <div class="text-[11px] font-semibold text-slate-800 truncate">{{ client.name }}</div>
                  <div class="text-[9px] text-slate-500 font-mono">{{ client.nip }}</div>
                </div>
                <div class="text-[8px] uppercase font-black text-slate-400 shrink-0">{{ client.status }}</div>
              </button>
              <div v-if="eligibleClients.length === 0" class="px-3 py-3 text-[10px] text-slate-400 text-center">Brak firm</div>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <section class="flex-1 min-w-0">
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
