<script setup lang="ts">
import { computed } from 'vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';

const store = useCalculatorStore();

const summary = computed(() => store.wyniki?.podsumowanie || null);

const setMode = (mode: 'STANDARD' | 'PRIME') => {
  store.comparisonState.activeCard = mode;
  store.prowizjaProc = mode === 'STANDARD' ? store.comparisonState.customStandardRate : store.comparisonState.customPrimeRate;
};

const updateRate = (mode: 'STANDARD' | 'PRIME', value: number) => {
  if (mode === 'STANDARD') store.comparisonState.customStandardRate = value;
  else store.comparisonState.customPrimeRate = value;
  if (store.comparisonState.activeCard === mode) store.prowizjaProc = value;
};
</script>

<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <button type="button" class="border rounded-2xl p-6 text-left" :class="store.comparisonState.activeCard === 'STANDARD' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'" @click="setMode('STANDARD')">
        <div class="text-xs uppercase tracking-widest">Standard</div>
        <div class="text-2xl font-bold">Prowizja {{ store.comparisonState.customStandardRate }}%</div>
      </button>
      <button type="button" class="border rounded-2xl p-6 text-left" :class="store.comparisonState.activeCard === 'PRIME' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'" @click="setMode('PRIME')">
        <div class="text-xs uppercase tracking-widest">Prime</div>
        <div class="text-2xl font-bold">Prowizja {{ store.comparisonState.customPrimeRate }}%</div>
      </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Prowizja Standard</label>
        <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3 font-mono" :value="store.comparisonState.customStandardRate" @input="updateRate('STANDARD', Number(($event.target as HTMLInputElement).value))" />
      </div>
      <div>
        <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Prowizja Prime</label>
        <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3 font-mono" :value="store.comparisonState.customPrimeRate" @input="updateRate('PRIME', Number(($event.target as HTMLInputElement).value))" />
      </div>
    </div>

    <div v-if="summary" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Oszczędność netto</div>
        <div class="text-xl font-bold text-emerald-600">{{ formatPLN(summary.oszczednoscNetto) }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Oszczędność roczna</div>
        <div class="text-xl font-bold text-slate-900">{{ formatPLN(summary.oszczednoscRoczna) }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase tracking-widest">Prowizja</div>
        <div class="text-xl font-bold text-slate-900">{{ formatPLN(summary.prowizja) }}</div>
      </div>
    </div>
  </div>
</template>
