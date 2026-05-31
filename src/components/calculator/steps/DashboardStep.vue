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
  <div class="space-y-3">

    <!-- D365 Page Header — matches CompanyStep layout -->
    <div class="mb-2 min-h-[68px]">
      <!-- breadcrumb -->
      <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">
        <AppIcon name="shield-check" class="w-3 h-3" />
        <span>Stratton Prime</span>
        <span class="text-slate-300">/</span>
        <span>Kalkulator</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600">Pulpit</span>
      </div>
      <!-- title + command bar -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 class="text-xl font-black text-slate-900 tracking-tight">Kalkulator Eliton Prime™</h1>
          <p class="text-[11px] text-slate-400 mt-0.5">Symulacja oszczędności w kosztach zatrudnienia · Stratton Prime</p>
        </div>
        <div class="flex items-center gap-1.5 flex-wrap">
          <button
            type="button"
            class="h-8 px-4 rounded-md bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white text-xs font-black uppercase tracking-widest border border-white/20 hover:brightness-110 active:scale-95 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.35)] flex items-center gap-1.5"
            @click="handleNew"
          >
            <AppIcon name="plus" class="w-3 h-3" />
            Nowa kalkulacja
          </button>
          <button
            type="button"
            class="h-8 px-3 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95 flex items-center gap-1.5"
            @click="showHistory = true"
          >
            <AppIcon name="clock" class="w-3.5 h-3.5 text-slate-400" />
            Baza ofert
          </button>
        </div>
      </div>
    </div>

    <!-- KPI + info row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

      <!-- Import JSON -->
      <label class="bg-white border border-slate-200 rounded-xl overflow-hidden cursor-pointer group hover:border-stratton-gold/60 hover:shadow-sm transition-all">
        <input type="file" accept=".json" class="hidden" @change="handleJsonUpload" />
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Import</span>
        </div>
        <div class="flex items-center gap-3 px-4 py-4">
          <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-amber-50 group-hover:text-stratton-gold transition-colors">
            <AppIcon name="folder" class="w-4 h-4" />
          </div>
          <div>
            <div class="text-xs font-bold text-slate-800">Wczytaj JSON</div>
            <div class="text-[10px] text-slate-400">Kopia zapasowa kalkulacji</div>
          </div>
        </div>
      </label>

      <!-- Employee count -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Aktualna sesja</span>
        </div>
        <div class="flex items-center justify-between px-4 py-4">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
              <AppIcon name="users" class="w-4 h-4" />
            </div>
            <div class="text-[10px] text-slate-500 font-medium">Pracownicy</div>
          </div>
          <div class="text-xl font-black text-slate-900">{{ store.pracownicy.length }}</div>
        </div>
      </div>

      <!-- History count -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/60">
          <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Baza ofert</span>
        </div>
        <div class="flex items-center justify-between px-4 py-4">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
              <AppIcon name="chart-line" class="w-4 h-4" />
            </div>
            <div class="text-[10px] text-slate-500 font-medium">Zapisanych kalkulacji</div>
          </div>
          <div class="text-xl font-black text-slate-900">{{ store.historia.length }}</div>
        </div>
      </div>
    </div>

    <!-- Quick start info -->
    <div class="bg-white border border-slate-200 rounded-xl px-5 py-4">
      <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3">Jak zacząć</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="flex items-start gap-3">
          <div class="w-5 h-5 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">1</div>
          <div>
            <div class="text-[10px] font-bold text-slate-700">Wybierz klienta</div>
            <div class="text-[10px] text-slate-400">Wskaż firmę z CRM lub wprowadź dane ręcznie</div>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <div class="w-5 h-5 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">2</div>
          <div>
            <div class="text-[10px] font-bold text-slate-700">Dodaj pracowników</div>
            <div class="text-[10px] text-slate-400">Importuj z Excela lub wprowadź ręcznie</div>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">3</div>
          <div>
            <div class="text-[10px] font-bold text-slate-700">Generuj ofertę</div>
            <div class="text-[10px] text-slate-400">Symulacja + raport PDF + wysyłka do klienta</div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Modals -->
  <HistoryModal v-if="showHistory" @close="showHistory = false" />

  <!-- New Calc Confirmation Modal -->
  <div v-if="showNewCalcModal" class="fixed inset-0 bg-black/50 z-50 flex items-end md:items-center p-0 md:p-4" @click="closeNewCalcModal">
    <div class="bg-white rounded-t-xl md:rounded-xl w-full md:max-w-md shadow-2xl overflow-hidden border border-slate-200" @click.stop>
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-800">Nowa kalkulacja</h3>
        <button type="button" class="text-slate-400 hover:text-slate-700 transition-colors" @click="closeNewCalcModal">
          <AppIcon name="xmark" class="w-4 h-4" />
        </button>
      </div>
      <!-- Body -->
      <div class="px-5 py-5">
        <p class="text-[11px] text-slate-600 mb-4">
          Masz niezapisane dane w bieżącej kalkulacji. Wybierz jak chcesz postąpić:
        </p>
        <div class="space-y-2">
          <button
            type="button"
            class="w-full h-9 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded-md text-[10px] font-black uppercase tracking-widest hover:brightness-110 active:scale-95 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.3)]"
            @click="saveAndStart"
          >
            Zapisz postęp i zacznij nową
          </button>
          <button type="button" class="w-full h-9 border border-slate-200 text-slate-600 rounded-md text-[10px] font-bold uppercase tracking-widest hover:bg-slate-50 transition-colors" @click="closeNewCalcModal">
            Wróć do obecnej kalkulacji
          </button>
          <button type="button" class="w-full h-9 border border-rose-200 text-rose-600 rounded-md text-[10px] font-bold uppercase tracking-widest hover:bg-rose-50 transition-colors" @click="startFresh">
            Zacznij nową bez zapisu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
