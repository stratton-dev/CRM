<script setup lang="ts">
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';

const store = useCalculatorStore();
</script>

<template>
  <div class="space-y-4">
    <div v-if="!store.wyniki" class="text-sm text-slate-400">Brak danych do obliczeń.</div>
    <div v-else class="space-y-6">
      <!-- Info Box requested by user -->
      <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-[10px] leading-relaxed text-red-600 font-bold uppercase text-center">
          W TYM KROKU POKAZUJESZ JAK DZIELIMY WYNAGRODZENIE W MODELU ELITON PRIME, PAMIĘTAJ ZE PRACOWNIK OTRZYMUJE 
          2 PRZELEWY TEGO SAMEGO DNIA NA SWOJE PRYWATNE KONTO BANKOWE: PRZELEW NR1 OD PRACODAWCY TYTUŁEM WYNAGRODZENIA 
          ZA PRACĘ ORAZ PRZELEW NR 2 OD STRATTON PRIME TYTUŁEM ODKUPU NIEWYKORZYSTANYCH VOUCHERÓW CYFROWYCH
        </p>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[640px]">
          <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
            <tr>
              <th class="px-4 py-3 text-left">Pracownik</th>
              <th class="px-4 py-3 text-center border-x border-slate-200 bg-blue-50/50 text-blue-700">PRZELEW OD PRACODAWCY</th>
              <th class="px-4 py-3 text-center border-r border-slate-200 bg-orange-50/50 text-orange-700">PRZELEW Z TYTUŁU<br/>ODKUPU VOUCHERÓW</th>
              <th class="px-4 py-3 text-right">Koszt pracodawcy</th>
              <th class="px-4 py-3 text-right">Całkowita wygenerowana oszczędność*</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in store.wyniki.szczegoly" :key="row.pracownik.id">
              <td class="px-4 py-3">{{ row.pracownik.imie }} {{ row.pracownik.nazwisko }}</td>
              <td class="px-4 py-3 text-right font-mono border-x border-slate-100">{{ formatPLN(row.podzial.zasadnicza.nettoGotowka) }}</td>
              <td class="px-4 py-3 text-right font-mono border-r border-slate-100">{{ formatPLN(row.podzial.swiadczenie.netto) }}</td>
              <td class="px-4 py-3 text-right font-mono font-bold">{{ formatPLN(row.podzial.kosztPracodawcy) }}</td>
              <td class="px-4 py-3 text-right font-mono text-emerald-600">{{ formatPLN(row.oszczednosc) }}</td>
            </tr>
          </tbody>
          </table>
        </div>
      </div>
      <div class="text-right mt-2 text-xs text-slate-400 font-medium">* przed opłatą serwisową</div>
    </div>
  </div>
</template>
