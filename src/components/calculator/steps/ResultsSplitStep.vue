<script setup lang="ts">
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';
import AppIcon from '@/components/AppIcon.vue';

const store = useCalculatorStore();
</script>

<template>
  <div class="space-y-3">

    <!-- D365 Page Header -->
    <div class="mb-2">
      <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1.5">
        <AppIcon name="building" class="w-3 h-3" />
        <span>{{ store.firma?.nazwa || 'Firma' }}</span>
        <span class="text-slate-300">/</span>
        <span>Kalkulator</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600">Krok 4 — Podział wynagrodzenia</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <h1 class="text-xl font-black text-slate-900 tracking-tight">Wynagrodzenie Eliton Prime™</h1>
          <p class="text-[11px] text-slate-400 mt-0.5">Podział wynagrodzenia na przelew bankowy + odkup voucherów cyfrowych</p>
        </div>
        <div v-if="store.wyniki" class="flex items-center gap-2">
          <div class="h-8 px-3 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md">
            <span class="text-[9px] font-black uppercase tracking-widest text-amber-600">Nowy koszt</span>
            <span class="text-sm font-black text-amber-700 tabular-nums">{{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.podzial.kosztPracodawcy, 0)) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!store.wyniki" class="bg-white border border-slate-200 rounded-xl px-5 py-8 text-center text-xs text-slate-400 uppercase tracking-widest">
      Brak danych — oblicz wyniki
    </div>
    <div v-else class="space-y-3">

      <!-- Info callout — D365 style info bar -->
      <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl">
        <div class="mt-0.5 w-4 h-4 rounded-full bg-amber-400 text-white flex items-center justify-center shrink-0 text-[9px] font-black">i</div>
        <p class="text-[10px] leading-relaxed text-amber-800 font-semibold uppercase tracking-wide">
          Pracownik otrzymuje 2 przelewy tego samego dnia na swoje konto: 
          <strong class="font-black">Przelew nr 1</strong> od pracodawcy (wynagrodzenie za pracę) 
          oraz <strong class="font-black">Przelew nr 2</strong> od Stratton Prime (odkup niewykorzystanych voucherów cyfrowych)
        </p>
      </div>

      <!-- Data grid -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <!-- Section header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/60">
          <div class="flex items-center gap-3">
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Podział wynagrodzenia</span>
            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">
              {{ store.wyniki.szczegoly.length }} pracowników
            </span>
          </div>
          <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Model Eliton Prime™</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[680px]">
            <thead>
              <tr class="border-b border-slate-100">
                <th class="px-5 py-2.5 text-left text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Pracownik</th>
                <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-blue-600 bg-blue-50/40 border-x border-blue-100/60">Przelew od pracodawcy</th>
                <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-orange-600 bg-orange-50/40 border-r border-orange-100/60">Odkup voucherów</th>
                <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-slate-400 bg-white">Koszt pracodawcy</th>
                <th class="px-5 py-2.5 text-right text-[9px] font-black uppercase tracking-widest text-emerald-600 bg-emerald-50/40">Oszczędność*</th>
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
                <td class="px-5 py-2.5 text-right text-xs font-mono text-blue-700 bg-blue-50/20 border-x border-blue-50">{{ formatPLN(row.podzial.zasadnicza.nettoGotowka) }}</td>
                <td class="px-5 py-2.5 text-right text-xs font-mono text-orange-700 bg-orange-50/20 border-r border-orange-50">{{ formatPLN(row.podzial.swiadczenie.netto) }}</td>
                <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-slate-900">{{ formatPLN(row.podzial.kosztPracodawcy) }}</td>
                <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-emerald-600 bg-emerald-50/30">{{ formatPLN(row.oszczednosc) }}</td>
              </tr>
            </tbody>
            <!-- Totals footer -->
            <tfoot>
              <tr class="border-t-2 border-slate-200 bg-slate-50">
                <td class="px-5 py-2.5 text-[9px] font-black uppercase tracking-widest text-slate-500">Suma</td>
                <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-blue-800 bg-blue-50/30 border-x border-blue-100/40">
                  {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.podzial.zasadnicza.nettoGotowka, 0)) }}
                </td>
                <td class="px-5 py-2.5 text-right text-xs font-mono font-bold text-orange-800 bg-orange-50/30 border-r border-orange-100/40">
                  {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.podzial.swiadczenie.netto, 0)) }}
                </td>
                <td class="px-5 py-2.5 text-right text-xs font-mono font-black text-slate-900 bg-slate-100">
                  {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.podzial.kosztPracodawcy, 0)) }}
                </td>
                <td class="px-5 py-2.5 text-right text-xs font-mono font-black text-emerald-700 bg-emerald-50/50">
                  {{ formatPLN(store.wyniki.szczegoly.reduce((s, r) => s + r.oszczednosc, 0)) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="text-right text-[9px] text-slate-400 font-semibold uppercase tracking-widest">* przed opłatą serwisową Stratton Prime</div>
    </div>
  </div>
</template>
