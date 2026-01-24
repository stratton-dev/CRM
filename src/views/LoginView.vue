<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/logo.svg'
import AppIcon from '@/components/AppIcon.vue'
import { useDataStore } from '@/stores/data'
import { storeToRefs } from 'pinia'
import { useSessionStore } from '@/stores/session'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const data = useDataStore()
const { users } = storeToRefs(data)
const session = useSessionStore()
const loading = ref(false)
const devRole = ref<'ADMIN' | 'DIRECTOR' | 'MANAGER' | 'SALES' | 'CLIENT_HR'>(
  (localStorage.getItem('stratton_dev_role') as 'ADMIN' | 'DIRECTOR' | 'MANAGER' | 'SALES' | 'CLIENT_HR') || 'ADMIN'
)

const redirectTarget = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/app/dashboard'
})


const goAfterLogin = async () => {
  await router.replace(redirectTarget.value)
}

const doLogin = async () => {
  loading.value = true
  try {
    const redirectUri = `${window.location.origin}${redirectTarget.value}`
    await auth.login(redirectUri)
    if (!auth.enabled) {
      localStorage.setItem('stratton_dev_role', devRole.value)
      const list = Array.isArray(users.value) ? users.value : []
      const match = list.find((user) => user.role === devRole.value) || list[0]
      if (match) session.setCurrentUser(match.id)
      await goAfterLogin()
    }
  } catch (error) {
    // error handled in store
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (auth.enabled && !auth.isAuthenticated && !auth.initializing) {
    await auth.ensureInitialized()
  }
  if (auth.isAuthenticated) {
    await goAfterLogin()
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-100 relative overflow-hidden">
    <div class="absolute -top-[20%] -right-[20%] w-[800px] h-[800px] rounded-full bg-gradient-to-br from-stratton-gold/10 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-[20%] -left-[20%] w-[800px] h-[800px] rounded-full bg-gradient-to-tr from-stratton-blue/10 to-transparent blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md p-8 space-y-8 bg-white rounded-2xl shadow-lg relative z-10 transition-all duration-500 hover:shadow-xl border border-slate-100">
      <div class="text-center">
        <div class="flex flex-col items-center justify-center gap-4 mb-6">
          <img :src="logoUrl" alt="Stratton logo" class="w-16 h-16" />
          <span class="text-2xl font-serif font-bold text-stratton-dark tracking-widest">STRATTON</span>
        </div>
        <h2 class="text-xl font-bold text-slate-700 font-serif">Logowanie do Systemu CRM 2.0</h2>
        <p class="text-sm text-slate-400 mt-2">Zaloguj się, aby uzyskać dostęp.</p>
      </div>

      <div v-if="auth.error" class="text-red-600 text-sm text-center bg-red-50 py-2 rounded-lg border border-red-100 flex items-center justify-center">
        <AppIcon name="exclamation-triangle" class="w-4 h-4 mr-2" />
        {{ auth.error }}
      </div>

      <div class="space-y-4">
        <div v-if="!auth.enabled" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <label class="block text-[11px] font-bold uppercase text-amber-700 mb-2">Tryb deweloperski: wybierz rolę</label>
          <select v-model="devRole" class="w-full border border-amber-300 rounded px-3 py-2 text-sm bg-white">
            <option value="ADMIN">ADMIN</option>
            <option value="DIRECTOR">DIRECTOR</option>
            <option value="MANAGER">MANAGER</option>
            <option value="SALES">SALES</option>
            <option value="CLIENT_HR">CLIENT_HR</option>
          </select>
          <p class="mt-2 text-xs text-amber-700/80">Rola zostanie ustawiona po kliknięciu „Zaloguj przez Keycloak”.</p>
        </div>
        <button
          type="button"
          class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide"
          :disabled="auth.initializing || loading"
          @click="doLogin"
        >
          <AppIcon v-if="auth.initializing || loading" name="refresh" class="animate-spin h-4 w-4 text-white mr-2" />
          Zaloguj przez Keycloak
          <span class="absolute right-4"><AppIcon name="arrow-right" class="w-4 h-4" /></span>
        </button>
      </div>
    </div>
  </div>
</template>
