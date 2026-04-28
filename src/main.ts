import { createApp } from 'vue'
import { createPinia } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
import App from './App.vue'
import router from './router'
import { setupRealtime } from './realtime/setup'

// Global styles
import './assets/tailwind.css'

// Suppress unhandled rejections from pusher-js TabsManager inter-tab coordination.
// These are benign internal events that fire when the "master" tab listener isn't ready yet.
window.addEventListener('unhandledrejection', (event) => {
  const msg = event?.reason?.message ?? ''
  if (typeof msg === 'string' && (msg.includes('No Listener: tabs:') || msg.includes('tabs:outgoing'))) {
    event.preventDefault()
  }
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(VueApexCharts)
setupRealtime()
app.mount('#app')
