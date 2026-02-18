<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { OrgChart } from 'd3-org-chart'
import type { User } from '@/types/models'

const props = defineProps<{
  users: User[]
}>()

const chartContainer = ref<HTMLDivElement | null>(null)
let chart: OrgChart<any> | null = null

type OrgNode = User & {
  parentId?: string | null
  name: string
  _expanded?: boolean
}

const data = ref<OrgNode[]>([])

watch(
  () => props.users,
  (newUsers) => {
    if (newUsers.length > 0) {
      data.value = newUsers.map((u) => ({
        ...u,
        id: u.id,
        parentId: u.parentId ?? (u as any).id_parent ?? null,
        name: `${u.firstName ?? (u as any).first_name ?? ''} ${u.lastName ?? (u as any).last_name ?? ''}`.trim() || u.name,
      }))
      renderChart()
    }
  },
  { immediate: true, deep: true }
)

function renderChart() {
  if (chartContainer.value && data.value.length > 0) {
    if (!chart) {
      chart = new OrgChart()
    }

    chart
      .container(chartContainer.value as any)
      .data(data.value)
      .nodeId((d: any) => d.id)
      .parentNodeId((d: any) => d.parentId)
      .nodeContent((d: any) => {
        if (d.data._expanded) {
          return `
            <div class="p-4 bg-white rounded-lg shadow-md border border-gray-200" style="width: 300px;">
              <div class="flex items-center mb-4">
                <img src="${d.data.avatar_url || 'https://cdn.vectorstock.com/i/1000x1000/30/97/flat-business-man-user-profile-avatar-icon-vector-4333097.jpg'}" class="w-16 h-16 rounded-full mr-4" alt="Avatar">
                <div>
                  <div class="font-bold text-lg">${d.data.name}</div>
                  <div class="text-gray-500">${d.data.position}</div>
                </div>
              </div>
              <div class="space-y-2 text-sm">
                <div><strong>ID Hier.:</strong> ${d.data.id_hier}</div>
                <div><strong>Email:</strong> ${d.data.email}</div>
                <div><strong>Telefon:</strong> ${d.data.phone_number}</div>
                <div><strong>Status:</strong> <span class="px-2 py-1 text-xs font-semibold rounded-full ${d.data.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${d.data.is_active ? 'Aktywny' : 'Nieaktywny'}</span></div>
              </div>
              <div class="mt-4 flex justify-end space-x-2">
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm">Zarządzaj</button>
                <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded-md text-sm">Zobacz profil</button>
              </div>
            </div>
          `
        }
        return `
          <div class="px-6 py-3 bg-gray-800 text-white rounded-lg shadow-lg">
            <div class="font-semibold text-center">${d.data.id_hier}</div>
          </div>
        `
      })
      .onNodeClick((d: any) => {
        // In d3-org-chart, you might need to manually trigger a re-render
        // by slightly modifying the data or calling the render method again.
        const clickedId = typeof d === 'object' ? d?.id ?? d?.data?.id : d
        const node = data.value.find((n) => n.id === clickedId)
        if (node) {
          node._expanded = !node._expanded
          chart?.render()
        }
      })
      .render()
  }
}

onMounted(() => {
  renderChart()
})
</script>

<template>
  <div ref="chartContainer" class="chart-container bg-dots"></div>
</template>

<style>
.chart-container {
  width: 100%;
  height: calc(100vh - 200px);
}

.bg-dots {
  background-image: radial-gradient(circle, #d1d5db 1px, rgba(0, 0, 0, 0) 1px);
  background-size: 20px 20px;
}

.org-chart-node-content {
  background-color: transparent !important;
  border: none !important;
}

.org-chart-node-content .node {
  background-color: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

.link {
  stroke: #6b7280;
  stroke-width: 2px;
}
</style>
