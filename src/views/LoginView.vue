<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)

const redirectTarget = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') ? r : '/'
})

async function goAfterLogin() {
  await router.replace(redirectTarget.value)
}

async function doLogin() {
  loading.value = true
  try {
    const redirectUri = `${window.location.origin}${redirectTarget.value}`
    await auth.login(redirectUri)
    if (!auth.enabled) await goAfterLogin()
  } catch (e) {
    // błąd trzymamy w store (auth.error)
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
  <div class="min-h-screen flex items-center justify-center bg-gray-100 p-6">
    <div class="relative w-full max-w-md bg-white border rounded-xl shadow p-8 text-center space-y-6">
      <div class="flex flex-col items-center gap-3">
        <img src="@/assets/logo.svg" alt="STRATTON" class="h-12" />
        <h1 class="text-xl font-semibold textstratton700">Logowanie</h1>
      </div>

      <p class="text-sm text-gray-600">
        Zaloguj się, aby korzystać z CRM.
      </p>

      <div v-if="auth.error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">
        {{ auth.error }}
      </div>

      <div class="space-y-2">
        <button
          type="button"
          class="w-full px-4 py-2 rounded-md bgstratton500 text-white hover:bgstratton600 disabled:opacity-60 inline-flex items-center justify-center gap-2"
          :disabled="auth.initializing || loading"
          @click="doLogin"
        >
          <svg v-if="auth.initializing || loading" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          <span>Zaloguj przez Keycloak</span>
        </button>
        <div v-if="!auth.enabled" class="text-xs text-gray-500">
          Tryb deweloperski: autoryzacja jest wyłączona (VITE_AUTH_ENABLED != 'true'). Kliknięcie „Zaloguj” przepuści dalej jako użytkownik testowy.
        </div>
      </div>

      <div class="text-xs text-gray-400">
        Masz problem z logowaniem? Skontaktuj się z administratorem.
      </div>
    </div>
  </div>

</template>
