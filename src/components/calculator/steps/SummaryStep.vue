<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { useMailboxStore } from '@/stores/mailbox';
import { formatPLN } from '../utils/formatters';
import { ZapisanaKalkulacja } from '../models/history';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

import { useToastStore } from '@/stores/toast';
import { generateOfferEmailBody } from '@/utils/offerEmailGenerator';
import { usePdfGenerator } from '@/composables/usePdfGenerator';

const emit = defineEmits<{ (event: 'backToDashboard'): void }>();
const store = useCalculatorStore();
const mailboxStore = useMailboxStore();
const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const isSaving = ref(false);
const showOfferModal = ref(false);
const documentLayout = ref<'horizontal' | 'vertical'>('vertical');
const offerStatus = ref<'preparing' | 'generated' | 'sent' | null>(null);
const latestCalculationId = ref<string | null>(null);
const offerAction = ref<'generate' | 'preview'>('generate');

const summary = computed(() => store.wyniki?.podsumowanie || null);
const isOfferLocked = computed(() => offerStatus.value === 'generated' || offerStatus.value === 'sent');

// ── Table: employee filter & PLUS model ────────────────────────────────────
const showEmployeePicker = ref(false);
const selectedEmpId = ref<number | null>(null);
const isPlus = computed(() => store.comparisonState.activeCard === 'PRIME');

const tableScope = computed(() => {
  if (!store.wyniki?.szczegoly) return [];
  if (selectedEmpId.value === null) return store.wyniki.szczegoly;
  return store.wyniki.szczegoly.filter(w => w.pracownik.id === selectedEmpId.value);
});

const selectedLabel = computed(() => {
  if (selectedEmpId.value === null) return `Cała Firma (${store.pracownicy.length} os.)`;
  const p = store.pracownicy.find(p => p.id === selectedEmpId.value);
  return p ? `${p.imie} ${p.nazwisko}` : 'Cała Firma';
});

const tableModelLabel = computed(() =>
  isPlus.value ? `Legalizacja Gotówki ${store.prowizjaProc}%` : `Eliton Prime™ ${store.prowizjaProc}%`
);

const tBruttoStd      = computed(() => tableScope.value.reduce((a, w) => a + w.standard.brutto, 0));
const tBruttoEP       = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.zasadnicza.brutto + w.podzial.swiadczenie.brutto, 0));
const tZusPracStd     = computed(() => tableScope.value.reduce((a, w) => a + w.standard.zusPracodawca.suma, 0));
const tZusPracEP      = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.zasadnicza.zusPracodawca.suma, 0));
const tZusPracWnikStd = computed(() => tableScope.value.reduce((a, w) => a + w.standard.zusPracownik.suma, 0));
const tZusPracWnikEP  = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.zasadnicza.zusPracownik.suma, 0));
const tNettoStd       = computed(() => tableScope.value.reduce((a, w) => a + w.standard.netto, 0));
const tNettoEP        = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.zasadnicza.netto + w.podzial.swiadczenie.netto, 0));
const tProwizja       = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.swiadczenie.netto * (store.prowizjaProc / 100), 0));
// Prowizja dzieli się na: część bazową (opłata serwisowa Stratton) + bonus dla
// księgowości (reszta). Bonus = aktywna stawka − stawka bazowa:
//   Eliton Prime: baza 20% → bonus 2% gdy zaznaczony (22%), 0% gdy odznaczony (20%).
//   Legalizacja Gotówki: baza 15%, brak bonusu.
// Dzięki temu (tOplataSerwisowa + tAdminBonus === tProwizja) i bonus znika gdy odznaczony.
const baseRate        = computed(() => isPlus.value ? store.comparisonState.customPrimeRate : store.comparisonState.customStandardRateNoBonus);
const bonusRate       = computed(() => Math.max(0, store.prowizjaProc - baseRate.value));
const tOplataSerwisowa= computed(() => tableScope.value.reduce((a, w) => a + w.podzial.swiadczenie.netto * (baseRate.value / 100), 0));
const tPodwyzka       = computed(() => 0);
const tAdminBonus     = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.swiadczenie.netto * (bonusRate.value / 100), 0));
const tStandardTotal  = computed(() => tableScope.value.reduce((a, w) => a + w.standard.kosztPracodawcy, 0));
const tPodzialTotal   = computed(() => tableScope.value.reduce((a, w) => a + w.podzial.kosztPracodawcy, 0) + tProwizja.value);
const tOszczednosc    = computed(() => tStandardTotal.value - tPodzialTotal.value);

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

const offerButtonLabel = computed(() => (isOfferLocked.value ? 'Wygeneruj ponownie' : 'Generuj ofertę'))

const pdfGen = usePdfGenerator()

const buildLongOfferData = () => {
  if (!store.wyniki) return null
  const s = store.wyniki.podsumowanie
  const sumaZusStd = store.wyniki.szczegoly.reduce((acc, w) => acc + w.standard.zusPracodawca.suma, 0)
  return {
    firma: {
      nazwa: store.firma.nazwa,
      nip: store.firma.nip,
      miasto: store.firma.miasto || '',
      kontaktEmail: store.firma.kontakty?.[0]?.email || store.firma.email || '',
    },
    podsumowanie: {
      liczbaObjetchPracownikow: store.pracownicy.length,
      kosztObecny: s.sumaKosztStandard,
      kosztNowy: s.sumaKosztPodzial + s.prowizja,
      oszczednoscMiesieczna: s.oszczednoscNetto,
      oszczednoscRoczna: s.oszczednoscRoczna,
      prowizja: s.prowizja,
      prowizjaRoczna: s.prowizja * 12,
      prowizjaProc: store.prowizjaProc,
      oszczednoscBrutto: s.oszczednoscBrutto,
      zyskNetto: s.oszczednoscRoczna - s.prowizja * 12,
      roi: s.prowizja > 0 ? Math.round((s.oszczednoscRoczna / (s.prowizja * 12)) * 100) : 0,
      zwrotWMiesiacach: s.oszczednoscNetto > 0 ? Math.ceil(s.prowizja / s.oszczednoscNetto) : 0,
      sredniaOszczednoscNaEtat: s.sredniaOszczednoscNaEtat,
      sumaZusPracodawcyStandard: sumaZusStd,
    },
    pracownicy: store.wyniki.szczegoly.map(w => ({
      imie: w.pracownik.imie,
      nazwisko: w.pracownik.nazwisko,
      typUmowy: w.pracownik.typUmowy,
      kosztStandard: w.standard.kosztPracodawcy,
      bruttoStandard: w.standard.brutto,
      nettoStandard: w.standard.netto,
      zusStandard: w.standard.zusPracodawca.suma,
      kosztEliton: w.podzial.kosztPracodawcy,
      nettoElitonCalkowite: w.podzial.nettoCalkowite,
      nettoZasadnicza: w.podzial.zasadnicza.netto,
      nettoSwiadczenie: w.podzial.swiadczenie.netto,
      zusEliton: w.podzial.zasadnicza.zusPracodawca.suma,
      oszczednosc: w.oszczednosc,
      podwyzka: w.podzial.nettoCalkowite - w.standard.netto,
    })),
    handlowiec: { imie: 'Agnieszka', nazwisko: 'Cięciara', email: 'a.cieciara@stratton-prime.pl' },
    dataWystawienia: new Date().toLocaleDateString('pl-PL'),
    dataWaznosci: new Date(Date.now() + 14 * 86400000).toLocaleDateString('pl-PL'),
  }
}

const handleLongOffer = async () => {
  const data = buildLongOfferData()
  if (!data) return
  await pdfGen.generatePdf('long', data as Record<string, unknown>)
}

const handleProductCard = async () => {
  await pdfGen.generatePdf('product-card', {
    firma: { nazwa: store.firma.nazwa || '', nip: store.firma.nip || '' },
    handlowiec: { imie: 'Agnieszka', nazwisko: 'Cięciara', email: 'a.cieciara@stratton-prime.pl' },
    dataWystawienia: new Date().toLocaleDateString('pl-PL'),
  })
};

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

const handleTestOffer = async () => {
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  await store.generateTestOfferPdf(snapshot, { documentLayout: 'vertical' });
};

const handleGenerateOffer = async (layout: 'horizontal' | 'vertical') => {
  if (isOfferLocked.value) {
    await handlePreviewOffer(layout);
    return;
  }
  const snapshot = buildSnapshot();
  if (!snapshot) return;
  let calc: any = null;
  try {
    isSaving.value = true;
    // Run "preparing" status update + save calculation in parallel (independent)
    [calc] = await Promise.all([
      store.saveCalculationToApi('DETAILED'),
      store.updateMeetingOfferStatus('preparing'),
    ]);
    store.saveToHistory();
    await store.generateOfferPdf(snapshot, { documentLayout: layout });

    // ← Unlock "Wyślij ofertę" button immediately after PDF is ready
    offerStatus.value = 'generated';
    if (calc?.id) latestCalculationId.value = String(calc.id);
  } catch (error) {
    console.error(error);
  } finally {
    isSaving.value = false;
  }

  // Fire remaining status updates in background — don't block the UI
  void Promise.all([
    store.updateMeetingOfferStatus('generated'),
    store.updateClientStatus('IN_TALKS'),
    ...(latestCalculationId.value ? [store.updateCalculationStatus(latestCalculationId.value, 'READY')] : []),
  ]);
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

const openOfferEmail = async (force: boolean | Event = false) => {
  const shouldForce = typeof force === 'boolean' ? force : false;
  if (isSaving.value && !shouldForce) return;
  isSaving.value = true;
  try {
    const attachmentsData = await store.buildOfferEmailAttachments();
    if (!attachmentsData) {
      toast.error('Nie znaleziono danych oferty. Proszę wygenerować ofertę ponownie.');
      return;
    }

    const { offerHtml, offerFileName, excelBase64, excelFileName } = attachmentsData;

    const attachments: Array<{
      filename: string;
      content?: string;
      content_type?: string;
      encoding?: string;
      html?: string;
      convert_to_pdf?: boolean;
    }> = [
      {
        filename: offerFileName,
        html: offerHtml,
        convert_to_pdf: true
      }
    ];

    if (excelBase64) {
      attachments.push({
        filename: excelFileName,
        content: excelBase64,
        content_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        encoding: 'base64'
      });
    }

    const { subject: generatedSubject, body: generatedBody } = generateOfferEmailBody(store.firma);

    mailboxStore.composeState = {
      open: true,
      to: store.firma.email,
      subject: generatedSubject,
      body: generatedBody,
      attachments
    };
    
    await router.push('/app/mailbox');
  } catch (error) {
    console.error(error);
    toast.error('Wystąpił błąd podczas przygotowywania wiadomości.');
  } finally {
    isSaving.value = false;
  }
};

onMounted(async () => {
  if (!auth.enabled || !store.context.clientId) return;
  try {
    // Source the latest calculation directly from /v1/calculations filtered
    // by client_id. The meetings table no longer carries this state.
    const { data } = await api.get('/v1/calculations', {
      params: { client_id: store.context.clientId, per_page: 50 },
    });
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
    if (list.length > 0) {
      const latest = list.sort((a: any, b: any) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())[0];
      if (latest?.id) latestCalculationId.value = String(latest.id);
      const status = String(latest?.status || '').toUpperCase();
      offerStatus.value = status === 'READY' ? 'generated' : status === 'SENT' ? 'sent' : 'preparing';
    }
  } catch (error) {
    console.error(error);
  }
});
</script>

<template>
  <div class="pb-28">

    <!-- ── NO DATA STATE ──────────────────────────────────────────── -->
    <div v-if="!summary" class="flex flex-col items-center justify-center gap-3 py-20 text-center">
      <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-1">
        <AppIcon name="chart-bar" class="w-7 h-7 text-slate-300" />
      </div>
      <p class="text-sm font-semibold text-slate-500">Brak danych do podsumowania</p>
      <p class="text-xs text-slate-400">Dodaj pracowników i przeprowadź kalkulację.</p>
    </div>

    <template v-else>

      <!-- ══════════════════════════════════════════════════════════ -->
      <!-- PAGE HEADER — firma + model badge + action command bar    -->
      <!-- ══════════════════════════════════════════════════════════ -->
      <div class="mb-2 min-h-[68px]">
        <!-- breadcrumb / context row -->
        <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">
          <AppIcon name="building" class="w-3 h-3" />
          <span>{{ store.firma.nazwa || 'Firma' }}</span>
          <span class="text-slate-300">/</span>
          <span>Kalkulacja kosztów zatrudnienia</span>
          <span class="text-slate-300">/</span>
          <span class="text-slate-600">Podsumowanie</span>
        </div>
        <!-- title + command bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div class="flex items-center gap-3">
            <h1 class="text-xl font-black text-slate-900 tracking-tight">Raport Kalkulacji</h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 border border-amber-200 text-[10px] font-black uppercase tracking-widest text-amber-700">
              <span class="text-amber-400">⭐</span>{{ tableModelLabel }}
            </span>
            <span v-if="isOfferLocked" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 border border-emerald-200 text-[10px] font-black uppercase tracking-widest text-emerald-700">
              <AppIcon name="check-circle" class="w-3 h-3" />Oferta gotowa
            </span>
          </div>
          <!-- Command bar — D365 style: secondary actions left, primary right -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <button type="button"
              class="h-8 px-3 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95 flex items-center gap-1.5"
              @click="handleSave">
              <AppIcon name="cloud-arrow-up" class="w-3.5 h-3.5 text-slate-400" />Zapisz
            </button>
            <button type="button"
              class="h-8 px-3 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95 flex items-center gap-1.5"
              @click="handleExcel">
              <AppIcon name="table-cells" class="w-3.5 h-3.5 text-slate-400" />Excel
            </button>
            <button type="button"
              class="h-8 px-3 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95 flex items-center gap-1.5"
              @click="handleDetailedExcel">
              <AppIcon name="table-cells" class="w-3.5 h-3.5 text-slate-400" />Excel szczegółowy
            </button>
            <button type="button"
              class="h-8 px-3 rounded-md border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-all active:scale-95"
              @click="handleTestOffer">Test PDF
            </button>
            <button type="button"
              class="h-8 px-3 rounded-md border border-amber-200 bg-amber-50 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition-all active:scale-95 flex items-center gap-1.5 disabled:opacity-50"
              :disabled="pdfGen.isGenerating.value || !store.wyniki"
              @click="handleLongOffer">
              <AppIcon v-if="pdfGen.isGenerating.value" name="arrow-path" class="w-3 h-3 animate-spin" />
              <AppIcon v-else name="document-text" class="w-3 h-3" />
              {{ pdfGen.isGenerating.value ? '...' : 'Pełna oferta PDF' }}
            </button>
            <button type="button"
              class="h-8 px-3 rounded-md border border-indigo-200 bg-indigo-50 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-all active:scale-95 flex items-center gap-1.5 disabled:opacity-50"
              :disabled="pdfGen.isGenerating.value"
              @click="handleProductCard">
              <AppIcon name="rectangle-stack" class="w-3 h-3" />
              Karta produktu
            </button>
            <div class="w-px h-5 bg-slate-200 mx-0.5 hidden sm:block"></div>
            <button type="button"
              class="h-8 px-4 rounded-md bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white text-xs font-black flex items-center gap-1.5 hover:brightness-110 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.4)] border border-white/20 active:scale-95 disabled:opacity-50 uppercase tracking-widest"
              :disabled="isSaving" @click="openOfferModal">
              <AppIcon name="document-text" class="w-3.5 h-3.5" />
              {{ isSaving ? 'Przygotowywanie...' : offerButtonLabel }}
            </button>
            <button v-if="isOfferLocked" type="button"
              class="h-8 px-4 rounded-md bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white text-xs font-black flex items-center gap-1.5 hover:brightness-110 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.4)] border border-white/20 active:scale-95 uppercase tracking-widest"
              :disabled="isSaving" @click="openOfferEmail">
              <AppIcon v-if="!isSaving" name="envelope" class="w-3.5 h-3.5" />
              <AppIcon v-else name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
              {{ isSaving ? 'Wysyłanie...' : 'Wyślij ofertę' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════ -->
      <!-- ROW 1: KPI SCORECARDS (4 tiles)                           -->
      <!-- ══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">

        <!-- Koszt Standard -->
        <div class="group bg-white border border-slate-200 rounded-xl p-4 flex flex-col gap-2 relative overflow-hidden transition-all duration-300 ease-out hover:-translate-y-2 hover:border-slate-300 hover:shadow-[0_20px_28px_-6px_rgba(100,116,139,0.3),0_8px_16px_-4px_rgba(100,116,139,0.2)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-slate-400 before:rounded-t-xl">
          <div class="flex items-center justify-between">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-600 transition-colors duration-300">Koszt obecny</span>
            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 group-hover:bg-slate-200 group-hover:shadow-md">
              <AppIcon name="clipboard-document-list" class="w-3.5 h-3.5 text-slate-500" />
            </div>
          </div>
          <div class="text-xl font-black text-slate-900 tabular-nums leading-none">{{ formatPLN(summary.sumaKosztStandard) }}</div>
          <div class="text-[10px] text-slate-400 font-medium">Miesięcznie / Standard</div>
        </div>

        <!-- Nowy koszt Eliton Prime -->
        <div class="group bg-white border border-slate-200 rounded-xl p-4 flex flex-col gap-2 relative overflow-hidden transition-all duration-300 ease-out hover:-translate-y-2 hover:border-stratton-gold/50 hover:shadow-[0_20px_28px_-6px_rgba(197,160,89,0.35),0_8px_16px_-4px_rgba(197,160,89,0.25)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-stratton-gold before:rounded-t-xl">
          <div class="absolute top-0 left-0 w-1 h-full rounded-l-xl bg-stratton-gold"></div>
          <div class="flex items-center justify-between pl-2">
            <span class="text-[9px] font-black uppercase tracking-widest text-amber-600 group-hover:text-amber-500 transition-colors duration-300">Nowy koszt</span>
            <div class="w-7 h-7 rounded-lg bg-amber-50 border border-amber-100 flex items-center justify-center transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 group-hover:bg-stratton-gold group-hover:border-stratton-gold group-hover:shadow-[0_6px_12px_-2px_rgba(197,160,89,0.45)]">
              <AppIcon name="chart-pie" class="w-3.5 h-3.5 text-stratton-gold group-hover:text-white transition-colors duration-300" />
            </div>
          </div>
          <div class="text-xl font-black text-slate-900 tabular-nums leading-none pl-2">{{ formatPLN(summary.sumaKosztPodzial + summary.prowizja) }}</div>
          <div class="text-[10px] text-slate-400 font-medium pl-2">Miesięcznie / Eliton Prime™</div>
        </div>

        <!-- Oszczędność miesięczna -->
        <div class="group bg-emerald-600 rounded-xl p-4 flex flex-col gap-2 relative overflow-hidden transition-all duration-300 ease-out hover:-translate-y-2 hover:bg-emerald-500 hover:shadow-[0_20px_28px_-6px_rgba(16,185,129,0.55),0_8px_16px_-4px_rgba(16,185,129,0.40)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-white/60 before:rounded-t-xl">
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(255,255,255,0.15)_0%,transparent_65%)] transition-opacity duration-300 group-hover:opacity-0"></div>
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(255,255,255,0.18)_0%,transparent_70%)] opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
          <div class="relative flex items-center justify-between">
            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-100">Oszczędność</span>
            <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 group-hover:bg-white/35 group-hover:shadow-[0_4px_12px_-2px_rgba(0,0,0,0.25)]">
              <AppIcon name="arrow-trending-up" class="w-3.5 h-3.5 text-white" />
            </div>
          </div>
          <div class="relative text-xl font-black text-white tabular-nums leading-none">{{ formatPLN(summary.oszczednoscNetto) }}</div>
          <div class="relative text-[10px] text-emerald-200 font-medium">Miesięcznie</div>
        </div>

        <!-- Oszczędność roczna -->
        <div class="group bg-emerald-700 rounded-xl p-4 flex flex-col gap-2 relative overflow-hidden transition-all duration-300 ease-out hover:-translate-y-2 hover:bg-emerald-600 hover:shadow-[0_20px_28px_-6px_rgba(4,120,87,0.55),0_8px_16px_-4px_rgba(4,120,87,0.40)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-white/50 before:rounded-t-xl">
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(255,255,255,0.10)_0%,transparent_60%)] transition-opacity duration-300 group-hover:opacity-0"></div>
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(255,255,255,0.14)_0%,transparent_70%)] opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
          <div class="relative flex items-center justify-between">
            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-200">Rocznie</span>
            <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 group-hover:bg-white/35 group-hover:shadow-[0_4px_12px_-2px_rgba(0,0,0,0.25)]">
              <AppIcon name="banknotes" class="w-3.5 h-3.5 text-white" />
            </div>
          </div>
          <div class="relative text-xl font-black text-white tabular-nums leading-none">{{ formatPLN(summary.oszczednoscRoczna) }}</div>
          <div class="relative flex items-center gap-1.5">
            <span v-if="summary.sumaKosztStandard > 0" class="text-[10px] font-black text-white/90 bg-white/15 px-1.5 py-0.5 rounded-md group-hover:bg-white/25 transition-colors duration-300">
              {{ ((summary.oszczednoscNetto / summary.sumaKosztStandard) * 100).toFixed(1) }}% taniej
            </span>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════ -->
      <!-- ROW 2: STRUKTURA KOSZTU (donut left) + CHART (right)     -->
      <!-- ══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-4">

        <!-- ── Struktura kosztu (donut + legend) ── 2/5 cols -->
        <div class="group lg:col-span-2 bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-indigo-200 hover:shadow-[0_20px_28px_-6px_rgba(99,102,241,0.18),0_8px_16px_-4px_rgba(99,102,241,0.12)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-linear-to-r before:from-indigo-400 before:via-stratton-gold before:to-emerald-400 before:z-10 before:rounded-t-xl">
          <!-- section header -->
          <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest group-hover:text-indigo-700 transition-colors duration-300">Struktura kosztu</h3>
              <p class="text-[10px] text-slate-400 mt-0.5">Alokacja środków · Model Eliton Prime™</p>
            </div>
          </div>
          <div class="p-5 flex flex-col gap-5 items-center">
            <!-- donut -->
            <div class="relative shrink-0 flex items-center justify-center w-40 h-40">
              <svg viewBox="0 0 120 120" class="w-40 h-40 -rotate-90">
                <template v-if="summary.sumaKosztStandard > 0">
                  <circle cx="60" cy="60" r="48" fill="none" stroke="#f1f5f9" stroke-width="14" />
                  <circle cx="60" cy="60" r="48" fill="none" stroke="#6366f1" stroke-width="14" stroke-dasharray="301.59"
                    :stroke-dashoffset="301.59 * (1 - (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.netto, 0) ?? 0) / summary.sumaKosztStandard)"
                    stroke-linecap="butt" />
                  <circle cx="60" cy="60" r="48" fill="none" stroke="#f59e0b" stroke-width="14" stroke-dasharray="301.59"
                    :stroke-dashoffset="301.59 * (1 - ((store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.netto, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.zusPracodawca.suma + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.pit, 0) ?? 0)) / summary.sumaKosztStandard)"
                    stroke-linecap="butt" />
                  <circle cx="60" cy="60" r="48" fill="none" stroke="#38bdf8" stroke-width="14" stroke-dasharray="301.59"
                    :stroke-dashoffset="301.59 * (1 - ((store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.netto, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.zusPracodawca.suma + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.pit, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.swiadczenie.netto, 0) ?? 0)) / summary.sumaKosztStandard)"
                    stroke-linecap="butt" />
                  <circle cx="60" cy="60" r="48" fill="none" stroke="#D4AF37" stroke-width="14" stroke-dasharray="301.59"
                    :stroke-dashoffset="301.59 * (1 - ((store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.netto, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.zusPracodawca.suma + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.pit, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.swiadczenie.netto, 0) ?? 0) + summary.prowizja) / summary.sumaKosztStandard)"
                    stroke-linecap="butt" />
                  <circle cx="60" cy="60" r="48" fill="none" stroke="#10b981" stroke-width="14" stroke-dasharray="301.59"
                    :stroke-dashoffset="301.59 * (1 - ((store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.netto, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.zusPracodawca.suma + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.pit, 0) ?? 0) + (store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.swiadczenie.netto, 0) ?? 0) + summary.prowizja + summary.oszczednoscNetto) / summary.sumaKosztStandard)"
                    stroke-linecap="butt" />
                </template>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <div class="text-[8px] font-black uppercase tracking-widest text-slate-400">OSZCZĘDNOŚĆ</div>
                <div class="text-sm font-black text-emerald-600 tabular-nums">{{ formatPLN(summary.oszczednoscNetto) }}</div>
                <div class="text-[8px] text-slate-400">/ mies.</div>
              </div>
            </div>
            <!-- legend -->
            <div class="flex-1 w-full space-y-0">
              <div v-for="row in [
                { color: 'bg-indigo-500', label: 'Wynagrodzenie NETTO', value: store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.netto, 0) ?? 0 },
                { color: 'bg-amber-400',  label: 'ZUS + PIT', value: store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.zasadnicza.zusPracodawca.suma + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.pit, 0) ?? 0 },
                { color: 'bg-sky-400',    label: 'Świadczenia pracownika', value: store.wyniki?.szczegoly?.reduce((a, w) => a + w.podzial.swiadczenie.netto, 0) ?? 0 },
                { color: 'bg-stratton-gold', label: 'Opłata serwisowa EBS', value: summary.prowizja },
                { color: 'bg-emerald-500', label: 'Oszczędność', value: summary.oszczednoscNetto },
              ]" :key="row.label"
                class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-50 last:border-0">
                <div class="flex items-center gap-2 min-w-0">
                  <div :class="[row.color, 'w-2 h-2 rounded-sm shrink-0']"></div>
                  <span class="text-[11px] text-slate-600 truncate font-medium">{{ row.label }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-[11px] font-black text-slate-800 tabular-nums">{{ formatPLN(row.value) }}</span>
                  <span v-if="summary.sumaKosztStandard > 0" class="text-[10px] text-slate-400 w-10 text-right">{{ ((row.value / summary.sumaKosztStandard) * 100).toFixed(0) }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Narastające oszczędności (bar chart) ── 3/5 cols -->
        <div class="group lg:col-span-3 bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-emerald-200 hover:shadow-[0_20px_28px_-6px_rgba(16,185,129,0.22),0_8px_16px_-4px_rgba(16,185,129,0.15)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-linear-to-r before:from-emerald-400 before:to-emerald-600 before:z-10 before:rounded-t-xl">
          <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest group-hover:text-emerald-700 transition-colors duration-300">Narastające oszczędności</h3>
              <p class="text-[10px] text-slate-400 mt-0.5">Skumulowane przez 12 miesięcy</p>
            </div>
            <div class="text-xs font-black text-emerald-600 tabular-nums">{{ formatPLN(summary.oszczednoscRoczna) }} / rok</div>
          </div>
          <div class="p-5">
            <div class="relative h-52 mt-8 mb-8 ml-14 mr-2">
              <div class="absolute inset-x-0 inset-y-0 flex flex-col justify-between pointer-events-none">
                <div v-for="tick in yAxisTicks" :key="tick" class="relative w-full border-b border-slate-100 flex items-center h-0">
                  <span class="absolute -left-14 text-[10px] font-semibold text-slate-400 w-12 text-right pr-2">
                    {{ tick >= 1000 ? (tick/1000).toFixed(0) + 'k' : tick }}
                  </span>
                </div>
              </div>
              <div class="absolute left-0 bottom-0 top-0 w-px bg-slate-200"></div>
              <div class="absolute left-0 bottom-0 right-0 h-px bg-slate-200"></div>
              <div class="absolute inset-0 flex items-end justify-around px-2 gap-1 overflow-visible">
                <div v-for="item in chartData" :key="item.label"
                  class="relative group/bar flex-1 flex flex-col items-center justify-end h-full">
                  <div
                    class="w-full max-w-8 bg-linear-to-t from-emerald-600 to-emerald-400 rounded-t transition-all duration-300 group-hover/bar:from-emerald-500 group-hover/bar:to-emerald-300 cursor-pointer"
                    :style="{ height: `${(item.value / maxChartValue) * 100}%` }">
                    <div class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[9px] py-1 px-2 rounded-lg opacity-0 group-hover/bar:opacity-100 transition-all whitespace-nowrap z-20 pointer-events-none shadow-xl">
                      <span class="font-bold text-emerald-300">M{{ item.label }}</span> · {{ formatPLN(item.value) }}
                      <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                    </div>
                  </div>
                  <div class="absolute top-full mt-1.5 text-[9px] font-semibold text-slate-400 group-hover/bar:text-emerald-600 transition-colors">{{ item.label }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════ -->
      <!-- ROW 3: SYMULACJA SZCZEGÓŁOWA — data grid                  -->
      <!-- ══════════════════════════════════════════════════════════ -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-4">
        <!-- section header + controls -->
        <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Symulacja Szczegółowa</h3>
            <p class="text-[10px] text-slate-400 mt-0.5">Porównanie kosztów zatrudnienia · Standard vs {{ tableModelLabel }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <!-- scope filter -->
            <div class="relative">
              <button type="button"
                class="h-7 px-3 flex items-center gap-1.5 bg-white border border-slate-200 rounded-md text-[11px] font-semibold text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all"
                @click="showEmployeePicker = !showEmployeePicker">
                <AppIcon name="users" class="w-3 h-3 text-slate-400" />
                {{ selectedLabel }}
                <AppIcon name="chevron-down" :class="`w-3 h-3 text-slate-400 transition-transform${showEmployeePicker ? ' rotate-180' : ''}`" />
              </button>
              <div v-if="showEmployeePicker" class="absolute right-0 top-full mt-1 z-30 bg-white border border-slate-200 rounded-xl shadow-2xl py-1 min-w-52">
                <button type="button"
                  class="w-full text-left px-3.5 py-2 text-[11px] font-semibold hover:bg-slate-50 transition-colors flex items-center gap-2"
                  :class="selectedEmpId === null ? 'text-stratton-gold' : 'text-slate-700'"
                  @click="selectedEmpId = null; showEmployeePicker = false">
                  <AppIcon name="building" class="w-3 h-3" />Cała Firma ({{ store.pracownicy.length }} os.)
                </button>
                <div class="h-px bg-slate-100 mx-2 my-0.5"></div>
                <button v-for="p in store.pracownicy" :key="p.id" type="button"
                  class="w-full text-left px-3.5 py-2 text-[11px] font-semibold hover:bg-slate-50 transition-colors flex items-center gap-2"
                  :class="selectedEmpId === p.id ? 'text-stratton-gold' : 'text-slate-700'"
                  @click="selectedEmpId = p.id; showEmployeePicker = false">
                  <AppIcon name="user" class="w-3 h-3" />{{ p.imie }} {{ p.nazwisko }}
                </button>
              </div>
            </div>
            <!-- model badge -->
            <div class="h-7 px-3 flex items-center gap-1.5 bg-amber-50 border border-amber-200 rounded-md">
              <span class="text-amber-400 text-[10px]">⭐</span>
              <span class="text-[10px] font-black uppercase tracking-widest text-amber-700">{{ tableModelLabel }}</span>
            </div>
          </div>
        </div>

        <!-- data grid -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100">
                <th class="px-5 py-2.5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400 bg-white">Kategoria kosztowa</th>
                <th class="px-5 py-2.5 text-right text-[10px] font-black uppercase tracking-widest text-slate-400 bg-white">Obecnie (Standard)</th>
                <th class="px-5 py-2.5 text-right text-[10px] font-black uppercase tracking-widest text-amber-500 bg-amber-50/60">⭐ {{ tableModelLabel }}</th>
                <th class="px-5 py-2.5 text-right text-[10px] font-black uppercase tracking-widest text-emerald-600 bg-emerald-50/60">Δ Zmiana</th>
              </tr>
            </thead>
            <tbody>
              <!-- row template: alternating bg, left accent bar on EP column -->
              <tr class="border-b border-slate-50 hover:bg-blue-50/30 transition-colors group">
                <td class="px-5 py-3 text-[12px] font-medium text-slate-700">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-slate-200 group-hover:bg-indigo-400 transition-colors"></div>
                    Wynagrodzenie Brutto <span class="text-slate-400 font-normal">(zasadnicze + świadczenia)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right font-semibold text-slate-700 tabular-nums text-[12px]">{{ formatPLN(tBruttoStd) }}</td>
                <td class="px-5 py-3 text-right font-bold text-slate-900 tabular-nums text-[12px] bg-amber-50/40">{{ formatPLN(tBruttoEP) }}</td>
                <td class="px-5 py-3 text-right font-black tabular-nums text-[12px] bg-emerald-50/40" :class="(tBruttoEP - tBruttoStd) <= 0 ? 'text-emerald-600' : 'text-rose-500'">
                  {{ (tBruttoEP - tBruttoStd) > 0 ? '+' : '' }}{{ formatPLN(tBruttoEP - tBruttoStd) }}
                </td>
              </tr>
              <tr class="border-b border-slate-50 hover:bg-blue-50/30 transition-colors group">
                <td class="px-5 py-3 text-[12px] font-medium text-slate-700">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-slate-200 group-hover:bg-amber-400 transition-colors"></div>
                    ZUS Pracodawcy
                  </div>
                </td>
                <td class="px-5 py-3 text-right font-semibold text-slate-700 tabular-nums text-[12px]">{{ formatPLN(tZusPracStd) }}</td>
                <td class="px-5 py-3 text-right font-bold text-slate-900 tabular-nums text-[12px] bg-amber-50/40">{{ formatPLN(tZusPracEP) }}</td>
                <td class="px-5 py-3 text-right font-black tabular-nums text-[12px] bg-emerald-50/40" :class="(tZusPracEP - tZusPracStd) <= 0 ? 'text-emerald-600' : 'text-rose-500'">
                  {{ (tZusPracEP - tZusPracStd) > 0 ? '+' : '' }}{{ formatPLN(tZusPracEP - tZusPracStd) }}
                </td>
              </tr>
              <tr class="border-b border-slate-50 hover:bg-blue-50/30 transition-colors group">
                <td class="px-5 py-3 text-[12px] font-medium text-slate-700">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-slate-200 group-hover:bg-amber-300 transition-colors"></div>
                    ZUS Pracownika
                  </div>
                </td>
                <td class="px-5 py-3 text-right font-semibold text-slate-700 tabular-nums text-[12px]">{{ formatPLN(tZusPracWnikStd) }}</td>
                <td class="px-5 py-3 text-right font-bold text-slate-900 tabular-nums text-[12px] bg-amber-50/40">{{ formatPLN(tZusPracWnikEP) }}</td>
                <td class="px-5 py-3 text-right font-black tabular-nums text-[12px] bg-emerald-50/40" :class="(tZusPracWnikEP - tZusPracWnikStd) <= 0 ? 'text-emerald-600' : 'text-rose-500'">
                  {{ (tZusPracWnikEP - tZusPracWnikStd) > 0 ? '+' : '' }}{{ formatPLN(tZusPracWnikEP - tZusPracWnikStd) }}
                </td>
              </tr>
              <tr class="border-b border-slate-50 hover:bg-blue-50/30 transition-colors group">
                <td class="px-5 py-3 text-[12px] font-medium text-slate-700">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-slate-200 group-hover:bg-indigo-400 transition-colors"></div>
                    Netto Pracownika <span class="text-slate-400 font-normal">(całkowite)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right font-semibold text-slate-700 tabular-nums text-[12px]">{{ formatPLN(tNettoStd) }}</td>
                <td class="px-5 py-3 text-right font-bold text-slate-900 tabular-nums text-[12px] bg-amber-50/40">{{ formatPLN(tNettoEP) }}</td>
                <td class="px-5 py-3 text-right font-black tabular-nums text-[12px] bg-emerald-50/40" :class="(tNettoEP - tNettoStd) >= 0 ? 'text-emerald-600' : 'text-rose-500'">
                  {{ (tNettoEP - tNettoStd) > 0 ? '+' : '' }}{{ formatPLN(tNettoEP - tNettoStd) }}
                </td>
              </tr>
              <!-- separator -->
              <tr class="bg-slate-50/80">
                <td colspan="4" class="px-5 py-1.5">
                  <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Składniki modelu Eliton Prime™</span>
                </td>
              </tr>
              <tr class="border-b border-slate-50 hover:bg-blue-50/30 transition-colors group">
                <td class="px-5 py-3 text-[12px] font-medium text-slate-700">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-stratton-gold shrink-0"></div>
                    Opłata serwisowa EBS <span class="text-slate-400 font-normal">(prowizja {{ baseRate }}%)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right text-slate-300 tabular-nums text-[12px]">—</td>
                <td class="px-5 py-3 text-right font-bold text-stratton-gold tabular-nums text-[12px] bg-amber-50/40">{{ formatPLN(tOplataSerwisowa) }}</td>
                <td class="px-5 py-3 text-right font-black text-amber-500 tabular-nums text-[12px] bg-emerald-50/40">+{{ formatPLN(tOplataSerwisowa) }}</td>
              </tr>
              <!-- 4% podwyżka usunięte z modelu Legalizacja Gotówki (post 2026-05-31) -->

              <tr v-if="bonusRate > 0" class="border-b border-slate-50 hover:bg-blue-50/30 transition-colors group">
                <td class="px-5 py-3 text-[12px] font-medium text-slate-700">
                  <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-violet-400 shrink-0"></div>
                    Bonus dla księgowości <span class="text-slate-400 font-normal">({{ bonusRate }}%)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right text-slate-300 tabular-nums text-[12px]">—</td>
                <td class="px-5 py-3 text-right font-bold text-violet-600 tabular-nums text-[12px] bg-amber-50/40">{{ formatPLN(tAdminBonus) }}</td>
                <td class="px-5 py-3 text-right font-black text-violet-600 tabular-nums text-[12px] bg-emerald-50/40">+{{ formatPLN(tAdminBonus) }}</td>
              </tr>
              <!-- totals footer -->
              <tr class="bg-slate-900">
                <td class="px-5 py-3.5">
                  <span class="text-[10px] font-black uppercase tracking-widest text-slate-300">Całkowity koszt pracodawcy</span>
                </td>
                <td class="px-5 py-3.5 text-right">
                  <div class="text-[10px] font-semibold text-slate-500 mb-0.5">Standard</div>
                  <div class="text-sm font-black text-rose-400 tabular-nums">{{ formatPLN(tStandardTotal) }}</div>
                </td>
                <td class="px-5 py-3.5 text-right bg-amber-900/20">
                  <div class="text-[10px] font-semibold text-slate-500 mb-0.5">{{ tableModelLabel }}</div>
                  <div class="text-sm font-black text-stratton-gold tabular-nums">{{ formatPLN(tPodzialTotal) }}</div>
                </td>
                <td class="px-5 py-3.5 text-right bg-emerald-900/20">
                  <div class="text-[10px] font-semibold text-slate-500 mb-0.5">Oszczędność</div>
                  <div class="text-sm font-black text-emerald-400 tabular-nums">{{ formatPLN(tOszczednosc) }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </template>

    <!-- ── STICKY COMMAND BAR (D365 bottom status bar) ───────────── -->
    <div v-if="summary" class="hidden md:block fixed bottom-0 left-0 right-0 z-40 bg-[#1a1a2e] border-t border-slate-700/60 shadow-2xl">
      <div class="max-w-screen-2xl mx-auto px-5 py-2.5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-slate-500">
          <AppIcon name="building" class="w-3 h-3 text-slate-600" />
          <span>{{ store.firma.nazwa || 'Firma' }}</span>
          <span class="text-slate-700">·</span>
          <span>{{ store.pracownicy.length }} pracowników</span>
          <span class="text-slate-700">·</span>
          <span class="text-amber-600">{{ tableModelLabel }}</span>
        </div>
        <div class="flex items-center gap-5">
          <div class="text-center">
            <div class="text-[8px] font-black uppercase tracking-widest text-slate-600 mb-0.5">Standard</div>
            <div class="text-sm font-black text-rose-400 tabular-nums">{{ formatPLN(summary.sumaKosztStandard) }}</div>
          </div>
          <div class="w-px h-6 bg-slate-700"></div>
          <div class="text-center">
            <div class="text-[8px] font-black uppercase tracking-widest text-slate-600 mb-0.5">Nowy model</div>
            <div class="text-sm font-black text-stratton-gold tabular-nums">{{ formatPLN(summary.sumaKosztPodzial + summary.prowizja) }}</div>
          </div>
          <div class="w-px h-6 bg-slate-700"></div>
          <div class="text-center">
            <div class="text-[8px] font-black uppercase tracking-widest text-slate-600 mb-0.5">Oszczędność / mies.</div>
            <div class="text-sm font-black text-emerald-400 tabular-nums">−{{ formatPLN(summary.oszczednoscNetto) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── OFFER MODAL ────────────────────────────────────────────── -->
    <div v-if="showOfferModal" class="fixed inset-0 bg-black/50 z-50 flex items-end md:items-center p-0 md:p-4" @click="showOfferModal = false">
      <div class="bg-white rounded-t-xl md:rounded-xl w-full md:max-w-sm shadow-2xl overflow-hidden" @click.stop>
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">{{ offerAction === 'preview' ? 'Podgląd oferty' : 'Generowanie oferty' }}</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors" @click="showOfferModal = false">
            <AppIcon name="xmark" class="w-4.5 h-4.5" />
          </button>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Układ dokumentu</label>
            <select v-model="documentLayout" class="mt-1.5 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-stratton-gold/30 focus:border-stratton-gold">
              <option value="horizontal">Poziomy</option>
              <option value="vertical">Pionowy</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-100 bg-slate-50">
          <button type="button" class="h-8 px-4 rounded-md text-xs font-semibold text-slate-500 hover:text-slate-700 transition-colors" @click="showOfferModal = false">
            Anuluj
          </button>
          <button type="button" class="h-8 px-5 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded-md shadow-[0_4px_12px_-2px_rgba(197,160,89,0.35)] transition-all duration-200 font-black text-xs uppercase tracking-widest flex items-center justify-center gap-1.5 hover:brightness-110 active:scale-95 border border-white/20 disabled:opacity-50" :disabled="isSaving" @click="confirmGenerateOffer">
            <AppIcon v-if="offerAction === 'preview'" name="eye" class="w-3.5 h-3.5" />
            <AppIcon v-else name="bolt" class="w-3.5 h-3.5" />
            <span>{{ offerAction === 'preview' ? 'Pokaż' : 'Generuj' }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
