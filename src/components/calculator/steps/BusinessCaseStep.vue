<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';

const store = useCalculatorStore();

const handleSelectStandard = () => {
  store.comparisonState.activeCard = 'STANDARD';
  store.prowizjaProc = store.comparisonState.customStandardRate;
};

const handleSelectPrime = () => {
  store.comparisonState.activeCard = 'PRIME';
  store.prowizjaProc = store.comparisonState.customPrimeRate;
};

const handleChangeStandardRate = (value: number) => {
  store.comparisonState.customStandardRate = value;
  if (store.comparisonState.activeCard === 'STANDARD') {
    store.prowizjaProc = value;
  }
};

const handleChangePrimeRate = (value: number) => {
  store.comparisonState.customPrimeRate = value;
  if (store.comparisonState.activeCard === 'PRIME') {
    store.prowizjaProc = value;
  }
};

const syncRates = () => {
  if (store.comparisonState.activeCard === 'STANDARD' && store.prowizjaProc !== store.comparisonState.customStandardRate) {
    store.comparisonState.customStandardRate = store.prowizjaProc;
  }
  if (store.comparisonState.activeCard === 'PRIME' && store.prowizjaProc !== store.comparisonState.customPrimeRate) {
    store.comparisonState.customPrimeRate = store.prowizjaProc;
  }
};

onMounted(syncRates);
watch(() => store.prowizjaProc, syncRates);

const stats = computed(() => {
  if (!store.wyniki) return null;

  const qualifiedEmployees = store.wyniki.szczegoly.filter((w) => w.pracownik.trybSkladek !== 'STUDENT_UZ');
  const excludedCount = store.wyniki.szczegoly.length - qualifiedEmployees.length;

  const sumaKosztStandard = qualifiedEmployees.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
  const sumaKosztPodzial = qualifiedEmployees.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
  const benefitBruttoTotal = qualifiedEmployees.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0);

  const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
  const totalCommissionAmount = benefitBruttoTotal * (store.prowizjaProc / 100);

  const isStandard = store.comparisonState.activeCard === 'STANDARD';
  const raiseRate = isStandard ? 0 : 4;
  const adminRate = isStandard ? 0 : 2;

  const raiseAmount = benefitBruttoTotal * (raiseRate / 100);
  const adminAmount = benefitBruttoTotal * (adminRate / 100);
  const feeAmount = Math.max(0, totalCommissionAmount - raiseAmount - adminAmount);

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

const raiseAmountDisplay = computed(() => {
  if (!stats.value) return 0;
  return stats.value.benefitBase * 0.04;
});

const topSavers = computed(() => {
  if (!store.wyniki) return [];
  return [...store.wyniki.szczegoly]
    .filter((w) => w.pracownik.trybSkladek !== 'STUDENT_UZ')
    .sort((a, b) => b.oszczednosc - a.oszczednosc)
    .slice(0, 5);
});

const isCustomStandard = computed(() => store.comparisonState.customStandardRate !== 28);
const isCustomPrime = computed(() => store.comparisonState.customPrimeRate !== 26);
</script>

<template>
  <div v-if="stats" class="animate-in fade-in zoom-in-95 duration-300 space-y-8">
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-blue-50 rounded-lg text-blue-700">
            <AppIcon name="arrow-trending-up" class="w-5 h-5" />
          </div>
          <h2 class="text-2xl font-bold text-slate-900">Business Case & ROI</h2>
        </div>
        <div v-if="stats.excludedCount > 0" class="text-xs bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1.5 rounded-lg flex items-center gap-2 font-medium">
          <AppIcon name="info" class="w-4 h-4 text-amber-600" />
          <span>Analiza dla {{ stats.qualifiedCount }} pracowników (pominięto {{ stats.excludedCount }} studentów)</span>
        </div>
      </div>
      <p class="text-slate-500 text-sm max-w-3xl">
        Wybierz strategię wdrożeniową. Kliknij na kartę, aby wybrać model docelowy. Parametry procentowe są edytowalne.
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
      <div
        class="lg:col-span-5 relative p-6 rounded-2xl flex flex-col border-2 cursor-pointer transition-all duration-300"
        :class="store.comparisonState.activeCard === 'STANDARD'
          ? 'bg-white border-blue-500 ring-4 ring-blue-500/10 shadow-xl scale-[1.01] z-10'
          : 'bg-white border-slate-200 hover:border-blue-200 shadow-sm opacity-80 hover:opacity-100 hover:scale-[1.005]'"
        @click="handleSelectStandard"
      >
        <div v-if="store.comparisonState.activeCard === 'STANDARD'" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm flex items-center gap-1">
          <AppIcon name="check-circle" class="w-3 h-3" /> Wybrany Scenariusz
        </div>

        <div class="flex justify-between items-start mb-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <AppIcon name="shield-check" class="w-4 h-4" :class="store.comparisonState.activeCard === 'STANDARD' ? 'text-blue-500' : 'text-slate-400'" />
              <span class="text-xs font-bold uppercase tracking-wider" :class="store.comparisonState.activeCard === 'STANDARD' ? 'text-blue-600' : 'text-slate-400'">Wariant Podstawowy</span>
            </div>
            <h3 class="text-xl font-bold" :class="store.comparisonState.activeCard === 'STANDARD' ? 'text-slate-900' : 'text-slate-700'">
              Model Eliton Prime <span class="text-blue-600">STANDARD</span>
            </h3>
          </div>

          <div class="flex flex-col items-end" @click.stop>
            <div class="relative group">
              <input
                type="number"
                min="0"
                max="100"
                :value="store.comparisonState.customStandardRate"
                class="text-3xl font-black text-slate-400 text-right w-24 bg-transparent outline-none border-b-2 border-transparent hover:border-slate-300 focus:border-blue-500 focus:text-blue-600 transition-all"
                @input="handleChangeStandardRate(Number(($event.target as HTMLInputElement).value))"
              />
              <span class="absolute top-1 right-0 text-xs font-bold text-slate-300 pointer-events-none">%</span>
            </div>
            <span v-if="isCustomStandard" class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded mt-1 animate-in fade-in">
              Zmieniono (Indywidualna)
            </span>
          </div>
        </div>

        <div class="flex-1">
          <ul class="space-y-3 text-sm text-slate-500">
            <li class="flex gap-3 items-start">
              <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"></span>
              <span>Maksymalizacja zysku firmy (<strong>100% dla Zarządu</strong>)</span>
            </li>
            <li class="flex gap-3 items-start">
              <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"></span>
              <span>Wynagrodzenie pracownika bez zmian (Netto const)</span>
            </li>
            <li class="flex gap-3 items-start">
              <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"></span>
              <span>Opłata za sukces: <strong class="text-slate-600">{{ store.comparisonState.customStandardRate }}%</strong></span>
            </li>
          </ul>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100">
          <div class="text-xs text-slate-400 mb-1 font-medium">Miesięczny zysk netto firmy</div>
          <div class="text-2xl font-bold" :class="store.comparisonState.activeCard === 'STANDARD' ? 'text-blue-600' : 'text-slate-400 grayscale'">
            {{ formatPLN(profitStandardCalc) }}
          </div>
        </div>
      </div>

      <div
        class="lg:col-span-7 relative rounded-2xl flex flex-col border-2 cursor-pointer transition-all duration-300 overflow-visible"
        :class="store.comparisonState.activeCard === 'PRIME'
          ? 'bg-gradient-to-b from-white to-[#FFF9E5] border-amber-400 ring-4 ring-amber-400/20 shadow-2xl scale-[1.01] z-10'
          : 'bg-white border-slate-200 hover:border-amber-200 shadow-sm opacity-80 hover:opacity-100 hover:scale-[1.005]'"
        @click="handleSelectPrime"
      >
        <div v-if="store.comparisonState.activeCard === 'PRIME'" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm flex items-center gap-1 z-20">
          <AppIcon name="check-circle" class="w-3 h-3" /> Rekomendowany Wybór
        </div>

        <div class="h-full w-full p-6 flex flex-col relative z-10">
          <div v-if="store.comparisonState.activeCard === 'PRIME'" class="absolute -top-20 -right-20 w-64 h-64 bg-amber-200/30 blur-[60px] rounded-full pointer-events-none"></div>

          <div class="flex justify-between items-start mb-6">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider shadow-sm border"
                  :class="store.comparisonState.activeCard === 'PRIME' ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                  <AppIcon name="trophy" class="w-3 h-3" /> Rekomendowany
                </div>
              </div>
              <h3 class="text-2xl font-extrabold text-slate-900">
                Model Eliton Prime <span class="text-amber-600">PLUS +</span>
              </h3>
            </div>

            <div class="flex flex-col items-end z-20" @click.stop>
              <div class="relative group">
                <input
                  type="number"
                  min="0"
                  max="100"
                  :value="store.comparisonState.customPrimeRate"
                  class="text-4xl font-black text-amber-600 text-right w-24 bg-transparent outline-none border-b-2 border-transparent hover:border-amber-300 focus:border-amber-500 transition-all"
                  @input="handleChangePrimeRate(Number(($event.target as HTMLInputElement).value))"
                />
                <span class="absolute top-2 right-0 text-xs font-bold text-amber-400 pointer-events-none">%</span>
              </div>
              <span v-if="isCustomPrime" class="text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded mt-1 animate-in fade-in">
                Zmieniono (Indywidualna)
              </span>
            </div>
          </div>

          <div class="flex-1">
            <ul class="space-y-3 text-sm text-slate-600">
              <li class="flex gap-3 items-start">
                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                <span><strong>Win-Win:</strong> część zysku wraca do pracowników w formie podwyżek</span>
              </li>
              <li class="flex gap-3 items-start">
                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                <span>Podwyżka systemowa: <strong>4%</strong> oraz bonus administracyjny: <strong>2%</strong></span>
              </li>
              <li class="flex gap-3 items-start">
                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                <span>Średnia podwyżka (na bazie benefitu): <strong>{{ formatPLN(raiseAmountDisplay) }}</strong></span>
              </li>
            </ul>
          </div>

          <div class="mt-6 pt-4 border-t border-amber-200/70">
            <div class="text-xs text-amber-700 mb-1 font-medium">Miesięczny zysk netto firmy</div>
            <div class="text-3xl font-extrabold text-amber-700">
              {{ formatPLN(profitPrimeCalc) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Oszczędność brutto</div>
        <div class="text-xl font-bold text-slate-900">{{ formatPLN(stats.oszczednoscBrutto) }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Prowizja (faktura)</div>
        <div class="text-xl font-bold text-slate-900">{{ formatPLN(stats.prowizja) }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Oszczędność netto</div>
        <div class="text-xl font-bold text-emerald-600">{{ formatPLN(stats.oszczednoscNetto) }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Oszczędność roczna</div>
        <div class="text-xl font-bold text-slate-900">{{ formatPLN(stats.oszczednoscRoczna) }}</div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
      <div class="flex items-center gap-2">
        <AppIcon name="chart-pie" class="w-5 h-5 text-slate-500" />
        <h3 class="text-base font-bold text-slate-900">Struktura kosztów (model docelowy)</h3>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50">
          <div class="text-xs text-slate-400 uppercase tracking-widest">Kapitał ludzki</div>
          <div class="text-lg font-bold text-slate-900">{{ formatPLN(stats.sumaKosztPodzial) }}</div>
          <div class="text-[11px] text-slate-500 mt-1">Wynagrodzenia netto + benefity</div>
        </div>
        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50">
          <div class="text-xs text-slate-400 uppercase tracking-widest">Podwyżki i bonusy</div>
          <div class="text-lg font-bold text-amber-600">{{ formatPLN(stats.raiseAmount + stats.adminAmount) }}</div>
          <div class="text-[11px] text-slate-500 mt-1">4% + 2% (wariant Prime)</div>
        </div>
        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50">
          <div class="text-xs text-slate-400 uppercase tracking-widest">Opłata za sukces</div>
          <div class="text-lg font-bold text-indigo-600">{{ formatPLN(stats.feeAmount) }}</div>
          <div class="text-[11px] text-slate-500 mt-1">Pozostała część prowizji</div>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <AppIcon name="trophy" class="w-5 h-5 text-emerald-600" />
          <h3 class="text-base font-bold text-slate-900">Top oszczędności (pracownicy)</h3>
        </div>
        <span class="text-[10px] uppercase tracking-widest text-slate-400">Top 5</span>
      </div>
      <div class="space-y-2">
        <div v-for="entry in topSavers" :key="entry.pracownik.id" class="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-slate-50">
          <div>
            <div class="text-sm font-semibold text-slate-800">{{ entry.pracownik.imie }} {{ entry.pracownik.nazwisko }}</div>
            <div class="text-[11px] text-slate-500">Oszczędność: {{ formatPLN(entry.oszczednosc) }}</div>
          </div>
          <div class="text-sm font-bold text-emerald-600">{{ formatPLN(entry.oszczednosc) }}</div>
        </div>
        <div v-if="topSavers.length === 0" class="text-sm text-slate-400">Brak danych do wyświetlenia.</div>
      </div>
    </div>
  </div>
</template>
