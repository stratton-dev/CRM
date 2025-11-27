<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
const token = localStorage.getItem('crm_token')
const auth = useAuthStore()

const modeLabel = computed(() => auth.enabled ? 'Keycloak (PROD)' : 'DEV (auth wyłączony)')
const currentUser = computed(() => auth.user || null)
const kcUrl = import.meta.env.VITE_KEYCLOAK_URL || ''
const kcRealm = import.meta.env.VITE_KEYCLOAK_REALM || ''
const kcClientId = import.meta.env.VITE_KEYCLOAK_CLIENT_ID || ''
const appOrigin = window.location.origin
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-2xl font-semibold textstratton700">Settings</h2>

    <div class="bg-white rounded shadow p-4">
      <h3 class="font-semibold mb-2">Backend API</h3>
      <div class="text-sm text-gray-600">Base URL</div>
      <div class="font-mono">{{ apiBase }}</div>
      <p class="text-sm text-gray-500 mt-2">
        Configure via <code>.env</code> file in <code>crm</code> folder with <code>VITE_API_BASE_URL</code>.
      </p>
    </div>

    <div class="bg-white rounded shadow p-4 space-y-3">
      <h3 class="font-semibold">Auth</h3>
      <div>
        <div class="text-sm text-gray-600">Tryb</div>
        <span class="inline-block px-2 py-0.5 text-xs rounded border"
              :class="auth.enabled ? 'bg-green-50 border-green-300 text-green-800' : 'bg-yellow-50 border-yellow-300 text-yellow-800'">
          {{ modeLabel }}
        </span>
      </div>

      <div v-if="auth.enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <div class="text-sm text-gray-600">Keycloak URL</div>
          <div class="text-sm font-mono break-all">{{ kcUrl || '—' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">Realm</div>
          <div class="text-sm font-mono">{{ kcRealm || '—' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">Client ID</div>
          <div class="text-sm font-mono">{{ kcClientId || '—' }}</div>
        </div>
      </div>

      <div>
        <div class="text-sm text-gray-600">Token obecny</div>
        <div class="mb-1">{{ token ? 'Yes' : 'No' }}</div>
      </div>

      <div>
        <div class="text-sm text-gray-600">Aktualny użytkownik</div>
        <div>
          <template v-if="currentUser">
            <div class="text-sm">{{ currentUser.firstName || '' }} {{ currentUser.lastName || '' }}
              <span v-if="currentUser.email" class="text-gray-500"> — {{ currentUser.email }}</span>
            </div>
          </template>
          <template v-else>
            <div class="text-sm text-gray-500">Brak danych</div>
          </template>
        </div>
      </div>

      <div class="text-xs text-gray-500 border-t pt-3">
        Aby włączyć logowanie przez Keycloak ustaw w <code>.env</code>:
        <pre class="whitespace-pre-wrap mt-1">VITE_AUTH_ENABLED=true
VITE_KEYCLOAK_URL=https://keycloak.example.com
VITE_KEYCLOAK_REALM=your-realm
VITE_KEYCLOAK_CLIENT_ID=crm-frontend</pre>
        Upewnij się, że w kliencie Keycloak dozwolony jest redirect na Twój origin: {{ appOrigin }}
      </div>
    </div>
  </div>
</template>
