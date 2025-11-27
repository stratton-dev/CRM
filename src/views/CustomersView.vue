<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'

type Customer = { id: number; name: string; email?: string; phone?: string }

const loading = ref(false)
const rows = ref<Customer[]>([])
const error = ref<string | null>(null)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/customers') // GET /api/customers
    rows.value = Array.isArray(data?.data) ? data.data : data
  } catch (e: any) {
    error.value = e?.message || 'Failed to load customers'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-semibold textstratton700">Customers</h2>
      <button class="px-3 py-2 border rounded bg-white hover:bg-gray-50">Add customer</button>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left">
          <tr>
            <th class="px-4 py-2">#</th>
            <th class="px-4 py-2">Name</th>
            <th class="px-4 py-2">Email</th>
            <th class="px-4 py-2">Phone</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="px-4 py-3">Loading…</td>
          </tr>
          <tr v-else-if="error">
            <td colspan="4" class="px-4 py-3 text-red-600">{{ error }}</td>
          </tr>
          <tr v-else v-for="c in rows" :key="c.id" class="border-t">
            <td class="px-4 py-2">{{ c.id }}</td>
            <td class="px-4 py-2">{{ c.name }}</td>
            <td class="px-4 py-2">{{ c.email || '—' }}</td>
            <td class="px-4 py-2">{{ c.phone || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
