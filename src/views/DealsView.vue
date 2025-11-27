<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'

type Deal = { id: number; title: string; stage?: string; amount?: number; customer?: { id: number; name: string } }

const loading = ref(false)
const rows = ref<Deal[]>([])
const error = ref<string | null>(null)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/deals') // GET /api/deals
    rows.value = Array.isArray(data?.data) ? data.data : data
  } catch (e: any) {
    error.value = e?.message || 'Failed to load deals'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-semibold textstratton700">Deals</h2>
      <button class="px-3 py-2 border rounded bg-white hover:bg-gray-50">Add deal</button>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left">
          <tr>
            <th class="px-4 py-2">#</th>
            <th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Customer</th>
            <th class="px-4 py-2">Stage</th>
            <th class="px-4 py-2">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="px-4 py-3">Loading…</td>
          </tr>
          <tr v-else-if="error">
            <td colspan="5" class="px-4 py-3 text-red-600">{{ error }}</td>
          </tr>
          <tr v-else v-for="d in rows" :key="d.id" class="border-t">
            <td class="px-4 py-2">{{ d.id }}</td>
            <td class="px-4 py-2">{{ d.title }}</td>
            <td class="px-4 py-2">{{ d.customer?.name || '—' }}</td>
            <td class="px-4 py-2">{{ d.stage || '—' }}</td>
            <td class="px-4 py-2">{{ d.amount != null ? Intl.NumberFormat().format(d.amount) : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
