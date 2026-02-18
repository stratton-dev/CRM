<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
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
const router = useRouter();
const route = useRoute();

const empCount = ref(props.initialEmployees);
// Initialize two separate salary refs
const avgSalaryUop = ref(props.initialAvgWage);
const avgSalaryUz = ref(props.initialAvgWage);
const salaryMode = ref<'NETTO' | 'BRUTTO'>(props.initialSalaryMode);

const countUopInput = ref(props.initialContractType === 'UZ' ? 0 : (props.initialContractType === 'MIXED' ? Math.floor(props.initialEmployees / 2) : props.initialEmployees));
const countUzInput = ref(props.initialContractType === 'UOP' ? 0 : (props.initialContractType === 'MIXED' ? Math.ceil(props.initialEmployees / 2) : props.initialEmployees));

const strategy = ref<StrategyType>('SAVINGS');

const handleBack = () => {
  if (route.query.source === 'process') {
    const clientId = route.query.clientId
    const meetingId = route.query.meetingId
    router.push({
      path: '/app/sales/start',
      query: { clientId, meetingId, step: 4 }
    })
  } else {
    router.back()
  }
}

const isCountValid = computed(() => {
  return (countUopInput.value + countUzInput.value) <= empCount.value;
});

const simulation = computed(() => {
  const countUOP = countUopInput.value;
  const countUZ = countUzInput.value;

  const calculateOne = (type: 'UOP' | 'UZ') => {
    // Select the correct base wage
    let baseNetto = type === 'UOP' ? avgSalaryUop.value : avgSalaryUz.value;
    
    // Apply Brutto conversion based on which wage is being processed
    if (salaryMode.value === 'BRUTTO') {
      baseNetto = type === 'UOP' ? baseNetto * 0.71 : baseNetto * 0.78;
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
    const provision = opt.swiadczenie.netto * (provPercent / 100);

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

  const createEmp = (type: 'UOP' | 'UZ', i: number): Pracownik => {
    // Determine wage based on type
    const sourceWage = type === 'UOP' ? avgSalaryUop.value : avgSalaryUz.value;
    
    // Calculate netto target based on current mode
    let target = sourceWage;
    if (salaryMode.value === 'NETTO') {
      target = sourceWage;
    } else {
      // Brutto mode conversion
      target = type === 'UOP' ? sourceWage * 0.71 : sourceWage * 0.78;
    }
    
    return {
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
      nettoDocelowe: target,
      nettoZasadnicza: type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto,
      pitMode: 'AUTO',
      skladkaFP: true,
      skladkaFGSP: true,
    };
  };

  for (let i = 0; i < genUOP; i++) newEmployees.push(createEmp('UOP', i));
  for (let i = 0; i < genUZ; i++) newEmployees.push(createEmp('UZ', genUOP + i));

  store.prowizjaProc = strategy.value === 'SAVINGS' ? 28 : 26;
  store.pracownicy = newEmployees;
  emit('transfer');
};
</script>

<template>
  <div class="animate-fade-in">
    <div class="max-w-screen-2xl mx-auto space-y-8">
      
      <!-- Top Header Area: Results & Controls (Full Width) -->
      <div class="bg-slate-900 rounded-3xl shadow-xl border border-slate-800 p-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 pb-8 border-b border-slate-800 gap-6">
            <!-- Left: Back Button + Title -->
            <div class="flex items-center gap-6 self-start md:self-center">
                <button type="button" class="inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-xl text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group" @click="handleBack">
                    <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
                </button>
                <div>
                    <h2 class="font-serif font-bold text-3xl text-white tracking-tight">Wyniki Symulacji</h2>
                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">Podsumowanie Oszczędności</p>
                </div>
            </div>

            <!-- Right: Next Button -->
            <button 
              type="button" 
              class="flex items-center gap-3 bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl shadow-lg shadow-indigo-500/30 transition-all font-bold group disabled:opacity-50 disabled:grayscale self-end md:self-center"
              :disabled="!isCountValid"
              @click="handleTransfer"
            >
              <span class="text-sm">Dalej</span>
              <AppIcon name="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
            </button>
        </div>

        <!-- Content: Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Card 1: Monthly Savings (Dark Mode) -->
            <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 shadow-sm relative overflow-hidden group hover:bg-slate-750 transition-colors">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Oszczędność miesięczna</div>
                        <div class="text-sm text-slate-500 font-medium mt-1">
                            {{ strategy === 'WIN_WIN' ? 'Po wypłaceniu podwyżek' : 'Netto dla firmy' }}
                        </div>
                    </div>
                    <div class="p-2 bg-emerald-500/10 text-emerald-500 rounded-lg">
                        <AppIcon name="arrow-trending-up" class="w-6 h-6" />
                    </div>
                </div>
                <div class="text-4xl lg:text-5xl font-extrabold text-white tracking-tight">
                    {{ formatPLN(simulation.monthlySavings) }}
                </div>
                 <div v-if="strategy === 'WIN_WIN'" class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-500/20 text-blue-400 text-xs font-bold border border-blue-500/30">
                    <AppIcon name="users" class="w-3 h-3" />
                     + Zadowoleni pracownicy
                 </div>
            </div>

            <!-- Card 2: Yearly Potential (Dark Mode) -->
            <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 shadow-sm text-white relative overflow-hidden group hover:bg-slate-750 transition-colors">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-500 rounded-full blur-[80px] opacity-10 group-hover:opacity-20 transition-opacity"></div>

                <div class="flex justify-between items-start mb-4 relative z-10">
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Potencjał roczny</div>
                        <div class="text-sm text-slate-500 font-medium mt-1">Skumulowana oszczędność</div>
                    </div>
                    <div class="p-2 bg-slate-700 text-white rounded-lg">
                         <AppIcon name="chart-pie" class="w-6 h-6" />
                    </div>
                </div>
                <div class="text-4xl lg:text-5xl font-extrabold text-white tracking-tight relative z-10">
                    {{ formatPLN(simulation.yearlySavings) }}
                </div>
                <div class="mt-4 h-1.5 w-full bg-slate-700 rounded-full overflow-hidden relative z-10">
                    <div class="h-full bg-gradient-to-r from-emerald-500 to-blue-500 w-[70%] animate-pulse"></div>
                </div>
            </div>
        </div>
      </div>

      <!-- Main Content Grid: Structure (Left) vs Comparison (Right) side-by-side -->
      <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
        
        <!-- Left Panel: Structure -->
        <div class="xl:col-span-4 bg-slate-900 text-white flex flex-col shrink-0 border border-slate-800 rounded-2xl shadow-xl">
          <div class="p-6 pb-2">
            <div class="flex items-center gap-2 mb-3 text-emerald-400">
              <div class="p-1.5 bg-emerald-500/10 rounded-lg">
                <AppIcon name="bolt" class="w-5 h-5" />
              </div>
              <span class="font-bold uppercase tracking-widest text-xs">Szybka Symulacja v2.8</span>
            </div>
            <h2 class="text-2xl font-bold text-white leading-tight">Struktura zatrudnienia</h2>
            <p class="text-slate-400 text-sm mt-2 leading-relaxed">Skonfiguruj strukturę zatrudnienia i wybierz model wynagradzania</p>
          </div>

          <div class="p-6 space-y-6">
            <div class="space-y-4">
              <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                <AppIcon name="users" class="w-4 h-4" />
                pracownicy zatrudnieni ogółem:
              </label>
              <div class="relative group">
                <input v-model.number="empCount" type="number" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2.5 px-4 text-white font-bold text-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all group-hover:border-slate-600" />
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                  <AppIcon name="filter" class="w-4 h-4" />
                  Struktura Umów
                </label>
                <div class="flex items-center bg-slate-800 rounded-lg p-1 border border-slate-700">
                  <button 
                    type="button" 
                    class="px-2 py-0.5 text-[10px] font-bold rounded transition-colors" 
                    :class="salaryMode === 'NETTO' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'" 
                    @click="salaryMode = 'NETTO'"
                  >
                    netto
                  </button>
                  <button 
                    type="button" 
                    class="px-2 py-0.5 text-[10px] font-bold rounded transition-colors" 
                    :class="salaryMode === 'BRUTTO' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'" 
                    @click="salaryMode = 'BRUTTO'"
                  >
                    brutto
                  </button>
                </div>
              </div>
              <div v-if="!isCountValid" class="text-[10px] text-red-500 font-bold bg-red-500/10 px-2 py-0.5 rounded animate-pulse text-center">
                Suma zatrudnionych przekracza zadeklarowane zatrudnienie
              </div>

              <div class="grid grid-cols-2 gap-4">
                <!-- Column UoP -->
                <div class="space-y-2">
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-bold uppercase">Umowa o Pracę</label>
                    <input 
                      v-model.number="countUopInput" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800 border rounded-xl py-2 px-3 text-white font-bold focus:ring-1 outline-none transition-all"
                      :class="!isCountValid ? 'border-red-500 focus:ring-red-500' : 'border-slate-700 focus:ring-blue-500'"
                    />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-bold uppercase">Średnia płaca UoP</label>
                    <input 
                      v-model.number="avgSalaryUop" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2 px-3 text-white font-bold focus:ring-1 focus:ring-emerald-500 outline-none transition-all"
                    />
                  </div>
                </div>

                <!-- Column UZ -->
                <div class="space-y-2">
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-bold uppercase">Umowa Zlecenie</label>
                    <input 
                      v-model.number="countUzInput" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800 border rounded-xl py-2 px-3 text-white font-bold focus:ring-1 outline-none transition-all"
                      :class="!isCountValid ? 'border-red-500 focus:ring-red-500' : 'border-slate-700 focus:ring-amber-500'" 
                    />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-bold uppercase">Średnia płaca UZ</label>
                    <input 
                      v-model.number="avgSalaryUz" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2 px-3 text-white font-bold focus:ring-1 focus:ring-emerald-500 outline-none transition-all"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <label class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                <AppIcon name="layers" class="w-4 h-4" />
                wybierz model dla klienta
              </label>
              <div class="grid grid-cols-1 gap-3">
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
                    <div class="text-sm font-bold text-white">Eliton Prime<sup class="text-[8px] ml-0.5">TM</sup></div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Wszystkie oszczędności dla firmy. Prowizja 28%.</div>
                  </div>
                </button>

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
                    <div class="text-sm font-bold text-white">Eliton Prime PLUS<sup class="text-[8px] ml-0.5">TM</sup></div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Oszczędność + podwyżki. Prowizja 26%.</div>
                  </div>
                </button>
              </div>
            </div>
          </div>

          <div class="p-6">
            <button 
              type="button" 
              class="w-full h-12 bg-indigo-600 hover:bg-indigo-700 text-white font-bold uppercase tracking-wide rounded-xl shadow-lg shadow-indigo-500/30 transition-all flex items-center justify-center gap-3 group disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:shadow-none" 
              :disabled="!isCountValid"
              @click="handleTransfer"
            >
              <span>Przejdź do szczegółów</span>
              <AppIcon name="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
            </button>
          </div>
        </div>

        <!-- Right Panel: Comparisons & Tables -->
        <div class="xl:col-span-8 space-y-8">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                <AppIcon name="coins" class="text-emerald-500" />
                Porównanie kosztów zatrudnienia
              </h3>
              <div class="text-xs font-medium text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                UoP: {{ countUopInput }} / UZ: {{ countUzInput }}
              </div>
            </div>

            <div class="space-y-6">
              <div>
                <div class="w-full h-6 flex justify-center items-center mb-1">
                  <span class="text-xs font-bold text-red-500 uppercase tracking-wide">{{ formatPLN(simulation.totalStd) }}</span>
                </div>
                <div class="h-10 w-full bg-slate-100 rounded-lg overflow-hidden flex relative">
                  <div class="h-full bg-slate-400 flex items-center justify-center text-white text-xs font-bold w-full uppercase">DO TEJ PORY</div>
                </div>
              </div>

              <div>
                <div class="relative h-6 mb-1">
                  <span class="absolute left-0 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-500 uppercase tracking-wide">Eliton Prime<sup class="text-[8px] ml-0.5">TM</sup></span>
                  <div class="absolute left-0 h-full flex justify-center items-center transition-all duration-700" :style="{ width: `${costRatio}%` }">
                    <span class="text-emerald-600 font-extrabold text-xs uppercase tracking-wide whitespace-nowrap">{{ formatPLN(simulation.totalNew) }}</span>
                  </div>
                </div>
                <div class="h-10 w-full bg-slate-100 rounded-lg overflow-hidden flex relative">
                  <div
                    class="h-full bg-gradient-to-r from-blue-600 to-blue-500 flex items-center justify-center text-white text-xs font-bold shadow-[0_0_15px_rgba(37,99,235,0.3)] z-10 transition-all duration-700"
                    :style="{ width: `${costRatio}%` }"
                  >
                    PO WPROWADZENIU MODELU
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
                  <th class="px-6 py-4 text-right">DO TEJ PORY</th>
                  <th class="px-6 py-4 text-right">Eliton Prime<sup class="text-[8px] ml-0.5">TM</sup></th>
                  <th class="px-6 py-4 text-right text-emerald-600">OSZCZĘDNOŚCI</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr class="hover:bg-slate-50/50">
                  <td class="px-6 py-4 font-medium text-slate-700">Całkowity koszt zatrudnienia</td>
                  <td class="px-6 py-4 text-right text-red-500 font-bold">{{ formatPLN(simulation.totalStd) }}</td>
                  <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatPLN(simulation.totalNew) }}</td>
                  <td class="px-6 py-4 text-right font-bold text-emerald-600 bg-emerald-50/30">+{{ formatPLN(simulation.savings) }}</td>
                </tr>
                <tr class="hover:bg-slate-50/50">
                  <td class="px-6 py-4 text-slate-600 pl-10">
                    Opłata serwisowa
                    <div class="text-[10px] text-slate-400">Success fee</div>
                  </td>
                  <td class="px-6 py-4 text-right text-slate-300">-</td>
                  <td class="px-6 py-4 text-right font-medium text-amber-600">{{ formatPLN(simulation.totalProv) }}</td>
                  <td class="px-6 py-4 text-right text-xs text-slate-400"></td>
                </tr>
              </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
