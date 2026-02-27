<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'

type Deal = { id: number | string; title: string; stage?: string; amount?: number; customer?: { id: number; name: string } }

const loading = ref(false)
const rows = ref<Deal[]>([])
const error = ref<string | null>(null)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/v1/offers', { params: { per_page: 200 } })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    rows.value = list.map((item: any) => ({
      id: item.id,
      title: item.number || item.token || `Oferta #${item.id}`,
      stage: item.status || '—',
      amount: item.total_gross ?? item.subtotal_net ?? null,
      customer: item.company ? { id: item.company.id, name: item.company.name } : undefined,
    }))
  } catch (e: any) {
    error.value = e?.message || 'Failed to load deals'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="crm-section">
    <div class="crm-section-header">
      <h2 class="crm-heading">Deals</h2>
      <button class="crm-action-btn">Add deal</button>
    </div>

    <div class="crm-card">
      <div class="crm-table-wrapper">
        <table class="crm-table">
          <thead class="crm-table-head">
            <tr>
              <th class="crm-table-th">#</th>
              <th class="crm-table-th">Title</th>
              <th class="crm-table-th">Customer</th>
              <th class="crm-table-th">Stage</th>
              <th class="crm-table-th">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="crm-table-empty">
                <div class="crm-loading">
                  <AppIcon name="refresh" class="w-4 h-4 animate-spin" />
                  <span>Ładowanie...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="error">
              <td colspan="5" class="crm-table-error">{{ error }}</td>
            </tr>
            <tr v-else v-for="d in rows" :key="d.id" class="crm-table-row">
              <td class="crm-table-td">{{ d.id }}</td>
              <td class="crm-table-td">{{ d.title }}</td>
              <td class="crm-table-td">{{ d.customer?.name || '—' }}</td>
              <td class="crm-table-td">{{ d.stage || '—' }}</td>
              <td class="crm-table-td">{{ d.amount != null ? Intl.NumberFormat().format(d.amount) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
