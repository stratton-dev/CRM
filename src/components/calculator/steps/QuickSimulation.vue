<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatPLN } from '../utils/formatters';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { obliczWariantPodzial, obliczWariantStandard } from '../tax-engine';
import { Pracownik } from '../models/employee';

type ContractType = 'UOP' | 'UZ' | 'MIXED';
type StrategyType = 'SAVINGS' | 'WIN_WIN';

const props = withDefaults(
  defineProps<{
    initialEmployees?: number;
    initialAvgWage?: number;
    initialContractType?: ContractType;
    initialSalaryMode?: 'NETTO' | 'BRUTTO';
  }>(),
  {
    initialEmployees: 50,
    initialAvgWage: 6000,
    initialContractType: 'UOP',
    initialSalaryMode: 'NETTO',
  }
);

const emit = defineEmits<{ (event: 'transfer'): void }>();

const store = useCalculatorStore();

const empCount = ref(props.initialEmployees);
const avgSalary = ref(props.initialAvgWage);
const salaryMode = ref<'NETTO' | 'BRUTTO'>(props.initialSalaryMode);
const contractType = ref<ContractType>(props.initialContractType);
const mixRatio = ref(50);
const strategy = ref<StrategyType>('WIN_WIN');
const contractOptions: ContractType[] = ['UOP', 'MIXED', 'UZ'];

const simulation = computed(() => {
  let countUOP = 0;
  let countUZ = 0;

  if (contractType.value === 'UOP') countUOP = empCount.value;
  else if (contractType.value === 'UZ') countUZ = empCount.value;
  else {
    countUZ = Math.round(empCount.value * (mixRatio.value / 100));
    countUOP = empCount.value - countUZ;
  }

  const calculateOne = (type: 'UOP' | 'UZ') => {
    let baseNetto = avgSalary.value;
    if (salaryMode.value === 'BRUTTO') {
      baseNetto = type === 'UOP' ? avgSalary.value * 0.71 : avgSalary.value * 0.78;
    }

    const dummyEmployee: Pracownik = {
      id: 0,
      imie: 'X',
      nazwisko: 'X',
      dataUrodzenia: '1990-01-01',
      plec: 'M',
      typUmowy: type,
      trybSkladek: 'PELNE',
      choroboweAktywne: true,
      pit2: '300',
      ulgaMlodych: false,
      kupTyp: type === 'UZ' ? 'PROC_20' : 'STANDARD',
      nettoDocelowe: baseNetto,
      nettoZasadnicza: type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto,
      pitMode: 'AUTO',
      skladkaFP: true,
      skladkaFGSP: true,
    };

    const std = obliczWariantStandard(dummyEmployee, store.firma.stawkaWypadkowa, store.config);
    const opt = obliczWariantPodzial(dummyEmployee, store.firma.stawkaWypadkowa, dummyEmployee.nettoZasadnicza, store.config);

    const provPercent = strategy.value === 'SAVINGS' ? 28 : 26;
    const provision = opt.swiadczenie.brutto * (provPercent / 100);

    return {
      stdKoszt: std.kosztPracodawcy,
      optKosztTotal: opt.kosztPracodawcy + provision,
      provision,
      netto: baseNetto,
    };
  };

  const resUOP = calculateOne('UOP');
  const resUZ = calculateOne('UZ');

  const totalStd = resUOP.stdKoszt * countUOP + resUZ.stdKoszt * countUZ;
  const totalNew = resUOP.optKosztTotal * countUOP + resUZ.optKosztTotal * countUZ;
  const totalProv = resUOP.provision * countUOP + resUZ.provision * countUZ;
  const savings = totalStd - totalNew;

  return {
    countUOP,
    countUZ,
    totalStd,
    totalNew,
    totalProv,
    savings,
    monthlySavings: savings,
    yearlySavings: savings * 12,
    perEmployeeSavings: empCount.value > 0 ? savings / empCount.value : 0,
  };
});

const costRatio = computed(() => {
  if (!simulation.value.totalStd) return 0;
  return (simulation.value.totalNew / simulation.value.totalStd) * 100;
});

const handleTransfer = () => {
  const genUOP = simulation.value.countUOP;
  const genUZ = simulation.value.countUZ;

  const newEmployees: Pracownik[] = [];
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
    nettoDocelowe: salaryMode.value === 'NETTO' ? avgSalary.value : type === 'UOP' ? avgSalary.value * 0.71 : avgSalary.value * 0.78,
    nettoZasadnicza: type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto,
    pitMode: 'AUTO',
    skladkaFP: true,
    skladkaFGSP: true,
  });

  for (let i = 0; i < genUOP; i++) newEmployees.push(createEmp('UOP', i));
  for (let i = 0; i < genUZ; i++) newEmployees.push(createEmp('UZ', genUOP + i));

  store.prowizjaProc = strategy.value === 'SAVINGS' ? 28 : 26;
  store.pracownicy = newEmployees;
  emit('transfer');
};
</script>

<template>
  <div class="flex flex-col xl:flex-row h-full animate-fade-in overflow-hidden">
    <div class="xl:w-[420px] bg-slate-900 text-white flex flex-col shrink-0 overflow-y-auto border-r border-slate-800 custom-scrollbar">
      <div class="p-8 pb-2">
        <div class="flex items-center gap-2 mb-3 text-emerald-400">
          <div class="p-1.5 bg-emerald-500/10 rounded-lg">
            <AppIcon name="bolt" class="w-5 h-5" />
          </div>
          <span class="font-bold uppercase tracking-widest text-xs">Szybka Symulacja v2.0</span>
        </div>
        <h2 class="text-2xl font-bold text-white leading-tight">Parametry Biznesowe</h2>
        <p class="text-slate-400 text-sm mt-2 leading-relaxed">Skonfiguruj strukturę zatrudnienia i wybierz strategię optymalizacji.</p>
      </div>

      <div class="p-8 space-y-10 flex-1">
        <div class="space-y-4">
          <div class="flex justify-between items-end">
            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
              <AppIcon name="users" class="w-4 h-4" />
              Zatrudnienie
            </label>
            <span class="text-2xl font-bold text-white">{{ empCount }}</span>
          </div>
          <input v-model.number="empCount" type="range" min="1" max="300" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-emerald-500 hover:accent-emerald-400" />
          <div class="flex justify-between text-[10px] text-slate-500 font-mono">
            <span>1</span><span>150</span><span>300</span>
          </div>
        </div>

        <div class="space-y-4">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
            <AppIcon name="filter" class="w-4 h-4" />
            Struktura Umów
          </label>

          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="type in contractOptions"
              :key="type"
              type="button"
              class="py-2 px-1 text-xs font-bold rounded-md border transition-all"
              :class="contractType === type ? 'bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-900/50' : 'bg-slate-800 border-slate-700 text-slate-400 hover:border-slate-500 hover:text-white'"
              @click="contractType = type"
            >
              {{ type === 'MIXED' ? 'MIESZANY' : type }}
            </button>
          </div>

          <div v-if="contractType === 'MIXED'" class="bg-slate-800/50 p-4 rounded-xl border border-slate-700 animate-fade-in">
            <div class="flex justify-between text-xs font-bold mb-2">
              <span class="text-blue-400">{{ 100 - mixRatio }}% UoP</span>
              <span class="text-amber-400">{{ mixRatio }}% UZ</span>
            </div>
            <input v-model.number="mixRatio" type="range" min="0" max="100" step="10" class="w-full h-1.5 bg-slate-600 rounded-lg appearance-none cursor-pointer accent-white" />
          </div>
        </div>

        <div class="space-y-4">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
            <AppIcon name="wallet" class="w-4 h-4" />
            Średnia Płaca
          </label>
          <div class="relative group">
            <input v-model.number="avgSalary" type="number" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-3.5 pl-4 pr-20 text-white font-bold text-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all group-hover:border-slate-600" />
            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex bg-slate-700 rounded-lg p-1">
              <button type="button" class="px-2 py-1 text-[10px] font-bold rounded" :class="salaryMode === 'NETTO' ? 'bg-emerald-600 text-white' : 'text-slate-400'" @click="salaryMode = 'NETTO'">NET</button>
              <button type="button" class="px-2 py-1 text-[10px] font-bold rounded" :class="salaryMode === 'BRUTTO' ? 'bg-emerald-600 text-white' : 'text-slate-400'" @click="salaryMode = 'BRUTTO'">BRU</button>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
            <AppIcon name="layers" class="w-4 h-4" />
            Strategia
          </label>
          <div class="grid grid-cols-1 gap-3">
            <button
              type="button"
              class="relative p-3 rounded-xl border text-left transition-all flex items-start gap-3"
              :class="strategy === 'WIN_WIN' ? 'bg-slate-800 border-blue-500/50 ring-1 ring-blue-500/50' : 'bg-slate-800/30 border-slate-700 opacity-60 hover:opacity-100'"
              @click="strategy = 'WIN_WIN'"
            >
              <div class="mt-1 p-1 rounded-full" :class="strategy === 'WIN_WIN' ? 'bg-blue-500 text-white' : 'bg-slate-700 text-slate-400'">
                <AppIcon name="users" class="w-3 h-3" />
              </div>
              <div>
                <div class="text-sm font-bold text-white">Model Win-Win (Domyślny)</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Oszczędność + podwyżki. Prowizja 26%.</div>
              </div>
            </button>

            <button
              type="button"
              class="relative p-3 rounded-xl border text-left transition-all flex items-start gap-3"
              :class="strategy === 'SAVINGS' ? 'bg-slate-800 border-emerald-500/50 ring-1 ring-emerald-500/50' : 'bg-slate-800/30 border-slate-700 opacity-60 hover:opacity-100'"
              @click="strategy = 'SAVINGS'"
            >
              <div class="mt-1 p-1 rounded-full" :class="strategy === 'SAVINGS' ? 'bg-emerald-500 text-slate-900' : 'bg-slate-700 text-slate-400'">
                <AppIcon name="arrow-trending-up" class="w-3 h-3" />
              </div>
              <div>
                <div class="text-sm font-bold text-white">Max oszczędności</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Wszystkie zyski dla firmy. Prowizja 28%.</div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div class="p-6 border-t border-slate-800 bg-slate-900/90 backdrop-blur sticky bottom-0">
        <button type="button" class="w-full h-12 bg-indigo-600 hover:bg-indigo-700 text-white font-bold uppercase tracking-wide rounded-xl shadow-lg shadow-indigo-500/30 transition-all flex items-center justify-center gap-3 group" @click="handleTransfer">
          <span>Przejdź do szczegółów</span>
          <AppIcon name="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
        </button>
      </div>
    </div>

    <div class="flex-1 bg-[#f8fafc] p-6 md:p-12 overflow-y-auto flex flex-col">
      <div class="max-w-5xl mx-auto w-full space-y-10">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
            <div class="flex justify-between items-start mb-4">
              <div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Oszczędność miesięczna</div>
                <div class="text-sm text-slate-500 font-medium mt-1">
                  {{ strategy === 'WIN_WIN' ? 'Po wypłaceniu podwyżek' : 'Netto dla firmy' }}
                </div>
              </div>
              <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                <AppIcon name="arrow-trending-up" class="w-6 h-6" />
              </div>
            </div>
            <div class="text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight">
              {{ formatPLN(simulation.monthlySavings) }}
            </div>
            <div v-if="strategy === 'WIN_WIN'" class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold">
              <AppIcon name="users" class="w-3 h-3" />
              + Zadowoleni pracownicy
            </div>
          </div>

          <div class="bg-slate-900 rounded-2xl p-6 border border-slate-900 shadow-xl text-white relative overflow-hidden group">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-500 rounded-full blur-[80px] opacity-20 group-hover:opacity-30 transition-opacity"></div>

            <div class="flex justify-between items-start mb-4 relative z-10">
              <div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Potencjał roczny</div>
                <div class="text-sm text-slate-400 font-medium mt-1">Skumulowany zysk</div>
              </div>
              <div class="p-2 bg-white/10 text-white rounded-lg">
                <AppIcon name="chart-pie" class="w-6 h-6" />
              </div>
            </div>
            <div class="text-4xl lg:text-5xl font-extrabold text-white tracking-tight relative z-10">
              {{ formatPLN(simulation.yearlySavings) }}
            </div>
            <div class="mt-4 h-1.5 w-full bg-slate-800 rounded-full overflow-hidden relative z-10">
              <div class="h-full bg-gradient-to-r from-emerald-500 to-blue-500 w-[70%] animate-pulse"></div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
          <div class="flex items-center justify-between mb-8">
            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
              <AppIcon name="shield-check" class="text-blue-500" />
              Porównanie kosztów
            </h3>
            <div class="text-xs font-medium text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
              {{ contractType === 'MIXED' ? `Mix: ${100 - mixRatio}% UoP / ${mixRatio}% UZ` : contractType }}
            </div>
          </div>

          <div class="space-y-6">
            <div>
              <div class="flex justify-between text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">
                <span>Model Standard</span>
                <span>{{ formatPLN(simulation.totalStd) }}</span>
              </div>
              <div class="h-10 w-full bg-slate-100 rounded-lg overflow-hidden flex relative">
                <div class="h-full bg-slate-400 flex items-center justify-center text-white text-xs font-bold w-full">KOSZTY ZATRUDNIENIA (100%)</div>
              </div>
            </div>

            <div>
              <div class="flex justify-between text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">
                <span>Model Eliton Prime</span>
                <span class="text-emerald-600 font-extrabold">{{ formatPLN(simulation.totalNew) }}</span>
              </div>
              <div class="h-10 w-full bg-slate-100 rounded-lg overflow-hidden flex relative">
                <div
                  class="h-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white text-xs font-bold shadow-[0_0_15px_rgba(37,99,235,0.3)] z-10 transition-all duration-700"
                  :style="{ width: `${costRatio}%` }"
                >
                  NOWY KOSZT
                </div>
                <div class="flex-1 bg-emerald-500/10 flex items-center justify-center text-emerald-700 text-xs font-bold relative overflow-hidden">
                  <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/diagonal-stripes.png')] opacity-10"></div>
                  OSZCZĘDNOŚĆ
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left min-w-[640px]">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold">
              <tr>
                <th class="px-6 py-4">Kategoria</th>
                <th class="px-6 py-4 text-right">Standard</th>
                <th class="px-6 py-4 text-right">Eliton Prime</th>
                <th class="px-6 py-4 text-right text-emerald-600">Różnica</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr class="hover:bg-slate-50/50">
                <td class="px-6 py-4 font-medium text-slate-700">Koszt całkowity</td>
                <td class="px-6 py-4 text-right text-slate-500 line-through decoration-slate-300">{{ formatPLN(simulation.totalStd) }}</td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatPLN(simulation.totalNew) }}</td>
                <td class="px-6 py-4 text-right font-bold text-emerald-600 bg-emerald-50/30">-{{ formatPLN(simulation.savings) }}</td>
              </tr>
              <tr class="hover:bg-slate-50/50">
                <td class="px-6 py-4 text-slate-600 pl-10">
                  w tym Opłata serwisowa
                  <div class="text-[10px] text-slate-400">Success fee</div>
                </td>
                <td class="px-6 py-4 text-right text-slate-300">-</td>
                <td class="px-6 py-4 text-right font-medium text-amber-600">{{ formatPLN(simulation.totalProv) }}</td>
                <td class="px-6 py-4 text-right text-xs text-slate-400">Koszt operacyjny</td>
              </tr>
            </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
