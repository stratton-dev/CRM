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
  await store.updateClientStatus('CALCULATION_SENT');
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
          <label class="inline-flex items-center gap-2 px-3 py-2 text-xs font-black border-2 border-slate-100 rounded-xl cursor-pointer hover:border-stratton-gold hover:bg-stratton-gold/5 transition-all text-slate-600 uppercase tracking-widest">
            <input ref="jsonInput" type="file" accept=".json" class="hidden" @change="handleJsonUpload" />
            <AppIcon name="folder" class="w-4 h-4 text-slate-400" />
            Wczytaj JSON
          </label>
          <button type="button" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-black bg-slate-900 text-white rounded-xl hover:bg-slate-800 transition-all uppercase tracking-widest shadow-sm" @click="handleSync">
            <AppIcon name="refresh" class="w-4 h-4 text-stratton-gold" />
            Synchronizuj
          </button>
          <button type="button" class="ml-2 w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" @click="emit('close')">
            <AppIcon name="xmark" class="w-5 h-5" />
          </button>
        </div>
      </div>
      <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
        <div class="flex flex-col md:flex-row md:items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100 shadow-inner">
          <div class="relative flex-1 group">
            <AppIcon name="magnifying-glass" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 group-focus-within:text-stratton-gold transition-colors" />
            <input v-model="searchQuery" type="text" placeholder="Szukaj po nazwie firmy..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-stratton-gold/10 focus:border-stratton-gold transition-all font-bold text-right" />
          </div>
          <div class="flex gap-2">
            <select v-model="commissionFilter" class="text-xs font-black uppercase tracking-widest border border-slate-200 rounded-xl px-4 py-2.5 bg-white focus:ring-4 focus:ring-stratton-gold/10 focus:border-stratton-gold transition-all outline-none">
              <option value="all">Prowizje</option>
              <option value="standard">Standard (28%)</option>
              <option value="plus">Plus (26%)</option>
            </select>
            <select v-model="savingsFilter" class="text-xs font-black uppercase tracking-widest border border-slate-200 rounded-xl px-4 py-2.5 bg-white focus:ring-4 focus:ring-stratton-gold/10 focus:border-stratton-gold transition-all outline-none">
              <option value="all">Oszczędności</option>
              <option value="gt0">> 0 zł</option>
              <option value="gt50">> 50k zł</option>
              <option value="gt100">> 100k zł</option>
            </select>
          </div>
        </div>

        <div v-if="filteredHistory.length === 0" class="py-12 text-center text-slate-400 font-bold uppercase tracking-widest text-xs italic">
          Brak zapisanych kalkulacji spełniających kryteria.
        </div>
        
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="item in filteredHistory" :key="item.id" class="group bg-white border border-slate-200 rounded-2xl p-5 flex flex-col gap-4 hover:border-stratton-gold hover:shadow-xl hover:shadow-amber-900/5 transition-all relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-stratton-gold/5 rounded-full -mr-12 -mt-12 blur-2xl group-hover:bg-stratton-gold/10 transition-colors"></div>
            
            <div class="flex items-center justify-between relative z-10">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center group-hover:bg-stratton-gold/10 transition-colors">
                  <AppIcon name="building" class="w-5 h-5 text-slate-400 group-hover:text-stratton-gold" />
                </div>
                <div>
                  <div class="font-black text-slate-800 text-sm uppercase tracking-tight">{{ item.nazwaFirmy || 'Bez nazwy' }}</div>
                  <div class="text-[10px] text-slate-400 font-bold">{{ new Date(item.dataUtworzenia).toLocaleDateString('pl-PL') }}</div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-0.5">Rocznie</div>
                <div class="font-black text-stratton-gold text-lg lg:text-xl">{{ formatPLN(item.oszczednoscRoczna) }}</div>
              </div>
            </div>

            <div class="flex items-center gap-4 text-[11px] text-slate-500 font-bold uppercase tracking-widest bg-slate-50 p-2.5 rounded-xl border border-slate-100/50">
              <div class="flex items-center gap-1.5">
                <AppIcon name="users" class="w-3.5 h-3.5 text-slate-300" />
                {{ item.liczbaPracownikow }}
              </div>
              <div class="w-px h-3 bg-slate-200"></div>
              <div class="flex items-center gap-1.5">
                <AppIcon name="percent" class="w-3.5 h-3.5 text-slate-300" />
                {{ item.dane.prowizjaProc === 26 ? 'PLUS (26%)' : 'STANDARD (28%)' }}
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
              <button type="button" class="flex-1 min-w-[80px] px-3 py-2 text-[10px] font-black bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors uppercase tracking-widest shadow-sm" @click="handleLoad(item.id)">
                Wczytaj
              </button>
              <div class="flex gap-1">
                <button type="button" class="w-8 h-8 flex items-center justify-center text-slate-400 border border-slate-100 rounded-lg hover:border-stratton-gold hover:text-stratton-gold transition-all" title="Pobierz JSON" @click="store.downloadCalculation(item)">
                  <AppIcon name="folder-open" class="w-4 h-4" />
                </button>
                <button type="button" class="w-8 h-8 flex items-center justify-center text-slate-400 border border-slate-100 rounded-lg hover:border-emerald-500 hover:text-emerald-500 transition-all" title="Raport Excel" @click="handleExcel(item.id)">
                  <AppIcon name="file-excel" class="w-4 h-4" />
                </button>
                <button type="button" class="w-8 h-8 flex items-center justify-center text-slate-400 border border-slate-100 rounded-lg hover:border-amber-500 hover:text-amber-500 transition-all" title="Raport Szczegółowy" @click="handleDetailedExcel(item.id)">
                  <AppIcon name="table" class="w-4 h-4" />
                </button>
                <button type="button" class="w-8 h-8 flex items-center justify-center text-white bg-stratton-gold rounded-lg hover:brightness-110 shadow-sm transition-all shadow-amber-500/20" title="Generuj PDF" @click="handlePdf(item.id)">
                  <AppIcon name="file-pdf" class="w-4 h-4" />
                </button>
              </div>
              <button type="button" class="w-8 h-8 flex items-center justify-center text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all ml-auto" title="Usuń" @click="store.deleteFromHistory(item.id)">
                <AppIcon name="trash" class="w-4 h-4" />
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
