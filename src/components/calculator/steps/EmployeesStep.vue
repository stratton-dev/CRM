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

const uopCount = computed(() => store.pracownicy.filter(e => e.typUmowy === 'UOP').length);
const uzCount = computed(() => store.pracownicy.filter(e => e.typUmowy === 'UZ').length);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
      <div class="flex gap-4">
             <div class="relative w-32 bg-white border border-slate-200 rounded-xl px-3 py-2 flex items-center justify-between shadow-sm">
                <div class="w-full">
                   <div class="text-[8px] uppercase font-bold text-slate-400 tracking-widest mb-0.5">Umowa o Pracę</div>
                   <div class="flex items-baseline gap-1">
                      <span class="text-lg font-bold text-slate-800">{{ uopCount }}</span>
                      <span class="text-[9px] font-medium text-slate-400">os.</span>
                   </div>
                </div>
             </div>
             
             <div class="relative w-32 bg-white border border-slate-200 rounded-xl px-3 py-2 flex items-center justify-between shadow-sm">
                <div class="w-full">
                   <div class="text-[8px] uppercase font-bold text-slate-400 tracking-widest mb-0.5">Umowa Zlecenie</div>
                   <div class="flex items-baseline gap-1">
                      <span class="text-lg font-bold text-slate-800">{{ uzCount }}</span>
                      <span class="text-[9px] font-medium text-slate-400">os.</span>
                   </div>
                </div>
             </div>
      </div>
      
      <div class="flex-1 flex flex-wrap justify-end gap-2 items-center">
        <div class="relative w-full md:w-64 mr-2">
          <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input v-model="search" type="text" placeholder="Szukaj pracownika..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-stratton-gold transition-all shadow-sm text-right font-bold" />
        </div>
        
        <button type="button" class="px-6 py-2.5 text-xs font-extrabold bg-linear-to-r from-[#D4AF37] to-[#C5A059] text-white rounded-xl hover:brightness-110 active:scale-95 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.3)] border border-white/10 uppercase" @click="addEmployee">
          Dodaj pracownika
        </button>
        <button type="button" class="px-6 py-2.5 text-xs font-bold border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 active:scale-95 transition-all shadow-sm uppercase" @click="showImport = true">
          Importuj Excel
        </button>
        
        <!-- UoP Min Dropdown -->
        <div class="relative">
          <button type="button" class="px-5 py-2.5 text-xs font-bold border border-slate-200 text-slate-600 rounded-xl transition-all hover:bg-slate-50 uppercase shadow-sm" @click="showUopMinDropdown = !showUopMinDropdown">
            Ustaw min. UoP
          </button>
          <div v-if="showUopMinDropdown" class="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-2xl shadow-2xl p-4 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Netto zasadnicza wypłata:</label>
            <div class="flex gap-2">
              <input v-model.number="minWageUop" type="number" class="flex-1 border border-slate-200 rounded-lg px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-amber-500 outline-none" />
              <button type="button" class="bg-linear-to-r from-[#D4AF37] to-[#C5A059] text-white px-3 py-1 rounded-lg text-[10px] font-bold hover:brightness-110" @click="applyMinWage('UOP')">OK</button>
            </div>
          </div>
        </div>

        <!-- UZ Min Dropdown -->
        <div class="relative">
          <button type="button" class="px-5 py-2.5 text-xs font-bold border border-slate-200 text-slate-600 rounded-xl transition-all hover:bg-slate-50 uppercase shadow-sm" @click="showUzMinDropdown = !showUzMinDropdown">
            Ustaw min. UZ
          </button>
          <div v-if="showUzMinDropdown" class="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-2xl shadow-2xl p-4 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Netto zasadnicza wypłata:</label>
            <div class="flex gap-2">
              <input v-model.number="minWageUz" type="number" class="flex-1 border border-slate-200 rounded-lg px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-amber-500 outline-none" />
              <button type="button" class="bg-linear-to-r from-[#D4AF37] to-[#C5A059] text-white px-3 py-1 rounded-lg text-[10px] font-bold hover:brightness-110" @click="applyMinWage('UZ')">OK</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="store.pracownicy.length === 0" class="bg-white border border-dashed border-slate-300 rounded-2xl p-8 text-center text-slate-400">
      Brak pracowników. Dodaj pierwszą osobę.
    </div>

    <div v-else>
      <!-- Bulk Actions Bar -->
      <transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="selectedIds.size > 0" class="flex items-center justify-between p-3 bg-amber-50 border border-amber-200 rounded-xl mb-4 text-amber-900 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="bg-white border border-amber-200 text-amber-700 min-w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shadow-sm">
              {{ selectedIds.size }}
            </div>
            <span class="font-bold text-sm">Wybrano pracowników</span>
          </div>
          <div class="flex gap-2">
            <button type="button" class="px-3 py-1.5 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 hover:border-rose-300 text-xs font-bold rounded-lg transition-colors flex items-center gap-2 shadow-sm" @click="removeSelected">
              <AppIcon name="trash" class="w-3.5 h-3.5" /> Usuń zaznaczonych
            </button>
            <button type="button" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-lg transition-colors shadow-sm" @click="selectedIds = new Set()">
              Anuluj
            </button>
          </div>
        </div>
      </transition>

      <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[900px]">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold sticky top-0 z-10 shadow-sm">
              <tr>
                <th class="w-12 px-4 py-3 text-center bg-slate-50">
                  <input 
                    type="checkbox" 
                    class="rounded border-slate-300 text-stratton-gold focus:ring-amber-500 cursor-pointer w-4 h-4 transition-all"
                    :checked="isAllSelected"
                    @change="toggleAllSelection"
                  />
                </th>
                <th class="px-4 py-3 text-left bg-slate-50">Pracownik</th>
                <th class="px-4 py-3 text-left bg-slate-50">Umowa</th>
                <th class="px-4 py-3 text-right bg-slate-50">Netto docelowe</th>
                <th class="px-4 py-3 text-right bg-slate-50">Netto zasadnicza wypłata</th>
                <th class="px-4 py-3 text-right bg-slate-50 w-48">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-for="emp in filteredEmployees" :key="emp.id">
              <tr 
                class="group transition-colors duration-150"
                :class="selectedIds.has(emp.id) ? 'bg-amber-50/50 hover:bg-amber-50' : 'hover:bg-slate-50'"
              >
                <td class="px-4 py-3 text-center">
                  <input 
                    type="checkbox" 
                    class="rounded border-slate-300 text-stratton-gold focus:ring-amber-500 cursor-pointer w-4 h-4 transition-all"
                    :checked="selectedIds.has(emp.id)"
                    @change="toggleSelection(emp.id)"
                  />
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <input v-model="emp.imie" type="text" class="border border-slate-200 focus:border-amber-400 focus:ring-2 focus:ring-amber-100 rounded px-2 py-1.5 w-28 text-sm transition-all outline-none" placeholder="Imię" @input="store.updateEmployee(emp.id, { imie: emp.imie })" />
                    <input v-model="emp.nazwisko" type="text" class="border border-slate-200 focus:border-amber-400 focus:ring-2 focus:ring-amber-100 rounded px-2 py-1.5 w-36 text-sm transition-all outline-none" placeholder="Nazwisko" @input="store.updateEmployee(emp.id, { nazwisko: emp.nazwisko })" />
                  </div>
                </td>
                <td class="px-4 py-3">
                  <select
                    v-model="emp.typUmowy"
                    class="border border-slate-200 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 rounded px-2 py-1.5 text-sm transition-all outline-none bg-white cursor-pointer"
                    @change="store.updateEmployee(emp.id, { typUmowy: emp.typUmowy, nettoZasadnicza: emp.typUmowy === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto })"
                  >
                    <option value="UOP">UOP (Praca)</option>
                    <option value="UZ">UZ (Zlecenie)</option>
                  </select>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="relative">
                    <input v-model.number="emp.nettoDocelowe" type="number" class="border border-slate-200 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 rounded px-2 py-1.5 w-28 text-right font-mono text-sm transition-all outline-none" @input="store.updateEmployee(emp.id, { nettoDocelowe: emp.nettoDocelowe })" />

                  </div>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="relative">
                    <input v-model.number="emp.nettoZasadnicza" type="number" class="border border-slate-200 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 rounded px-2 py-1.5 w-28 text-right font-mono text-sm transition-all outline-none" @input="store.updateEmployee(emp.id, { nettoZasadnicza: emp.nettoZasadnicza })" />

                  </div>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                    <button type="button" class="p-1.5 hover:bg-slate-100 text-slate-500 rounded-lg transition-colors" title="Szczegóły" @click="toggleExpand(emp.id)">
                      <AppIcon :name="expandedIds[emp.id] ? 'chevron-up' : 'chevron-down'" class="w-4 h-4" />
                    </button>
                    <button type="button" class="p-1.5 hover:bg-blue-50 text-slate-500 hover:text-blue-600 rounded-lg transition-colors" title="Duplikuj" @click="store.duplicateEmployee(emp.id)">
                      <AppIcon name="copy" class="w-4 h-4" />
                    </button>
                    <button type="button" class="p-1.5 hover:bg-rose-50 text-slate-400 hover:text-rose-500 rounded-lg transition-colors" title="Usuń" @click="removeSingle(emp.id)">
                      <AppIcon name="trash" class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="expandedIds[emp.id]" class="bg-indigo-50/30 border-b border-indigo-100 shadow-inner">
                <td colspan="6" class="px-6 py-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 text-xs text-slate-600">
                  <div>
                    <div class="font-bold text-slate-700 mb-1">Dane osobowe</div>
                    <div class="space-y-2">
                      <label class="block">
                        <span class="block text-[11px] uppercase">Data urodzenia</span>
                        <input v-model="emp.dataUrodzenia" type="date" class="border border-slate-200 rounded px-2 py-1 w-full" @input="store.updateEmployee(emp.id, { dataUrodzenia: emp.dataUrodzenia })" />
                      </label>
                      <label class="block">
                        <span class="block text-[11px] uppercase">Płeć</span>
                        <select v-model="emp.plec" class="border border-slate-200 rounded px-2 py-1 w-full" @change="store.updateEmployee(emp.id, { plec: emp.plec })">
                          <option value="M">Mężczyzna</option>
                          <option value="K">Kobieta</option>
                        </select>
                      </label>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold text-slate-700 mb-1">Parametry umowy</div>
                    <div class="space-y-2">
                      <label class="block">
                        <span class="block text-[11px] uppercase">Tryb ZUS</span>
                        <select v-model="emp.trybSkladek" class="border border-slate-200 rounded px-2 py-1 w-full" @change="store.updateEmployee(emp.id, { trybSkladek: emp.trybSkladek })">
                          <option value="PELNE">Pełne składki</option>
                          <option value="BEZ_CHOROBOWEJ">Bez chorobowej</option>
                          <option value="STUDENT_UZ">Student &lt; 26 lat</option>
                          <option value="INNY_TYTUL">Inny tytuł (tylko zdrowotna)</option>
                          <option value="EMERYT_RENCISTA">Emeryt/Rencista</option>
                        </select>
                      </label>
                      <label class="block">
                        <span class="block text-[11px] uppercase">Koszty uzyskania (KUP)</span>
                        <select v-model="emp.kupTyp" class="border border-slate-200 rounded px-2 py-1 w-full" @change="store.updateEmployee(emp.id, { kupTyp: emp.kupTyp })">
                          <option value="STANDARD">Standardowe (250 zł)</option>
                          <option value="PODWYZSZONE">Podwyższone (300 zł)</option>
                          <option value="PROC_20">Ryczałtowe 20%</option>
                          <option value="PROC_50">Autorskie 50%</option>
                        </select>
                      </label>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold text-slate-700 mb-1">Podatki i ulgi</div>
                    <div class="space-y-2">
                      <label class="block">
                        <span class="block text-[11px] uppercase">Kwota wolna (PIT-2)</span>
                        <select v-model="emp.pit2" class="border border-slate-200 rounded px-2 py-1 w-full" @change="store.updateEmployee(emp.id, { pit2: emp.pit2 })">
                          <option value="300">300 zł (1/12)</option>
                          <option value="150">150 zł (1/24)</option>
                          <option value="100">100 zł (1/36)</option>
                          <option value="0">Brak (0 zł)</option>
                        </select>
                      </label>
                      <label class="block">
                        <span class="block text-[11px] uppercase">Zaliczka PIT</span>
                        <select v-model="emp.pitMode" class="border border-slate-200 rounded px-2 py-1 w-full" @change="store.updateEmployee(emp.id, { pitMode: emp.pitMode })">
                          <option value="AUTO">Automatycznie (progi)</option>
                          <option value="FLAT_12">Liniowo 12%</option>
                          <option value="FLAT_32">Liniowo 32%</option>
                          <option value="FLAT_0">Zwolnienie (0%)</option>
                        </select>
                      </label>
                      <label class="flex items-center gap-2">
                        <input v-model="emp.ulgaMlodych" type="checkbox" class="rounded border-slate-300" @change="store.updateEmployee(emp.id, { ulgaMlodych: emp.ulgaMlodych })" />
                        <span>Ulga dla młodych (&lt;26)</span>
                      </label>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold text-slate-700 mb-1">Składki dodatkowe</div>
                    <div class="space-y-2">
                      <label class="flex items-center gap-2">
                        <input :checked="!(emp.skladkaFP && emp.skladkaFGSP)" type="checkbox" class="rounded border-slate-300" @change="toggleFpFgsp(emp.id, ($event.target as HTMLInputElement).checked)" />
                        <span>Zwolnienie FP/FGŚP</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input v-model="emp.skladkaFP" type="checkbox" class="rounded border-slate-300" @change="store.updateEmployee(emp.id, { skladkaFP: emp.skladkaFP })" />
                        <span>Składka FP</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input v-model="emp.skladkaFGSP" type="checkbox" class="rounded border-slate-300" @change="store.updateEmployee(emp.id, { skladkaFGSP: emp.skladkaFGSP })" />
                        <span>Składka FGŚP</span>
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
    </div> <!-- End of v-else block -->

    <ImportModal v-if="showImport" @close="showImport = false" />
  </div>
</template>
