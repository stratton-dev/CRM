<script setup lang="ts">
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';
import AppIcon from '@/components/AppIcon.vue';

const store = useCalculatorStore();
</script>

<template>
  <div class="space-y-4">

    <!-- D365 Page Header -->
    <div class="mb-5">
      <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-2">
        <AppIcon name="building" class="w-3 h-3" />
        <span>{{ store.firma?.nazwa || 'Firma' }}</span>
        <span class="text-slate-300">/</span>
        <span>Kalkulator</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600">Krok 3 — Aktualny koszt zatrudnienia</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-black text-slate-900 tracking-tight">Aktualny koszt zatrudnienia</h1>
          <p class="text-[11px] text-slate-400 mt-0.5">Zestawienie kosztów w modelu Standard · przed wdrożeniem Eliton Prime™</p>
        </div>
        <div v-if="store.wyniki" class="flex items-center gap-2">
          <div class="h-8 px-3 flex items-center gap-2 bg-white border border-slate-200 rounded-md">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Suma kosztów</span>
            <span class="text-sm font-black text-slate-900 tabular-nums">{{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.standard.kosztPracodawcy, 0)) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!store.wyniki" class="bg-white border border-slate-200 rounded-xl px-5 py-8 text-center text-xs text-slate-400 uppercase tracking-widest">
      Brak danych — oblicz wyniki
    </div>
    <div v-else class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <!-- Section header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/60">
        <div class="flex items-center gap-3">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Wynagrodzenia Standard</span>
          <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">
            {{ store.wyniki.szczegoly.length }} pracowników
          </span>
        </div>
        <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Bez modelu Eliton Prime</span>
      </div>
      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
          <thead>
            <tr class="border-b border-slate-100">
              <th class="px-5 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Pracownik</th>
              <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Brutto</th>
              <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">ZUS prac.</th>
              <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">PIT</th>
              <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-500 bg-slate-50/80">Koszt pracodawcy</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in store.wyniki.szczegoly"
              :key="row.pracownik.id"
              class="border-b border-slate-50 hover:bg-blue-50/20 transition-colors"
            >
              <td class="px-5 py-2.5 text-xs font-semibold text-slate-800">
                <div class="flex items-center gap-2">
                  <span class="w-1 h-4 rounded-full bg-slate-200 shrink-0"></span>
                  {{ row.pracownik.imie }} {{ row.pracownik.nazwisko }}
                </div>
              </td>
              <td class="px-5 py-2.5 text-right text-xs font-mono text-slate-700">{{ formatPLN(row.standard.brutto) }}</td>
              <td class="px-5 py-2.5 text-right text-xs font-mono text-slate-700">{{ formatPLN(row.standard.zusPracownik.suma) }}</td>
              <td class="px-5 py-2.5 text-right text-xs font-mono text-slate-700">{{ formatPLN(row.standard.pit) }}</td>
              <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-slate-900 bg-slate-50/60">{{ formatPLN(row.standard.kosztPracodawcy) }}</td>
            </tr>
          </tbody>
          <!-- Totals footer -->
          <tfoot>
            <tr class="border-t-2 border-slate-200 bg-slate-50">
              <td class="px-5 py-2.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Suma</td>
              <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-slate-800">
                {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.standard.brutto, 0)) }}
              </td>
              <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-slate-800">
                {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.standard.zusPracownik.suma, 0)) }}
              </td>
              <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-slate-800">
                {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.standard.pit, 0)) }}
              </td>
              <td class="px-5 py-2.5 text-right text-xs font-mono font-black text-slate-900 bg-slate-100">
                {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.standard.kosztPracodawcy, 0)) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
