<script setup lang="ts">
import { computed, ref } from 'vue';
import ExcelJS from 'exceljs';
import AppIcon from '@/components/AppIcon.vue';
import { parseExcelData, ImportRow } from '../utils/excelParser';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { obliczWiek, czyZwolnionyZFpFgsp } from '../utils/dates';


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
  if (!showErrorsOnly.value) {
    return importRows.value.map((row, idx) => ({ row, originalIdx: idx }));
  }
  return importRows.value.map((row, idx) => ({ row, originalIdx: idx })).filter((item) => !item.row.isValid);
});

const handleFile = async (file: File) => {
  fileName.value = file.name;
  
  try {
    const arrayBuffer = await file.arrayBuffer();
    const workbook = new ExcelJS.Workbook();
    await workbook.xlsx.load(arrayBuffer);
    
    const worksheet = workbook.worksheets[0];
    const data: any[][] = [];
    
    // ExcelJS rows are 1-based
    worksheet.eachRow({ includeEmpty: true }, (row, _rowNumber) => {
      // row.values is 1-based (index 0 is undefined/null usually), so we slice(1)
      // to get a 0-based array for our parser
      const rowValues = Array.isArray(row.values) ? row.values.slice(1) : [];
      data.push(rowValues);
    });

    importRows.value = parseExcelData(data, store.config);
  } catch (error) {
    console.error('Error reading excel file:', error);
    // You might want to show an error message to the user here
  }
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

const zusOptions = [
  { label: 'Pełne składki', value: 'PELNE' },
  { label: 'Bez chorobowej', value: 'BEZ_CHOROBOWEJ' },
  { label: 'Student < 26', value: 'STUDENT_UZ' },
  { label: 'Inny tytuł (zdrowotna)', value: 'INNY_TYTUL' },
  { label: 'Emeryt/Rencista', value: 'EMERYT_RENCISTA' },
];

const kupOptions = [
  { label: 'Standardowe', value: 'STANDARD' },
  { label: 'Podwyższone', value: 'PODWYZSZONE' },
  { label: 'Ryczałtowe 20%', value: 'PROC_20' },
  { label: 'Autorskie 50%', value: 'PROC_50' },
];

const pitModes = [
  { label: 'AUTO', value: 'AUTO' },
  { label: '12%', value: 'FLAT_12' },
  { label: '32%', value: 'FLAT_32' },
  { label: '0%', value: 'FLAT_0' },
];

const updateRow = (rowIndex: number, patch: Record<string, any>) => {
  importRows.value = importRows.value.map((row, idx) => {
    if (idx !== rowIndex) return row;
    const data = { ...row.data, ...patch };
    const raw = { ...(row.raw || {}) };

    if (patch.dataUrodzenia) {
      raw.wiek = obliczWiek(patch.dataUrodzenia);
    }

    if (data.ulgaMlodych) {
      data.pitMode = 'FLAT_0';
      data.pit2 = '0';
    }

    const isExemptByAge = czyZwolnionyZFpFgsp(data.dataUrodzenia, data.plec, store.config);
    let skladkaFP = !isExemptByAge;
    let skladkaFGSP = !isExemptByAge;

    if (data.trybSkladek === 'STUDENT_UZ' || data.trybSkladek === 'INNY_TYTUL' || data.trybSkladek === 'EMERYT_RENCISTA') {
      skladkaFP = false;
      skladkaFGSP = false;
    }

    data.skladkaFP = skladkaFP;
    data.skladkaFGSP = skladkaFGSP;

    const errors: string[] = [];
    if (!data.imie) errors.push('Brak imienia');
    if (!data.nazwisko) errors.push('Brak nazwiska');
    if (!data.nettoDocelowe || data.nettoDocelowe <= 0) errors.push('Netto <= 0');

    return {
      ...row,
      data,
      raw,
      errors,
      isValid: errors.length === 0,
    };
  });
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
            <table class="w-full text-xs min-w-[1180px]">
              <thead class="bg-slate-50 text-slate-400 uppercase font-bold">
                <tr>
                  <th class="px-3 py-2 text-left">Pracownik</th>
                  <th class="px-3 py-2 text-left">Data ur.</th>
                  <th class="px-3 py-2 text-left">Płeć</th>
                  <th class="px-3 py-2 text-left">Umowa</th>
                  <th class="px-3 py-2 text-left">ZUS</th>
                  <th class="px-3 py-2 text-left">KUP</th>
                  <th class="px-3 py-2 text-left">PIT-2</th>
                  <th class="px-3 py-2 text-left">PIT</th>
                  <th class="px-3 py-2 text-left">Ulga &lt;26</th>
                  <th class="px-3 py-2 text-right">Netto</th>
                  <th class="px-3 py-2 text-left">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="item in filteredRows" :key="item.row.id" :class="item.row.isValid ? '' : 'bg-rose-50'">
                  <td class="px-3 py-2">{{ item.row.raw.imie }} {{ item.row.raw.nazwisko }}</td>
                  <td class="px-3 py-2">
                    <input
                      type="date"
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.dataUrodzenia"
                      @input="updateRow(item.originalIdx, { dataUrodzenia: ($event.target as HTMLInputElement).value })"
                    />
                  </td>
                  <td class="px-3 py-2">
                    <select
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.plec"
                      @change="updateRow(item.originalIdx, { plec: ($event.target as HTMLSelectElement).value })"
                    >
                      <option value="M">M</option>
                      <option value="K">K</option>
                    </select>
                  </td>
                  <td class="px-3 py-2">
                    <select
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.typUmowy"
                      @change="updateRow(item.originalIdx, { typUmowy: ($event.target as HTMLSelectElement).value })"
                    >
                      <option value="UOP">UOP</option>
                      <option value="UZ">UZ</option>
                    </select>
                  </td>
                  <td class="px-3 py-2">
                    <select
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.trybSkladek"
                      @change="updateRow(item.originalIdx, { trybSkladek: ($event.target as HTMLSelectElement).value })"
                    >
                      <option v-for="opt in zusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </td>
                  <td class="px-3 py-2">
                    <select
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.kupTyp"
                      @change="updateRow(item.originalIdx, { kupTyp: ($event.target as HTMLSelectElement).value })"
                    >
                      <option v-for="opt in kupOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </td>
                  <td class="px-3 py-2">
                    <select
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.pit2"
                      @change="updateRow(item.originalIdx, { pit2: ($event.target as HTMLSelectElement).value })"
                    >
                      <option value="300">300</option>
                      <option value="150">150</option>
                      <option value="100">100</option>
                      <option value="0">0</option>
                    </select>
                  </td>
                  <td class="px-3 py-2">
                    <select
                      class="border border-slate-200 rounded px-2 py-1 text-xs"
                      :value="item.row.data.pitMode"
                      @change="updateRow(item.originalIdx, { pitMode: ($event.target as HTMLSelectElement).value })"
                    >
                      <option v-for="opt in pitModes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <input
                      type="checkbox"
                      class="rounded border-slate-300"
                      :checked="item.row.data.ulgaMlodych"
                      @change="updateRow(item.originalIdx, { ulgaMlodych: ($event.target as HTMLInputElement).checked })"
                    />
                  </td>
                  <td class="px-3 py-2 text-right">
                    <input
                      type="number"
                      class="border border-slate-200 rounded px-2 py-1 w-24 text-right text-xs"
                      :value="item.row.data.nettoDocelowe"
                      @input="updateRow(item.originalIdx, { nettoDocelowe: Number(($event.target as HTMLInputElement).value) })"
                    />
                  </td>
                  <td class="px-3 py-2">
                    <span v-if="item.row.isValid" class="text-emerald-600">OK</span>
                    <span v-else class="text-rose-600">{{ item.row.errors.join(', ') }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200">
        <button type="button" class="text-xs font-extrabold text-slate-400 hover:text-slate-600 transition-colors uppercase tracking-widest" @click="store.generateImportTemplate(20)">
          Pobierz szablon
        </button>
        <div class="flex gap-2">
          <button type="button" class="px-6 py-2.5 text-xs font-bold border border-slate-200 text-slate-500 rounded-xl hover:bg-slate-50 transition-all active:scale-95 shadow-sm uppercase" @click="emit('close')">
            Anuluj
          </button>
          <button type="button" class="px-8 py-2.5 text-xs font-extrabold bg-linear-to-r from-[#D4AF37] to-[#C5A059] text-white rounded-xl disabled:opacity-50 hover:brightness-110 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.3)] border border-white/10 active:scale-95 uppercase tracking-widest" :disabled="stats.valid === 0" @click="confirmImport">
            Importuj
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
