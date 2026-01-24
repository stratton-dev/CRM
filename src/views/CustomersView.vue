<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'

type Customer = { id: number | string; name: string; email?: string; phone?: string }

const loading = ref(false)
const rows = ref<Customer[]>([])
const error = ref<string | null>(null)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/v1/clients', { params: { per_page: 200 } })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    rows.value = list.map((item: any) => ({
      id: item.id,
      name: item.name,
      email: item.crm_profile?.contact_email || item.email || undefined,
      phone: item.crm_profile?.contact_phone || item.phone || undefined,
    }))
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
            <td colspan="4" class="px-4 py-3 text-gray-500">
              <div class="flex items-center gap-2">
                <AppIcon name="refresh" class="w-4 h-4 animate-spin" />
                <span>Ładowanie...</span>
              </div>
            </td>
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
