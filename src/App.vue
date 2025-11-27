<script setup lang="ts">
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const menuOpen = ref(false)

const userLabel = computed(() => auth.fullName || 'Użytkownik')

function goLogin() {
  router.push({ path: '/login', query: { redirect: route.fullPath } })
}

async function doLogout() {
  await auth.logout()
  router.replace('/login')
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <header class="bg-white shadow">
      <nav class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <img src="@/assets/logo.svg" alt="Logo" class="h-10" />
          <h1 class="text-xl font-bold textstratton700">STRATTON CRM</h1>
        </div>

        <div class="hidden md:flex gap-6 items-center">
          <RouterLink to="/dashboard" class="text-gray-600 hover:textstratton600" active-class="textstratton600 font-semibold">Dashboard</RouterLink>
          <RouterLink to="/customers" class="text-gray-600 hover:textstratton600" active-class="textstratton600 font-semibold">Customers</RouterLink>
          <RouterLink to="/deals" class="text-gray-600 hover:textstratton600" active-class="textstratton600 font-semibold">Deals</RouterLink>
          <RouterLink to="/settings" class="text-gray-600 hover:textstratton600" active-class="textstratton600 font-semibold">Settings</RouterLink>
          <div class="flex items-center gap-3">
            <template v-if="auth.enabled">
              <template v-if="auth.isAuthenticated">
                <span class="text-sm text-gray-700">{{ userLabel }}</span>
                <button type="button" class="px-3 py-1 text-sm border rounded hover:bg-gray-50" @click="doLogout">Wyloguj</button>
              </template>
              <template v-else>
                <button type="button" class="px-3 py-1 text-sm border rounded hover:bg-gray-50" @click="goLogin">Zaloguj</button>
              </template>
            </template>
            <template v-else>
              <button type="button" class="px-3 py-1 text-sm border rounded hover:bg-gray-50" @click="goLogin">Zaloguj</button>
            </template>
          </div>
        </div>

        <div class="md:hidden flex items-center">
          <button type="button" class="p-2 rounded border text-gray-600 hover:bg-gray-50" aria-label="Menu" :aria-expanded="menuOpen ? 'true' : 'false'" @click="menuOpen = !menuOpen">☰</button>
        </div>
      </nav>
      <div v-if="menuOpen" class="md:hidden border-t bg-white">
        <div class="max-w-6xl mx-auto px-4 py-3 space-y-3">
          <div class="flex flex-col gap-3">
            <RouterLink to="/dashboard" class="text-gray-700 hover:textstratton600" @click="menuOpen = false">Dashboard</RouterLink>
            <RouterLink to="/customers" class="text-gray-700 hover:textstratton600" @click="menuOpen = false">Customers</RouterLink>
            <RouterLink to="/deals" class="text-gray-700 hover:textstratton600" @click="menuOpen = false">Deals</RouterLink>
            <RouterLink to="/settings" class="text-gray-700 hover:textstratton600" @click="menuOpen = false">Settings</RouterLink>
          </div>
          <div class="pt-3 border-t">
            <template v-if="auth.enabled">
              <template v-if="auth.isAuthenticated">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-700">{{ userLabel }}</span>
                  <button type="button" class="px-3 py-1 text-sm border rounded hover:bg-gray-50" @click="() => { menuOpen = false; doLogout() }">Wyloguj</button>
                </div>
              </template>
              <template v-else>
                <button type="button" class="w-full px-3 py-2 text-sm border rounded hover:bg-gray-50" @click="() => { menuOpen = false; goLogin() }">Zaloguj</button>
              </template>
            </template>
            <template v-else>
              <button type="button" class="w-full px-3 py-2 text-sm border rounded hover:bg-gray-50" @click="() => { menuOpen = false; goLogin() }">Zaloguj</button>
            </template>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6">
      <RouterView v-slot="{ Component }">
        <Transition name="route" mode="out-in" appear>
          <div :key="route.fullPath" class="route-view">
            <component :is="Component" />
          </div>
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<style scoped>
</style>