<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';

const store = useCalculatorStore();

const handleSelectStandard = () => {
  // STANDARD now means 'external accounting' (22%).
  store.setHasExternalAccounting(true);
  store.comparisonState.activeCard = 'STANDARD';
  store.prowizjaProc = store.comparisonState.customStandardRate;
};

const handleSelectPrime = () => {
  // PRIME now means 'own accounting' (20%).
  store.setHasExternalAccounting(false);
  store.comparisonState.activeCard = 'PRIME';
  store.prowizjaProc = store.comparisonState.customPrimeRate;
};

const syncRates = () => {
  // Keep the comparisonState.activeCard mirror in sync with the
  // accounting toggle so the BusinessCase view + downstream PDF
  // template (which still keys off activeCard) match the calculator
  // state. The admin override of customStandardRate / customPrimeRate
  // still flows through prowizjaProc.
  store.comparisonState.activeCard = store.hasExternalAccounting ? 'STANDARD' : 'PRIME';
  if (store.comparisonState.activeCard === 'STANDARD' && store.prowizjaProc !== store.comparisonState.customStandardRate) {
    store.comparisonState.customStandardRate = store.prowizjaProc;
  }
  if (store.comparisonState.activeCard === 'PRIME' && store.prowizjaProc !== store.comparisonState.customPrimeRate) {
    store.comparisonState.customPrimeRate = store.prowizjaProc;
  }
};

onMounted(syncRates);
watch(() => store.prowizjaProc, syncRates);
watch(() => store.hasExternalAccounting, syncRates);

const stats = computed(() => {
  if (!store.wyniki) return null;

  const qualifiedEmployees = store.wyniki.szczegoly.filter((w) => w.pracownik.trybSkladek !== 'STUDENT_UZ');
  const excludedCount = store.wyniki.szczegoly.length - qualifiedEmployees.length;

  const sumaKosztStandard = qualifiedEmployees.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
  const sumaKosztPodzial = qualifiedEmployees.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
  const benefitBruttoTotal = qualifiedEmployees.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0);
  const benefitNettoTotal = qualifiedEmployees.reduce((acc, w) => acc + w.podzial.swiadczenie.netto, 0);

  const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
  const totalCommissionAmount = benefitNettoTotal * (store.prowizjaProc / 100);

  // New 22%/20% model — Etap 1B/1C/1D:
  // - external accounting (22%): 20% Stratton fee + 2% accounting office
  // - own accounting (20%):      20% Stratton fee only
  // Employee raises moved to the standalone Kalkulator podwyżek Excel.
  const adminRate = store.hasExternalAccounting ? 2 : 0;
  const raiseAmount = 0;
  const adminAmount = benefitNettoTotal * (adminRate / 100);
  const feeAmount = Math.max(0, totalCommissionAmount - adminAmount);

  return {
    sumaKosztStandard,
    sumaKosztPodzial,
    benefitBruttoTotal,
    oszczednoscBrutto,
    feeAmount,
    raiseAmount,
    adminAmount,
    oszczednoscNetto: oszczednoscBrutto - totalCommissionAmount,
    baseSavings: oszczednoscBrutto,
    benefitBase: benefitBruttoTotal,
    oszczednoscRoczna: (oszczednoscBrutto - totalCommissionAmount) * 12,
    prowizja: totalCommissionAmount,
    qualifiedCount: qualifiedEmployees.length,
    excludedCount,
  };
});

const profitStandardCalc = computed(() => {
  if (!stats.value) return 0;
  return stats.value.baseSavings - stats.value.benefitBase * (store.comparisonState.customStandardRate / 100);
});

const profitPrimeCalc = computed(() => {
  if (!stats.value) return 0;
  return stats.value.baseSavings - stats.value.benefitBase * (store.comparisonState.customPrimeRate / 100);
});


const topSavers = computed(() => {
  if (!store.wyniki) return [];
  return [...store.wyniki.szczegoly]
    .filter((w) => w.pracownik.trybSkladek !== 'STUDENT_UZ')
    .sort((a, b) => b.oszczednosc - a.oszczednosc)
    .slice(0, 5);
});

const isStandard = computed(() => store.comparisonState.activeCard === 'STANDARD');
</script>

<template>
  <div>
  <div v-if="stats" class="space-y-3">

    <!-- D365 Page Header -->
    <div class="mb-2 min-h-[68px]">
      <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">
        <AppIcon name="building" class="w-3 h-3" />
        <span>{{ store.firma?.nazwa || 'Firma' }}</span>
        <span class="text-slate-300">/</span>
        <span>Kalkulator</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600">Krok 5 — Wybór modelu</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 class="text-xl font-black text-slate-900 tracking-tight">Oszczędności Eliton Prime™</h1>
          <p class="text-[11px] text-slate-400 mt-0.5">Porównanie modeli · Wybierz wariant dla klienta</p>
        </div>
        <div class="flex items-center gap-2">
          <div class="h-8 px-3 flex items-center gap-2 rounded-md" :class="isStandard ? 'bg-white border border-stratton-gold/40' : 'bg-amber-50 border border-amber-200'">
            <span class="text-amber-400 text-xs">⭐</span>
            <span class="text-[10px] font-black uppercase tracking-widest" :class="isStandard ? 'text-stratton-gold' : 'text-amber-700'">{{ isStandard ? 'Eliton Prime™' : 'Prime Plus' }}</span>
          </div>
          <div v-if="stats.excludedCount > 0" class="h-8 px-3 flex items-center border border-slate-200 rounded-md bg-white">
            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">{{ stats.excludedCount }} wykluczone</span>
          </div>
          <div class="h-8 px-3 flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-md">
            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-700">Oszczędność</span>
            <span class="text-sm font-black text-emerald-600 tabular-nums">{{ formatPLN(stats.oszczednoscNetto) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Model selection cards — D365 comparison layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

      <!-- Standard Card -->
      <div
        class="group/card lg:col-span-5 bg-white border-2 rounded-xl overflow-hidden cursor-pointer transition-all duration-300 ease-out relative hover:-translate-y-1.5 before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-stratton-gold before:rounded-t-xl before:z-10"
        :class="isStandard ? 'border-stratton-gold shadow-[0_0_0_3px_rgba(197,160,89,0.12),0_16px_24px_-6px_rgba(197,160,89,0.20)]' : 'border-slate-200 hover:border-stratton-gold/40 hover:shadow-[0_16px_24px_-6px_rgba(197,160,89,0.18),0_6px_12px_-4px_rgba(197,160,89,0.10)]'"
        @click="handleSelectStandard"
      >
        <!-- Card header -->
        <div class="flex items-center justify-between px-4 py-3 border-b" :class="isStandard ? 'border-stratton-gold/20 bg-amber-50/40' : 'border-slate-100 bg-slate-50/60'">
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-colors" :class="isStandard ? 'border-stratton-gold bg-stratton-gold' : 'border-slate-300 bg-white'">
              <div v-if="isStandard" class="w-1.5 h-1.5 rounded-full bg-white"></div>
            </div>
            <span class="text-sm font-black uppercase tracking-widest" :class="isStandard ? 'text-stratton-gold' : 'text-slate-600'">Eliton Prime™</span>
          </div>
          <span v-if="isStandard" class="text-[8px] font-black uppercase tracking-widest text-stratton-gold border border-stratton-gold/30 bg-amber-50 rounded px-1.5 py-0.5">Wybrany</span>
        </div>

        <!-- Rate input -->
        <div class="px-4 py-3 border-b border-slate-100">
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Stawka prowizji (%)</label>
          <div class="flex items-center gap-2">
            <input
              v-model.number="store.comparisonState.customStandardRate"
              type="number"
              step="0.5"
              min="0"
              max="100"
              class="w-24 h-8 border border-slate-200 rounded-md px-3 text-lg font-mono font-bold text-center focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors"
              @change="isStandard ? store.prowizjaProc = store.comparisonState.customStandardRate : null"
            />
            <span class="text-xs text-slate-400 font-semibold">% od wartości benefitu</span>
          </div>
        </div>

        <!-- Features -->
        <div class="px-4 py-3">
          <ul class="space-y-2">
            <li class="flex items-start gap-2">
              <span class="mt-1 w-1.5 h-1.5 rounded-full bg-slate-400 shrink-0"></span>
              <span class="text-[11px] text-slate-600">Model podstawowy – bez systemu podwyżek</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-1 w-1.5 h-1.5 rounded-full bg-slate-400 shrink-0"></span>
              <span class="text-[11px] text-slate-600">Wdrożenie do 14 dni</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-1 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
              <span class="text-[11px] text-slate-600">Bonus <strong>{{ formatPLN(stats.adminAmount) }}</strong> dla działu HR/księgowości</span>
            </li>
          </ul>
        </div>

        <!-- Savings footer -->
        <div class="px-4 py-3 border-t" :class="isStandard ? 'border-stratton-gold/20 bg-amber-50/20' : 'border-slate-100 bg-slate-50/40'">
          <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Mies. oszczędność firmy</div>
          <div class="text-xl font-black" :class="isStandard ? 'text-stratton-gold' : 'text-slate-700'">{{ formatPLN(profitStandardCalc) }}</div>
        </div>
      </div>

      <!-- PRIME Card -->
      <div
        class="group/card lg:col-span-7 border-2 rounded-xl overflow-hidden cursor-pointer transition-all duration-300 ease-out relative hover:-translate-y-1.5 before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-amber-400 before:rounded-t-xl before:z-10"
        :class="!isStandard ? 'border-amber-400 shadow-[0_0_0_3px_rgba(197,160,89,0.15),0_16px_24px_-6px_rgba(197,160,89,0.22)] bg-linear-to-b from-white to-amber-50/20' : 'border-slate-200 hover:border-amber-300 hover:shadow-[0_16px_24px_-6px_rgba(251,191,36,0.18),0_6px_12px_-4px_rgba(251,191,36,0.10)] bg-white'"
        @click="handleSelectPrime"
      >
        <!-- Card header -->
        <div class="flex items-center justify-between px-4 py-3 border-b" :class="!isStandard ? 'border-amber-200/60 bg-amber-50/50' : 'border-slate-100 bg-slate-50/60'">
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-colors" :class="!isStandard ? 'border-amber-500 bg-amber-500' : 'border-slate-300 bg-white'">
              <div v-if="!isStandard" class="w-1.5 h-1.5 rounded-full bg-white"></div>
            </div>
            <span class="text-xl font-black uppercase tracking-widest" :class="!isStandard ? 'text-amber-700' : 'text-slate-600'">Eliton Prime™ Plus</span>
            <span class="text-[8px] font-black uppercase tracking-widest text-amber-600 border border-amber-200 bg-amber-50 rounded px-1.5 py-0.5">Rekomendowany</span>
          </div>
          <span v-if="!isStandard" class="text-[8px] font-black uppercase tracking-widest text-amber-700 border border-amber-300 bg-amber-50 rounded px-1.5 py-0.5">Wybrany</span>
        </div>

        <!-- Rate input -->
        <div class="px-4 py-3 border-b border-amber-100/60">
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Stawka prowizji (%)</label>
          <div class="flex items-center gap-2">
            <input
              v-model.number="store.comparisonState.customPrimeRate"
              type="number"
              step="0.5"
              min="0"
              max="100"
              class="w-24 h-8 border border-amber-200 rounded-md px-3 text-lg font-mono font-bold text-center focus:outline-none focus:ring-1 focus:ring-amber-400 focus:border-amber-400 transition-colors"
              @change="!isStandard ? store.prowizjaProc = store.comparisonState.customPrimeRate : null"
            />
            <span class="text-xs text-slate-400 font-semibold">% od wartości benefitu</span>
          </div>
        </div>

        <!-- Features -->
        <div class="px-4 py-3">
          <ul class="space-y-2">
            <li class="flex items-start gap-2">
              <span class="mt-1 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
              <span class="text-[11px] text-slate-600">Wdrożenie priorytetowe max do 14 dni</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-1 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
              <span class="text-[11px] text-slate-600">Podwyżki <strong>{{ formatPLN(stats.raiseAmount) }}</strong> finansowane przez Stratton Prime</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-1 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
              <span class="text-[11px] text-slate-600">Bonus <strong>{{ formatPLN(stats.adminAmount) }}</strong> dla działu HR/księgowości</span>
            </li>
          </ul>
        </div>

        <!-- Savings footer -->
        <div class="px-4 py-3 border-t" :class="!isStandard ? 'border-amber-200/60 bg-amber-50/30' : 'border-slate-100 bg-slate-50/40'">
          <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-0.5">Mies. oszczędność firmy</div>
          <div class="text-xl font-black" :class="!isStandard ? 'text-amber-700' : 'text-slate-700'">{{ formatPLN(profitPrimeCalc) }}</div>
        </div>
      </div>
    </div>

    <!-- KPI Scorecards row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="group bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-slate-300 hover:shadow-[0_20px_28px_-6px_rgba(100,116,139,0.25),0_8px_16px_-4px_rgba(100,116,139,0.15)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-slate-400 before:rounded-t-xl before:z-10">
        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-600 transition-colors duration-300">Oszczędność całkowita</span>
        </div>
        <div class="px-4 py-3">
          <div class="text-lg font-black text-slate-900">{{ formatPLN(stats.oszczednoscBrutto) }}</div>
          <div class="text-[9px] text-slate-400 font-medium mt-0.5">przed opłatą serwisową</div>
        </div>
      </div>
      <div class="group bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-indigo-200 hover:shadow-[0_20px_28px_-6px_rgba(99,102,241,0.25),0_8px_16px_-4px_rgba(99,102,241,0.15)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-indigo-400 before:rounded-t-xl before:z-10">
        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 group-hover:text-indigo-600 transition-colors duration-300">Opłata serwisowa</span>
        </div>
        <div class="px-4 py-3">
          <div class="text-lg font-black text-indigo-700">{{ formatPLN(stats.prowizja) }}</div>
          <div class="text-[9px] text-slate-400 font-medium mt-0.5">miesięcznie</div>
        </div>
      </div>
      <div class="group bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-emerald-200 hover:shadow-[0_20px_28px_-6px_rgba(16,185,129,0.30),0_8px_16px_-4px_rgba(16,185,129,0.18)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-emerald-400 before:rounded-t-xl before:z-10">
        <div class="px-4 py-2.5 border-b border-emerald-100/60 bg-emerald-50/30">
          <span class="text-[9px] font-black uppercase tracking-widest text-emerald-700 group-hover:text-emerald-600 transition-colors duration-300">Oszczędność firmy</span>
        </div>
        <div class="px-4 py-3">
          <div class="text-lg font-black text-emerald-600">{{ formatPLN(stats.oszczednoscNetto) }}</div>
          <div class="text-[9px] text-slate-400 font-medium mt-0.5">po opłacie / miesiąc</div>
        </div>
      </div>
      <div class="group bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-stratton-gold/40 hover:shadow-[0_20px_28px_-6px_rgba(197,160,89,0.28),0_8px_16px_-4px_rgba(197,160,89,0.18)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-stratton-gold before:rounded-t-xl before:z-10">
        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 group-hover:text-amber-600 transition-colors duration-300">Oszczędność roczna</span>
        </div>
        <div class="px-4 py-3">
          <div class="text-lg font-black text-slate-900">{{ formatPLN(stats.oszczednoscRoczna) }}</div>
          <div class="text-[9px] text-slate-400 font-medium mt-0.5">12 miesięcy</div>
        </div>
      </div>
    </div>

    <!-- Struktura + Top Savers row -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-3">

      <!-- Struktura podziału oszczędności -->
      <div class="group lg:col-span-3 bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-slate-300 hover:shadow-[0_20px_28px_-6px_rgba(100,116,139,0.20),0_8px_16px_-4px_rgba(100,116,139,0.12)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-linear-to-r before:from-slate-400 before:via-indigo-400 before:to-emerald-400 before:z-10 before:rounded-t-xl">
        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-700 group-hover:text-slate-800 transition-colors duration-300">Struktura podziału oszczędności</span>
        </div>
        <div class="px-5 py-4 grid grid-cols-3 gap-3">
          <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
            <div class="text-[8px] font-black uppercase tracking-widest text-slate-400 mb-1">Koszty pracownicze</div>
            <div class="text-sm font-black text-slate-900">{{ formatPLN(stats.sumaKosztPodzial) }}</div>
            <div class="text-[9px] text-slate-400 mt-0.5">wynagrodzenia + benefity</div>
          </div>
          <div class="rounded-lg border border-amber-100 bg-amber-50/40 px-3 py-2.5">
            <div class="text-[8px] font-black uppercase tracking-widest text-amber-600 mb-1">Podwyżki i bonusy</div>
            <div class="text-sm font-black text-amber-700">{{ formatPLN(stats.raiseAmount + stats.adminAmount) }}</div>
            <div class="text-[9px] text-slate-400 mt-0.5">{{ isStandard ? '2% bonus HR' : '4% podwyżki + 2% HR' }}</div>
          </div>
          <div class="rounded-lg border border-indigo-100 bg-indigo-50/30 px-3 py-2.5">
            <div class="text-[8px] font-black uppercase tracking-widest text-indigo-600 mb-1">Opłata serwisowa</div>
            <div class="text-sm font-black text-indigo-700">{{ formatPLN(stats.feeAmount) }}</div>
            <div class="text-[9px] text-slate-400 mt-0.5">pozostała część prowizji</div>
          </div>
        </div>
      </div>

      <!-- Top 5 savers -->
      <div class="group lg:col-span-2 bg-white border border-slate-200 rounded-xl overflow-hidden relative transition-all duration-300 ease-out hover:-translate-y-2 hover:border-emerald-200 hover:shadow-[0_20px_28px_-6px_rgba(16,185,129,0.22),0_8px_16px_-4px_rgba(16,185,129,0.14)] before:absolute before:top-0 before:left-0 before:h-[3px] before:w-0 hover:before:w-full before:transition-all before:duration-300 before:bg-linear-to-r before:from-emerald-400 before:to-emerald-600 before:z-10 before:rounded-t-xl">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-700 group-hover:text-emerald-700 transition-colors duration-300">Top oszczędności</span>
          <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">Top 5</span>
        </div>
        <div>
          <div
            v-for="(entry, idx) in topSavers"
            :key="entry.pracownik.id"
            class="flex items-center justify-between px-4 py-2.5 border-b border-slate-50 hover:bg-blue-50/20 transition-colors"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="text-[9px] font-black text-slate-400 w-4 text-center shrink-0">{{ idx + 1 }}</span>
              <div class="min-w-0">
                <div class="text-[11px] font-bold text-slate-800 truncate">{{ entry.pracownik.imie }} {{ entry.pracownik.nazwisko }}</div>
              </div>
            </div>
            <div class="text-[11px] font-black text-emerald-600 shrink-0">{{ formatPLN(entry.oszczednosc) }}</div>
          </div>
          <div v-if="topSavers.length === 0" class="px-4 py-4 text-[11px] text-slate-400">Brak danych</div>
        </div>
      </div>
    </div>

  </div>
  <div v-else class="bg-white border border-slate-200 rounded-xl px-5 py-8 text-center text-xs text-slate-400 uppercase tracking-widest">
    Brak danych — oblicz wyniki w poprzednich krokach
  </div>
  </div>
</template>
