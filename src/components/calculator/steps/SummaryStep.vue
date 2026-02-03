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
        <div class="text-xs uppercase tracking-widest text-slate-400">Oszczędność netto</div>
          <div class="text-xl font-bold text-emerald-600">{{ formatPLN(summary.oszczednoscNetto) }}</div>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
        <div class="text-xs uppercase tracking-widest text-slate-400">Oszczędność roczna</div>
          <div class="text-xl font-bold text-slate-900">{{ formatPLN(summary.oszczednoscRoczna) }}</div>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
          <div class="text-xs uppercase tracking-widest text-slate-400">Prowizja</div>
          <div class="text-xl font-bold text-slate-900">{{ formatPLN(summary.prowizja) }}</div>
        </div>
      </div>
      <div v-else class="text-sm text-slate-400">Brak danych do podsumowania.</div>
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
