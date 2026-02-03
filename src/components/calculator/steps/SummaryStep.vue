<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';
import { ZapisanaKalkulacja } from '../models/history';

const emit = defineEmits<{ (event: 'backToDashboard'): void }>();
const store = useCalculatorStore();
const isSaving = ref(false);

const summary = computed(() => store.wyniki?.podsumowanie || null);

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

const handleSave = () => {
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

const handleGenerateOffer = async () => {
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  try {
    isSaving.value = true;
    await store.updateMeetingOfferStatus('preparing');
    await store.saveCalculationToApi();
    store.saveToHistory();
    store.generateOfferPdf(snapshot);
    await store.updateMeetingOfferStatus('generated');
    // await store.updateMeetingOfferStatus('sent'); // Don't mark as sent yet
    await store.updateClientStatus('OFFER_GENERATED');
  } catch (error) {
    console.error(error);
  } finally {
    isSaving.value = false;
  }
};
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
      <button type="button" class="px-6 py-3 rounded-xl bg-indigo-600 text-white text-sm font-bold flex items-center gap-2 hover:bg-indigo-700 disabled:opacity-50" :disabled="isSaving" @click="handleGenerateOffer">
        <AppIcon name="document-text" class="w-4 h-4" />
        {{ isSaving ? 'Przygotowywanie oferty...' : 'Generuj ofertę' }}
      </button>
    </div>
  </div>
</template>
