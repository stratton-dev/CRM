<script setup lang="ts">
import { computed, ref } from 'vue';
import * as XLSX from 'xlsx';
import AppIcon from '@/components/AppIcon.vue';
import { parseExcelData, ImportRow } from '../utils/excelParser';
import { useCalculatorStore } from '../store/useCalculatorStore';


const emit = defineEmits<{ (event: 'close'): void }>();
const store = useCalculatorStore();
const importRows = ref<ImportRow[]>([]);
const fileName = ref('');
const showErrorsOnly = ref(false);

const stats = computed(() => {
  const valid = importRows.value.filter((row) => row.isValid).length;
  const invalid = importRows.value.length - valid;
  return { valid, invalid, total: importRows.value.length };
});

const filteredRows = computed(() => {
  if (!showErrorsOnly.value) return importRows.value;
  return importRows.value.filter((row) => !row.isValid);
});

const handleFile = (file: File) => {
  fileName.value = file.name;
  const reader = new FileReader();
  reader.onload = (evt) => {
    const bstr = evt.target?.result;
    if (!bstr) return;
    const wb = XLSX.read(bstr, { type: 'binary' });
    const wsname = wb.SheetNames[0];
    const ws = wb.Sheets[wsname];
    const data = XLSX.utils.sheet_to_json(ws, { header: 1, range: 0 });
    importRows.value = parseExcelData(data, store.config);
  };
  reader.readAsBinaryString(file);
};

const handleUpload = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (file) handleFile(file);
  input.value = '';
};

const confirmImport = () => {
  const validRows = importRows.value.filter((row) => row.isValid).map((row) => row.data).filter(Boolean);
  if (validRows.length === 0) return;
  store.pracownicy = [...store.pracownicy, ...validRows];
  emit('close');
};
</script>

<template>
  <div class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click="emit('close')">
    <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden" @click.stop>
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div>
          <h3 class="font-bold text-slate-900">Import Excel</h3>
          <p class="text-xs text-slate-400">{{ fileName || 'Wybierz plik .xlsx lub .csv' }}</p>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="emit('close')">
          <AppIcon name="xmark" class="w-5 h-5" />
        </button>
      </div>
      <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
        <label class="inline-flex items-center gap-2 px-4 py-2 border border-slate-200 rounded-lg text-xs font-bold cursor-pointer">
          <input type="file" class="hidden" accept=".xlsx,.csv" @change="handleUpload" />
          <AppIcon name="file-invoice-dollar" class="w-4 h-4" />
          Wybierz plik
        </label>

        <div v-if="importRows.length > 0" class="flex items-center justify-between text-xs text-slate-500">
          <div>Razem: {{ stats.total }} | Poprawne: {{ stats.valid }} | Błędy: {{ stats.invalid }}</div>
          <label class="inline-flex items-center gap-2">
            <input v-model="showErrorsOnly" type="checkbox" />
            Pokaż tylko błędy
          </label>
        </div>

        <div v-if="importRows.length === 0" class="text-sm text-slate-400">
          Brak wczytanych danych.
        </div>

        <div v-else class="border border-slate-200 rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-xs min-w-[520px]">
              <thead class="bg-slate-50 text-slate-400 uppercase font-bold">
                <tr>
                  <th class="px-3 py-2 text-left">Pracownik</th>
                  <th class="px-3 py-2 text-left">Umowa</th>
                  <th class="px-3 py-2 text-right">Netto</th>
                  <th class="px-3 py-2 text-left">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in filteredRows" :key="row.id" :class="row.isValid ? '' : 'bg-rose-50'">
                  <td class="px-3 py-2">{{ row.raw.imie }} {{ row.raw.nazwisko }}</td>
                  <td class="px-3 py-2">{{ row.raw.typUmowy }}</td>
                  <td class="px-3 py-2 text-right">{{ row.raw.netto }}</td>
                  <td class="px-3 py-2">
                    <span v-if="row.isValid" class="text-emerald-600">OK</span>
                    <span v-else class="text-rose-600">{{ row.errors.join(', ') }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200">
        <button type="button" class="text-xs font-bold text-slate-500" @click="store.generateImportTemplate(20)">
          Pobierz szablon
        </button>
        <div class="flex gap-2">
          <button type="button" class="px-4 py-2 text-xs font-bold border border-slate-200 rounded-lg" @click="emit('close')">
            Anuluj
          </button>
          <button type="button" class="px-4 py-2 text-xs font-bold bg-slate-900 text-white rounded-lg" :disabled="stats.valid === 0" @click="confirmImport">
            Importuj
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
