<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import HistoryModal from './HistoryModal.vue';

const emit = defineEmits<{ (event: 'start'): void }>();

const store = useCalculatorStore();
const showHistory = ref(false);
const showNewCalcModal = ref(false);

const handleNew = () => {
  if (store.pracownicy.length > 0) {
    showNewCalcModal.value = true;
    return;
  }
  store.resetSession();
  emit('start');
};

const handleJsonUpload = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const json = JSON.parse(String(reader.result || ''));
      const success = store.loadBackup(json, true);
      if (success) emit('start');
    } catch (error) {
      console.error(error);
      alert('Błąd odczytu pliku JSON.');
    }
  };
  reader.readAsText(file);
  input.value = '';
};

const startFresh = () => {
  store.resetSession();
  showNewCalcModal.value = false;
  emit('start');
};

const saveAndStart = async () => {
  await store.saveCalculationToApi();
  store.saveToHistory();
  startFresh();
};

const closeNewCalcModal = () => {
  showNewCalcModal.value = false;
};
</script>

<template>
  <div class="space-y-6">
    <div class="bg-slate-900 text-white rounded-3xl p-8 flex flex-col gap-4 shadow-2xl">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-white/10 rounded-xl">
          <AppIcon name="shield-check" class="w-8 h-8" />
        </div>
        <div>
          <div class="text-xs uppercase tracking-widest text-slate-300">Stratton Prime</div>
          <h2 class="text-2xl font-bold">Kalkulator symulacji oszczędności</h2>
        </div>
      </div>
      <p class="text-sm text-slate-300 max-w-2xl">
        Rozpocznij nową kalkulację lub wczytaj zapisaną bazę. Dane z kalkulacji synchronizujemy z CRM.
      </p>
      <div class="flex flex-wrap gap-3">
        <button type="button" class="bg-white text-slate-900 px-6 py-3 rounded-xl font-bold" @click="handleNew">
          Nowa kalkulacja
        </button>
        <button type="button" class="bg-slate-800 text-white px-6 py-3 rounded-xl font-bold border border-slate-700" @click="showHistory = true">
          Baza ofert
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <label class="bg-white rounded-2xl border border-slate-200 p-5 flex items-center gap-4 cursor-pointer hover:border-slate-400 transition">
        <input type="file" accept=".json" class="hidden" @change="handleJsonUpload" />
        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
          <AppIcon name="folder" class="w-5 h-5" />
        </div>
        <div>
          <div class="font-bold text-slate-800">Wczytaj JSON</div>
          <div class="text-xs text-slate-400">Import kopii zapasowej</div>
        </div>
      </label>

      <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
          <AppIcon name="users" class="w-5 h-5" />
        </div>
        <div>
          <div class="font-bold text-slate-800">{{ store.pracownicy.length }} pracowników</div>
          <div class="text-xs text-slate-400">W aktywnej sesji</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
          <AppIcon name="chart-line" class="w-5 h-5" />
        </div>
        <div>
          <div class="font-bold text-slate-800">{{ store.historia.length }} zapisów</div>
          <div class="text-xs text-slate-400">W bazie ofert</div>
        </div>
      </div>
    </div>

    <HistoryModal v-if="showHistory" @close="showHistory = false" />
    <div v-if="showNewCalcModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click="closeNewCalcModal">
      <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h3 class="font-bold text-slate-900 text-lg">Nowa kalkulacja</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeNewCalcModal">
            <AppIcon name="xmark" class="w-5 h-5" />
          </button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-slate-600">
            Masz niezapisane zmiany w bieżącej kalkulacji. Możesz zapisać postęp albo rozpocząć nową bez zapisu.
          </p>
          <div class="grid grid-cols-1 gap-3">
            <button type="button" class="w-full bg-slate-900 text-white px-4 py-3 rounded-xl font-bold" @click="saveAndStart">
              Zapisz postęp i rozpocznij nową
            </button>
            <button type="button" class="w-full border border-slate-200 text-slate-700 px-4 py-3 rounded-xl font-bold hover:border-slate-400" @click="closeNewCalcModal">
              Wróć do obecnej kalkulacji
            </button>
            <button type="button" class="w-full border border-amber-200 text-amber-700 px-4 py-3 rounded-xl font-bold hover:bg-amber-50" @click="startFresh">
              Rozpocznij nową bez zapisu
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
