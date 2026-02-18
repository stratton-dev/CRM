<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';
import { ZapisanaKalkulacja } from '../models/history';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

const emit = defineEmits<{ (event: 'backToDashboard'): void }>();
const store = useCalculatorStore();
const auth = useAuthStore();
const router = useRouter();
const isSaving = ref(false);
const showOfferModal = ref(false);
const documentLayout = ref<'horizontal' | 'vertical'>('vertical');
const offerStatus = ref<'preparing' | 'generated' | 'sent' | null>(null);
const latestCalculationId = ref<string | null>(null);
const offerAction = ref<'generate' | 'preview'>('generate');

const summary = computed(() => store.wyniki?.podsumowanie || null);
const isOfferLocked = computed(() => offerStatus.value === 'generated' || offerStatus.value === 'sent');

const chartData = computed(() => {
  if (!summary.value) return [];
  const monthly = summary.value.oszczednoscNetto;
  return Array.from({ length: 12 }, (_, i) => ({
    label: i + 1,
    value: monthly * (i + 1)
  }));
});

const maxChartValue = computed(() => {
  if (chartData.value.length === 0) return 1;
  const actualMax = chartData.value[chartData.value.length - 1].value;
  // Round up to nearest 50k for cleaner axis
  return Math.ceil(actualMax / 50000) * 50000;
});

const yAxisTicks = computed(() => {
  const ticks = [];
  const step = 50000;
  for (let val = 0; val <= maxChartValue.value; val += step) {
    ticks.push(val);
  }
  return ticks.reverse();
});

const offerButtonLabel = computed(() => (isOfferLocked.value ? 'Oferta gotowa' : 'Generuj ofertę'));

const buildSnapshot = (): ZapisanaKalkulacja | null => {
  if (!store.wyniki) return null;
  return {
    id: Date.now().toString(),
    dataUtworzenia: new Date().toISOString(),
    nazwaFirmy: store.firma.nazwa || 'Bez nazwy',
    liczbaPracownikow: store.pracownicy.length,
    oszczednoscRoczna: store.wyniki.podsumowanie.oszczednoscRoczna,
    dane: {
      firma: store.firma,
      pracownicy: store.pracownicy,
      config: store.config,
      prowizjaProc: store.prowizjaProc,
    },
  };
};

const handleSave = async () => {
  await store.saveCalculationToApi();
  store.saveToHistory();
};

const handleExcel = async () => {
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  await store.generateExcelReport(snapshot);
};

const handleDetailedExcel = async () => {
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  await store.generateDetailedExcelReport(snapshot);
};

const handlePreviewOffer = async (layout: 'horizontal' | 'vertical') => {
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  await store.generateOfferPdf(snapshot, { documentLayout: layout });
};

const handleGenerateOffer = async (layout: 'horizontal' | 'vertical') => {
  if (isOfferLocked.value) {
    await handlePreviewOffer(layout);
    return;
  }
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  try {
    isSaving.value = true;
    await store.updateMeetingOfferStatus('preparing');
    const calc = await store.saveCalculationToApi();
    store.saveToHistory();
    await store.generateOfferPdf(snapshot, { documentLayout: layout });
    await store.updateMeetingOfferStatus('generated');
    await store.updateClientStatus('OFFER_GENERATED');
    if (calc?.id) {
      await store.updateCalculationStatus(String(calc.id), 'READY');
      latestCalculationId.value = String(calc.id);
    }
    offerStatus.value = 'generated';
  } catch (error) {
    console.error(error);
  } finally {
    isSaving.value = false;
  }
};

const openOfferModal = () => {
  documentLayout.value = 'vertical';
  offerAction.value = isOfferLocked.value ? 'preview' : 'generate';
  showOfferModal.value = true;
};

const confirmGenerateOffer = async () => {
  showOfferModal.value = false;
  if (offerAction.value === 'preview') {
    await handlePreviewOffer(documentLayout.value);
    return;
  }
  await handleGenerateOffer(documentLayout.value);
};

const openOfferEmail = () => {
  const meetingId = store.context.meetingId ? String(store.context.meetingId) : '';
  const clientId = store.context.clientId ? String(store.context.clientId) : '';
  router.push({
    path: '/app/sales/email-compose',
    query: {
      meetingId,
      clientId,
      template: 'offer-calculator',
    },
  });
};

onMounted(async () => {
  if (!auth.enabled || !store.context.meetingId) return;
  try {
    const { data } = await api.get(`/v1/meetings/${store.context.meetingId}`);
    offerStatus.value = data?.offer_status || null;
    const list = Array.isArray(data?.calculations) ? data.calculations : [];
    if (list.length > 0) {
      const latest = list.sort((a: any, b: any) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())[0];
      if (latest?.id) latestCalculationId.value = String(latest.id);
      if (offerStatus.value === 'generated' && latest?.id && String(latest.status || '').toUpperCase() !== 'READY') {
        await store.updateCalculationStatus(String(latest.id), 'READY');
      }
    }
  } catch (error) {
    console.error(error);
  }
});
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-slate-900">Podsumowanie</h3>
        <button type="button" class="text-xs font-bold text-slate-500" @click="emit('backToDashboard')">
          Wroc do pulpitu
        </button>
      </div>

      <div v-if="summary" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
        <div class="text-xs uppercase tracking-widest text-slate-400">OSZCZĘDNOŚĆ MIESIĘCZNA</div>
          <div class="text-xl font-bold text-emerald-600">{{ formatPLN(summary.oszczednoscNetto) }}</div>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
        <div class="text-xs uppercase tracking-widest text-slate-400">Oszczędność roczna</div>
          <div class="text-xl font-bold text-slate-900">{{ formatPLN(summary.oszczednoscRoczna) }}</div>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
          <div class="text-xs uppercase tracking-widest text-slate-400">OPŁATA SERWISOWA</div>
          <div class="text-xl font-bold text-slate-900">{{ formatPLN(summary.prowizja) }}</div>
        </div>
      </div>
      <div v-else class="text-sm text-slate-400">Brak danych do podsumowania.</div>

      <div v-if="summary" class="mt-12 pt-10 border-t border-slate-100">
        <h3 class="text-xl font-bold text-slate-900 mb-12">Narastające oszczędności w czasie</h3>
        
        <div class="relative h-80 mt-16 mb-16 ml-16 mr-8">
          <!-- Y Axis Grid and Labels -->
          <div class="absolute inset-x-0 inset-y-0 flex flex-col justify-between pointer-events-none">
            <div v-for="tick in yAxisTicks" :key="tick" class="relative w-full border-b border-slate-100 flex items-center h-0">
              <span class="absolute -left-16 text-[11px] font-bold text-slate-400 w-14 text-right pr-2">
                {{ tick >= 1000 ? (tick/1000).toFixed(0) + 'k' : tick }}
              </span>
            </div>
          </div>

          <!-- Vertical Axis Line -->
          <div class="absolute left-0 bottom-0 top-0 w-1 bg-slate-200 rounded-full">
            <div class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-white px-2">PLN</div>
          </div>

          <!-- Horizontal Axis Line -->
          <div class="absolute left-0 bottom-0 right-0 h-1 bg-slate-200 rounded-full">
            <div class="absolute -right-4 top-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-white px-2">Miesiace</div>
          </div>

          <!-- Chart Bars -->
          <div class="absolute inset-0 flex items-end justify-around px-8 gap-4 overflow-visible">
            <div 
              v-for="item in chartData" 
              :key="item.label"
              class="relative group flex-1 flex flex-col items-center justify-end h-full"
            >
              <!-- Bar -->
              <div 
                class="w-full max-w-[24px] bg-gradient-to-t from-emerald-600 to-emerald-400 rounded-t-lg transition-all duration-300 group-hover:scale-x-110 group-hover:brightness-110 cursor-pointer shadow-lg shadow-emerald-500/10"
                :style="{ height: `${(item.value / maxChartValue) * 100}%` }"
              >
                <!-- Tooltip Overlay -->
                <div class="absolute bottom-full mb-3 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] py-1.5 px-3 rounded-lg opacity-0 group-hover:opacity-100 transition-all transform translate-y-2 group-hover:translate-y-0 whitespace-nowrap z-30 pointer-events-none shadow-2xl border border-white/10">
                  <div class="font-bold text-emerald-400 mb-0.5">Miesiąc {{ item.label }}</div>
                  <div class="text-[12px]">{{ formatPLN(item.value) }}</div>
                  <!-- Little Arrow -->
                  <div class="absolute top-full left-1/2 -translate-x-1/2 border-8 border-transparent border-t-slate-900"></div>
                </div>
              </div>

              <!-- Month Label -->
              <div class="absolute top-full mt-4 text-[12px] font-extrabold text-slate-500 group-hover:text-emerald-600 transition-colors">
                {{ item.label }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="button" class="px-6 py-3 rounded-xl bg-slate-900 text-white text-sm font-bold" @click="handleSave">
        Zapisz lokalnie
      </button>
      <button type="button" class="px-6 py-3 rounded-xl border border-slate-200 text-sm font-bold" @click="handleExcel">
        Eksportuj Excel
      </button>
      <button type="button" class="px-6 py-3 rounded-xl border border-slate-200 text-sm font-bold" @click="handleDetailedExcel">
        Eksportuj Excel szczegółowy
      </button>
      <button type="button" class="px-6 py-3 rounded-xl bg-indigo-600 text-white text-sm font-bold flex items-center gap-2 hover:bg-indigo-700 disabled:opacity-50" :disabled="isSaving" @click="openOfferModal">
        <AppIcon name="document-text" class="w-4 h-4" />
        {{ isSaving ? 'Przygotowywanie oferty...' : offerButtonLabel }}
      </button>
      <button v-if="isOfferLocked" type="button" class="px-6 py-3 rounded-xl bg-emerald-600 text-white text-sm font-bold flex items-center gap-2 hover:bg-emerald-700" @click="openOfferEmail">
        <AppIcon name="envelope" class="w-4 h-4" />
        Wyślij ofertę
      </button>
      <p v-if="isOfferLocked" class="text-xs text-slate-500 self-center">Oferta została już wygenerowana — możesz otworzyć podgląd.</p>
    </div>

    <div v-if="showOfferModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click="showOfferModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h3 class="font-bold text-slate-900">{{ offerAction === 'preview' ? 'Podgląd oferty' : 'Generowanie oferty' }}</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="showOfferModal = false">
            <AppIcon name="xmark" class="w-5 h-5" />
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Układ dokumentu</label>
            <select v-model="documentLayout" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
              <option value="horizontal">Poziomy</option>
              <option value="vertical">Pionowy</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-200">
          <button type="button" class="px-4 py-2 text-xs font-bold border border-slate-200 rounded-lg" @click="showOfferModal = false">
            Anuluj
          </button>
          <button type="button" class="px-4 py-2 text-xs font-bold bg-slate-900 text-white rounded-lg" :disabled="isSaving" @click="confirmGenerateOffer">
            {{ offerAction === 'preview' ? 'Pokaż' : 'Generuj' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
