<script setup lang="ts">
import { useCalculatorStore } from '../store/useCalculatorStore';
import { formatPLN } from '../utils/formatters';

const store = useCalculatorStore();
</script>

<template>
  <div class="space-y-4">
    <div v-if="!store.wyniki" class="text-sm text-slate-400">Brak danych do obliczeń.</div>
    <div v-else class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
          <tr>
            <th class="px-4 py-3 text-left">Pracownik</th>
            <th class="px-4 py-3 text-right">Brutto</th>
            <th class="px-4 py-3 text-right">ZUS prac.</th>
            <th class="px-4 py-3 text-right">PIT</th>
            <th class="px-4 py-3 text-right">Koszt pracodawcy</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in store.wyniki.szczegoly" :key="row.pracownik.id">
            <td class="px-4 py-3">{{ row.pracownik.imie }} {{ row.pracownik.nazwisko }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ formatPLN(row.standard.brutto) }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ formatPLN(row.standard.zusPracownik.suma) }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ formatPLN(row.standard.pit) }}</td>
            <td class="px-4 py-3 text-right font-mono font-bold">{{ formatPLN(row.standard.kosztPracodawcy) }}</td>
          </tr>
        </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
