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
  <div class="space-y-3 md:space-y-6 animate-fade-in view-transition">
    <div class="flex items-center justify-between mb-2 md:mb-4">
       <h1 class="text-xl md:text-2xl font-bold text-slate-900">Logi Systemowe</h1>
    </div>

    <div class="crm-card animate-fade-in">
      <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
        <h3 class="font-bold text-slate-700">Rejestr Zdarzeń (Audit Log)</h3>
      </div>
      <table class="crm-table divide-y divide-slate-200">
        <thead class="crm-table-head">
          <tr>
            <th class="crm-table-th crm-table-th-xs">Data</th>
            <th class="crm-table-th crm-table-th-xs">Użytkownik (ID)</th>
            <th class="crm-table-th crm-table-th-xs">Akcja</th>
            <th class="crm-table-th crm-table-th-xs">Szczegóły</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-slate-50 transition">
            <td class="crm-table-td whitespace-nowrap text-xs text-slate-500">{{ new Date(log.date).toLocaleString() }}</td>
            <td class="crm-table-td whitespace-nowrap text-xs font-bold text-slate-700">{{ log.actorId }}</td>
            <td class="crm-table-td whitespace-nowrap text-xs text-indigo-600 font-medium">{{ log.action }}</td>
            <td class="crm-table-td text-xs text-slate-600">{{ log.details }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
