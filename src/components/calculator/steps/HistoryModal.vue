<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatPLN } from '../utils/formatters';
import { useCalculatorStore } from '../store/useCalculatorStore';

const emit = defineEmits<{ (event: 'close'): void }>();
const store = useCalculatorStore();

const sortedHistory = computed(() => {
  return [...store.historia].sort((a, b) => new Date(b.dataUtworzenia).getTime() - new Date(a.dataUtworzenia).getTime());
});

const searchQuery = ref('');
const savingsFilter = ref<'all' | 'gt0' | 'gt50' | 'gt100'>('all');
const commissionFilter = ref<'all' | 'standard' | 'plus'>('all');

const filteredHistory = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  return sortedHistory.value.filter((item) => {
    if (query) {
      const name = (item.nazwaFirmy || '').toLowerCase();
      if (!name.includes(query)) return false;
    }
    if (commissionFilter.value !== 'all') {
      const prov = Number(item.dane?.prowizjaProc || 0);
      if (commissionFilter.value === 'standard' && prov !== 28) return false;
      if (commissionFilter.value === 'plus' && prov !== 26) return false;
    }
    if (savingsFilter.value !== 'all') {
      const savings = Number(item.oszczednoscRoczna || 0);
      if (savingsFilter.value === 'gt0' && savings <= 0) return false;
      if (savingsFilter.value === 'gt50' && savings < 50000) return false;
      if (savingsFilter.value === 'gt100' && savings < 100000) return false;
    }
    return true;
  });
});

const jsonInput = ref<HTMLInputElement | null>(null);
const showLoadConfirm = ref(false);
const pendingLoadId = ref<string | null>(null);

const handleLoad = (id: string) => {
  pendingLoadId.value = id;
  showLoadConfirm.value = true;
};

const confirmLoad = () => {
  if (!pendingLoadId.value) return;
  const item = store.historia.find((entry) => entry.id === pendingLoadId.value);
  if (!item) return;
  store.loadFromHistory(item, true);
  showLoadConfirm.value = false;
  pendingLoadId.value = null;
  emit('close');
};

const cancelLoad = () => {
  showLoadConfirm.value = false;
  pendingLoadId.value = null;
};

const handleExcel = async (id: string) => {
  const item = store.historia.find((entry) => entry.id === id);
  if (!item) return;
  await store.generateExcelReport(item);
};

const handleDetailedExcel = async (id: string) => {
  const item = store.historia.find((entry) => entry.id === id);
  if (!item) return;
  await store.generateDetailedExcelReport(item);
};

const handlePdf = async (id: string) => {
  const item = store.historia.find((entry) => entry.id === id);
  if (!item) return;
  store.generateOfferPdf(item);
  await store.updateMeetingOfferStatus('sent');
};

const handleJsonUpload = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const json = JSON.parse(String(reader.result || ''));
      const success = store.loadBackup(json);
      if (success) emit('close');
    } catch (error) {
      console.error(error);
      alert('Błąd odczytu pliku JSON.');
    }
  };
  reader.readAsText(file);
  input.value = '';
};

const handleSync = async () => {
  await store.syncHistoryToApiByNip();
};
</script>

<template>
  <div class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click="emit('close')">
    <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden" @click.stop>
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="font-bold text-slate-900 text-lg">Baza ofert</h3>
        <div class="flex items-center gap-2">
          <label class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold border border-slate-200 rounded-lg cursor-pointer hover:border-slate-400">
            <input ref="jsonInput" type="file" accept=".json" class="hidden" @change="handleJsonUpload" />
            <AppIcon name="folder" class="w-4 h-4 text-slate-500" />
            Wczytaj JSON
          </label>
          <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold border border-indigo-200 text-indigo-700 rounded-lg hover:bg-indigo-50" @click="handleSync">
            <AppIcon name="refresh" class="w-4 h-4" />
            Synchronizuj z CRM
          </button>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="emit('close')">
            <AppIcon name="xmark" class="w-5 h-5" />
          </button>
        </div>
      </div>
      <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
        <div class="flex flex-col md:flex-row md:items-center gap-3">
          <div class="relative flex-1">
            <AppIcon name="magnifying-glass" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="searchQuery" type="text" placeholder="Szukaj po nazwie firmy..." class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-slate-400 focus:border-slate-400" />
          </div>
          <select v-model="commissionFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-2">
            <option value="all">Wszystkie prowizje</option>
            <option value="standard">Standard (28%)</option>
            <option value="plus">Plus (26%)</option>
          </select>
          <select v-model="savingsFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-2">
            <option value="all">Wszystkie oszczędności</option>
            <option value="gt0">Powyżej 0 zł</option>
            <option value="gt50">Powyżej 50 000 zł</option>
            <option value="gt100">Powyżej 100 000 zł</option>
          </select>
        </div>
        <div v-if="filteredHistory.length === 0" class="text-sm text-slate-400">
          Brak zapisanych kalkulacji.
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="item in filteredHistory" :key="item.id" class="border border-slate-200 rounded-xl p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between">
              <div class="font-semibold text-slate-800">{{ item.nazwaFirmy || 'Bez nazwy' }}</div>
              <div class="text-xs text-slate-400">{{ new Date(item.dataUtworzenia).toLocaleDateString('pl-PL') }}</div>
            </div>
            <div class="text-xs text-slate-500">
              Pracownicy: {{ item.liczbaPracownikow }} - Prowizja: {{ item.dane.prowizjaProc }}%
            </div>
            <div class="font-bold text-emerald-600">{{ formatPLN(item.oszczednoscRoczna) }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" class="px-2 py-1 text-[11px] font-bold bg-slate-900 text-white rounded-lg" @click="handleLoad(item.id)">
                Wczytaj
              </button>
              <button type="button" class="px-2 py-1 text-[11px] font-bold border border-slate-200 rounded-lg" @click="store.downloadCalculation(item)">
                Pobierz JSON
              </button>
              <button type="button" class="px-2 py-1 text-[11px] font-bold border border-slate-200 rounded-lg" @click="handleExcel(item.id)">
                Excel
              </button>
              <button type="button" class="px-2 py-1 text-[11px] font-bold border border-slate-200 rounded-lg" @click="handleDetailedExcel(item.id)">
                Excel szczegółowy
              </button>
              <button type="button" class="px-2 py-1 text-[11px] font-bold border border-slate-200 rounded-lg" @click="handlePdf(item.id)">
                PDF oferty
              </button>
              <button type="button" class="px-2 py-1 text-[11px] font-bold text-rose-600 border border-rose-200 rounded-lg ml-auto" @click="store.deleteFromHistory(item.id)">
                Usun
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showLoadConfirm" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click="cancelLoad">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h3 class="font-bold text-slate-900 text-lg">Wczytać ofertę?</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="cancelLoad">
            <AppIcon name="xmark" class="w-5 h-5" />
          </button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-slate-600">
            Bieżące niezapisane zmiany zostaną utracone. Czy na pewno chcesz wczytać tę ofertę?
          </p>
          <div class="flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2 text-sm font-semibold border border-slate-200 rounded-lg" @click="cancelLoad">
              Anuluj
            </button>
            <button type="button" class="px-4 py-2 text-sm font-semibold bg-slate-900 text-white rounded-lg" @click="confirmLoad">
              Wczytaj
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
