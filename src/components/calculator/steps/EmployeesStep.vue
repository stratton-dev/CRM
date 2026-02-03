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

const filteredEmployees = computed(() => {
  if (!search.value.trim()) return store.pracownicy;
  const q = search.value.toLowerCase();
  return store.pracownicy.filter((item) => item.imie.toLowerCase().includes(q) || item.nazwisko.toLowerCase().includes(q));
});

const addEmployee = () => {
  store.addEmployee();
};

const applyMinWage = (type: 'UOP' | 'UZ') => {
  store.pracownicy = store.pracownicy.map((item) => {
    if (item.typUmowy !== type) return item;
    const nettoZasadnicza = type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto;
    return { ...item, nettoZasadnicza };
  });
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="relative w-full md:w-80">
        <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" type="text" placeholder="Szukaj pracownika..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-lg" />
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="px-4 py-2 text-xs font-bold bg-slate-900 text-white rounded-lg" @click="addEmployee">
          Dodaj pracownika
        </button>
        <button type="button" class="px-4 py-2 text-xs font-bold border border-slate-200 rounded-lg" @click="showImport = true">
          Importuj Excel
        </button>
        <button type="button" class="px-4 py-2 text-xs font-bold border border-slate-200 rounded-lg" @click="applyMinWage('UOP')">
          Ustaw min. UoP
        </button>
        <button type="button" class="px-4 py-2 text-xs font-bold border border-slate-200 rounded-lg" @click="applyMinWage('UZ')">
          Ustaw min. UZ
        </button>
      </div>
    </div>

    <div v-if="store.pracownicy.length === 0" class="bg-white border border-dashed border-slate-300 rounded-2xl p-8 text-center text-slate-400">
      Brak pracowników. Dodaj pierwszą osobę.
    </div>

    <div v-else class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[860px]">
          <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
            <tr>
              <th class="px-4 py-3 text-left">Pracownik</th>
              <th class="px-4 py-3 text-left">Umowa</th>
              <th class="px-4 py-3 text-right">Netto docelowe</th>
              <th class="px-4 py-3 text-right">Netto zasadnicza</th>
              <th class="px-4 py-3 text-right">Akcje</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="emp in filteredEmployees" :key="emp.id">
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <input v-model="emp.imie" type="text" class="border border-slate-200 rounded px-2 py-1 w-24" @input="store.updateEmployee(emp.id, { imie: emp.imie })" />
                  <input v-model="emp.nazwisko" type="text" class="border border-slate-200 rounded px-2 py-1 w-32" @input="store.updateEmployee(emp.id, { nazwisko: emp.nazwisko })" />
                </div>
              </td>
              <td class="px-4 py-3">
                <select
                  v-model="emp.typUmowy"
                  class="border border-slate-200 rounded px-2 py-1"
                  @change="store.updateEmployee(emp.id, { typUmowy: emp.typUmowy, nettoZasadnicza: emp.typUmowy === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto })"
                >
                  <option value="UOP">UOP</option>
                  <option value="UZ">UZ</option>
                </select>
              </td>
              <td class="px-4 py-3 text-right">
                <input v-model.number="emp.nettoDocelowe" type="number" class="border border-slate-200 rounded px-2 py-1 w-28 text-right font-mono" @input="store.updateEmployee(emp.id, { nettoDocelowe: emp.nettoDocelowe })" />
              </td>
              <td class="px-4 py-3 text-right">
                <input v-model.number="emp.nettoZasadnicza" type="number" class="border border-slate-200 rounded px-2 py-1 w-28 text-right font-mono" @input="store.updateEmployee(emp.id, { nettoZasadnicza: emp.nettoZasadnicza })" />
              </td>
              <td class="px-4 py-3 text-right">
                <button type="button" class="text-xs text-slate-500 mr-2" @click="toggleExpand(emp.id)">
                  {{ expandedIds[emp.id] ? 'Zwiń' : 'Szczegóły' }}
                </button>
                <button type="button" class="text-xs text-slate-500 mr-2" @click="store.duplicateEmployee(emp.id)">Duplikuj</button>
                <button type="button" class="text-xs text-rose-500" @click="store.removeEmployee(emp.id)">Usuń</button>
              </td>
            </tr>
            <tr v-if="expandedIds[emp.id]" class="bg-slate-50">
              <td colspan="5" class="px-4 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs text-slate-500">
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

    <ImportModal v-if="showImport" @close="showImport = false" />
  </div>
</template>
