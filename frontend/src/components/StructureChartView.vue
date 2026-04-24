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
            <div class="p-4 bg-surface rounded-card shadow-card-hover border border-slate-200" style="width: 300px;">
              <div class="flex items-center mb-4">
                <img src="${d.data.avatar_url || 'https://ui-avatars.com/api/?name=' + d.data.name}" class="w-16 h-16 rounded-full mr-4" alt="Avatar">
                <div>
                  <div class="font-bold text-lg text-slate-900">${d.data.name}</div>
                  <div class="text-slate-500 text-sm">${d.data.role}</div>
                </div>
              </div>
              <div class="space-y-2 text-sm text-slate-600">
                <div><strong class="text-slate-500">ID Hier.:</strong> ${d.data.hierarchicalId || '-'}</div>
                <div><strong class="text-slate-500">Email:</strong> ${d.data.email}</div>
                <div><strong class="text-slate-500">Telefon:</strong> ${d.data.phone || '-'}</div>
                <div>
                  <strong class="text-slate-500">Status:</strong> 
                  <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${d.data.enabled !== false ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                    ${d.data.enabled !== false ? 'Aktywny' : 'Nieaktywny'}
                  </span>
                </div>
              </div>
            </div>
          `
        }
        return `
          <div class="px-3 py-2 bg-surface-dark text-white rounded-card shadow-card border border-slate-700 min-w-[120px] text-center">
            <div class="font-bold text-sm">${d.data.name}</div>
            <div class="text-xs text-slate-400 font-mono mt-1">${d.data.hierarchicalId || d.data.role}</div>
          </div>
        `
      })
      .onNodeClick((d: any) => {
        const clickedId = typeof d === 'object' ? d?.id ?? d?.data?.id : d
        const node = data.value.find((n) => n.id === clickedId)
        if (node) {
          node._expanded = !node._expanded
          if (chart) {
            chart.render()
          }
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
