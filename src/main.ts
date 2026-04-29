import { createApp } from 'vue'
import { createPinia } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
import App from './App.vue'
import router from './router'
import { setupRealtime } from './realtime/setup'

// Global styles
import './assets/tailwind.css'

// Backup suppression for pusher-js TabsManager noise (primary suppressor is in index.html)
const isTabsNoise = (v: unknown): boolean => {
  const s = [(v instanceof Error ? v.message : ''), String(v ?? '')].join(' ')
  return s.includes('No Listener: tabs:') || s.includes('tabs:outgoing')
}
window.addEventListener('unhandledrejection', (event) => {
  if (isTabsNoise(event?.reason)) event.preventDefault()
}, true)
window.addEventListener('error', (event) => {
  if (isTabsNoise(event?.error || event?.message)) event.preventDefault()
}, true)

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(VueApexCharts)
setupRealtime()
app.mount('#app')
