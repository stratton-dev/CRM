<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import ImportModal from './ImportModal.vue';

const store = useCalculatorStore();
const search = ref('');
const showImport = ref(false);
const expandedIds = ref<Record<number, boolean>>({});

const toggleExpand = (id: number) => {
  expandedIds.value = { ...expandedIds.value, [id]: !expandedIds.value[id] };
};

const toggleFpFgsp = (id: number, disabled: boolean) => {
  store.updateEmployee(id, { skladkaFP: !disabled, skladkaFGSP: !disabled });
};

const showUopMinDropdown = ref(false);
const showUzMinDropdown = ref(false);
const minWageUop = ref(store.config.placaMinimalna.netto);
const minWageUz = ref(store.config.minimalnaKwotaUZ.zasadniczaNetto);
const selectedIds = ref<Set<number>>(new Set());

const filteredEmployees = computed(() => {
  if (!search.value.trim()) return store.pracownicy;
  const q = search.value.toLowerCase();
  return store.pracownicy.filter((item) => item.imie.toLowerCase().includes(q) || item.nazwisko.toLowerCase().includes(q));
});

const isAllSelected = computed(() => {
  return filteredEmployees.value.length > 0 && selectedIds.value.size === filteredEmployees.value.length;
});

const toggleSelection = (id: number) => {
  const newSet = new Set(selectedIds.value);
  if (newSet.has(id)) {
    newSet.delete(id);
  } else {
    newSet.add(id);
  }
  selectedIds.value = newSet;
};

const toggleAllSelection = () => {
  if (isAllSelected.value) {
    selectedIds.value = new Set();
  } else {
    selectedIds.value = new Set(filteredEmployees.value.map(e => e.id));
  }
};

const removeSelected = () => {
  if (selectedIds.value.size === 0) return;
  if (!confirm(`Czy na pewno usunąć zaznaczonych pracowników (${selectedIds.value.size})?`)) return;
  
  const idsToRemove = Array.from(selectedIds.value);
  idsToRemove.forEach(id => store.removeEmployee(id));
  
  selectedIds.value = new Set();
};

const removeSingle = (id: number) => {
  store.removeEmployee(id);
  const newSet = new Set(selectedIds.value);
  if (newSet.has(id)) {
    newSet.delete(id);
    selectedIds.value = newSet;
  }
};

const addEmployee = () => {
  store.addEmployee();
};

const applyMinWage = (type: 'UOP' | 'UZ') => {
  const value = type === 'UOP' ? minWageUop.value : minWageUz.value;
  store.pracownicy = store.pracownicy.map((item) => {
    if (item.typUmowy !== type) return item;
    return { ...item, nettoZasadnicza: value };
  });
  if (type === 'UOP') showUopMinDropdown.value = false;
  else showUzMinDropdown.value = false;
};

// Przełączenie typu umowy (UoP/UZ) przez przyciski zamiast selecta.
const setUmowa = (emp: any, type: 'UOP' | 'UZ') => {
  if (emp.typUmowy === type) return;
  emp.typUmowy = type;
  store.updateEmployee(emp.id, {
    typUmowy: type,
    nettoZasadnicza: type === 'UZ'
      ? store.config.minimalnaKwotaUZ.zasadniczaNetto
      : store.config.placaMinimalna.netto,
  });
};

const uopCount = computed(() => store.pracownicy.filter(e => e.typUmowy === 'UOP').length);
const uzCount = computed(() => store.pracownicy.filter(e => e.typUmowy === 'UZ').length);
</script>

<template>
  <div>
  <div class="space-y-3">

    <!-- D365 Page Header -->
    <div class="mb-2 min-h-[68px]">
      <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">
        <AppIcon name="building" class="w-3 h-3" />
        <span>{{ store.firma.nazwa || 'Firma' }}</span>
        <span class="text-slate-300">/</span>
        <span>Kalkulator</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600">Krok 2 — Pracownicy</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 class="text-xl font-black text-slate-900 tracking-tight">Lista pracowników</h1>
          <p class="text-[11px] text-slate-400 mt-0.5">
            <span class="font-bold text-slate-600">{{ store.pracownicy.length }}</span> pracowników
            <span v-if="uopCount > 0"> · <span class="font-bold text-slate-600">{{ uopCount }}</span> UoP</span>
            <span v-if="uzCount > 0"> · <span class="font-bold text-slate-600">{{ uzCount }}</span> UZ</span>
          </p>
        </div>
      </div>
    </div>

    <!-- Toolbar / Command bar -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
        <!-- Search (lupa po lewej) -->
        <div class="relative">
          <AppIcon name="search" class="w-3 h-3 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Szukaj..."
            class="h-7 pl-9 pr-3 text-xs border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold w-44 transition-colors"
          />
        </div>
        <!-- Command bar actions (tekst + przyciski po prawej) -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <!-- Label + badges -->
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Lista pracowników</span>
          <div class="flex items-center gap-2 mr-1">
            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">
              UoP: {{ uopCount }}
            </span>
            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">
              UZ: {{ uzCount }}
            </span>
          </div>
          <!-- Separator -->
          <div class="h-5 w-px bg-slate-200"></div>
          <!-- Add employee -->
          <button
            type="button"
            class="h-7 px-3 rounded-md bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 active:scale-95 transition-all shadow-[0_2px_8px_-2px_rgba(197,160,89,0.4)]"
            @click="addEmployee"
          >
            + Dodaj
          </button>
          <button
            type="button"
            class="h-7 px-3 rounded-md border border-slate-200 bg-white text-[10px] font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
            @click="showImport = true"
          >
            Importuj Excel
          </button>
          <!-- Min wage UoP dropdown -->
          <div class="relative">
            <button
              type="button"
              class="h-7 px-3 rounded-md border border-slate-200 bg-white text-[10px] font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
              @click="showUopMinDropdown = !showUopMinDropdown"
            >
              Min UoP
            </button>
            <div v-if="showUopMinDropdown" class="absolute right-0 mt-1.5 w-56 bg-white border border-slate-200 rounded-xl shadow-2xl p-3 z-50">
              <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Netto zasadnicza UoP</label>
              <div class="flex gap-2">
                <input v-model.number="minWageUop" type="number" class="flex-1 h-7 border border-slate-200 rounded-md px-2 text-sm font-mono focus:ring-1 focus:ring-stratton-gold outline-none" />
                <button type="button" class="h-7 px-3 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded-md text-[10px] font-black hover:brightness-110" @click="applyMinWage('UOP')">OK</button>
              </div>
            </div>
          </div>
          <!-- Min wage UZ dropdown -->
          <div class="relative">
            <button
              type="button"
              class="h-7 px-3 rounded-md border border-slate-200 bg-white text-[10px] font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
              @click="showUzMinDropdown = !showUzMinDropdown"
            >
              Min UZ
            </button>
            <div v-if="showUzMinDropdown" class="absolute right-0 mt-1.5 w-56 bg-white border border-slate-200 rounded-xl shadow-2xl p-3 z-50">
              <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Netto zasadnicza UZ</label>
              <div class="flex gap-2">
                <input v-model.number="minWageUz" type="number" class="flex-1 h-7 border border-slate-200 rounded-md px-2 text-sm font-mono focus:ring-1 focus:ring-stratton-gold outline-none" />
                <button type="button" class="h-7 px-3 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded-md text-[10px] font-black hover:brightness-110" @click="applyMinWage('UZ')">OK</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk actions bar -->
      <transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition-all duration-150 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-1">
        <div v-if="selectedIds.size > 0" class="flex items-center justify-between px-4 py-2 bg-amber-50 border-b border-amber-200">
          <div class="flex items-center gap-2">
            <span class="bg-white border border-amber-200 text-amber-700 min-w-6 h-6 rounded-full flex items-center justify-center font-black text-[10px] shadow-sm">{{ selectedIds.size }}</span>
            <span class="text-[11px] font-bold text-amber-800">pracowników zaznaczonych</span>
          </div>
          <div class="flex gap-2">
            <button type="button" class="h-6 px-2.5 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 text-[10px] font-bold rounded-md transition-colors flex items-center gap-1.5" @click="removeSelected">
              <AppIcon name="trash" class="w-3 h-3" /> Usuń
            </button>
            <button type="button" class="h-6 px-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-[10px] font-bold rounded-md transition-colors" @click="selectedIds = new Set()">
              Anuluj
            </button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Empty state -->
    <div v-if="store.pracownicy.length === 0" class="bg-white border border-dashed border-slate-300 rounded-xl px-5 py-10 text-center">
      <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Brak pracowników</div>
      <button
        type="button"
        class="h-8 px-4 rounded-md bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white text-[10px] font-black uppercase tracking-widest hover:brightness-110 active:scale-95 transition-all"
        @click="addEmployee"
      >
        + Dodaj pierwszego pracownika
      </button>
    </div>

    <!-- MOBILE: card view -->
    <div v-else class="md:hidden space-y-2">
      <div
        v-for="emp in filteredEmployees"
        :key="emp.id"
        class="bg-white border rounded-xl overflow-hidden transition-colors"
        :class="selectedIds.has(emp.id) ? 'border-stratton-gold/40' : 'border-slate-200'"
      >
        <div class="flex items-center gap-2.5 px-3 py-2.5 border-b border-slate-100">
          <input
            type="checkbox"
            class="rounded border-slate-300 text-stratton-gold focus:ring-amber-400 cursor-pointer w-3.5 h-3.5 shrink-0"
            :checked="selectedIds.has(emp.id)"
            @change="toggleSelection(emp.id)"
          />
          <div class="flex gap-1.5 flex-1 min-w-0">
            <input v-model="emp.imie" type="text" class="h-7 border border-slate-200 focus:border-amber-400 focus:ring-1 focus:ring-amber-100 rounded-md px-2 text-xs flex-1 min-w-0 outline-none" placeholder="Imię" @input="store.updateEmployee(emp.id, { imie: emp.imie })" />
            <input v-model="emp.nazwisko" type="text" class="h-7 border border-slate-200 focus:border-amber-400 focus:ring-1 focus:ring-amber-100 rounded-md px-2 text-xs flex-1 min-w-0 outline-none" placeholder="Nazwisko" @input="store.updateEmployee(emp.id, { nazwisko: emp.nazwisko })" />
          </div>
          <div class="flex gap-1 shrink-0">
            <button type="button" class="w-6 h-6 hover:bg-slate-100 text-slate-400 rounded flex items-center justify-center transition-colors" @click="toggleExpand(emp.id)">
              <AppIcon :name="expandedIds[emp.id] ? 'chevron-up' : 'chevron-down'" class="w-3 h-3" />
            </button>
            <button type="button" class="w-6 h-6 hover:bg-blue-50 text-slate-400 hover:text-blue-600 rounded flex items-center justify-center transition-colors" @click="store.duplicateEmployee(emp.id)">
              <AppIcon name="copy" class="w-3 h-3" />
            </button>
            <button type="button" class="w-6 h-6 hover:bg-rose-50 text-slate-400 hover:text-rose-500 rounded flex items-center justify-center transition-colors" @click="removeSingle(emp.id)">
              <AppIcon name="trash" class="w-3 h-3" />
            </button>
          </div>
        </div>
        <div class="px-3 py-2.5 grid grid-cols-3 gap-2">
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Umowa</label>
            <div class="inline-flex h-7 w-full rounded-md border border-slate-200 overflow-hidden">
              <button type="button" class="flex-1 text-[11px] font-bold transition-colors" :class="emp.typUmowy === 'UOP' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" @click="setUmowa(emp, 'UOP')">UoP</button>
              <button type="button" class="flex-1 text-[11px] font-bold border-l border-slate-200 transition-colors" :class="emp.typUmowy === 'UZ' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" @click="setUmowa(emp, 'UZ')">UZ</button>
            </div>
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Netto docelowe</label>
            <input v-model.number="emp.nettoDocelowe" type="number" class="h-7 border border-slate-200 focus:border-indigo-400 rounded-md px-2 w-full text-right font-mono text-xs outline-none" @input="store.updateEmployee(emp.id, { nettoDocelowe: emp.nettoDocelowe })" />
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Netto zasadnicza</label>
            <input v-model.number="emp.nettoZasadnicza" type="number" class="h-7 border border-slate-200 focus:border-indigo-400 rounded-md px-2 w-full text-right font-mono text-xs outline-none" @input="store.updateEmployee(emp.id, { nettoZasadnicza: emp.nettoZasadnicza })" />
          </div>
        </div>
        <!-- Expanded details (mobile) -->
        <div v-if="expandedIds[emp.id]" class="px-3 py-3 border-t border-slate-100 grid grid-cols-2 gap-4 text-xs text-slate-600 bg-slate-50/60">
          <div>
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Dane osobowe</div>
            <div class="space-y-2">
              <label class="block">
                <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Data ur.</span>
                <input v-model="emp.dataUrodzenia" type="date" class="h-7 border border-slate-200 rounded-md px-2 text-xs w-full" @input="store.updateEmployee(emp.id, { dataUrodzenia: emp.dataUrodzenia })" />
              </label>
              <label class="block">
                <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Płeć</span>
                <select v-model="emp.plec" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { plec: emp.plec })">
                  <option value="M">Mężczyzna</option>
                  <option value="K">Kobieta</option>
                </select>
              </label>
            </div>
          </div>
          <div>
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Podatki</div>
            <div class="space-y-2">
              <label class="block">
                <span class="block text-[9px] uppercase text-slate-400 mb-0.5">PIT-2</span>
                <select v-model="emp.pit2" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { pit2: emp.pit2 })">
                  <option value="300">300 zł</option>
                  <option value="150">150 zł</option>
                  <option value="100">100 zł</option>
                  <option value="0">Brak</option>
                </select>
              </label>
              <label class="flex items-center gap-1.5">
                <input v-model="emp.ulgaMlodych" type="checkbox" class="rounded border-slate-300 w-3 h-3" @change="store.updateEmployee(emp.id, { ulgaMlodych: emp.ulgaMlodych })" />
                <span class="text-[10px]">Ulga młodych</span>
              </label>
            </div>
          </div>
          <div>
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">ZUS</div>
            <div class="space-y-2">
              <select v-model="emp.trybSkladek" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { trybSkladek: emp.trybSkladek })">
                <option value="PELNE">Pełne</option>
                <option value="BEZ_CHOROBOWEJ">Bez chorobowej</option>
                <option value="STUDENT_UZ">Student &lt;26</option>
                <option value="INNY_TYTUL">Inny tytuł</option>
                <option value="EMERYT_RENCISTA">Emeryt</option>
              </select>
              <label class="flex items-center gap-1.5">
                <input :checked="!(emp.skladkaFP && emp.skladkaFGSP)" type="checkbox" class="rounded border-slate-300 w-3 h-3" @change="toggleFpFgsp(emp.id, ($event.target as HTMLInputElement).checked)" />
                <span class="text-[10px]">Zwolnienie FP/FGŚP</span>
              </label>
            </div>
          </div>
          <div>
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">KUP / PIT</div>
            <div class="space-y-2">
              <select v-model="emp.kupTyp" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { kupTyp: emp.kupTyp })">
                <option value="STANDARD">Standard 250 zł</option>
                <option value="PODWYZSZONE">Podwyższone</option>
                <option value="PROC_20">20%</option>
                <option value="PROC_50">50%</option>
              </select>
              <select v-model="emp.pitMode" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { pitMode: emp.pitMode })">
                <option value="AUTO">Auto (progi)</option>
                <option value="FLAT_12">12%</option>
                <option value="FLAT_32">32%</option>
                <option value="FLAT_0">0%</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- DESKTOP: data table -->
    <div v-if="store.pracownicy.length > 0" class="hidden md:block bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
          <thead>
            <tr class="border-b border-slate-100">
              <th class="w-10 px-4 py-2.5 text-center bg-white">
                <input
                  type="checkbox"
                  class="rounded border-slate-300 text-stratton-gold focus:ring-amber-400 cursor-pointer w-3.5 h-3.5"
                  :checked="isAllSelected"
                  @change="toggleAllSelection"
                />
              </th>
              <th class="px-4 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Pracownik</th>
              <th class="px-4 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Umowa</th>
              <th class="px-4 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Netto docelowe</th>
              <th class="px-4 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Netto zasadnicza</th>
              <th class="px-4 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white w-36">Akcje</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="emp in filteredEmployees" :key="emp.id">
              <tr
                class="border-b border-slate-50 transition-colors"
                :class="selectedIds.has(emp.id) ? 'bg-amber-50/40 hover:bg-amber-50/60' : 'hover:bg-blue-50/20'"
              >
                <td class="px-4 py-2.5 text-center">
                  <input
                    type="checkbox"
                    class="rounded border-slate-300 text-stratton-gold focus:ring-amber-400 cursor-pointer w-3.5 h-3.5"
                    :checked="selectedIds.has(emp.id)"
                    @change="toggleSelection(emp.id)"
                  />
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-1.5">
                    <input v-model="emp.imie" type="text" class="h-7 border border-slate-200 focus:border-amber-400 focus:ring-1 focus:ring-amber-100 rounded-md px-2 w-24 text-xs outline-none transition-colors" placeholder="Imię" @input="store.updateEmployee(emp.id, { imie: emp.imie })" />
                    <input v-model="emp.nazwisko" type="text" class="h-7 border border-slate-200 focus:border-amber-400 focus:ring-1 focus:ring-amber-100 rounded-md px-2 w-32 text-xs outline-none transition-colors" placeholder="Nazwisko" @input="store.updateEmployee(emp.id, { nazwisko: emp.nazwisko })" />
                  </div>
                </td>
                <td class="px-4 py-2.5">
                  <div class="inline-flex h-7 rounded-md border border-slate-200 overflow-hidden">
                    <button type="button" class="px-3 text-[11px] font-bold transition-colors" :class="emp.typUmowy === 'UOP' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" @click="setUmowa(emp, 'UOP')">UoP</button>
                    <button type="button" class="px-3 text-[11px] font-bold border-l border-slate-200 transition-colors" :class="emp.typUmowy === 'UZ' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" @click="setUmowa(emp, 'UZ')">UZ</button>
                  </div>
                </td>
                <td class="px-4 py-2.5 text-right">
                  <input v-model.number="emp.nettoDocelowe" type="number" class="h-7 border border-slate-200 focus:border-indigo-400 rounded-md px-2 w-28 text-right font-mono text-xs outline-none transition-colors" @input="store.updateEmployee(emp.id, { nettoDocelowe: emp.nettoDocelowe })" />
                </td>
                <td class="px-4 py-2.5 text-right">
                  <input v-model.number="emp.nettoZasadnicza" type="number" class="h-7 border border-slate-200 focus:border-indigo-400 rounded-md px-2 w-28 text-right font-mono text-xs outline-none transition-colors" @input="store.updateEmployee(emp.id, { nettoZasadnicza: emp.nettoZasadnicza })" />
                </td>
                <td class="px-4 py-2.5 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button type="button" class="w-6 h-6 hover:bg-slate-100 text-slate-400 rounded flex items-center justify-center transition-colors" title="Szczegóły" @click="toggleExpand(emp.id)">
                      <AppIcon :name="expandedIds[emp.id] ? 'chevron-up' : 'chevron-down'" class="w-3 h-3" />
                    </button>
                    <button type="button" class="w-6 h-6 hover:bg-blue-50 text-slate-400 hover:text-blue-600 rounded flex items-center justify-center transition-colors" title="Duplikuj" @click="store.duplicateEmployee(emp.id)">
                      <AppIcon name="copy" class="w-3 h-3" />
                    </button>
                    <button type="button" class="w-6 h-6 hover:bg-rose-50 text-slate-400 hover:text-rose-500 rounded flex items-center justify-center transition-colors" title="Usuń" @click="removeSingle(emp.id)">
                      <AppIcon name="trash" class="w-3 h-3" />
                    </button>
                  </div>
                </td>
              </tr>
              <!-- Expanded row -->
              <tr v-if="expandedIds[emp.id]" class="border-b border-indigo-50">
                <td colspan="6" class="px-5 py-4 bg-slate-50/80">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 text-xs text-slate-600">
                    <div>
                      <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Dane osobowe</div>
                      <div class="space-y-2">
                        <label class="block">
                          <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Data urodzenia</span>
                          <input v-model="emp.dataUrodzenia" type="date" class="h-7 border border-slate-200 rounded-md px-2 text-xs w-full" @input="store.updateEmployee(emp.id, { dataUrodzenia: emp.dataUrodzenia })" />
                        </label>
                        <label class="block">
                          <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Płeć</span>
                          <select v-model="emp.plec" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { plec: emp.plec })">
                            <option value="M">Mężczyzna</option>
                            <option value="K">Kobieta</option>
                          </select>
                        </label>
                      </div>
                    </div>
                    <div>
                      <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Parametry umowy</div>
                      <div class="space-y-2">
                        <label class="block">
                          <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Tryb ZUS</span>
                          <select v-model="emp.trybSkladek" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { trybSkladek: emp.trybSkladek })">
                            <option value="PELNE">Pełne składki</option>
                            <option value="BEZ_CHOROBOWEJ">Bez chorobowej</option>
                            <option value="STUDENT_UZ">Student &lt; 26 lat</option>
                            <option value="INNY_TYTUL">Inny tytuł</option>
                            <option value="EMERYT_RENCISTA">Emeryt/Rencista</option>
                          </select>
                        </label>
                        <label class="block">
                          <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Koszty uzyskania (KUP)</span>
                          <select v-model="emp.kupTyp" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { kupTyp: emp.kupTyp })">
                            <option value="STANDARD">Standardowe (250 zł)</option>
                            <option value="PODWYZSZONE">Podwyższone (300 zł)</option>
                            <option value="PROC_20">Ryczałtowe 20%</option>
                            <option value="PROC_50">Autorskie 50%</option>
                          </select>
                        </label>
                      </div>
                    </div>
                    <div>
                      <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Podatki i ulgi</div>
                      <div class="space-y-2">
                        <label class="block">
                          <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Kwota wolna (PIT-2)</span>
                          <select v-model="emp.pit2" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { pit2: emp.pit2 })">
                            <option value="300">300 zł (1/12)</option>
                            <option value="150">150 zł (1/24)</option>
                            <option value="100">100 zł (1/36)</option>
                            <option value="0">Brak (0 zł)</option>
                          </select>
                        </label>
                        <label class="block">
                          <span class="block text-[9px] uppercase text-slate-400 mb-0.5">Zaliczka PIT</span>
                          <select v-model="emp.pitMode" class="h-7 border border-slate-200 rounded-md px-1.5 text-xs w-full bg-white" @change="store.updateEmployee(emp.id, { pitMode: emp.pitMode })">
                            <option value="AUTO">Automatycznie (progi)</option>
                            <option value="FLAT_12">Liniowo 12%</option>
                            <option value="FLAT_32">Liniowo 32%</option>
                            <option value="FLAT_0">Zwolnienie (0%)</option>
                          </select>
                        </label>
                        <label class="flex items-center gap-1.5">
                          <input v-model="emp.ulgaMlodych" type="checkbox" class="rounded border-slate-300 w-3.5 h-3.5" @change="store.updateEmployee(emp.id, { ulgaMlodych: emp.ulgaMlodych })" />
                          <span class="text-[10px]">Ulga dla młodych (&lt;26)</span>
                        </label>
                      </div>
                    </div>
                    <div>
                      <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Składki dodatkowe</div>
                      <div class="space-y-2">
                        <label class="flex items-center gap-1.5">
                          <input :checked="!(emp.skladkaFP && emp.skladkaFGSP)" type="checkbox" class="rounded border-slate-300 w-3.5 h-3.5" @change="toggleFpFgsp(emp.id, ($event.target as HTMLInputElement).checked)" />
                          <span class="text-[10px]">Zwolnienie FP/FGŚP</span>
                        </label>
                        <label class="flex items-center gap-1.5">
                          <input v-model="emp.skladkaFP" type="checkbox" class="rounded border-slate-300 w-3.5 h-3.5" @change="store.updateEmployee(emp.id, { skladkaFP: emp.skladkaFP })" />
                          <span class="text-[10px]">Składka FP</span>
                        </label>
                        <label class="flex items-center gap-1.5">
                          <input v-model="emp.skladkaFGSP" type="checkbox" class="rounded border-slate-300 w-3.5 h-3.5" @change="store.updateEmployee(emp.id, { skladkaFGSP: emp.skladkaFGSP })" />
                          <span class="text-[10px]">Składka FGŚP</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <Teleport to="body">
    <ImportModal v-if="showImport" @close="showImport = false" />
  </Teleport>
  </div>
</template>
