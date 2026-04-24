<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { DEFAULT_CONFIG } from '../tax-engine/constants';

const emit = defineEmits<{ (event: 'close'): void }>();
const store = useCalculatorStore();

const updateConfig = (path: string, value: number) => {
  const newConfig = JSON.parse(JSON.stringify(store.config));
  const keys = path.split('.');
  let current = newConfig;
  for (let i = 0; i < keys.length - 1; i++) current = current[keys[i]];
  current[keys[keys.length - 1]] = value;
  store.config = newConfig;
};
</script>

<template>
  <div class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click="emit('close')">
    <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden" @click.stop>
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="font-bold text-slate-900 text-lg">Parametry kalkulatora</h3>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="emit('close')">
          <AppIcon name="xmark" class="w-5 h-5" />
        </button>
      </div>
      <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Placa minimalna (brutto)</label>
            <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2" :value="store.config.placaMinimalna.brutto" @input="updateConfig('placaMinimalna.brutto', Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Placa minimalna (netto)</label>
            <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2" :value="store.config.placaMinimalna.netto" @input="updateConfig('placaMinimalna.netto', Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Minimalna UZ (netto)</label>
            <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2" :value="store.config.minimalnaKwotaUZ.zasadniczaNetto" @input="updateConfig('minimalnaKwotaUZ.zasadniczaNetto', Number(($event.target as HTMLInputElement).value))" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">PIT prog 1 (%)</label>
            <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2" :value="store.config.pit.prog1Stawka" @input="updateConfig('pit.prog1Stawka', Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">PIT prog 2 (%)</label>
            <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2" :value="store.config.pit.prog2Stawka" @input="updateConfig('pit.prog2Stawka', Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">PIT prog 1 limit</label>
            <input type="number" class="mt-2 w-full border border-slate-200 rounded-lg px-3 py-2" :value="store.config.pit.prog1Limit" @input="updateConfig('pit.prog1Limit', Number(($event.target as HTMLInputElement).value))" />
          </div>
        </div>
      </div>
      <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200">
        <button type="button" class="text-xs font-bold text-slate-500" @click="store.config = DEFAULT_CONFIG">
          Przywroc domyslne
        </button>
        <button type="button" class="px-4 py-2 text-xs font-bold bg-slate-900 text-white rounded-lg" @click="emit('close')">
          Zamknij
        </button>
      </div>
    </div>
  </div>
</template>
