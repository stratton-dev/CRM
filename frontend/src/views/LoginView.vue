<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/logo.svg'
import AppIcon from '@/components/AppIcon.vue'
import { useSessionStore } from '@/stores/session'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const session = useSessionStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const formError = ref<string | null>(null)

const redirectTarget = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/app/dashboard'
})

const goAfterLogin = async () => {
  await router.replace(redirectTarget.value)
}

const doLogin = async () => {
  formError.value = null
  if (!email.value || !password.value) {
    formError.value = 'Podaj adres e-mail i haslo.'
    return
  }
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    await session.resolveUserFromAuth()
    await goAfterLogin()
  } catch (err: any) {
    formError.value = err?.message || 'Blad logowania.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await auth.ensureInitialized()
  if (auth.isAuthenticated) {
    await goAfterLogin()
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-100 relative overflow-hidden">
    <div class="absolute -top-[20%] -right-[20%] w-[800px] h-[800px] rounded-full bg-linear-to-br from-stratton-gold/10 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-[20%] -left-[20%] w-[800px] h-[800px] rounded-full bg-linear-to-tr from-stratton-blue/10 to-transparent blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md p-8 space-y-8 bg-white rounded-2xl shadow-lg relative z-10 transition-all duration-500 hover:shadow-xl border border-slate-100">
      <div class="text-center">
        <div class="flex flex-col items-center justify-center gap-4 mb-6">
          <img :src="logoUrl" alt="Stratton logo" class="w-16 h-16" />
          <span class="text-2xl font-sans font-bold text-stratton-dark tracking-widest">STRATTON</span>
        </div>
        <h2 class="text-xl font-bold text-slate-700 font-sans">Logowanie do Systemu CRM 2.0</h2>
        <p class="text-sm text-slate-400 mt-2">Zaloguj sie, aby uzyskac dostep.</p>
      </div>

      <div v-if="formError || auth.error" class="text-red-600 text-sm text-center bg-red-50 py-2 px-4 rounded-lg border border-red-100 flex items-center justify-center gap-2">
        <AppIcon name="exclamation-triangle" class="w-4 h-4 shrink-0" />
        {{ formError || auth.error }}
      </div>

      <form class="space-y-4" @submit.prevent="doLogin">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1 uppercase tracking-wide">Adres e-mail</label>
          <input
            v-model="email"
            type="email"
            autocomplete="email"
            required
            placeholder="twoj@email.pl"
            class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-stratton-gold/50 focus:border-stratton-gold transition"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1 uppercase tracking-wide">Haslo</label>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            placeholder="••••••••"
            class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-stratton-gold/50 focus:border-stratton-gold transition"
          />
        </div>
        <button
          type="submit"
          class="group relative w-full flex justify-center items-center gap-2 py-3 px-4 border border-stratton-gold/50 text-sm font-bold rounded-lg text-slate-900 bg-stratton-gold hover:bg-[#b6924f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stratton-gold transition-all shadow-md hover:shadow-lg uppercase tracking-wide"
          :disabled="loading"
        >
          <AppIcon v-if="loading" name="refresh" class="animate-spin h-4 w-4 text-white mr-2" />
          Zaloguj sie
          <span class="absolute right-4"><AppIcon name="arrow-right" class="w-4 h-4" /></span>
        </button>
      </form>
    </div>
  </div>
</template>