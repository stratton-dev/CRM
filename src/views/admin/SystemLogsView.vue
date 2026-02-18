<script setup lang="ts">
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuditLogStore } from '@/stores/auditLog'

const auditLogStore = useAuditLogStore()
const { logs: auditLogs } = storeToRefs(auditLogStore)

onMounted(() => {
  auditLogStore.fetchLogs()
})
</script>

<template>
  <div class="space-y-6 animate-fade-in view-transition">
    <div class="flex items-center justify-between mb-4">
       <h1 class="text-2xl font-bold text-slate-900">Logi Systemowe</h1>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden animate-fade-in border border-slate-200">
      <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
        <h3 class="font-bold text-slate-700">Rejestr Zdarzeń (Audit Log)</h3>
      </div>
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Data</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Użytkownik (ID)</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Akcja</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Szczegóły</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-slate-50 transition">
            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">{{ new Date(log.date).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-xs font-bold text-slate-700">{{ log.actorId }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-xs text-indigo-600 font-medium">{{ log.action }}</td>
            <td class="px-6 py-4 text-xs text-slate-600">{{ log.details }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
