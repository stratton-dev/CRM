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
  <div class="crm-section">
    <div class="crm-section-header">
      <h2 class="crm-heading">Customers</h2>
      <button class="crm-action-btn">Add customer</button>
    </div>

    <div class="crm-card">
      <div class="crm-table-wrapper">
        <table class="crm-table">
          <thead class="crm-table-head">
            <tr>
              <th class="crm-table-th">#</th>
              <th class="crm-table-th">Name</th>
              <th class="crm-table-th">Email</th>
              <th class="crm-table-th">Phone</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="4" class="crm-table-empty">
                <div class="crm-loading">
                  <AppIcon name="refresh" class="w-4 h-4 animate-spin" />
                  <span>Ładowanie...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="error">
              <td colspan="4" class="crm-table-error">{{ error }}</td>
            </tr>
            <tr v-else v-for="c in rows" :key="c.id" class="crm-table-row">
              <td class="crm-table-td">{{ c.id }}</td>
              <td class="crm-table-td">{{ c.name }}</td>
              <td class="crm-table-td">{{ c.email || '—' }}</td>
              <td class="crm-table-td">{{ c.phone || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
